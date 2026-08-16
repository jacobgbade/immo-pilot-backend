<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesOwnership;
use App\Http\Controllers\Api\InspectionController;
use App\Http\Controllers\Controller;
use App\Models\Lease;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\LegalRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

        LegalRules::validateDeposit($data['rent_amount'], $data['deposit'] ?? null);

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
                'initial_rent_amount' => $data['rent_amount'],
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

        if (isset($data['rent_amount']) && $data['rent_amount'] !== $lease->rent_amount) {
            LegalRules::validateRentRevision($lease->initial_rent_amount, $data['rent_amount']);
        }

        $lease->update($data);

        return response()->json($lease->fresh());
    }

    /**
     * Everything the owner needs to decide a fair deposit refund before confirming the
     * move-out: damages surfaced by the entrée/sortie comparison (Art. 70) and any unpaid
     * rent still open against this lease (Art. 70 also lets the deposit cover arrears).
     */
    public function vacateSummary(Request $request, Lease $lease)
    {
        $this->authorizeOwner($request, $lease, via: 'unit.property');

        $inspections = $lease->inspections()->with('items')->get()->keyBy('type');
        $entreeItems = $inspections->get('entree')?->items->keyBy('category') ?? collect();
        $sortieItems = $inspections->get('sortie')?->items->keyBy('category') ?? collect();
        $rank = fn (string $c) => ['bon' => 0, 'moyen' => 1, 'mauvais' => 2][$c];

        $degradedItems = collect(InspectionController::CATEGORIES)
            ->map(function (string $category) use ($entreeItems, $sortieItems, $rank) {
                $before = $entreeItems->get($category);
                $after = $sortieItems->get($category);
                if ($before && $after && $rank($after->condition) > $rank($before->condition)) {
                    return [
                        'category' => $category,
                        'entree_condition' => $before->condition,
                        'sortie_condition' => $after->condition,
                        'sortie_notes' => $after->notes,
                    ];
                }
                return null;
            })->filter()->values();

        $openArrears = $lease->demandLetters()->whereNull('resolved_at')->get(['period', 'amount']);
        $openArrearsTotal = $openArrears->sum('amount');
        $deposit = $lease->deposit ?? 0;

        return response()->json([
            'deposit' => $lease->deposit,
            'degraded_items' => $degradedItems,
            'open_arrears' => $openArrears,
            'open_arrears_total' => $openArrearsTotal,
            'suggested_max_refund' => max(0, $deposit - $openArrearsTotal),
        ]);
    }

    /** The tenant moves out: end the lease, settle the deposit, and free up the unit. */
    public function vacate(Request $request, Lease $lease)
    {
        $this->authorizeOwner($request, $lease, via: 'unit.property');

        abort_unless(
            $lease->inspections()->where('type', 'sortie')->exists(),
            422,
            "L'état des lieux de sortie est obligatoire avant la clôture du bail — Art. 11 de la loi n°2022-30."
        );

        $data = $request->validate([
            'deposit_refund_amount' => ['nullable', 'integer', 'min:0'],
            'deposit_refund_notes' => ['nullable', 'string'],
        ]);

        // Art. 71: le montant de la caution ne peut être révisé — la restitution ne peut
        // donc jamais dépasser ce qui a réellement été versé à l'entrée.
        if (isset($data['deposit_refund_amount']) && $data['deposit_refund_amount'] > ($lease->deposit ?? 0)) {
            throw ValidationException::withMessages([
                'deposit_refund_amount' => [sprintf(
                    'Le montant restitué ne peut dépasser la caution versée (%s FCFA) — Art. 69 et 71 de la loi n°2022-30.',
                    number_format($lease->deposit ?? 0, 0, ',', ' '),
                )],
            ]);
        }

        DB::transaction(function () use ($lease, $data) {
            $lease->update([
                'status' => 'ended',
                'end_date' => now(),
                'deposit_refund_amount' => $data['deposit_refund_amount'] ?? null,
                'deposit_refund_notes' => $data['deposit_refund_notes'] ?? null,
                'deposit_refunded_at' => array_key_exists('deposit_refund_amount', $data) ? now() : null,
            ]);
            $lease->unit->update(['status' => 'vacant']);
        });

        return response()->json($lease->fresh());
    }
}
