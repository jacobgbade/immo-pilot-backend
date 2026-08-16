<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesOwnership;
use App\Http\Controllers\Controller;
use App\Models\Lease;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InspectionController extends Controller
{
    use AuthorizesOwnership;

    public const CATEGORIES = [
        'murs', 'plafonds', 'sols', 'portes', 'fenetres', 'plomberie',
        'electricite', 'sanitaires', 'cuisine', 'equipements', 'compteurs',
    ];

    /** Both états des lieux (entrée/sortie) for a lease, if they exist. */
    public function index(Request $request, Lease $lease)
    {
        $this->authorizeOwner($request, $lease, via: 'unit.property');

        return response()->json($lease->inspections()->with('items')->get());
    }

    /**
     * Art. 11 loi n°2022-30: état des lieux contradictoire, un par type (entrée/sortie).
     * One item per category is required so the record is complete, not partial.
     */
    public function store(Request $request, Lease $lease)
    {
        $this->authorizeOwner($request, $lease, via: 'unit.property');

        abort_if(
            $lease->inspections()->where('type', $request->input('type'))->exists(),
            422,
            "Un état des lieux de ce type existe déjà pour ce bail — utilisez la comparaison plutôt qu'un doublon."
        );

        $data = $request->validate([
            'type' => ['required', 'in:entree,sortie'],
            'form' => ['sometimes', 'in:sous_seing_prive,huissier'],
            'notes' => ['nullable', 'string'],
            'signed_at' => ['nullable', 'date'],
            'items' => ['required', 'array', 'size:' . count(self::CATEGORIES)],
            'items.*.category' => ['required', Rule::in(self::CATEGORIES), 'distinct'],
            'items.*.condition' => ['required', 'in:bon,moyen,mauvais'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        $inspection = DB::transaction(function () use ($data, $lease) {
            $inspection = $lease->inspections()->create([
                'type' => $data['type'],
                'form' => $data['form'] ?? 'sous_seing_prive',
                'notes' => $data['notes'] ?? null,
                'signed_at' => $data['signed_at'] ?? null,
            ]);

            $inspection->items()->createMany($data['items']);

            return $inspection;
        });

        return response()->json($inspection->load('items'), 201);
    }

    /**
     * Spec section 11 "COMPARER ENTRÉE / SORTIE": pairs each category's entry vs exit
     * condition so degradations are visible at a glance ahead of a deposit decision.
     */
    public function compare(Request $request, Lease $lease)
    {
        $this->authorizeOwner($request, $lease, via: 'unit.property');

        $inspections = $lease->inspections()->with('items')->get()->keyBy('type');
        $entree = $inspections->get('entree');
        $sortie = $inspections->get('sortie');

        $entreeItems = $entree?->items->keyBy('category') ?? collect();
        $sortieItems = $sortie?->items->keyBy('category') ?? collect();

        $rows = collect(self::CATEGORIES)->map(function (string $category) use ($entreeItems, $sortieItems) {
            $before = $entreeItems->get($category);
            $after = $sortieItems->get($category);

            return [
                'category' => $category,
                'entree_condition' => $before?->condition,
                'entree_notes' => $before?->notes,
                'sortie_condition' => $after?->condition,
                'sortie_notes' => $after?->notes,
                'degraded' => $before && $after && self::rank($after->condition) > self::rank($before->condition),
            ];
        });

        return response()->json([
            'entree' => $entree,
            'sortie' => $sortie,
            'rows' => $rows,
        ]);
    }

    private static function rank(string $condition): int
    {
        return ['bon' => 0, 'moyen' => 1, 'mauvais' => 2][$condition];
    }
}
