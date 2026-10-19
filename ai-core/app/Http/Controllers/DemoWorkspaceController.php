<?php

namespace App\Http\Controllers;

use App\Concerns\LogsActivity;
use App\Models\DemoInvitation;
use App\Models\DemoSandboxOrder;
use App\Models\DemoWorkspace;
use App\Models\LocalBusiness;
use App\Models\Menu;
use App\Models\ProspectMenuResearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class DemoWorkspaceController extends Controller
{
    use LogsActivity;

    private const STATUSES = ['new', 'confirmed', 'preparing', 'ready', 'completed', 'cancelled'];

    public function create(Request $request, LocalBusiness $business): JsonResponse
    {
        $data = $request->validate([
            'request_note' => ['nullable', 'string', 'max:1500'],
            'style' => ['nullable', 'string', 'in:modern,playful,luxury,classic'],
            'invitation_id' => ['nullable', 'integer'],
            'menu_research_id' => ['nullable', 'integer'],
        ]);

        $invitation = null;
        if (! empty($data['invitation_id'])) {
            $invitation = DemoInvitation::query()
                ->whereKey($data['invitation_id'])
                ->where('local_business_id', $business->id)
                ->where('status', 'requested')
                ->whereNull('demo_workspace_id')
                ->first();
            abort_unless($invitation, 422, 'طلب المعاينة غير موجود أو تم تجهيز Demo له بالفعل.');
        }

        $menuResearch = null;
        if (! empty($data['menu_research_id'])) {
            $menuResearch = ProspectMenuResearch::query()
                ->whereKey($data['menu_research_id'])
                ->where('local_business_id', $business->id)
                ->where('status', 'reviewed')
                ->first();
            abort_unless($menuResearch, 422, 'اعتمد مراجعة المنيو المرتبطة بالمطعم قبل تجهيز المعاينة.');
        }

        $previewToken = Str::random(64);
        $ownerToken = Str::random(64);
        $expiresAt = now()->addDays(7);
        $workspace = DB::transaction(function () use ($request, $business, $data, $invitation, $menuResearch, $previewToken, $ownerToken, $expiresAt) {
            if ($invitation) {
                $invitation = DemoInvitation::query()
                    ->whereKey($invitation->id)
                    ->where('local_business_id', $business->id)
                    ->where('status', 'requested')
                    ->whereNull('demo_workspace_id')
                    ->lockForUpdate()
                    ->first();
                abort_unless($invitation, 409, 'طلب المعاينة اتجهز له Demo بالفعل أو لم يعد متاحًا.');
            }
            if ($menuResearch) {
                $menuResearch = ProspectMenuResearch::query()
                    ->whereKey($menuResearch->id)
                    ->where('local_business_id', $business->id)
                    ->where('status', 'reviewed')
                    ->lockForUpdate()
                    ->first();
                abort_unless($menuResearch, 409, 'مراجعة المنيو لم تعد متاحة أو تم استخدامها في Demo آخر.');
            }
            $style = $data['style'] ?? $invitation?->style ?? 'modern';
            $requestNote = filled($data['request_note'] ?? null) ? trim($data['request_note']) : $invitation?->note;
            $menuSnapshot = $menuResearch
                ? $this->menuSnapshotFromResearch($menuResearch, $business, $style)
                : $this->initialMenuSnapshot($business, $style);

            $workspace = DemoWorkspace::create([
                'local_business_id' => $business->id,
                'created_by' => $request->user()->id,
                'preview_token_hash' => hash('sha256', $previewToken),
                'owner_token_hash' => hash('sha256', $ownerToken),
                'menu_snapshot' => $menuSnapshot,
                'preview_request' => [
                    'note' => $requestNote,
                    'style' => $style,
                    'invitation_id' => $invitation?->id,
                    'requested_mode' => $invitation?->requested_mode,
                    'requested_features' => $invitation?->requested_features ?? [],
                    'menu_research_id' => $menuResearch?->id,
                    'menu_source_url' => $menuResearch?->selected_menu_url,
                    'requires_owner_confirmation' => $menuResearch !== null,
                ],
                'status' => 'active',
                'expires_at' => $expiresAt,
            ]);

            if ($invitation) {
                $invitation->update(['demo_workspace_id' => $workspace->id, 'status' => 'demo_ready']);
            }
            if ($menuResearch) {
                $menuResearch->update(['status' => 'demo_ready']);
            }

            // This records the generated demo internally, not a customer contact.
            $business->activities()->create([
                'user_id' => $request->user()->id,
                'type' => 'demo',
                'outcome' => null,
                'channel' => null,
                'notes' => 'تم إنشاء معاينة وساحة اختبار منفصلة. الرابط لم يتم إرساله، ولم تتغير مرحلة العميل.'
                    .' النمط: '.$style
                    .($requestNote ? ' · طلب المعاينة: '.$requestNote : '')
                    .($invitation ? ' · مرتبط بطلب المعاينة #'.$invitation->id : '')
                    .($menuResearch ? ' · مرتبط بمراجعة المنيو #'.$menuResearch->id : ''),
                'occurred_at' => now(),
            ]);

            return $workspace;
        });

        $recommendedUrl = $invitation?->requested_mode === 'owner_dashboard'
            ? route('demo.show', ['token' => $ownerToken])
            : route('demo.show', ['token' => $previewToken]);
        $message = match ($invitation?->requested_mode) {
            'owner_dashboard' => "أهلًا بحضرتك، جهّزنا لوحة معاينة خاصة لـ{$business->name} من NEXORA. تقدر تجرب تعديل الأصناف ومراجعة الطلبات التجريبية من الرابط ده. التعديلات والطلبات معزولة تمامًا عن التشغيل الفعلي، والأسعار والأصناف التوضيحية لحد ما حضرتك تأكدها.",
            'custom_preview' => "أهلًا بحضرتك، جهّزنا معاينة مبدئية مخصصة لـ{$business->name} من NEXORA حسب طلبك. المعاينة للمراجعة والتجربة فقط؛ الأسعار والأصناف التوضيحية تحتاج تأكيد، ومفيش نشر أو طلبات تشغيل فعلية.",
            default => "أهلًا بحضرتك، جهّزنا معاينة منيو لـ{$business->name} من NEXORA. تقدر تتصفح الأصناف وتجرب طلبًا تجريبيًا من الرابط. المعاينة للمراجعة فقط، والأسعار والأصناف التوضيحية تحتاج تأكيد حضرتك قبل أي تشغيل.",
        };

        return response()->json([
            'workspace_id' => $workspace->id,
            'invitation_id' => $invitation?->id,
            'preview_url' => route('demo.show', ['token' => $previewToken]),
            'owner_url' => route('demo.show', ['token' => $ownerToken]),
            'recommended_url' => $recommendedUrl,
            'expires_at' => $expiresAt->toIso8601String(),
            'message' => $message.' المعاينة صالحة لمدة 7 أيام.',
        ], 201);
    }

    public function show(Request $request, string $token): SymfonyResponse
    {
        [$workspace, $mode] = $this->resolveToken($token);
        $workspace->load('business:id,name,city,area,category,phone');

        $response = Inertia::render('Demo/Experience', [
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->business->name,
                'city' => $workspace->business->city,
                'category' => $workspace->business->category,
                'menu' => $workspace->menu_snapshot,
                'expires_at' => $workspace->expires_at->toIso8601String(),
                'orders' => $mode === 'owner' ? $workspace->orders()->limit(30)->get()->map(fn (DemoSandboxOrder $order) => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer_name' => $order->customer_name,
                    'customer_phone' => $order->customer_phone,
                    'items' => $order->items,
                    'total' => (float) $order->total,
                    'status' => $order->status,
                    'created_at' => $order->created_at?->toIso8601String(),
                ])->values() : [],
            ],
            'accessMode' => $mode,
            'endpoint' => route('demo.show', ['token' => $token]),
        ])->toResponse($request);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');

        return $response;
    }

    public function storeOrder(Request $request, string $token): JsonResponse
    {
        [$workspace, $mode] = $this->resolveToken($token);
        abort_unless($mode === 'preview', 403, 'رابط المعاينة مخصص لعرض المنيو والطلبات التجريبية فقط.');

        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.product_id' => ['required', 'string', 'max:80'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:30'],
        ]);

        $catalog = collect($workspace->menu_snapshot['items'] ?? [])->keyBy('id');
        $orderItems = [];
        $totalQuantity = 0;
        $total = 0.0;
        foreach ($data['items'] as $line) {
            $product = $catalog->get($line['product_id']);
            if (! $product) {
                throw ValidationException::withMessages(['items' => 'فيه صنف مش موجود في المعاينة الحالية. حدّث الصفحة وجرّب تاني.']);
            }
            if (($product['is_available'] ?? true) !== true) {
                throw ValidationException::withMessages(['items' => 'أحد الأصناف غير متاح في المعاينة.']);
            }
            $totalQuantity += $line['quantity'];
            if ($totalQuantity > 30) {
                throw ValidationException::withMessages(['items' => 'الحد الأقصى للطلب التجريبي 30 قطعة.']);
            }
            $price = round(max(0, (float) $product['price']), 2);
            $lineTotal = round($price * $line['quantity'], 2);
            $total += $lineTotal;
            $orderItems[] = ['product_id' => (string) $product['id'], 'name' => $product['name'], 'quantity' => $line['quantity'], 'unit_price' => $price, 'line_total' => $lineTotal];
        }

        $order = $workspace->orders()->create([
            'order_number' => 'DEMO-'.strtoupper(Str::random(8)),
            'customer_name' => $data['customer_name'],
            'customer_phone' => $data['customer_phone'],
            'items' => $orderItems,
            'total' => round($total, 2),
            'status' => 'new',
        ]);

        return response()->json([
            'order' => ['number' => $order->order_number, 'total' => (float) $order->total, 'status' => $order->status],
            'notice' => 'تم حفظ طلب تجريبي داخل الـSandbox فقط. لم يدخل الطلب في طلبات المطعم الحقيقية أو التقارير المالية.',
        ], 201);
    }

    public function updateMenu(Request $request, string $token): JsonResponse
    {
        [$workspace, $mode] = $this->resolveToken($token);
        abort_unless($mode === 'owner', 403, 'تعديل الأصناف متاح من رابط صاحب المطعم فقط.');

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:60'],
            'items.*.id' => ['required', 'string', 'max:80'],
            'items.*.name' => ['required', 'string', 'max:120'],
            'items.*.category' => ['required', 'string', 'max:80'],
            'items.*.description' => ['nullable', 'string', 'max:300'],
            'items.*.price' => ['required', 'numeric', 'min:0', 'max:100000'],
            'items.*.is_available' => ['required', 'boolean'],
        ]);

        $ids = collect($data['items'])->pluck('id');
        if ($ids->unique()->count() !== $ids->count()) {
            throw ValidationException::withMessages(['items' => 'معرّفات الأصناف لازم تكون مختلفة.']);
        }

        $snapshot = $workspace->menu_snapshot;
        $previousItems = collect($snapshot['items'] ?? [])->keyBy('id');
        $snapshot['items'] = collect($data['items'])->map(fn (array $item) => [
            'id' => $item['id'], 'name' => trim($item['name']), 'category' => trim($item['category']),
            'description' => $item['description'] ?? null, 'price' => round((float) $item['price'], 2),
            'is_available' => (bool) $item['is_available'], 'is_example' => (bool) ($previousItems->get($item['id'])['is_example'] ?? true),
        ])->values()->all();
        $workspace->update(['menu_snapshot' => $snapshot]);

        return response()->json(['saved' => true, 'menu' => $workspace->menu_snapshot, 'message' => 'تم حفظ التعديلات داخل المعاينة فقط. لم تتغير بيانات المنيو الحقيقي.']);
    }

    public function updateOrderStatus(Request $request, string $token, DemoSandboxOrder $order): JsonResponse
    {
        [$workspace, $mode] = $this->resolveToken($token);
        abort_unless($mode === 'owner', 403, 'تحديث الطلبات متاح من رابط صاحب المطعم فقط.');
        abort_unless($order->demo_workspace_id === $workspace->id, 404);

        $data = $request->validate(['status' => ['required', 'string', 'in:'.implode(',', self::STATUSES)]]);
        $order->update(['status' => $data['status']]);

        return response()->json(['saved' => true, 'status' => $order->status, 'message' => 'تم تحديث حالة الطلب التجريبي فقط.']);
    }

    public function revoke(Request $request, DemoWorkspace $workspace): JsonResponse
    {
        abort_unless($request->user()?->isPlatformOwner(), 403);
        $workspace->update(['status' => 'revoked', 'revoked_at' => now()]);

        return response()->json(['revoked' => true]);
    }

    private function resolveToken(string $token): array
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{40,100}$/', $token) === 1, 404);
        $hash = hash('sha256', $token);
        $workspace = DemoWorkspace::query()->where(function ($query) use ($hash) {
            $query->where('preview_token_hash', $hash)->orWhere('owner_token_hash', $hash);
        })->firstOrFail();
        abort_unless($workspace->isUsable(), 410, 'انتهت صلاحية المعاينة أو تم إيقافها. اطلب رابطًا جديدًا من فريق NEXORA.');

        return [$workspace, hash_equals($workspace->owner_token_hash, $hash) ? 'owner' : 'preview'];
    }

    private function initialMenuSnapshot(LocalBusiness $business, string $style): array
    {
        $items = [];
        $menuName = 'منيو تجريبي';
        if ($business->restaurant_id && in_array($business->menu_rights_status, ['permission_granted', 'licensed'], true)) {
            $menu = Menu::query()->where('restaurant_id', $business->restaurant_id)
                ->with(['categories.products'])
                ->orderByDesc('is_published')->latest()->first();
            if ($menu) {
                $menuName = $menu->name;
                foreach ($menu->categories as $category) {
                    foreach ($category->products as $product) {
                        $items[] = [
                            'id' => 'product-'.$product->id, 'name' => $product->name,
                            'category' => $category->name, 'description' => $product->description,
                            'price' => (float) $product->price, 'is_available' => (bool) $product->is_available,
                            'is_example' => false,
                        ];
                    }
                }
            }
        }

        if ($items === []) {
            $menuName = 'منيو توضيحي — يحتاج مراجعة';
            $items = [
                ['id' => 'sample-1', 'name' => 'الصنف الأول', 'category' => 'الأكثر طلبًا', 'description' => 'مثال توضيحي — استبدله ببيانات المطعم المعتمدة', 'price' => 120, 'is_available' => true, 'is_example' => true],
                ['id' => 'sample-2', 'name' => 'الصنف الثاني', 'category' => 'الأكثر طلبًا', 'description' => 'مثال توضيحي — استبدله ببيانات المطعم المعتمدة', 'price' => 85, 'is_available' => true, 'is_example' => true],
                ['id' => 'sample-3', 'name' => 'إضافة جانبية', 'category' => 'الإضافات', 'description' => 'مثال توضيحي — استبدله ببيانات المطعم المعتمدة', 'price' => 35, 'is_available' => true, 'is_example' => true],
            ];
        }

        return ['name' => $menuName, 'style' => $style, 'items' => $items, 'all_items_are_examples' => collect($items)->every(fn ($item) => $item['is_example'] ?? false)];
    }

    private function menuSnapshotFromResearch(ProspectMenuResearch $research, LocalBusiness $business, string $style): array
    {
        $categories = $research->reviewed_data['categories'] ?? [];
        abort_if(! is_array($categories) || $categories === [], 422, 'مراجعة المنيو لا تحتوي على أصناف معتمدة للمعاينة.');

        $items = [];
        foreach ($categories as $category) {
            $categoryName = trim((string) ($category['name'] ?? ''));
            foreach (($category['items'] ?? []) as $item) {
                $name = trim((string) ($item['name'] ?? ''));
                if ($categoryName === '' || $name === '' || ! isset($item['price']) || ! is_numeric($item['price'])) {
                    throw ValidationException::withMessages(['menu_research_id' => 'يوجد صنف أو سعر غير مكتمل في مراجعة المنيو.']);
                }
                $items[] = [
                    'id' => 'research-'.$research->id.'-'.count($items + []),
                    'name' => mb_substr($name, 0, 120),
                    'category' => mb_substr($categoryName, 0, 80),
                    // Keep third-party creative descriptions out of prospect demos
                    // until the restaurant confirms the content/usage rights.
                    'description' => 'مسودة منيو — يلزم تأكيد صاحب المطعم',
                    'price' => round((float) $item['price'], 2),
                    'is_available' => true,
                    'is_example' => false,
                ];
                abort_if(count($items) > 60, 422, 'المعاينة تدعم حتى 60 صنفًا. قلل المسودة إلى 60 صنفًا أو أقل.');
            }
        }
        abort_if($items === [], 422, 'لم نعثر على أصناف صالحة للمعاينة.');

        return [
            'name' => 'مسودة منيو — '.$business->name.' (تحتاج تأكيد المالك)',
            'style' => $style,
            'items' => $items,
            'all_items_are_examples' => false,
            'source' => [
                'type' => 'prospect_official_website',
                'url' => $research->selected_menu_url,
                'review_status' => 'human_reviewed_draft',
                'requires_owner_confirmation' => true,
            ],
        ];
    }
}
