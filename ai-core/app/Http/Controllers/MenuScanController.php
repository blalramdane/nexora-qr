<?php

namespace App\Http\Controllers;

use App\Concerns\LogsActivity;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuScanJob;
use App\Models\Product;
use App\Services\MenuOcrService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class MenuScanController extends Controller
{
    use LogsActivity;

    public function index(Request $request): InertiaResponse
    {
        $restaurant = app(TenantContext::class)->restaurant();

        return Inertia::render('Menu/ScanMenu', [
            'restaurantId' => $restaurant->id,
            'menus' => Menu::query()->where('restaurant_id', $restaurant->id)->orderBy('name')->get(['id', 'name']),
            'recentJobs' => MenuScanJob::query()->where('restaurant_id', $restaurant->id)->latest()->limit(10)->get([
                'id', 'menu_id', 'status', 'image_paths', 'extracted_data', 'reviewed_data', 'import_summary', 'error_message', 'created_at',
            ]),
        ]);
    }

    public function upload(Request $request)
    {
        $restaurant = app(TenantContext::class)->restaurant();
        $data = $request->validate([
            'menu_id' => ['required', 'integer', 'exists:menus,id'],
            'images' => ['required', 'array', 'min:1', 'max:10'],
            'images.*' => ['required', 'image', 'max:10240'],
        ]);

        $menu = Menu::query()->where('restaurant_id', $restaurant->id)->findOrFail($data['menu_id']);

        $paths = [];
        foreach ($request->file('images') as $img) {
            $paths[] = $img->store('menu-scans', 'public');
        }

        $job = MenuScanJob::create([
            'restaurant_id' => $restaurant->id,
            'menu_id' => $menu->id,
            'user_id' => auth()->id(),
            'status' => 'uploaded',
            'image_paths' => $paths,
        ]);

        $this->logActivity('menu_scan.uploaded', MenuScanJob::class, $job->id, ['images' => count($paths)]);

        return response()->json($job, 201);
    }

    public function process(Request $request, MenuOcrService $ocr)
    {
        $job = $this->findJob((int) $request->route('job'), (int) $request->route('restaurant'));
        abort_unless(in_array($job->status, ['uploaded', 'failed'], true), 409, 'لا يمكن إعادة تحليل المهمة في حالتها الحالية.');
        $job->update(['status' => 'processing']);
        $result = $ocr->extract($job->image_paths ?? [], 'ar');

        if ($result['status'] === 'ok') {
            $job->update([
                'status' => 'extracted',
                'raw_ocr_text' => json_encode($result['data']),
                'extracted_data' => $result['data'],
                'processed_at' => now(),
            ]);
        } elseif ($result['status'] === 'not_configured') {
            $job->update(['status' => 'extracted', 'extracted_data' => ['categories' => [], 'note' => $result['error']], 'processed_at' => now(), 'error_message' => $result['error']]);
        } else {
            $job->update(['status' => 'failed', 'error_message' => $result['error'], 'processed_at' => now()]);
        }

        return response()->json($job->fresh());
    }

    public function show(Request $request)
    {
        $job = $this->findJob((int) $request->route('job'), (int) $request->route('restaurant'));

        return response()->json($job);
    }

    public function review(Request $request)
    {
        $job = $this->findJob((int) $request->route('job'), (int) $request->route('restaurant'));
        abort_unless($job->status === 'extracted', 409, 'لا يمكن مراجعة مهمة لم تكتمل معالجتها.');
        $data = $request->validate([
            'reviewed_data' => ['required', 'array'],
            'reviewed_data.categories' => ['required', 'array', 'min:1', 'max:100'],
            'reviewed_data.categories.*.name' => ['required', 'string', 'max:120'],
            'reviewed_data.categories.*.items' => ['required', 'array', 'max:250'],
            'reviewed_data.categories.*.items.*.name' => ['required', 'string', 'max:180'],
            'reviewed_data.categories.*.items.*.price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'reviewed_data.categories.*.items.*.description' => ['nullable', 'string', 'max:1000'],
        ]);
        $itemCount = collect($data['reviewed_data']['categories'])->sum(fn (array $category) => count($category['items']));
        abort_if($itemCount < 1 || $itemCount > 500, 422, 'عدد الأصناف المراجعة يجب أن يكون بين 1 و500.');
        $job->update(['status' => 'reviewed', 'reviewed_data' => $data['reviewed_data'], 'reviewed_at' => now()]);

        return response()->json($job->fresh());
    }

    public function approve(Request $request)
    {
        $job = $this->findJob((int) $request->route('job'), (int) $request->route('restaurant'));
        abort_unless($job->status === 'reviewed' && is_array($job->reviewed_data), 409, 'راجع أسماء الأصناف والأسعار واحفظ المراجعة أولًا.');
        $data = $job->reviewed_data ?: $job->extracted_data;
        $cats = $data['categories'] ?? [];
        $created = ['categories' => 0, 'products' => 0, 'failed' => []];

        $menu = Menu::query()->where('restaurant_id', $job->restaurant_id)->findOrFail($job->menu_id);

        $created = DB::transaction(function () use ($cats, $menu, $job, $created): array {
            foreach ($cats as $cat) {
                $categoryName = trim((string) ($cat['name'] ?? ''));
                if ($categoryName === '') {
                    continue;
                }
                $category = MenuCategory::firstOrCreate(
                    ['restaurant_id' => $job->restaurant_id, 'menu_id' => $menu->id, 'name' => $categoryName],
                    ['slug' => Str::slug($categoryName).'-'.Str::lower(Str::random(5)), 'is_active' => true, 'sort_order' => 0]
                );
                foreach ($cat['items'] ?? [] as $item) {
                    $name = trim((string) ($item['name'] ?? ''));
                    $price = $item['price'] ?? null;
                    if ($name === '' || ! is_numeric($price) || (float) $price < 0) {
                        $created['failed'][] = ['category' => $categoryName, 'item' => $name ?: null, 'reason' => 'اسم أو سعر غير صالح'];

                        continue;
                    }
                    $exists = Product::query()->where('restaurant_id', $job->restaurant_id)->where('menu_category_id', $category->id)->where('name', $name)->exists();
                    if ($exists) {
                        $created['failed'][] = ['category' => $categoryName, 'item' => $name, 'reason' => 'الصنف موجود بالفعل ولم يتم تكراره'];

                        continue;
                    }

                    Product::create([
                        'restaurant_id' => $job->restaurant_id,
                        'menu_category_id' => $category->id,
                        'name' => $name,
                        'slug' => Str::slug($name).'-'.Str::lower(Str::random(8)),
                        'description' => $item['description'] ?? null,
                        'price' => round((float) $price, 2),
                        'is_available' => true,
                        'is_featured' => false,
                        'sort_order' => 0,
                    ]);
                    $created['products']++;
                }
                $created['categories']++;
            }

            return $created;
        });

        $job->update(['status' => 'imported', 'import_summary' => $created, 'imported_at' => now()]);
        $this->logActivity('menu_scan.approved', MenuScanJob::class, $job->id, $created);

        return response()->json(['job' => $job->fresh(), 'summary' => $created]);
    }

    private function findJob(int $jobId, int $routeRestaurantId): MenuScanJob
    {
        $restaurantId = app(TenantContext::class)->id();
        abort_unless($jobId > 0 && $routeRestaurantId > 0 && $restaurantId === $routeRestaurantId, 404);

        return MenuScanJob::withoutGlobalScopes()
            ->where('restaurant_id', $routeRestaurantId)
            ->whereKey($jobId)
            ->firstOrFail();
    }
}
