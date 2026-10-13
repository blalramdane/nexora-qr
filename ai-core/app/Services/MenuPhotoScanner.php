<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Converts menu photos into a reviewable draft. It never writes restaurant
 * data; importing remains a separate, explicit user action.
 */
class MenuPhotoScanner
{
    /**
     * @param  array<int, UploadedFile>  $images
     * @return array{categories: array<int, array{name: string, products: array<int, array{name: string, description: ?string, price: int|float|null, confidence: string, variants: array<int, array{name: string, price: int|float|null}>}>}>}
     * @throws ConnectionException|RequestException|RuntimeException
     */
    public function scan(array $images): array
    {
        $apiKey = trim((string) config('services.openai.api_key'));
        if ($apiKey === '') {
            throw new RuntimeException('ميزة قراءة الصور غير مفعّلة على السيرفر. أضف OPENAI_API_KEY في إعدادات البيئة ثم حدّث إعدادات Laravel.');
        }

        $content = [[
            'type' => 'input_text',
            'text' => <<<'PROMPT'
اقرأ صور منيو مطعم وحوّل المعلومات الظاهرة فقط إلى بيانات منظمة باللغة المكتوبة في المنيو.
تعليمات مهمة:
- لا تخترع صنفًا أو سعرًا أو وصفًا غير واضح في الصورة.
- إذا كان السعر غير مقروء أو غير موجود، اجعل price = null ليصححه المستخدم قبل الحفظ.
- اجمع المنتجات تحت أسماء الأقسام الظاهرة. لا تدمج منتجات متشابهة إلا إذا كان واضحًا أنها نفس الصنف.
- استخدم variants فقط للأحجام أو الاختيارات التابعة بوضوح لصنف أساسي، وليس كأصناف مستقلة.
- لا تعتبر رقم الهاتف أو رقم الصفحة أو رقم العرض سعرًا للمنتج.
- confidence يعبّر عن وضوح قراءة اسم الصنف وسعره: high أو medium أو low.
- احتفظ بالأسماء كما تظهر قدر الإمكان، بالعربية أو الإنجليزية، ولا تترجم العلامات التجارية.
- هذه البيانات مسودة للمراجعة البشرية وليست تأكيدًا لصحة الأسعار.
PROMPT,
        ]];

        foreach ($images as $image) {
            $mime = $image->getMimeType();
            if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                throw new RuntimeException('ارفع صور JPG أو PNG أو WEBP فقط.');
            }

            $content[] = [
                'type' => 'input_image',
                'image_url' => 'data:'.$mime.';base64,'.base64_encode(file_get_contents($image->getRealPath())),
                'detail' => 'high',
            ];
        }

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout(75)
            ->post(rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/').'/responses', [
                'model' => (string) config('services.openai.menu_scan_model', 'gpt-5.5'),
                'store' => false,
                'input' => [[ 'role' => 'user', 'content' => $content ]],
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'restaurant_menu_extraction',
                        'strict' => true,
                        'schema' => $this->schema(),
                    ],
                ],
                'max_output_tokens' => 12000,
            ]);

        if (! $response->successful()) {
            // Keep provider credentials and image data out of user-facing errors.
            $response->throw();
        }

        $outputText = $response->json('output_text');
        if (! is_string($outputText) || trim($outputText) === '') {
            foreach ((array) $response->json('output', []) as $output) {
                foreach ((array) data_get($output, 'content', []) as $part) {
                    if (data_get($part, 'type') === 'output_text' && is_string(data_get($part, 'text'))) {
                        $outputText = data_get($part, 'text');
                        break 2;
                    }
                }
            }
        }

        if (! is_string($outputText) || trim($outputText) === '') {
            throw new RuntimeException('لم نقدر نقرأ بيانات واضحة من الصور. جرّب صورًا أوضح ومضاءة جيدًا.');
        }

        $draft = json_decode($outputText, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($draft) || ! isset($draft['categories']) || ! is_array($draft['categories'])) {
            throw new RuntimeException('نتيجة قراءة المنيو غير مكتملة. جرّب مرة أخرى أو استخدم استيراد CSV.');
        }

        $productsCount = 0;
        foreach ($draft['categories'] as $category) {
            if (! is_array($category) || ! is_string($category['name'] ?? null) || ! is_array($category['products'] ?? null)) {
                throw new RuntimeException('تعذر التحقق من أقسام المنيو المستخرجة.');
            }
            $productsCount += count($category['products']);
        }

        if ($productsCount === 0 || $productsCount > 500) {
            throw new RuntimeException('الصور لم تنتج أصنافًا كافية أو تحتوي على أصناف أكثر من الحد المسموح للمراجعة (500). قسّم المنيو إلى مجموعات صور أصغر.');
        }

        return $draft;
    }

    private function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['categories'],
            'properties' => [
                'categories' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['name', 'products'],
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'products' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'additionalProperties' => false,
                                    'required' => ['name', 'description', 'price', 'confidence', 'variants'],
                                    'properties' => [
                                        'name' => ['type' => 'string'],
                                        'description' => ['type' => ['string', 'null']],
                                        'price' => ['type' => ['number', 'null']],
                                        'confidence' => ['type' => 'string', 'enum' => ['high', 'medium', 'low']],
                                        'variants' => [
                                            'type' => 'array',
                                            'items' => [
                                                'type' => 'object',
                                                'additionalProperties' => false,
                                                'required' => ['name', 'price'],
                                                'properties' => [
                                                    'name' => ['type' => 'string'],
                                                    'price' => ['type' => ['number', 'null']],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
