<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\OrderIntegrationEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SendRestaurantOrderWhatsAppNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 15;

    public function __construct(public int $orderId)
    {
    }

    public function backoff(): array
    {
        return [15, 60, 180];
    }

    /**
     * Uses Meta's official Cloud API and an approved template. The order is
     * already committed before this job is dispatched, so a provider failure
     * cannot lose or roll back the customer's order.
     *
     * @throws ConnectionException|RequestException
     */
    public function handle(): void
    {
        $order = Order::query()->with(['restaurant', 'items'])->find($this->orderId);

        if (! $order || ! $order->restaurant) {
            return;
        }

        $eventType = 'notification.whatsapp.order_created';
        $event = OrderIntegrationEvent::query()->firstOrCreate(
            ['order_id' => $order->id, 'event_type' => $eventType],
            [
                'restaurant_id' => $order->restaurant_id,
                'status' => 'pending',
                'payload' => ['order_id' => $order->id, 'order_number' => $order->order_number],
            ],
        );

        if ($event->status === 'sent') {
            return;
        }

        if (! $order->restaurant->whatsapp_order_notifications_enabled) {
            $event->update(['status' => 'skipped', 'processed_at' => now(), 'last_error' => null]);

            return;
        }

        $token = (string) Config::get('services.whatsapp_cloud.access_token');
        $phoneNumberId = (string) Config::get('services.whatsapp_cloud.phone_number_id');
        $apiVersion = (string) Config::get('services.whatsapp_cloud.api_version');
        $templateName = (string) Config::get('services.whatsapp_cloud.order_template_name');
        $templateLanguage = (string) Config::get('services.whatsapp_cloud.order_template_language', 'ar');
        $recipient = preg_replace('/\D+/', '', (string) $order->restaurant->whatsapp_phone);

        if (
            $token === ''
            || $phoneNumberId === ''
            || preg_match('/^v\d+\.\d+$/', $apiVersion) !== 1
            || $templateName === ''
            || $templateLanguage === ''
            || ! $recipient
            || strlen($recipient) < 10
            || strlen($recipient) > 15
        ) {
            $event->update([
                'status' => 'failed',
                'attempts' => max(1, (int) $event->attempts),
                'processed_at' => now(),
                'last_error' => 'WhatsApp Cloud API configuration, approved template, or restaurant destination phone is missing or invalid.',
            ]);

            return;
        }

        $event->update([
            'status' => 'processing',
            'attempts' => (int) $event->attempts + 1,
            'last_error' => null,
        ]);

        $items = $order->items->take(6)->map(fn ($item) => $item->product_name.' × '.(int) $item->quantity)->implode('، ');
        if ($order->items->count() > 6) {
            $items .= '، +'.($order->items->count() - 6).' أصناف أخرى';
        }
        if ($order->fulfillment_type === 'dine_in' && $order->restaurant_table_name) {
            $items = 'طلب ترابيزة '.$order->restaurant_table_name.' · '.$items;
        }

        $templateParameters = [
            (string) $order->order_number,
            Str::limit((string) $order->customer_name, 120, ''),
            Str::limit($items, 900, '…'),
            number_format((float) $order->total, 2, '.', '').' EGP',
        ];

        $response = Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout(10)
            ->post("https://graph.facebook.com/{$apiVersion}/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $recipient,
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => ['code' => $templateLanguage],
                    'components' => [[
                        'type' => 'body',
                        'parameters' => array_map(fn (string $value) => ['type' => 'text', 'text' => $value], $templateParameters),
                    ]],
                ],
            ]);

        if (! $response->successful()) {
            $providerMessage = (string) ($response->json('error.message') ?? 'WhatsApp provider rejected the request.');
            $event->update(['status' => 'pending', 'last_error' => Str::limit($providerMessage, 1800)]);

            throw new RuntimeException('WhatsApp Cloud API returned HTTP '.$response->status().': '.Str::limit($providerMessage, 500));
        }

        $event->update([
            'status' => 'sent',
            'processed_at' => now(),
            'last_error' => null,
            'payload' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'provider_message_id' => $response->json('messages.0.id'),
            ],
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        OrderIntegrationEvent::query()
            ->where('order_id', $this->orderId)
            ->where('event_type', 'notification.whatsapp.order_created')
            ->update([
                'status' => 'failed',
                'processed_at' => now(),
                'last_error' => Str::limit($exception?->getMessage() ?? 'WhatsApp notification job failed.', 1800),
                'updated_at' => now(),
            ]);
    }
}
