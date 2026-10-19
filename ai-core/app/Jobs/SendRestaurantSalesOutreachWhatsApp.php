<?php

namespace App\Jobs;

use App\Models\LocalBusiness;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class SendRestaurantSalesOutreachWhatsApp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 15;

    public function __construct(public int $businessId) {}

    public function backoff(): array
    {
        return [15, 60, 180];
    }

    public function handle(): void
    {
        $business = LocalBusiness::query()->find($this->businessId);
        if (! $business || $business->last_contacted_at) {
            return;
        }

        $token = (string) Config::get('services.whatsapp_cloud.access_token');
        $phoneNumberId = (string) Config::get('services.whatsapp_cloud.phone_number_id');
        $apiVersion = (string) Config::get('services.whatsapp_cloud.api_version');
        $templateName = (string) Config::get('services.whatsapp_outreach.template_name', Config::get('services.whatsapp_cloud.order_template_name'));
        $templateLanguage = (string) Config::get('services.whatsapp_outreach.template_language', 'ar');
        $recipient = preg_replace('/\D+/', '', (string) $business->phone);

        if ($token === '' || $phoneNumberId === '' || preg_match('/^v\d+\.\d+$/', $apiVersion) !== 1
            || $templateName === '' || ! $recipient || strlen($recipient) < 10 || strlen($recipient) > 15) {
            $business->update(['lead_status' => 'contact_failed']);

            return;
        }

        $response = Http::withToken($token)->acceptJson()->asJson()->timeout(10)
            ->post("https://graph.facebook.com/{$apiVersion}/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $recipient,
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => ['code' => $templateLanguage],
                ],
            ]);

        if (! $response->successful()) {
            $business->update(['lead_status' => 'contact_failed']);
            throw new RuntimeException('WhatsApp outreach failed: HTTP '.$response->status());
        }

        $business->update([
            'lead_status' => 'contacted',
            'last_contacted_at' => now(),
            'contact_channel' => 'whatsapp',
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        LocalBusiness::query()->where('id', $this->businessId)->update(['lead_status' => 'contact_failed']);
    }
}
