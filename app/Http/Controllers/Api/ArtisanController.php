<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesOwnership;
use App\Http\Controllers\Controller;
use App\Models\Artisan;
use Illuminate\Http\Request;

class ArtisanController extends Controller
{
    use AuthorizesOwnership;

    /** Spec section 29: directory, optionally filtered by trade. */
    public function index(Request $request)
    {
        $artisans = $request->user()->artisans()
            ->when($request->query('trade'), fn ($q, $trade) => $q->where('trade', $trade))
            ->orderBy('name')
            ->get();

        return response()->json($artisans);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'trade' => ['required', 'in:plombier,electricien,peintre,macon,climatisation,serrurier'],
            'phone' => ['required', 'string', 'max:30'],
            'zone' => ['nullable', 'string', 'max:255'],
        ]);

        $artisan = $request->user()->artisans()->create($data);

        return response()->json($artisan, 201);
    }

    public function update(Request $request, Artisan $artisan)
    {
        $this->authorizeOwner($request, $artisan);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'trade' => ['sometimes', 'in:plombier,electricien,peintre,macon,climatisation,serrurier'],
            'phone' => ['sometimes', 'string', 'max:30'],
            'zone' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $artisan->update($data);

        return response()->json($artisan->fresh());
    }

    public function destroy(Request $request, Artisan $artisan)
    {
        $this->authorizeOwner($request, $artisan);
        $artisan->delete();

        return response()->json(null, 204);
    }
}
