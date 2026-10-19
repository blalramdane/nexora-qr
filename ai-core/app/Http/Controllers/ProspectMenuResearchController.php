<?php

namespace App\Http\Controllers;

use App\Models\LocalBusiness;
use App\Models\LocalBusinessActivity;
use App\Models\ProspectMenuResearch;
use App\Services\MenuOcrService;
use App\Services\OfficialMenuDiscoveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ProspectMenuResearchController extends Controller
{
    /**
     * Turn a user-confirmed official website from a transient Places result
     * into a private prospect, storing only the reusable Place ID from Google.
     */
    public function intakeFromOfficialSite(Request $request, OfficialMenuDiscoveryService $discovery): JsonResponse
    {
        $data = $request->validate([
            'source_place_id' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_-]{8,255}$/'],
            'website_url' => ['required', 'url', 'max:255'],
            'city' => ['required', 'in:دمياط,الزرقا'],
        ]);

        try {
            $result = $discovery->discover($data['website_url']);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'تعذر فحص الموقع الرسمي؛ لم يتم إنشاء سجل للمطعم.'], 502);
        }

        $name = trim((string) ($result['site_name'] ?? ''));
        if ($name === '') {
            return response()->json([
                'message' => 'لم نستطع تحديد اسم المطعم من بيانات الموقع الرسمي بثقة. افتح نموذج الإضافة اليدوية، واكتب الاسم بعد التحقق بنفسك.',
            ], 422);
        }

        $outcome = DB::transaction(function () use ($request, $data, $result, $name) {
            $business = LocalBusiness::query()
                ->where('source_place_id', $data['source_place_id'])
                ->lockForUpdate()
                ->first();

            if ($business) {
                if (! $this->sameHost((string) $business->website_url, $result['source_url'])) {
                    return ['conflict' => true];
                }
                $research = ProspectMenuResearch::query()
                    ->where('local_business_id', $business->id)
                    ->where('source_hash', $result['content_hash'])
                    ->latest()->first();
                if ($research) {
                    return ['business' => $business, 'research' => $research, 'created' => false];
                }
            } else {
                $business = LocalBusiness::create([
                    'name' => $name,
                    'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
                    'city' => $data['city'],
                    'category' => 'مطعم/كافيه',
                    'website_url' => $result['source_url'],
                    'source_url' => $result['source_url'],
                    'source_place_id' => $data['source_place_id'],
                    'source' => 'official_website_verified',
                    'menu_url' => $result['candidates'][0]['url'] ?? null,
                    'menu_status' => $result['candidates'] === [] ? 'unknown' : 'needs_review',
                    'listing_status' => 'draft',
                    'lead_status' => 'needs_verification',
                    'claim_status' => 'unclaimed',
                    'menu_rights_status' => 'not_checked',
                    'source_checked_at' => now(),
                    'internal_notes' => 'تم إنشاء السجل بعد مراجعة الموظف للموقع الرسمي من نتيجة اكتشاف مؤقتة. الاسم مستخرج من الموقع الرسمي. تم حفظ Google Place ID فقط كمعرّف قابل لإعادة الاستخدام؛ لم تُحفظ بيانات الاسم/العنوان/الهاتف من استجابة Google. لم يحدث تواصل أو نشر.',
                ]);
            }

            $research = ProspectMenuResearch::create([
                'local_business_id' => $business->id,
                'created_by' => $request->user()->id,
                'status' => $result['candidates'] === [] ? 'no_menu_found' : 'links_found',
                'source_website_url' => $result['source_url'],
                'discovered_links' => $result['candidates'],
                'source_hash' => $result['content_hash'],
                'discovered_at' => now(),
            ]);

            if (! empty($business->wasRecentlyCreated)) {
                LocalBusinessActivity::create([
                    'local_business_id' => $business->id,
                    'user_id' => $request->user()->id,
                    'type' => 'note',
                    'channel' => null,
                    'notes' => 'تم إنشاء Lead خاص بعد فحص الموقع الرسمي. تم اكتشاف '.count($result['candidates']).' رابط/روابط منيو مرشحة. لا نشر ولا تواصل تلقائي.',
                    'occurred_at' => now(),
                ]);
            }

            return ['business' => $business, 'research' => $research, 'created' => (bool) $business->wasRecentlyCreated];
        });

        if (! empty($outcome['conflict'])) {
            return response()->json(['message' => 'المعرّف مرتبط بالفعل بموقع مختلف. راجع السجل بدل إنشاء نسخة أخرى.'], 409);
        }

        /** @var LocalBusiness $business */
        $business = $outcome['business'];
        /** @var ProspectMenuResearch $research */
        $research = $outcome['research'];

        return response()->json([
            'business_id' => $business->id,
            'business_name' => $business->name,
            'business_created' => $outcome['created'],
            'research_id' => $research->id,
            'status' => $research->status,
            'source_url' => $research->source_website_url,
            'candidates' => $research->discovered_links ?? [],
            'message' => $outcome['created']
                ? 'تم إنشاء Lead خاص من الموقع الرسمي واكتشاف روابط المنيو. اختَر رابطًا لاستخراج الأصناف والأسعار ومراجعتها. لم يتم التواصل أو النشر.'
                : 'السجل موجود بالفعل؛ استعدنا البحث المحفوظ بدل إنشاء مطعم مكرر.',
        ], $outcome['created'] ? 201 : 200);
    }

    public function discover(Request $request, LocalBusiness $business, OfficialMenuDiscoveryService $discovery): JsonResponse
    {
        $this->assertOfficialWebsite($business);

        try {
            $result = $discovery->discover((string) $business->website_url);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'تعذر فحص الموقع الرسمي. راجع سجل التطبيق أو استخدم رابط المنيو يدويًا.'], 502);
        }

        $research = ProspectMenuResearch::create([
            'local_business_id' => $business->id,
            'created_by' => $request->user()->id,
            'status' => $result['candidates'] === [] ? 'no_menu_found' : 'links_found',
            'source_website_url' => $result['source_url'],
            'discovered_links' => $result['candidates'],
            'source_hash' => $result['content_hash'],
            'discovered_at' => now(),
        ]);

        LocalBusinessActivity::create([
            'local_business_id' => $business->id,
            'user_id' => $request->user()->id,
            'type' => 'note',
            'channel' => null,
            'notes' => 'تم فحص الموقع الرسمي يدويًا بطلب من فريق NEXORA. روابط مرشحة: '.count($result['candidates']).'. لم يتم نشر منيو أو التواصل مع المطعم.',
            'occurred_at' => now(),
        ]);

        return response()->json([
            'research_id' => $research->id,
            'status' => $research->status,
            'source_url' => $research->source_website_url,
            'candidates' => $research->discovered_links,
            'message' => $result['candidates'] === []
                ? 'لم نعثر على رابط منيو واضح في الصفحة الرسمية. راجع الموقع يدويًا أو أضف رابط المنيو بنفسك.'
                : 'تم العثور على روابط مرشحة من الموقع الرسمي. اختر رابط المنيو الصحيح لبدء الاستخراج.',
        ], 201);
    }

    public function extract(Request $request, ProspectMenuResearch $research, OfficialMenuDiscoveryService $discovery, MenuOcrService $ocr): JsonResponse
    {
        abort_unless($research->status !== 'demo_ready', 409, 'تم استخدام هذه المراجعة لتجهيز Demo بالفعل.');
        $this->assertOfficialWebsite($research->business);

        $data = $request->validate(['candidate_url' => ['required', 'url', 'max:1000']]);

        try {
            $candidate = $discovery->fetchCandidate(
                $research->source_website_url,
                $data['candidate_url'],
                $research->discovered_links ?? [],
            );
            $result = $ocr->extractCandidate($candidate['body'], $candidate['kind'], $candidate['mime'], 'ar');
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'تعذر تحميل أو تحليل المنيو من المصدر الرسمي.'], 502);
        }

        $status = match ($result['status']) {
            'ok' => 'needs_review',
            'not_configured' => 'awaiting_ai_config',
            'unsupported' => 'manual_required',
            default => 'extraction_failed',
        };

        $research->update([
            'status' => $status,
            'selected_menu_url' => $data['candidate_url'],
            'source_content_type' => $candidate['mime'],
            'source_hash' => $candidate['content_hash'],
            'extracted_data' => $result['data'],
            'error_message' => $result['error'],
            'processed_at' => now(),
        ]);

        if ($result['status'] !== 'ok') {
            return response()->json([
                'research_id' => $research->id,
                'status' => $status,
                'message' => $result['error'] ?: 'تعذر استخراج المنيو.',
                'needs_provider_setup' => $result['status'] === 'not_configured',
            ], $result['status'] === 'not_configured' ? 422 : 422);
        }

        return response()->json([
            'research_id' => $research->id,
            'status' => $status,
            'source_url' => $research->selected_menu_url,
            'categories' => $result['data']['categories'] ?? [],
            'message' => 'تم استخراج مسودة منيو. راجع كل صنف وسعر قبل اعتماد البيانات وتجهيز Demo.',
        ]);
    }

    public function review(Request $request, ProspectMenuResearch $research): JsonResponse
    {
        abort_unless($research->status === 'needs_review', 409, 'لا توجد مسودة مستخرجة جاهزة للمراجعة.');

        $data = $request->validate([
            'categories' => ['required', 'array', 'min:1', 'max:100'],
            'categories.*.name' => ['required', 'string', 'max:120'],
            'categories.*.items' => ['required', 'array', 'min:1', 'max:250'],
            'categories.*.items.*.name' => ['required', 'string', 'max:180'],
            'categories.*.items.*.price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'categories.*.items.*.description' => ['nullable', 'string', 'max:1000'],
        ]);

        $count = collect($data['categories'])->sum(fn (array $category) => count($category['items']));
        abort_if($count < 1 || $count > 500, 422, 'عدد الأصناف لازم يكون من 1 إلى 500.');

        $normalised = collect($data['categories'])->map(fn (array $category) => [
            'name' => trim($category['name']),
            'items' => collect($category['items'])->map(fn (array $item) => [
                'name' => trim($item['name']),
                'price' => round((float) $item['price'], 2),
                'description' => filled($item['description'] ?? null) ? trim($item['description']) : null,
            ])->values()->all(),
        ])->values()->all();

        $research->update(['reviewed_data' => ['categories' => $normalised], 'status' => 'reviewed', 'reviewed_at' => now(), 'error_message' => null]);

        return response()->json([
            'research_id' => $research->id,
            'status' => 'reviewed',
            'items_count' => $count,
            'message' => 'تم حفظ المسودة التي راجعتها. البيانات خاصة ولم يتم نشرها أو إرسالها للمطعم.',
        ]);
    }

    private function assertOfficialWebsite(LocalBusiness $business): void
    {
        abort_unless(filled($business->website_url), 422, 'أضف رابط الموقع الرسمي للمطعم وتأكد منه قبل البحث عن المنيو.');
        $parts = parse_url((string) $business->website_url);
        abort_unless(
            is_array($parts)
            && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && filled($parts['host'] ?? null)
            && ! isset($parts['user'])
            && ! isset($parts['pass']),
            422,
            'لازم يكون رابط الموقع الرسمي HTTPS، بدون بيانات دخول داخل الرابط.'
        );
    }

    private function sameHost(string $left, string $right): bool
    {
        return filled($left)
            && strtolower((string) parse_url($left, PHP_URL_HOST)) === strtolower((string) parse_url($right, PHP_URL_HOST));
    }
}
