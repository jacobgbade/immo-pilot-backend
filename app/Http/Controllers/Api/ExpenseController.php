<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesOwnership;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\MaintenanceRequest;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    use AuthorizesOwnership;

    public function index(Request $request)
    {
        return response()->json(
            $request->user()->expenses()->with('property')->latest('spent_at')->get()
        );
    }

    /** Spec section 30: électricité/eau/autres are logged — maintenance is read, not logged. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'property_id' => ['nullable', 'exists:properties,id'],
            'category' => ['required', 'in:electricite,eau,autres'],
            'amount' => ['required', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:255'],
            'spent_at' => ['required', 'date'],
        ]);

        if (! empty($data['property_id'])) {
            $property = \App\Models\Property::findOrFail($data['property_id']);
            $this->authorizeOwner($request, $property);
        }

        $expense = $request->user()->expenses()->create($data);

        return response()->json($expense, 201);
    }

    public function destroy(Request $request, Expense $expense)
    {
        $this->authorizeOwner($request, $expense);
        $expense->delete();

        return response()->json(null, 204);
    }

    /** Spec section 30/31: this month's total by category, "maintenance" derived from real costs. */
    public function summary(Request $request)
    {
        $period = $request->query('period', now()->format('Y-m'));
        $propertyIds = $request->user()->properties()->pluck('id');

        $maintenance = (int) MaintenanceRequest::whereIn('property_id', $propertyIds)
            ->where('reported_at', 'like', "$period%")
            ->get()
            ->sum(fn (MaintenanceRequest $r) => $r->final_cost ?? $r->estimated_cost ?? 0);

        $logged = $request->user()->expenses()
            ->where('spent_at', 'like', "$period%")
            ->get()
            ->groupBy('category')
            ->map(fn ($group) => $group->sum('amount'));

        $byCategory = [
            'maintenance' => $maintenance,
            'electricite' => (int) ($logged['electricite'] ?? 0),
            'eau' => (int) ($logged['eau'] ?? 0),
            'autres' => (int) ($logged['autres'] ?? 0),
        ];

        return response()->json([
            'period' => $period,
            'by_category' => $byCategory,
            'total' => array_sum($byCategory),
        ]);
    }
}
