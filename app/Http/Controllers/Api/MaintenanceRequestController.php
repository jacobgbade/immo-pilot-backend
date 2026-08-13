<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesOwnership;
use App\Http\Controllers\Controller;
use App\Models\Artisan;
use App\Models\MaintenanceRequest;
use App\Models\Property;
use Illuminate\Http\Request;

class MaintenanceRequestController extends Controller
{
    use AuthorizesOwnership;

    /** Spec section 27: list, most recent first. */
    public function index(Request $request)
    {
        $requests = MaintenanceRequest::whereIn('property_id', $request->user()->properties()->whereNull('archived_at')->pluck('id'))
            ->with('property', 'unit', 'tenant', 'artisan')
            ->latest('reported_at')
            ->get();

        return response()->json($requests);
    }

    /** Spec section 21 (Nouvelle demande): property + optional unit, title, description. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'property_id' => ['required', 'exists:properties,id'],
            'unit_id' => ['nullable', 'exists:units,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $property = Property::findOrFail($data['property_id']);
        $this->authorizeOwner($request, $property);

        $maintenanceRequest = MaintenanceRequest::create($data + [
            'status' => 'reported',
            'reported_at' => now(),
        ]);

        $request->user()->alerts()->create([
            'category' => 'maintenance',
            'icon' => '🔧',
            'message' => "Nouvelle demande de maintenance : {$maintenanceRequest->title} ({$property->name}).",
            'subject_type' => MaintenanceRequest::class,
            'subject_id' => $maintenanceRequest->id,
        ]);

        return response()->json($maintenanceRequest->load('property', 'unit', 'tenant', 'artisan'), 201);
    }

    public function show(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        $this->authorizeOwner($request, $maintenanceRequest, via: 'property');

        return response()->json($maintenanceRequest->load('property', 'unit', 'tenant', 'artisan'));
    }

    /** Spec section 28 timeline: Signalé → Artisan assigné → En cours → Résolu. */
    public function assignArtisan(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        $this->authorizeOwner($request, $maintenanceRequest, via: 'property');

        $data = $request->validate(['artisan_id' => ['required', 'exists:artisans,id']]);
        $artisan = Artisan::findOrFail($data['artisan_id']);
        $this->authorizeOwner($request, $artisan);

        $maintenanceRequest->update(['artisan_id' => $artisan->id, 'status' => 'assigned']);

        return response()->json($maintenanceRequest->fresh()->load('property', 'unit', 'tenant', 'artisan'));
    }

    public function markInProgress(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        $this->authorizeOwner($request, $maintenanceRequest, via: 'property');
        $maintenanceRequest->update(['status' => 'in_progress']);

        return response()->json($maintenanceRequest->fresh()->load('property', 'unit', 'tenant', 'artisan'));
    }

    public function markResolved(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        $this->authorizeOwner($request, $maintenanceRequest, via: 'property');

        $data = $request->validate(['final_cost' => ['nullable', 'integer', 'min:0']]);

        $maintenanceRequest->update([
            'status' => 'resolved',
            'final_cost' => $data['final_cost'] ?? $maintenanceRequest->estimated_cost,
        ]);

        if ($maintenanceRequest->artisan) {
            $maintenanceRequest->artisan->increment('interventions_count');
        }

        $request->user()->alerts()->create([
            'category' => 'maintenance',
            'icon' => '✓',
            'message' => "Intervention \"{$maintenanceRequest->title}\" résolue.",
            'subject_type' => MaintenanceRequest::class,
            'subject_id' => $maintenanceRequest->id,
        ]);

        return response()->json($maintenanceRequest->fresh()->load('property', 'unit', 'tenant', 'artisan'));
    }
}
