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
        if (!$request->user()->isAdmin()) {
            return response()->json([
                'stock' => AgentStock::all(),
                'count' => AgentStock::count()
            ]);
        } else {
            return response()->json([
                'message' => 'admin cannot have a stock'
            ], 400);
        }
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
                'action' => ActionEnum::REDUCTION
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
                        'message' => 'you cannot sale more than what you have'
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
        if ($user->isAdmin()) {
            $history = StockHistory::latest()->paginate(10);
        } else {
            $history = $user->history;
        }

        return response()->json([
            'message' => 'history fetched successfully',
            'history' => $history,
            'count' => $history->count()
        ], 200);
    }
}
