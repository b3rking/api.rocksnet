<?php

namespace App\Http\Controllers;

use App\Enums\ClientEtat;
use App\Models\Client;
use Exception;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    /**
     * Display a listing of the resource.
     * Accessible by admin, super agent, and agents.
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $userRole = $user->role?->name;

            // Enforce explicit authorization matching payment system rules
            if (!in_array($userRole, ['admin', 'super agent', 'agent'])) {
                return response()->json([
                    'message' => 'Unauthorized',
                    'status' => 'error'
                ], 403);
            }

            // Fetch clients eager-loading subscription relations
            $clients = Client::with(['subscription.currency'])->get();

            return response()->json([
                'message' => 'Clients retrieved successfully',
                'clients' => $clients,
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
     * Only admin and super agent can create clients.
     */
    public function store(Request $request)
    {
        try {
            $user = $request->user();
            $userRole = $user->role?->name;

            if (!in_array($userRole, ['admin', 'super agent'])) {
                return response()->json([
                    'message' => 'Unauthorized: Only admin and super agent can register clients',
                    'status' => 'error'
                ], 403);
            }

            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:clients,email'], // Added Email rules
                'phone' => ['required', 'string', 'max:50'],
                'adress' => ['required', 'string', 'max:255'],
                'subscription_id' => ['required', 'exists:subscriptions,id'],
                'etat' => ['required', 'in:' . implode(',', array_column(ClientEtat::cases(), 'value'))]
            ]);

            $client = Client::create($validated);

            return response()->json([
                'message' => 'Client registered successfully',
                'data' => $client->load('subscription'),
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
    public function show(Request $request, Client $client)
    {
        try {
            $user = $request->user();
            $userRole = $user->role?->name;

            if (!in_array($userRole, ['admin', 'super agent', 'agent'])) {
                return response()->json([
                    'message' => 'Unauthorized',
                    'status' => 'error'
                ], 403);
            }

            $client->load(['subscription', 'payments.currency']);

            return response()->json([
                'message' => 'Client details retrieved successfully',
                'data' => $client,
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
     * Only admin and super agent can update client information.
     */
    public function update(Request $request, Client $client)
    {
        try {
            $user = $request->user();
            $userRole = $user->role?->name;

            if (!in_array($userRole, ['admin', 'super agent'])) {
                return response()->json([
                    'message' => 'Unauthorized: Only admin and super agent can update client configurations',
                    'status' => 'error'
                ], 403);
            }

            $validated = $request->validate([
                'name' => ['sometimes', 'string', 'max:255'],
                'email' => ['sometimes', 'string', 'email', 'max:255', 'unique:clients,email,' . $client->id], // Added Dynamic unique check exclusion
                'phone' => ['sometimes', 'string', 'max:50'],
                'adress' => ['sometimes', 'string', 'max:255'],
                'subscription_id' => ['sometimes', 'exists:subscriptions,id'],
                'etat' => ['sometimes', 'in:' . implode(',', array_column(ClientEtat::cases(), 'value'))]
            ]);

            $client->update($validated);

            return response()->json([
                'message' => 'Client record updated successfully',
                'data' => $client->load('subscription'),
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
     * Only admin can delete client accounts.
     */
    public function destroy(Request $request, Client $client)
    {
        try {
            $user = $request->user();
            $userRole = $user->role?->name;

            if ($userRole !== 'admin') {
                return response()->json([
                    'message' => 'Unauthorized: Only admin accounts can delete client profiles',
                    'status' => 'error'
                ], 403);
            }

            $client->delete();

            return response()->json([
                'message' => 'Client profile removed successfully',
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
