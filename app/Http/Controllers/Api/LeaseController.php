<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesOwnership;
use App\Http\Controllers\Controller;
use App\Models\Lease;
use App\Models\Tenant;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaseController extends Controller
{
    use AuthorizesOwnership;

    /**
     * Spec section 13 (Ajouter locataire): assign a tenant to a vacant unit, creating
     * the tenant record if `tenant_id` isn't given, and marking the unit occupied.
     */
    public function store(Request $request, Unit $unit)
    {
        $this->authorizeOwner($request, $unit, via: 'property');
        abort_if($unit->status === 'occupied', 422, 'Ce logement est déjà occupé.');

        $data = $request->validate([
            'tenant_id' => ['nullable', 'exists:tenants,id'],
            'tenant_name' => ['required_without:tenant_id', 'string', 'max:255'],
            'tenant_phone' => ['nullable', 'string', 'max:30'],
            'tenant_email' => ['nullable', 'email', 'max:255'],
            'start_date' => ['required', 'date'],
            'rent_amount' => ['required', 'integer', 'min:0'],
            'deposit' => ['nullable', 'integer', 'min:0'],
            'due_day' => ['required', 'integer', 'min:1', 'max:28'],
        ]);

        $lease = DB::transaction(function () use ($data, $unit, $request) {
            if (! empty($data['tenant_id'])) {
                $tenant = Tenant::findOrFail($data['tenant_id']);
                $this->authorizeOwner($request, $tenant);
            } else {
                $tenant = $request->user()->tenants()->create([
                    'name' => $data['tenant_name'],
                    'phone' => $data['tenant_phone'] ?? null,
                    'email' => $data['tenant_email'] ?? null,
                ]);
            }

            $lease = $tenant->leases()->create([
                'unit_id' => $unit->id,
                'start_date' => $data['start_date'],
                'rent_amount' => $data['rent_amount'],
                'deposit' => $data['deposit'] ?? null,
                'due_day' => $data['due_day'],
                'status' => 'active',
            ]);

            $unit->update(['status' => 'occupied']);

            return $lease;
        });

        return response()->json($lease->load('tenant', 'unit'), 201);
    }

    public function update(Request $request, Lease $lease)
    {
        $this->authorizeOwner($request, $lease, via: 'unit.property');

        $data = $request->validate([
            'end_date' => ['sometimes', 'nullable', 'date'],
            'rent_amount' => ['sometimes', 'integer', 'min:0'],
            'due_day' => ['sometimes', 'integer', 'min:1', 'max:28'],
            'status' => ['sometimes', 'in:active,expiring_soon,ended'],
        ]);

        $lease->update($data);

        return response()->json($lease->fresh());
    }

    /** The tenant moves out: end the lease and free up the unit. */
    public function vacate(Request $request, Lease $lease)
    {
        $this->authorizeOwner($request, $lease, via: 'unit.property');

        DB::transaction(function () use ($lease) {
            $lease->update(['status' => 'ended', 'end_date' => now()]);
            $lease->unit->update(['status' => 'vacant']);
        });

        return response()->json($lease->fresh());
    }
}
