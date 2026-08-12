<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesOwnership;
use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    use AuthorizesOwnership;

    /** Spec section 12 (Ajouter logement): label, bedrooms, rent — starts vacant. */
    public function store(Request $request, Property $property)
    {
        $this->authorizeOwner($request, $property);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'bedrooms' => ['required', 'integer', 'min:0', 'max:20'],
            'rent' => ['required', 'integer', 'min:0'],
        ]);

        $unit = $property->units()->create($data + ['status' => 'vacant']);

        return response()->json($unit, 201);
    }

    public function update(Request $request, Unit $unit)
    {
        $this->authorizeOwner($request, $unit, via: 'property');

        $data = $request->validate([
            'label' => ['sometimes', 'string', 'max:255'],
            'bedrooms' => ['sometimes', 'integer', 'min:0', 'max:20'],
            'rent' => ['sometimes', 'integer', 'min:0'],
            'status' => ['sometimes', 'in:occupied,vacant,maintenance'],
        ]);

        $unit->update($data);

        return response()->json($unit->fresh());
    }
}
