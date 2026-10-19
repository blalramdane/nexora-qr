<?php

namespace App\Http\Controllers;

use App\Models\DemoInvitation;
use App\Models\DemoWorkspace;
use App\Models\LocalBusiness;
use App\Models\LocalBusinessActivity;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\ProspectMenuResearch;
use App\Services\GooglePlacesDiscoveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LocalScoutController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', Rule::in(['دمياط', 'الزرقا'])],
            'status' => ['nullable', Rule::in(['draft', 'published', 'archived'])],
            'menu_status' => ['nullable', Rule::in(['unknown', 'available', 'missing', 'needs_review'])],
            'lead_status' => ['nullable', Rule::in(['needs_verification', 'verified', 'contacted', 'interested', 'demo_booked', 'proposal_sent', 'partner', 'not_interested'])],
        ]);

        $businesses = LocalBusiness::query()
            ->with([
                'activities' => fn ($query) => $query->limit(5),
                'restaurant:id,name',
            ])
            ->when($filters['q'] ?? null, fn ($query, $term) => $query->where(function ($q) use ($term) {
                $q->where('name', 'like', '%'.$term.'%')->orWhere('address', 'like', '%'.$term.'%')->orWhere('phone', 'like', '%'.$term.'%');
            }))
            ->when($filters['city'] ?? null, fn ($query, $city) => $query->where('city', $city))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('listing_status', $status))
            ->when($filters['menu_status'] ?? null, fn ($query, $status) => $query->where('menu_status', $status))
            ->when($filters['lead_status'] ?? null, fn ($query, $status) => $query->where('lead_status', $status))
            ->orderByRaw('CASE WHEN next_action_at IS NOT NULL AND next_action_at <= ? THEN 0 WHEN next_action_at IS NOT NULL THEN 1 ELSE 2 END', [now()])
            ->orderBy('next_action_at')->latest()->paginate(20)->withQueryString();

        $linkedRestaurantIds = $businesses->getCollection()->pluck('restaurant_id')->filter()->unique()->values();
        $allLinkedRestaurantIds = LocalBusiness::query()->whereNotNull('restaurant_id')->pluck('restaurant_id')->unique()->values();
        $orderStats = Order::query()
            ->whereIn('restaurant_id', $linkedRestaurantIds)
            ->selectRaw('restaurant_id, COUNT(*) as orders_count, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed_orders, SUM(CASE WHEN payment_status = ? THEN total ELSE 0 END) as paid_revenue, SUM(CASE WHEN status IN (?, ?, ?) THEN 1 ELSE 0 END) as open_orders', ['completed', 'paid', 'new', 'confirmed', 'preparing'])
            ->groupBy('restaurant_id')->get()->keyBy('restaurant_id');

        $demoWorkspaces = DemoWorkspace::query()
            ->whereIn('local_business_id', $businesses->getCollection()->pluck('id'))
            ->latest()->get(['id', 'local_business_id', 'status', 'expires_at', 'created_at'])
            ->groupBy('local_business_id');

        $demoInvitations = DemoInvitation::query()
            ->whereIn('local_business_id', $businesses->getCollection()->pluck('id'))
            ->latest()->get(['id', 'local_business_id', 'demo_workspace_id', 'status', 'requested_mode', 'style', 'expires_at', 'submitted_at', 'created_at'])
            ->groupBy('local_business_id');

        $menuResearches = ProspectMenuResearch::query()
            ->whereIn('local_business_id', $businesses->getCollection()->pluck('id'))
            ->latest()->get([
                'id', 'local_business_id', 'status', 'source_website_url', 'discovered_links',
                'selected_menu_url', 'extracted_data', 'reviewed_data', 'error_message',
                'discovered_at', 'processed_at', 'reviewed_at', 'created_at',
            ])
            ->groupBy('local_business_id');

        $businesses->getCollection()->transform(function (LocalBusiness $business) use ($orderStats, $demoWorkspaces, $demoInvitations, $menuResearches) {
            $summary = $business->restaurant_id ? $orderStats->get($business->restaurant_id) : null;
            $business->setAttribute('order_stats', [
                'orders_count' => (int) ($summary->orders_count ?? 0),
                'completed_orders' => (int) ($summary->completed_orders ?? 0),
                'open_orders' => (int) ($summary->open_orders ?? 0),
                'paid_revenue' => (float) ($summary->paid_revenue ?? 0),
            ]);
            $business->setAttribute('demo_workspaces', ($demoWorkspaces->get($business->id) ?? collect())
                ->take(3)->map(fn (DemoWorkspace $demo) => [
                    'id' => $demo->id,
                    'status' => $demo->status,
                    'expires_at' => $demo->expires_at?->toIso8601String(),
                    'created_at' => $demo->created_at?->toIso8601String(),
                    'is_usable' => $demo->isUsable(),
                ])->values());
            $business->setAttribute('demo_invitations', ($demoInvitations->get($business->id) ?? collect())
                ->take(3)->map(fn (DemoInvitation $invitation) => [
                    'id' => $invitation->id,
                    'demo_workspace_id' => $invitation->demo_workspace_id,
                    'status' => $invitation->status,
                    'requested_mode' => $invitation->requested_mode,
                    'style' => $invitation->style,
                    'expires_at' => $invitation->expires_at?->toIso8601String(),
                    'submitted_at' => $invitation->submitted_at?->toIso8601String(),
                    'created_at' => $invitation->created_at?->toIso8601String(),
                ])->values());
            $business->setAttribute('menu_researches', ($menuResearches->get($business->id) ?? collect())
                ->take(3)->map(fn (ProspectMenuResearch $research) => [
                    'id' => $research->id,
                    'status' => $research->status,
                    'source_website_url' => $research->source_website_url,
                    'selected_menu_url' => $research->selected_menu_url,
                    'discovered_links' => $research->discovered_links ?? [],
                    'extracted_data' => $research->extracted_data,
                    'reviewed_data' => $research->reviewed_data,
                    'error_message' => $research->error_message,
                    'discovered_at' => $research->discovered_at?->toIso8601String(),
                    'processed_at' => $research->processed_at?->toIso8601String(),
                    'reviewed_at' => $research->reviewed_at?->toIso8601String(),
                    'created_at' => $research->created_at?->toIso8601String(),
                ])->values());

            return $business;
        });

        $recentOrders = Order::query()
            ->whereIn('restaurant_id', $allLinkedRestaurantIds)
            ->with(['restaurant:id,name', 'statusHistory.actor:id,name'])
            ->latest()->limit(30)->get()
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'restaurant_id' => $order->restaurant_id,
                'restaurant_name' => $order->restaurant?->name,
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'total' => (float) $order->total,
                'created_at' => $order->created_at?->toIso8601String(),
                'history' => $order->statusHistory->sortByDesc('created_at')->take(5)->values()->map(fn (OrderStatusHistory $history) => [
                    'from_status' => $history->from_status,
                    'to_status' => $history->to_status,
                    'actor' => $history->actor?->name,
                    'created_at' => $history->created_at?->toIso8601String(),
                ])->values(),
            ])->values();

        $recentActivities = LocalBusinessActivity::query()
            ->with(['business:id,name,city', 'user:id,name'])
            ->whereHas('business')
            ->latest('occurred_at')->limit(30)->get()
            ->map(fn (LocalBusinessActivity $activity) => [
                'id' => $activity->id,
                'business_id' => $activity->local_business_id,
                'business_name' => $activity->business?->name,
                'city' => $activity->business?->city,
                'type' => $activity->type,
                'outcome' => $activity->outcome,
                'channel' => $activity->channel,
                'notes' => $activity->notes,
                'actor' => $activity->user?->name,
                'occurred_at' => $activity->occurred_at?->toIso8601String(),
            ])->values();

        return Inertia::render('Local/Scout', [
            'businesses' => $businesses,
            'recentOrders' => $recentOrders,
            'recentActivities' => $recentActivities,
            'filters' => $filters,
            'stats' => [
                'all' => LocalBusiness::query()->count(),
                'draft' => LocalBusiness::query()->where('listing_status', 'draft')->count(),
                'needs_menu' => LocalBusiness::query()->whereIn('menu_status', ['unknown', 'missing', 'needs_review'])->count(),
                'published' => LocalBusiness::query()->where('listing_status', 'published')->count(),
                'contacted' => LocalBusiness::query()->whereIn('lead_status', ['contacted', 'interested', 'demo_booked', 'proposal_sent'])->count(),
                'partners' => LocalBusiness::query()->where('lead_status', 'partner')->count(),
                'followups_due' => LocalBusiness::query()->whereNotNull('next_action_at')->where('next_action_at', '<=', now())->whereNotIn('lead_status', ['partner', 'not_interested'])->count(),
            ],
            'provider' => ['name' => 'Google Places API', 'configured' => filled(config('services.google_places.key')), 'enabled' => (bool) config('services.google_places.enabled')],
        ]);
    }

    public function discover(Request $request, GooglePlacesDiscoveryService $discovery): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'max:120'],
            'city' => ['required', Rule::in(['دمياط', 'الزرقا'])],
        ]);

        try {
            $places = $discovery->search($data['query'], $data['city']);
        } catch (\RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'تعذر إكمال البحث. راجع إعدادات مزود البيانات وسجل التطبيق.'], 502);
        }

        // Places content has storage and attribution restrictions. Keep results
        // request-scoped until the UI implements the required Google attribution
        // and the user verifies business-owned details from an official source.
        return response()->json([
            'found' => count($places),
            'saved' => 0,
            'attribution' => 'Google Maps',
            'places' => $places,
            'message' => 'النتائج مؤقتة للمعاينة فقط. لم نحفظ بيانات Google؛ افتح المصدر وتحقق من البيانات قبل إنشاء سجل مستقل.',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'city' => ['required', Rule::in(['دمياط', 'الزرقا'])],
            'area' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:500'],
            'category' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website_url' => ['nullable', 'url', 'max:500'],
            'maps_url' => ['nullable', 'url', 'max:500'],
            'source_url' => ['nullable', 'url', 'max:500'],
            'menu_url' => ['nullable', 'url', 'max:500'],
            'menu_status' => ['required', Rule::in(['unknown', 'available', 'missing', 'needs_review'])],
            'internal_notes' => ['nullable', 'string', 'max:3000'],
            'evidence_notes' => ['nullable', 'string', 'max:3000'],
            'confidence_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'contact_channel' => ['nullable', Rule::in(['phone', 'whatsapp', 'facebook', 'instagram', 'in_person', 'email'])],
            'lead_status' => ['nullable', Rule::in(['needs_verification', 'verified', 'contacted', 'interested', 'demo_booked', 'proposal_sent', 'partner', 'not_interested'])],
            'menu_rights_status' => ['nullable', Rule::in(['not_checked', 'permission_requested', 'permission_granted', 'licensed', 'not_allowed'])],
            'next_action_at' => ['nullable', 'date'],
        ]);

        $data['source'] = 'manual';
        $data['source_checked_at'] = now();
        $data['slug'] = Str::slug($data['name']).'-'.Str::lower(Str::random(6));
        LocalBusiness::create($data);

        return back()->with('success', 'اتضاف المطعم لقائمة الاكتشاف بنجاح.');
    }

    public function update(Request $request, LocalBusiness $business): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:180'],
            'city' => ['sometimes', 'required', Rule::in(['دمياط', 'الزرقا'])],
            'area' => ['sometimes', 'nullable', 'string', 'max:120'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'category' => ['sometimes', 'required', 'string', 'max:100'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'website_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'maps_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'source_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'menu_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'menu_status' => ['sometimes', Rule::in(['unknown', 'available', 'missing', 'needs_review'])],
            'claim_status' => ['sometimes', Rule::in(['unclaimed', 'contacted', 'claimed', 'verified'])],
            'listing_status' => ['sometimes', Rule::in(['draft', 'published', 'archived'])],
            'internal_notes' => ['sometimes', 'nullable', 'string', 'max:3000'],
            'evidence_notes' => ['sometimes', 'nullable', 'string', 'max:3000'],
            'confidence_score' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'contact_channel' => ['sometimes', 'nullable', Rule::in(['phone', 'whatsapp', 'facebook', 'instagram', 'in_person', 'email'])],
            'lead_status' => ['sometimes', Rule::in(['needs_verification', 'verified', 'contacted', 'interested', 'demo_booked', 'proposal_sent', 'partner', 'not_interested'])],
            'menu_rights_status' => ['sometimes', Rule::in(['not_checked', 'permission_requested', 'permission_granted', 'licensed', 'not_allowed'])],
            'next_action_at' => ['sometimes', 'nullable', 'date'],
        ]);

        if (($data['listing_status'] ?? null) === 'published' && empty($business->source_url) && empty($data['source_url'])) {
            return back()->withErrors(['listing_status' => 'أضف رابط مصدر موثوق قبل نشر صفحة المطعم.']);
        }

        $contactedStages = ['contacted', 'interested', 'demo_booked', 'proposal_sent', 'partner'];
        if (in_array($data['lead_status'] ?? null, $contactedStages, true) && ! $business->last_contacted_at) {
            $data['last_contacted_at'] = now();
        }

        // Closed leads should not keep appearing in the follow-up queue.
        if (in_array($data['lead_status'] ?? null, ['partner', 'not_interested'], true)) {
            $data['next_action_at'] = null;
        }
        $business->update($data);

        return back()->with('success', 'تم تحديث بيانات المطعم.');
    }

    public function storeActivity(Request $request, LocalBusiness $business): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['call', 'message', 'visit', 'demo', 'proposal', 'note'])],
            'outcome' => ['nullable', Rule::in(['no_answer', 'follow_up', 'interested', 'not_interested', 'demo_booked', 'proposal_sent', 'partner'])],
            'channel' => ['nullable', Rule::in(['phone', 'whatsapp', 'facebook', 'instagram', 'in_person', 'email'])],
            'notes' => ['nullable', 'string', 'max:3000'],
            'occurred_at' => ['nullable', 'date', 'before_or_equal:now'],
        ]);

        $data['user_id'] = $request->user()->id;
        $data['occurred_at'] = $data['occurred_at'] ?? now();
        $business->activities()->create($data);

        $updates = ['last_contacted_at' => $data['occurred_at']];
        if (in_array($data['type'], ['call', 'message', 'visit', 'demo', 'proposal'], true)) {
            $updates['contact_channel'] = $data['channel'] ?? $business->contact_channel;
        }

        $stageForOutcome = [
            'interested' => 'interested',
            'not_interested' => 'not_interested',
            'demo_booked' => 'demo_booked',
            'proposal_sent' => 'proposal_sent',
            'partner' => 'partner',
        ];
        if (isset($stageForOutcome[$data['outcome'] ?? ''])) {
            $updates['lead_status'] = $stageForOutcome[$data['outcome']];
        }
        if (in_array($data['outcome'] ?? null, ['partner', 'not_interested'], true)) {
            $updates['next_action_at'] = null;
        }

        $business->update($updates);

        return back()->with('success', 'تم تسجيل النشاط وتحديث مرحلة العميل.');
    }
}
