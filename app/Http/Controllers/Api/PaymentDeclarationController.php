<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesOwnership;
use App\Http\Controllers\Api\Concerns\ResolvesTenantLease;
use App\Http\Controllers\Controller;
use App\Models\PaymentDeclaration;
use App\Support\PaymentRecorder;
use Illuminate\Http\Request;

class PaymentDeclarationController extends Controller
{
    use AuthorizesOwnership, ResolvesTenantLease;

    /**
     * Tenant: signale avoir payé un mois hors app (espèces, virement). Reste "en attente"
     * jusqu'à confirmation du propriétaire (spec 0bis) — jamais confirmé automatiquement.
     */
    public function store(Request $request)
    {
        $lease = $this->activeLeaseForTenant($request);

        $data = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
            'amount' => ['required', 'integer', 'min:0'],
            'method' => ['required', 'in:mobile_money,especes,bancaire'],
            'reference' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
        ]);

        abort_if($lease->paymentForPeriod($data['period']), 422, 'Ce mois est déjà marqué payé.');
        abort_if(
            $lease->paymentDeclarations()->where('period', $data['period'])->where('status', 'pending')->exists(),
            422,
            'Une déclaration est déjà en attente pour cette période.'
        );

        $declaration = $lease->paymentDeclarations()->create($data + [
            'status' => 'pending',
            'declared_at' => now(),
        ]);

        $lease->unit->property->user->alerts()->create([
            'category' => 'paiements',
            'icon' => '📩',
            'message' => $lease->tenant->name . ' déclare avoir payé ' .
                number_format($data['amount'], 0, ',', ' ') . ' FCFA pour ' . $data['period'] . '.',
            'subject_type' => PaymentDeclaration::class,
            'subject_id' => $declaration->id,
        ]);

        return response()->json($declaration, 201);
    }

    /** Tenant: historique de ses propres déclarations, plus récentes en premier. */
    public function mine(Request $request)
    {
        $lease = $this->activeLeaseForTenant($request);

        return response()->json($lease->paymentDeclarations()->latest('declared_at')->get());
    }

    /** Owner: toutes les déclarations sur son parc, plus récentes en premier. */
    public function index(Request $request)
    {
        $propertyIds = $request->user()->properties()->whereNull('archived_at')->pluck('id');

        $declarations = PaymentDeclaration::whereHas(
            'lease.unit',
            fn ($q) => $q->whereIn('property_id', $propertyIds)
        )->with('lease.tenant', 'lease.unit.property')
            ->latest('declared_at')
            ->get();

        return response()->json($declarations);
    }

    /** Owner confirme : la déclaration devient un vrai paiement (Art. 66, quittance générée). */
    public function confirm(Request $request, PaymentDeclaration $declaration)
    {
        $this->authorizeOwner($request, $declaration->lease, via: 'unit.property');
        abort_if($declaration->status !== 'pending', 422, 'Cette déclaration a déjà été traitée.');

        PaymentRecorder::record($request->user(), $declaration->lease, [
            'period' => $declaration->period,
            'amount' => $declaration->amount,
            'method' => $declaration->method,
            'reference' => $declaration->reference,
        ]);

        $declaration->update(['status' => 'confirmed', 'reviewed_at' => now()]);

        return response()->json($declaration->fresh());
    }

    public function reject(Request $request, PaymentDeclaration $declaration)
    {
        $this->authorizeOwner($request, $declaration->lease, via: 'unit.property');
        abort_if($declaration->status !== 'pending', 422, 'Cette déclaration a déjà été traitée.');

        $declaration->update(['status' => 'rejected', 'reviewed_at' => now()]);

        return response()->json($declaration->fresh());
    }
}
