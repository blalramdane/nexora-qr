<?php

namespace App\Http\Controllers;

use App\Models\RestaurantExpense;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RestaurantExpenseController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeManagement();
        $restaurantId = app(TenantContext::class)->id();
        $date = $request->query('date', now()->toDateString());
        if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! \Illuminate\Support\Carbon::canBeCreatedFromFormat($date, 'Y-m-d')) $date = now()->toDateString();
        $query = RestaurantExpense::query()->where('restaurant_id', $restaurantId)->whereDate('expense_date', $date);
        $expenses = (clone $query)->latest('expense_date')->latest('id')->limit(200)->get()->map(fn ($e) => [
            'id' => $e->id, 'category' => $e->category, 'title' => $e->title, 'amount' => (float) $e->amount,
            'payment_method' => $e->payment_method, 'expense_date' => $e->expense_date->toDateString(), 'reference' => $e->reference, 'notes' => $e->notes,
        ]);
        return Inertia::render('Finance/Expenses', ['date' => $date, 'expenses' => $expenses,
            'total' => round((float) (clone $query)->sum('amount'), 2),
            'categories' => ['rent' => 'إيجار', 'utilities' => 'كهرباء ومياه وغاز', 'supplies' => 'مستلزمات تشغيل', 'maintenance' => 'صيانة', 'salaries' => 'رواتب وأجور', 'marketing' => 'تسويق وإعلانات', 'transport' => 'نقل ومواصلات', 'hospitality' => 'ضيافة', 'other' => 'أخرى'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManagement();
        $data = $request->validate([
            'category' => ['required', 'in:rent,utilities,supplies,maintenance,salaries,marketing,transport,hospitality,other'],
            'title' => ['required', 'string', 'min:2', 'max:160'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999.99'],
            'payment_method' => ['required', 'in:cash,card,wallet,bank_transfer,credit'],
            'expense_date' => ['required', 'date_format:Y-m-d'],
            'reference' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['restaurant_id'] = app(TenantContext::class)->id();
        $data['created_by'] = $request->user()->id;
        RestaurantExpense::create($data);
        return to_route('expenses.index', ['date' => $data['expense_date']])->with('success', 'تم تسجيل المصروف بنجاح.');
    }

    public function destroy(int $expense): RedirectResponse
    {
        $this->authorizeManagement();
        $record = RestaurantExpense::query()->where('restaurant_id', app(TenantContext::class)->id())->findOrFail($expense);
        $date = $record->expense_date->toDateString();
        $record->delete();
        return to_route('expenses.index', ['date' => $date])->with('success', 'تم حذف المصروف.');
    }

    private function authorizeManagement(): void
    {
        $user = request()->user(); $restaurant = app(TenantContext::class)->restaurant();
        abort_unless($user?->isOwnerOf($restaurant) || $user?->roleIn($restaurant) === 'manager', 403);
    }
}
