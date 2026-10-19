<?php

namespace App\Console\Commands;

use App\Jobs\SendRestaurantSalesOutreachWhatsApp;
use App\Models\LocalBusiness;
use App\Services\GooglePlacesDiscoveryService;
use Illuminate\Console\Command;

class DiscoverRestaurantsCommand extends Command
{
    protected $signature = 'nexora:discover-restaurants {--city=دمياط} {--query=مطاعم} {--limit=20}';

    protected $description = 'Search nearby restaurants via Google Places and queue WhatsApp sales outreach.';

    public function handle(GooglePlacesDiscoveryService $discovery): int
    {
        $city = (string) $this->option('city');
        $query = (string) $this->option('query');

        $this->info("Searching restaurants: {$query} in {$city}");

        try {
            $places = $discovery->search($query, $city);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $new = 0;
        foreach ($places as $place) {
            $business = LocalBusiness::query()->firstOrNew(['source_place_id' => $place['source_place_id']]);
            if (! $business->exists) {
                $business->fill([
                    'name' => $place['name'],
                    'city' => $city,
                    'category' => $place['category'] ?? 'مطعم',
                    'address' => $place['address'],
                    'phone' => $place['phone'],
                    'website_url' => $place['website_url'],
                    'maps_url' => $place['maps_url'],
                    'source' => 'google_places',
                    'source_place_id' => $place['source_place_id'],
                    'source_url' => $place['maps_url'],
                    'listing_status' => 'draft',
                    'lead_status' => 'new',
                    'contact_channel' => 'whatsapp',
                    'menu_status' => 'unknown',
                    'source_checked_at' => now(),
                ]);
                $business->save();
                $new++;
                SendRestaurantSalesOutreachWhatsApp::dispatch($business->id);
            }
        }

        $this->info('Discovered '.count($places)." places, {$new} new, outreach queued.");

        return self::SUCCESS;
    }
}
