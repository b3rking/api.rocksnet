<?php

namespace App\Http\Controllers;

use App\Models\Profil;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProfilController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->query('per_page', 10);

        // Récupération des paramètres de tri (Cohérence totale avec ta structure)
        $sortField = $request->query('sort_by', 'created_at');
        $sortDirection = $request->query('sort_desc', 'true') === 'true' ? 'desc' : 'asc';

        $query = Profil::with('currency');

        // --- FILTRES DYNAMIQUES ET CONFIGURABLES ---
        // 1. Recherche globale (Nom / Durée / Prix)
        if ($search = $request->query('search')) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                ->orWhere('duration', 'LIKE', "%{$search}%")
                ->orWhere('price', 'LIKE', "%{$search}%");
            });
        }

        // 2. Filtre exact par devise si besoin (optionnel, mais garde la logique)
        if ($currencyId = $request->query('currency_id')) {
            $query->where('currency_id', $currencyId);
        }

        // --- TRI EFFECTIF ---
        // Gestion du tri sur la relation currency (ex: currency.name ou currency.code)
        if ($sortField === 'currency.name' || $sortField === 'currency.code') {
            $column = $sortField === 'currency.name' ? 'currencies.name' : 'currencies.code';

            $query->leftJoin('currencies', 'profils->currency_id', '=', 'currencies.id')
                ->select('profils.*') // Évite la collision d'IDs
                ->orderBy($column, $sortDirection);
        } else {
            // Tri classique sur la table profils
            $query->orderBy($sortField, $sortDirection);
        }

        $profils = $query->paginate($perPage);

        return response()->json($profils);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|min:4',
            'duration' => 'required',
            'price' => 'required',
            'currency_id' => 'required|exists:currencies,id'
        ]);

        return response()->json([
            'message' => 'profile creer avec success',
            'profil' => Profil::create($validated)
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return Profil::find($id)
            ->load('currency');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Profil $profil)
    {
        $validated = $request->validate([
            'name' => 'sometimes|min:4',
            'duration' => 'sometimes',
            'price' => 'sometimes',
            'currency_id' => 'sometimes|exists:currencies,id'
        ]);

        $profil->update($validated);

        return response()->json([
            'message' => 'profile mise a jour avec success',
            'profil' => $profil->fresh()->load('currency')
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Profil $profil)
    {
        $profil = $profil->delete();
        return response()->json([], 204);
    }
}
