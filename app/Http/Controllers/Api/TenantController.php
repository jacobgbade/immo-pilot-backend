<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesOwnership;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    use AuthorizesOwnership;

    /** Spec section 20: list with payment status for the given (or current) period. */
    public function index(Request $request)
    {
        $period = $request->query('period', now()->format('Y-m'));

        $tenants = $request->user()->tenants()
            ->with(['leases' => fn ($q) => $q->where('status', '!=', 'ended')->latest('start_date')->with('unit.property', 'payments')])
            ->get()
            ->map(fn (Tenant $tenant) => $this->presentTenant($tenant, $period))
            ->filter() // drop tenants with no active lease
            ->values();

        return response()->json($tenants);
    }

    public function show(Request $request, Tenant $tenant)
    {
        $this->authorizeOwner($request, $tenant);

        $period = $request->query('period', now()->format('Y-m'));
        $tenant->load(['leases' => fn ($q) => $q->latest('start_date')->with('unit.property', 'payments')]);

        return response()->json([
            'tenant' => $this->presentTenant($tenant, $period),
            'payment_history' => $tenant->leases->first()?->payments->sortByDesc('period')->values() ?? [],
        ]);
    }

    public function update(Request $request, Tenant $tenant)
    {
        $this->authorizeOwner($request, $tenant);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
        ]);

        $tenant->update($data);

        return response()->json($tenant->fresh());
    }

    private function presentTenant(Tenant $tenant, string $period): ?array
    {
        $lease = $tenant->leases->first();
        if (! $lease) {
            return null;
        }

        $payment = $lease->payments->firstWhere('period', $period);

        return [
            'id' => $tenant->id,
            'lease_id' => $lease->id,
            'name' => $tenant->name,
            'phone' => $tenant->phone,
            'email' => $tenant->email,
            'property_id' => $lease->unit->property->id,
            'property_name' => $lease->unit->property->name,
            'unit_label' => $lease->unit->label,
            'rent' => $lease->rent_amount,
            'due_day' => $lease->due_day,
            'deposit' => $lease->deposit,
            'lease_start' => $lease->start_date?->toDateString(),
            'contract_status' => $lease->status,
            'payment_status' => $payment ? 'up_to_date' : 'overdue',
            'debt' => $payment ? 0 : $lease->rent_amount,
        ];
    }
}
