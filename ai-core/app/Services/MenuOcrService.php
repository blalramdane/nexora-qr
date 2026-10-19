<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pluggable OCR/Vision service for paper menu recognition.
 *
 * Providers (checked in order):
 *  1. OpenAI Vision (if OPENAI_API_KEY set) - uses configured menu_scan_model
 *  2. NullProvider (returns "not configured" status) - default
 *
 * To add a provider, implement extract() and register in providerFor().
 */
class MenuOcrService
{
    public function providerName(): string
    {
        if (config('services.openai.api_key')) {
            return 'openai-vision';
        }

        return 'none';
    }

    public function isConfigured(): bool
    {
        return $this->providerName() !== 'none';
    }

    /**
     * Extract structured menu data from image paths.
     * Returns ['status'=>'ok'|'not_configured'|'error', 'provider'=>..., 'data'=>..., 'error'=>...]
     */
    public function extract(array $imagePaths, string $language = 'ar'): array
    {
        if (! $this->isConfigured()) {
            return [
                'status' => 'not_configured',
                'provider' => 'none',
                'data' => null,
                'error' => 'No OCR/Vision provider configured. Set OPENAI_API_KEY in .env to enable. Manual entry is still available.',
            ];
        }

        try {
            $images = [];
            foreach ($imagePaths as $p) {
                $full = storage_path('app/public/'.ltrim($p, '/'));
                if (! file_exists($full)) {
                    continue;
                }
                $images[] = base64_encode(file_get_contents($full));
            }

            if (empty($images)) {
                return ['status' => 'error', 'provider' => $this->providerName(), 'data' => null, 'error' => 'No readable images.'];
            }

            return $this->requestExtraction(array_map(fn (string $b64) => ['type' => 'image_url', 'image_url' => ['url' => 'data:image/jpeg;base64,'.$b64]], $images), $language);
        } catch (\Throwable $e) {
            Log::error('MenuOcrService exception: '.$e->getMessage());

            return ['status' => 'error', 'provider' => $this->providerName(), 'data' => null, 'error' => $e->getMessage()];
        }
    }

    /** Extract a selected menu candidate fetched from the official restaurant website. */
    public function extractCandidate(string $body, string $kind, string $mime, string $language = 'ar'): array
    {
        if (! $this->isConfigured()) {
            return ['status' => 'not_configured', 'provider' => 'none', 'data' => null, 'error' => 'يلزم إعداد OPENAI_API_KEY لتشغيل استخراج الأصناف والأسعار.'];
        }

        if ($kind === 'pdf') {
            return ['status' => 'unsupported', 'provider' => $this->providerName(), 'data' => null, 'error' => 'عثرنا على ملف PDF. ارفعه من شاشة رفع المنيو الحالية كصفحات صور، ثم راجع النتائج قبل الاعتماد.'];
        }

        if ($kind === 'image') {
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
            if (! in_array($mime, $allowedMimes, true)) {
                return ['status' => 'error', 'provider' => $this->providerName(), 'data' => null, 'error' => 'نوع صورة المنيو غير مدعوم.'];
            }

            return $this->requestExtraction([[
                'type' => 'image_url',
                'image_url' => ['url' => 'data:'.$mime.';base64,'.base64_encode($body)],
            ]], $language);
        }

        if ($kind === 'html') {
            $text = trim(strip_tags($body));
            if ($text === '') {
                return ['status' => 'error', 'provider' => $this->providerName(), 'data' => null, 'error' => 'لم نعثر على نص واضح في صفحة المنيو. جرّب صورة أو ملفًا مدعومًا.'];
            }
            $sourceText = 'Source type: public official restaurant website text. Only extract item names and prices explicitly present below. '
                .'Do not infer missing prices, recipes, descriptions, sizes, or options. Set unclear/missing price to null and mark uncertain=true. '
                .'Website text:\n'.mb_substr($text, 0, 40000);

            return $this->requestExtraction([['type' => 'text', 'text' => $sourceText]], $language);
        }

        return ['status' => 'unsupported', 'provider' => $this->providerName(), 'data' => null, 'error' => 'نوع رابط المنيو غير مدعوم للاستخراج الآلي.'];
    }

    private function extractionPrompt(string $language): string
    {
        return 'You are a careful menu extraction assistant. Return ONLY valid JSON with this structure: {"categories":[{"name":"...","items":[{"name":"...","price":12.5,"description":"...","sizes":[{"name":"...","price":12.5}],"options":[{"name":"...","price_delta":0}],"uncertain":false}]}]}. '
            .'Preserve Arabic RTL and English. Bind each price to the correct item. Never invent or guess prices. '
            .'If a price is missing or unclear, set price to null and uncertain=true. Extract only content explicitly present in the source. '
            .'Avoid duplicates, detect visible category headers, and do not create invented descriptions, variants, or add-ons. Language hint: '.$language.'.';
    }

    private function requestExtraction(array $inputContent, string $language): array
    {
        $content = [['type' => 'text', 'text' => $this->extractionPrompt($language)]];
        $content = array_merge($content, $inputContent);

        $response = Http::withToken(config('services.openai.api_key'))
            ->acceptJson()
            ->connectTimeout(8)
            ->timeout(120)
            ->post(rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/').'/chat/completions', [
                'model' => (string) config('services.openai.menu_scan_model', 'gpt-4o-mini'),
                'messages' => [['role' => 'user', 'content' => $content]],
                'max_tokens' => 5000,
                'response_format' => ['type' => 'json_object'],
            ]);

        if (! $response->successful()) {
            Log::warning('MenuOcrService provider request failed', ['status' => $response->status()]);

            return ['status' => 'error', 'provider' => $this->providerName(), 'data' => null, 'error' => 'فشل مزود استخراج المنيو برمز HTTP '.$response->status().'.'];
        }

        $data = json_decode((string) $response->json('choices.0.message.content'), true);
        if (! is_array($data) || ! is_array($data['categories'] ?? null)) {
            return ['status' => 'error', 'provider' => $this->providerName(), 'data' => null, 'error' => 'رجع مزود الاستخراج بيانات غير صالحة.'];
        }

        return ['status' => 'ok', 'provider' => $this->providerName(), 'data' => $data, 'error' => null];
    }
}
