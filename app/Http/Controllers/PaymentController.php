<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethodEnum;
use App\Enums\PaymentTypeEnum;
use App\Models\Payment;
use Exception;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     * Only admin and super agent can view all payments
     * Agent can only view payments they created or are assigned to
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $userRole = $user->role?->name;

            if ($userRole === 'admin') {
                // Admin can see all payments
                $payments = Payment::with(['currency', 'savedBy', 'agent', 'stockHistory', 'invoice'])->get();
            } elseif ($userRole === 'super agent') {
                // Super agent can see all payments
                $payments = Payment::with(['currency', 'savedBy', 'agent', 'stockHistory', 'invoice'])->get();
            } elseif ($userRole === 'agent') {
                // Agent can only see payments they created or are assigned to
                $payments = Payment::where('saved_by', $user->id)
                    ->orWhere('agent_id', $user->id)
                    ->with(['currency', 'savedBy', 'agent', 'stockHistory', 'invoice'])
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
     * Only admin and super agent can create payments
     */
    // public function store(Request $request)
    // {
    //     try {
    //         $user = $request->user();
    //         $userRole = $user->role?->name;

    //         // Check authorization
    //         if (!in_array($userRole, ['admin', 'super agent'])) {
    //             return response()->json([
    //                 'message' => 'Unauthorized: Only admin and super agent can create payments',
    //                 'status' => 'error'
    //             ], 403);
    //         }

    //         $validated = $request->validate([
    //             'amount' => ['required', 'decimal:2'],
    //             'currency_id' => ['required', 'exists:currencies,id'],
    //             'saved_by' => ['required', 'exists:users,id'],
    //             'agent_id' => ['sometimes', 'exists:users,id'],
    //             'description' => ['sometimes', 'string', 'min:10'],
    //             'stock_history_id' => ['sometimes', 'exists:stock_histories,id'],
    //             'payment_type' => ['required', 'in:' . implode(',', array_column(PaymentTypeEnum::cases(), 'value'))],
    //             'invoice_id' => ['sometimes', 'exists:invoices,id'],
    //             'payment_method' => ['required', 'in:' . implode(',', array_column(PaymentMethodEnum::cases(), 'value'))]
    //         ]);

    //         $payment = Payment::create($validated);
    //         return response()->json([
    //             'message' => 'Payment saved successfully',
    //             'data' => $payment,
    //             'status' => 'success'
    //         ], 201);
    //     } catch (Exception $err) {
    //         return response()->json([
    //             'message' => 'An error occurred',
    //             'error' => $err->getMessage(),
    //             'status' => 'error'
    //         ], 500);
    //     }
    // }
    public function store(Request $request)
    {
        try {
            $user = $request->user();
            $userRole = $user->role?->name;

            // Check authorization
            if (!in_array($userRole, ['admin', 'super agent'])) {
                return response()->json([
                    'message' => 'Unauthorized: Only admin and super agent can create payments',
                    'status' => 'error'
                ], 403);
            }

            // Fallback injection logic if the client UI sends a null or empty saved_by parameter
            if (!$request->has('saved_by') || empty($request->input('saved_by'))) {
                $request->merge(['saved_by' => $user->id]);
            }

            $validated = $request->validate([
                'amount' => ['required', 'numeric'],
                'currency_id' => ['required', 'exists:currencies,id'],
                'saved_by' => ['required', 'exists:users,id'],
                'agent_id' => ['sometimes', 'nullable', 'exists:users,id'],
                'description' => ['required', 'string', 'min:5'],
                'payment_method' => ['required', 'string'],

                // Core dynamic conditional payload switches
                'payment_type' => ['required', 'string', 'in:Subscription,Ticket'],

                // client_id must exist if the payment_type is a Subscription
                'client_id' => ['required_if:payment_type,Subscription', 'nullable', 'exists:clients,id'],

                // stock_history_id must exist if the payment_type is a Ticket
                'stock_history_id' => ['required_if:payment_type,Ticket', 'nullable', 'exists:stock_histories,id'],

                'invoice_id' => ['sometimes', 'nullable', 'exists:invoices,id'],
            ]);

            // Backend enforcement: Ensure selected stock history item is actually a reduction (sale)
            if ($validated['payment_type'] === 'Ticket' && !empty($validated['stock_history_id'])) {
                $movement = \App\Models\StockHistory::find($validated['stock_history_id']);

                // Adjust 'reduction' or 'out' string check based on your actual database enum status
                if ($movement && $movement->type !== 'reduction' && $movement->type !== 'out') {
                    return response()->json([
                        'message' => 'Validation error',
                        'errors' => ['stock_history_id' => ['Le mouvement de stock sélectionné doit obligatoirement être une réduction (vente).']]
                    ], 422);
                }
            }

            $payment = Payment::create($validated);

            return response()->json([
                'message' => 'Payment saved successfully',
                'data' => $payment->load(['currency', 'savedBy']),
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
     * Only authorized users can view a payment
     */
    public function show(Request $request, Payment $payment)
    {
        try {
            $user = $request->user();
            $userRole = $user->role?->name;

            // Check authorization
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
     * Only admin and super agent can update payments
     */
    public function update(Request $request, Payment $payment)
    {
        try {
            $user = $request->user();
            $userRole = $user->role?->name;

            // Check authorization
            if (!in_array($userRole, ['admin', 'super agent'])) {
                return response()->json([
                    'message' => 'Unauthorized: Only admin and super agent can update payments',
                    'status' => 'error'
                ], 403);
            }

            $validated = $request->validate([
                'amount' => ['sometimes', 'decimal:2'],
                'currency_id' => ['sometimes', 'exists:currencies,id'],
                'saved_by' => ['sometimes', 'exists:users,id'],
                'agent_id' => ['sometimes', 'exists:users,id'],
                'description' => ['sometimes', 'string', 'min:10'],
                'stock_history_id' => ['sometimes', 'exists:stock_histories,id'],
                'payment_type' => ['sometimes', 'in:' . implode(',', array_column(PaymentTypeEnum::cases(), 'value'))],
                'invoice_id' => ['sometimes', 'exists:invoices,id'],
                'payment_method' => ['sometimes', 'in:' . implode(',', array_column(PaymentMethodEnum::cases(), 'value'))]
            ]);

            $payment->update($validated);

            return response()->json([
                'message' => 'Payment updated successfully',
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
     * Remove the specified resource from storage.
     * Only admin can delete payments
     */
    public function destroy(Request $request, Payment $payment)
    {
        try {
            $user = $request->user();
            $userRole = $user->role?->name;

            // Check authorization - only admin can delete
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
