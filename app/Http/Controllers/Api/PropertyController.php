<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesOwnership;
use App\Http\Controllers\Controller;
use App\Models\Property;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    use AuthorizesOwnership;

    /** Spec section 16: list, with each unit's occupancy already loaded. */
    public function index(Request $request)
    {
        $properties = $request->user()
            ->properties()
            ->whereNull('archived_at')
            ->with('units')
            ->latest()
            ->get();

        return response()->json($properties);
    }

    /** Spec section 9 (Ajouter propriété): name, address, monthly_rent — no units yet. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'monthly_rent' => ['required', 'integer', 'min:0'],
            'estimated_value' => ['nullable', 'integer', 'min:0'],
        ]);

        $property = $request->user()->properties()->create($data);

        return response()->json($property, 201);
    }

    public function show(Request $request, Property $property)
    {
        $this->authorizeOwner($request, $property);

        return response()->json($property->load('units', 'maintenanceRequests'));
    }

    public function update(Request $request, Property $property)
    {
        $this->authorizeOwner($request, $property);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'address' => ['sometimes', 'string', 'max:255'],
            'monthly_rent' => ['sometimes', 'integer', 'min:0'],
            'estimated_value' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ]);

        $property->update($data);

        return response()->json($property->fresh());
    }

    /** Spec section 39: archiving is a soft, reversible action — not a hard delete. */
    public function archive(Request $request, Property $property)
    {
        $this->authorizeOwner($request, $property);

        $property->update(['archived_at' => now()]);

        return response()->json($property->fresh());
    }
}
