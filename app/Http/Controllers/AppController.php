<?php

namespace App\Http\Controllers;

use App\Models\AgentStock;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Currency;
use App\Models\Payment;
use App\Models\Role;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class AppController extends Controller
{

    public function listAgent(): JsonResponse
    {
        return response()->json(User::where('role_id', 3)->get());
    }

    public function listSuperAgent(): JsonResponse
    {
        return response()->json(User::where('role_id', 2)->get());
    }

    public function listAdmin(): JsonResponse
    {
        return response()->json(User::where('role_id', 1)->get());
    }

    public function users(Request $request): JsonResponse
    {
        // On récupère dynamiquement le pageSize envoyé par React (par défaut 10)
        $perPage = $request->query('per_page', 10);

        $users = User::latest()
            ->with('role')
            ->paginate($perPage);

        return response()->json($users);
    }

    public function currencies(): JsonResponse
    {
        return response()->json([
            'currencies' => Currency::all(),
            'count' => Currency::count()
        ]);
    }

    public function roles(): JsonResponse
    {
        return response()->json([
            'roles' => Role::all()
        ]);
    }

    public function getChartData()
    {
        // 1. Définir les mois en français pour le mapping final
        $monthsFr = [
            1 => 'Jan', 2 => 'Fév', 3 => 'Mar', 4 => 'Avr', 5 => 'Mai', 6 => 'Juin',
            7 => 'Juil', 8 => 'Août', 9 => 'Sept', 10 => 'Oct', 11 => 'Nov', 12 => 'Déc'
        ];

        // 2. Récupérer les données agrégées par mois depuis la BDD
        // Ajustez 'payment_type' et ses valeurs d'Enum selon votre logique exacte
        $payments = Payment::query()
            ->select(
                DB::raw('MONTH(created_at) as month'),
                // Somme des montants si le paiement est de type "sale" / vente
                DB::raw("SUM(CASE WHEN payment_type = 'Subscription' THEN amount ELSE 0 END) as sales"),
                // Somme des montants pour les revenus généraux (ou totaux)
                DB::raw("SUM(CASE WHEN payment_type = 'Ticket' THEN amount ELSE 0 END) as revenue")
            )
            ->whereYear('created_at', Carbon::now()->year)
            ->groupBy(DB::raw('MONTH(created_at)'))
            ->orderBy('month')
            ->get();

        // 3. Formater le tableau pour qu'il inclue tous les mois (même ceux à 0) jusqu'au mois actuel
        $currentMonth = Carbon::now()->month;
        $formattedChartData = [];

        for ($m = 1; $m <= $currentMonth; $m++) {
            // Trouver la ligne correspondante dans la collection de la BDD
            $dbData = $payments->firstWhere('month', $m);

            $formattedChartData[] = [
                'name' => $monthsFr[$m],
                'sales' => $dbData ? (float) $dbData->sales : 0,
                'revenue' => $dbData ? (float) $dbData->revenue : 0,
            ];
        }

        return $formattedChartData;
    }

    public function dashboard_data(): JsonResponse
    {
        $total_clients = Client::count();
        $actif_clients = Client::where('etat', 'Actif')->count();
        $total_sales = Payment::count();
        $stock = AgentStock::all()->groupBy('profil_id')->map(function($group) {
            return $group->sum('quantity');
        })->sum();
        $performance = $this->getChartData();
        $recent_payment = Payment::latest()->get()->take(5);
        try {
            return response()->json([
                'message' => 'fetch successful',
                'data' => [
                    'total_client' => $total_clients,
                    'total_client_actif' => $actif_clients,
                    'total_sales' => $total_sales,
                    'stock' => $stock,
                    'performance' => $performance,
                    'recent_payment' => $recent_payment
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'an error occured',
                'error' => $e->getMessage()
            ]);
        }
    }
}
