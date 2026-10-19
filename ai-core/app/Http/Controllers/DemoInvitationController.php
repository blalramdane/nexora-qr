<?php

namespace App\Http\Controllers;

use App\Concerns\LogsActivity;
use App\Models\DemoInvitation;
use App\Models\LocalBusiness;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class DemoInvitationController extends Controller
{
    use LogsActivity;

    private const MODES = ['customer_preview', 'owner_dashboard', 'custom_preview'];

    private const STYLES = ['modern', 'playful', 'luxury', 'classic'];

    private const FEATURES = ['digital_menu', 'qr_code', 'order_dashboard', 'whatsapp', 'delivery', 'product_editing', 'photos', 'other'];

    public function create(Request $request, LocalBusiness $business): JsonResponse
    {
        $token = Str::random(64);
        $expiresAt = now()->addDays(7);

        $invitation = DemoInvitation::create([
            'local_business_id' => $business->id,
            'created_by' => $request->user()->id,
            'token_hash' => hash('sha256', $token),
            'status' => 'invited',
            'expires_at' => $expiresAt,
        ]);

        $business->activities()->create([
            'user_id' => $request->user()->id,
            'type' => 'demo',
            'notes' => 'تم إنشاء رابط خاص لاختيار تجربة NEXORA وطلب المعاينة. الرابط لم يتم إرساله تلقائيًا ولم تتغير مرحلة التواصل.',
            'occurred_at' => now(),
        ]);

        return response()->json([
            'invitation_id' => $invitation->id,
            'request_url' => route('demo.request.show', ['token' => $token]),
            'expires_at' => $expiresAt->toIso8601String(),
            'message' => 'اختار تجربة العميل أو لوحة صاحب المطعم أو اطلب تخصيصًا. الطلب هيتسجل على سجل NEXORA لمراجعته؛ لن يتم إرسال رسالة أو نشر أي شيء تلقائيًا.',
        ], 201);
    }

    public function show(Request $request, string $token): SymfonyResponse
    {
        $invitation = $this->resolveToken($token);
        $invitation->load('business:id,name,city,area,category');

        $response = Inertia::render('Demo/Request', [
            'business' => [
                'name' => $invitation->business->name,
                'city' => $invitation->business->city,
                'area' => $invitation->business->area,
                'category' => $invitation->business->category,
            ],
            'status' => $invitation->status,
            'expiresAt' => $invitation->expires_at->toIso8601String(),
            'endpoint' => route('demo.request.submit', ['token' => $token]),
        ])->toResponse($request);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');

        return $response;
    }

    public function submit(Request $request, string $token): JsonResponse
    {
        $data = $request->validate([
            'mode' => ['required', 'string', 'in:'.implode(',', self::MODES)],
            'style' => ['required', 'string', 'in:'.implode(',', self::STYLES)],
            'note' => ['nullable', 'string', 'max:1500'],
            'features' => ['sometimes', 'array', 'max:8'],
            'features.*' => ['string', 'distinct', 'in:'.implode(',', self::FEATURES)],
        ]);

        DB::transaction(function () use ($token, $data): void {
            $invitation = $this->resolveToken($token, lock: true);
            abort_unless($invitation->status === 'invited', 409, 'تم تسجيل الطلب بالفعل. تواصل مع فريق NEXORA لو محتاج تعديل الطلب.');

            $invitation->update([
                'requested_mode' => $data['mode'],
                'style' => $data['style'],
                'note' => trim((string) ($data['note'] ?? '')) ?: null,
                'requested_features' => array_values($data['features'] ?? []),
                'status' => 'requested',
                'submitted_at' => now(),
            ]);

            $business = $invitation->business()->lockForUpdate()->firstOrFail();
            $modeLabel = match ($data['mode']) {
                'customer_preview' => 'تجربة العميل',
                'owner_dashboard' => 'لوحة صاحب المطعم',
                default => 'معاينة مخصصة',
            };
            $featureLabels = collect($data['features'] ?? [])->map(fn (string $feature) => match ($feature) {
                'digital_menu' => 'منيو رقمي', 'qr_code' => 'QR', 'order_dashboard' => 'إدارة الطلبات',
                'whatsapp' => 'واتساب', 'delivery' => 'توصيل', 'product_editing' => 'تعديل الأصناف',
                'photos' => 'صور', default => 'متطلبات أخرى',
            })->implode('، ');

            $business->activities()->create([
                'user_id' => null,
                'type' => 'demo',
                'outcome' => 'demo_booked',
                'channel' => null,
                'notes' => 'طلب معاينة وارد من رابط NEXORA QR. النوع: '.$modeLabel
                    .'؛ النمط: '.$data['style']
                    .($featureLabels !== '' ? '؛ المطلوب: '.$featureLabels : '')
                    .(filled($data['note'] ?? null) ? '؛ ملاحظات: '.trim($data['note']) : ''),
                'occurred_at' => now(),
            ]);

            // A public request is a real intent signal, not proof that staff contacted the lead.
            if (! in_array($business->lead_status, ['partner', 'not_interested'], true)) {
                $business->update(['lead_status' => 'demo_booked', 'next_action_at' => now()]);
            }
        });

        return response()->json([
            'submitted' => true,
            'message' => 'وصل طلب المعاينة لفريق NEXORA واتربط بسجل المطعم. هنراجع التفاصيل قبل تجهيز الرابط؛ مفيش رسالة اتبعتت ولا نشر حصل تلقائيًا.',
        ], 201);
    }

    public function revoke(Request $request, DemoInvitation $invitation): JsonResponse
    {
        $invitation->update(['status' => 'revoked', 'revoked_at' => now()]);

        return response()->json(['revoked' => true]);
    }

    private function resolveToken(string $token, bool $lock = false): DemoInvitation
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{40,100}$/', $token) === 1, 404);

        $query = DemoInvitation::query()->where('token_hash', hash('sha256', $token));
        if ($lock) {
            $query->lockForUpdate();
        }
        $invitation = $query->firstOrFail();
        abort_unless($invitation->isUsable(), 410, 'انتهت صلاحية الرابط أو تم إيقافه. اطلب رابطًا جديدًا من فريق NEXORA.');

        return $invitation;
    }
}
