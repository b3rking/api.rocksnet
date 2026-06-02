<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SubscriptionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->query('per_page', 10);

        // Extraction des paramètres de tri par défaut
        $sortField = $request->query('sort_by', 'created_at');
        $sortDirection = $request->query('sort_desc', 'true') === 'true' ? 'desc' : 'asc';

        // Initialisation de la requête avec la relation de devise
        $query = Subscription::with('currency');

        // 1. --- FILTRE RECHERCHE (Recherche sur la bande passante ou le prix) ---
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('bandwidth', 'LIKE', "%{$search}%")
                    ->orWhere('price', 'LIKE', "%{$search}%")
                    ->orWhereHas('currency', function ($currencyQuery) use ($search) {
                        $currencyQuery->where('code', 'LIKE', "%{$search}%")
                            ->orWhere('name', 'LIKE', "%{$search}%")
                            ->orWhere('symbol', 'LIKE', "%{$search}%");
                    });
            });
        }

        // 2. --- LOGIQUE DE TRI AVANCÉ ---
        if ($sortField === 'currency') {
            // Optionnel : Si vous souhaitez trier par le code ou le nom de la devise
            $query->leftJoin('currencies', 'subscriptions.currency_id', '=', 'currencies.id')
                ->select('subscriptions.*')
                ->orderBy('currencies.code', $sortDirection);
        } else {
            // Évite les collisions d'ID sur les colonnes de base (bandwidth, price, created_at)
            $query->orderBy('subscriptions.' . $sortField, $sortDirection);
        }

        // Exécution de la pagination
        $subscriptions = $query->paginate($perPage);

        return response()->json([
            'message' => 'Subscriptions fetched successfully',
            'subscriptions' => $subscriptions,
            'count' => Subscription::count() // Compte total global gardé à titre informatif
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'bandwidth' => ['required'],
            'price' => ['required', 'decimal:2'],
            'currency_id' => ['required', 'exists:currencies,id']
        ]);

        $sub = Subscription::create($validated);

        return response()->json([
            'message' => 'new subscription added successfully',
            'subscription' => $sub->load('currency')
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Subscription $subscription)
    {
        return response()->json([
            'subscription' => $subscription->load('currency')
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Subscription $subscription)
    {
        $validated = $request->validate([
            'bandwidth' => ['sometimes'],
            'price' => ['sometimes', 'decimal:2'],
            'currency_id' => ['sometimes', 'exists:currencies,id']
        ]);

        $subscription->update($validated);
        $sub = $subscription->fresh();

        return response()->json([
            'message' => 'new subscription added successfully',
            'subscription' => $sub->load('currency')
        ], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Subscription $subscription)
    {
        $subscription->delete();
        return response()->json([], 204);
    }
}
