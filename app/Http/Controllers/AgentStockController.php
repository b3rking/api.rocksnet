<?php

namespace App\Http\Controllers;

use App\Enums\ActionEnum;
use App\Models\AgentStock;
use App\Models\StockHistory;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AgentStockController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->query('per_page', 10);
        $sortField = $request->query('sort_by', 'updated_at');
        $sortDirection = $request->query('sort_desc', 'true') === 'true' ? 'desc' : 'asc';

        // Initialisation de la requête avec les relations requises
        $query = AgentStock::with(['user', 'profil.currency']);

        // 1. Restriction de sécurité selon les rôles
        if (!$request->user()->isAdmin()) {
            $query->where('user_id', $request->user()->id);
        }

        // 2. Filtres Dynamiques (Recherche croisée sur les relations)
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('quantity', 'LIKE', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('email', 'LIKE', "%{$search}%");
                    })
                    ->orWhereHas('profil', function ($profilQuery) use ($search) {
                        $profilQuery->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('duration', 'LIKE', "%{$search}%");
                    });
            });
        }

        // 3. Tri effectif standard et sur relations complexes
        if ($sortField === 'user.name') {
            $query->leftJoin('users', 'agent_stocks.user_id', '=', 'users.id')
                ->select('agent_stocks.*')
                ->orderBy('users.name', $sortDirection);
        } elseif ($sortField === 'profil.name') {
            $query->leftJoin('profils', 'agent_stocks.profil_id', '=', 'profils.id')
                ->select('agent_stocks.*')
                ->orderBy('profils.name', $sortDirection);
        } else {
            // Tri classique (ex: quantity, updated_at) sur la table principale
            // On s'assure d'éviter l'ambiguïté en forçant le nom de la table
            $query->orderBy('agent_stocks.' . $sortField, $sortDirection);
        }

        // Exécution de la pagination unifiée
        $stocks = $query->paginate($perPage);

        return response()->json($stocks);
    }

    public function attributeTicket(Request $request, User $user): JsonResponse
    {
        if ($request->user()->isAdmin()) {
            $validated = $request->validate([
                'user_id' => ['required', 'exists:users,id'],
                'profil_id' => ['required', 'exists:profils,id'],
                'quantity' => ['required']
            ]);

            $agent_stock = AgentStock::where('user_id', $request->user_id)->where('profil_id', $request->profil_id)->first();
            if ($agent_stock) {
                $agent_stock->update(['quantity' => $agent_stock->quantity + $request->quantity]);
                $agent_stock->fresh();
            } else {
                $agent_stock = AgentStock::create($validated);
            }

            StockHistory::create([
                'agent_id' => $request->user_id,
                'profil_id' => $request->profil_id,
                'quantity' => $request->quantity,
                'action' => ActionEnum::ATTRIBUTION
            ]);

            return response()->json([
                'message' => 'attribution successful',
                'agent_stock' => $agent_stock
            ]);
        } else {
            return response()->json([
                'message' => 'Action non autoriser'
            ], 403);
        }
    }

    public function saleTicket(Request $request)
    {
        $request->validate([
            'tickets_sold' => ['required', 'min:1'],
            'profil_id' => ['required', 'exists:profils,id']
        ]);
        // check if he is not admin
        if (!$request->user()->isAdmin()) {
            $stock = AgentStock::where('profil_id', $request->profil_id)
                ->where('user_id', $request->user()->id)
                ->first();

            if ($stock) {
                if ($stock->quantity >= $request->tickets_sold && $stock->quantity > 0) {
                    $transaction = DB::transaction(function () use ($request, $stock) {
                        $history = StockHistory::create([
                            'agent_id' => $request->user()->id,
                            'profil_id' => $request->profil_id,
                            'quantity' => $request->tickets_sold,
                            'action' => ActionEnum::REDUCTION
                        ]);

                        $updated_quantity = $stock->quantity - $request->tickets_sold;
                        if ($updated_quantity > 0) {
                            $stock->update(['quantity' => $updated_quantity]);
                        } else {
                            $stock->delete();
                        }
                        $stock->fresh();

                        return ['history' => $history, 'stock' => $stock];
                    });
                    return response()->json([
                        'message' => 'enregistrer avec success!',
                        'agent_stock' => $transaction['stock'],
                        'stock_history' => $transaction['history']
                    ], 201);
                } else {
                    return response()->json([
                        'message' => 'La quantite saisie n\'est pas disponible dans votre stock'
                    ], 422);
                }
            } else {
                return response()->json([
                    'message' => 'No stock found for this profil'
                ], 400);
            }
        }
        return response()->json(['message' => 'Action non autoriser'], 403);
    }

    public function saleHistory(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = $request->query('per_page', 10);

        // Extraction des paramètres de tri (Cohérence totale avec l'architecture de la plateforme)
        $sortField = $request->query('sort_by', 'created_at');
        $sortDirection = $request->query('sort_desc', 'true') === 'true' ? 'desc' : 'asc';

        // Initialisation de la requête globale avec ses relations
        $query = StockHistory::with(['agent', 'profil']);

        // 1. RÈGLE DE SÉCURITÉ & LOGIQUE INHÉRENTE : Uniquement ce qui n'a pas encore de paiement associé
        $query->whereDoesntHave('payment');

        // 2. Cloisonnement par rôles (Scoping)
        if (!$user->isAdmin()) {
            $query->where('agent_id', $user->id);
        } else {
            // Optionnel : Conserver le filtre précis par agent si passé via paramètres annexes
            if ($request->filled('agent_id')) {
                $query->where('agent_id', $request->query('agent_id'));
            }
        }

        // 3. Filtre exact par type d'opération (Action)
        if ($request->filled('action') && $request->query('action') !== 'all') {
            $query->where('action', $request->query('action'));
        }

        // 4. --- RECHERCHE GLOBALE ET DYNAMIQUE (Agent / Profil Technique) ---
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('quantity', 'LIKE', "%{$search}%")
                    ->orWhereHas('agent', function ($agentQuery) use ($search) {
                        $agentQuery->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('email', 'LIKE', "%{$search}%");
                    })
                    ->orWhereHas('profil', function ($profilQuery) use ($search) {
                        $profilQuery->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('duration', 'LIKE', "%{$search}%");
                    });
            });
        }

        // 5. --- TRI EFFECTIF ET AVANCÉ SUR RELATIONS ---
        if ($sortField === 'agent.name') {
            $query->leftJoin('users', 'stock_histories.agent_id', '=', 'users.id')
                ->select('stock_histories.*')
                ->orderBy('users.name', $sortDirection);
        } elseif ($sortField === 'profil.name') {
            $query->leftJoin('profils', 'stock_histories.profil_id', '=', 'profils.id')
                ->select('stock_histories.*')
                ->orderBy('profils.name', $sortDirection);
        } else {
            // Tri sur colonnes natives (created_at, quantity, action) sans collision d'ID
            $query->orderBy('stock_histories.' . $sortField, $sortDirection);
        }

        // Exécution de la pagination normalisée
        $history = $query->paginate($perPage);

        return response()->json($history);
    }
}
