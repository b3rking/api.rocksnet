<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethodEnum;
use App\Enums\PaymentTypeEnum;
use App\Models\Payment;
use App\Models\Invoice;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $userRole = $user->role?->name;

            if (in_array($userRole, ['admin', 'super agent'])) {
                $payments = Payment::with(['currency', 'savedBy', 'agent', 'stockHistory', 'invoice.client'])->get();
            } elseif ($userRole === 'agent') {
                $payments = Payment::where('saved_by', $user->id)
                    ->orWhere('agent_id', $user->id)
                    ->with(['currency', 'savedBy', 'agent', 'stockHistory', 'invoice.client'])
                    ->get();
            } else {
                return response()->json([
                    'message' => 'Unauthorized',
                    'status' => 'error'
                ], 403);
            }

            return response()->json([
                'message' => 'Payments retrieved successfully',
                'data' => $payments,
                'status' => 'success'
            ], 200);
        } catch (Exception $err) {
            return response()->json([
                'message' => 'An error occurred',
                'error' => $err->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $userRole = $user->role?->name;

            if (!in_array($userRole, ['admin', 'super agent'])) {
                return response()->json([
                    'message' => 'Unauthorized: Only admin and super agent can create payments',
                    'status' => 'error'
                ], 403);
            }

            if (!$request->has('saved_by') || empty($request->input('saved_by'))) {
                $request->merge(['saved_by' => $user->id]);
            }

            $validated = $request->validate([
                'amount' => ['required', 'numeric'],
                'currency_id' => ['required', 'exists:currencies,id'],
                'saved_by' => ['required', 'exists:users,id'],
                'agent_id' => ['sometimes', 'nullable', 'exists:users,id'],
                'payment_method' => ['required', 'string'],
                'payment_type' => ['required', 'string', 'in:Subscription,Ticket'],

                // Subscription specifics
                'client_id' => ['required_if:payment_type,Subscription', 'nullable', 'exists:clients,id'],
                'period' => ['required_if:payment_type,Subscription', 'nullable', 'string', 'in:15 days,1 month,3 months,6 months,1 year'],

                // Ticket specifics
                'stock_history_id' => ['required_if:payment_type,Ticket', 'nullable', 'exists:stock_histories,id'],
            ]);

            // Ticket Reduction validation check
            if ($validated['payment_type'] === 'Ticket' && !empty($validated['stock_history_id'])) {
                $movement = \App\Models\StockHistory::find($validated['stock_history_id']);
                if ($movement && $movement->action !== 'Reduction') {
                    return response()->json([
                        'message' => 'Validation error',
                        'errors' => ['stock_history_id' => ['Le mouvement de stock sélectionné doit obligatoirement être une réduction (vente).']]
                    ], 422);
                }
            }

            // Database isolation wrapper
            $payment = DB::transaction(function () use ($validated) {
                // 1. Create the base payment record
                $paymentRecord = Payment::create($validated);

                // 2. If it's a Subscription, generate the background invoice link directly
                if ($validated['payment_type'] === 'Subscription') {
                    Invoice::create([
                        'client_id' => $validated['client_id'],
                        'payment_id' => $paymentRecord->id,
                        'period' => $validated['period']
                    ]);
                }

                return $paymentRecord;
            });

            return response()->json([
                'message' => 'Payment saved successfully',
                'data' => $payment->load(['currency', 'savedBy', 'invoice']),
                'status' => 'success'
            ], 201);

        } catch (Exception $err) {
            return response()->json([
                'message' => 'An error occurred',
                'error' => $err->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Payment $payment): JsonResponse
    {
        try {
            $user = $request->user();
            $userRole = $user->role?->name;

            if ($userRole === 'agent' && $payment->saved_by !== $user->id && $payment->agent_id !== $user->id) {
                return response()->json([
                    'message' => 'Unauthorized: You can only view your own payments',
                    'status' => 'error'
                ], 403);
            }

            $payment->load(['currency', 'savedBy', 'agent', 'stockHistory', 'invoice']);

            return response()->json([
                'message' => 'Payment retrieved successfully',
                'data' => $payment,
                'status' => 'success'
            ], 200);
        } catch (Exception $err) {
            return response()->json([
                'message' => 'An error occurred',
                'error' => $err->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Payment $payment): JsonResponse
    {
        try {
            $user = $request->user();
            $userRole = $user->role?->name;

            if (!in_array($userRole, ['admin', 'super agent'])) {
                return response()->json([
                    'message' => 'Unauthorized: Only admin and super agent can update payments',
                    'status' => 'error'
                ], 403);
            }

            // CRITICAL SAFEGUARD: Block altering payment_type context structural modes entirely
            if ($request->has('payment_type') && $request->input('payment_type') !== $payment->payment_type) {
                return response()->json([
                    'message' => 'Validation error',
                    'errors' => ['payment_type' => ['Le type de paiement ne peut pas être modifié après sa création pour préserver la cohérence des données.']]
                ], 422);
            }

            $validated = $request->validate([
                'amount' => ['sometimes', 'numeric'],
                'currency_id' => ['sometimes', 'exists:currencies,id'],
                'saved_by' => ['sometimes', 'exists:users,id'],
                'agent_id' => ['sometimes', 'nullable', 'exists:users,id'],
                'description' => ['sometimes', 'string', 'min:5'],
                'payment_method' => ['sometimes', 'string'],

                // Subscription contextual changes
                'client_id' => ['required_if:payment_type,Subscription', 'nullable', 'exists:clients,id'],
                'period' => ['required_if:payment_type,Subscription', 'nullable', 'string', 'in:15 days,1 month,3 months,6 months,1 year'],

                // Ticket contextual changes
                'stock_history_id' => ['required_if:payment_type,Ticket', 'nullable', 'exists:stock_histories,id'],
            ]);

            // Validate stock history action rule on edit if provided
            if ($payment->payment_type === 'Ticket' && !empty($validated['stock_history_id'])) {
                $movement = \App\Models\StockHistory::find($validated['stock_history_id']);
                if ($movement && $movement->action !== 'Reduction') {
                    return response()->json([
                        'message' => 'Validation error',
                        'errors' => ['stock_history_id' => ['Le mouvement de stock sélectionné doit obligatoirement être une réduction (vente).']]
                    ], 422);
                }
            }

            // Run database updates inside isolated transaction block
            DB::transaction(function () use ($payment, $validated) {
                $payment->update($validated);

                // Sync invoice information updates if we are operating inside a Subscription structural timeline
                if ($payment->payment_type === 'Subscription') {
                    Invoice::updateOrCreate(
                        ['payment_id' => $payment->id],
                        [
                            'client_id' => $validated['client_id'] ?? $payment->client_id,
                            'period' => $validated['period'] ?? $payment->invoice?->period
                        ]
                    );
                }
            });

            return response()->json([
                'message' => 'Payment updated successfully',
                'data' => $payment->load(['currency', 'savedBy', 'invoice']),
                'status' => 'success'
            ], 200);
        } catch (Exception $err) {
            return response()->json([
                'message' => 'An error occurred',
                'error' => $err->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Payment $payment): JsonResponse
    {
        try {
            $user = $request->user();
            $userRole = $user->role?->name;

            if ($userRole !== 'admin') {
                return response()->json([
                    'message' => 'Unauthorized: Only admin can delete payments',
                    'status' => 'error'
                ], 403);
            }

            $payment->delete();

            return response()->json([
                'message' => 'Payment deleted successfully',
                'status' => 'success'
            ], 200);
        } catch (Exception $err) {
            return response()->json([
                'message' => 'An error occurred',
                'error' => $err->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }
}
