<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GooglePlacesDiscoveryService
{
    /** @return array<int, array<string, mixed>> */
    public function search(string $query, string $city): array
    {
        if (! config('services.google_places.enabled') || ! filled(config('services.google_places.key'))) {
            throw new RuntimeException('اكتشاف Google Places متوقف افتراضيًا. يلزم إعداد المفتاح وتفعيل الخدمة بموافقة صريحة قبل أي طلب قد يترتب عليه تكلفة.');
        }

        $response = Http::acceptJson()
            ->withHeaders([
                'X-Goog-Api-Key' => config('services.google_places.key'),
                'X-Goog-FieldMask' => 'places.id,places.displayName,places.formattedAddress,places.googleMapsUri,places.websiteUri,places.nationalPhoneNumber,places.primaryTypeDisplayName',
            ])
            ->timeout(12)
            ->post('https://places.googleapis.com/v1/places:searchText', [
                'textQuery' => $query.' في '.$city.', Egypt',
                'languageCode' => 'ar',
                'regionCode' => 'EG',
                'pageSize' => 20,
            ]);

        $response->throw();

        return collect($response->json('places', []))->map(fn (array $place) => [
            'source_place_id' => $place['id'] ?? null,
            'name' => $place['displayName']['text'] ?? null,
            'address' => $place['formattedAddress'] ?? null,
            'maps_url' => $place['googleMapsUri'] ?? null,
            'website_url' => $place['websiteUri'] ?? null,
            'phone' => $place['nationalPhoneNumber'] ?? null,
            'category' => $place['primaryTypeDisplayName']['text'] ?? 'مطعم',
        ])->filter(fn (array $place) => filled($place['source_place_id']) && filled($place['name']))->values()->all();
    }
}
