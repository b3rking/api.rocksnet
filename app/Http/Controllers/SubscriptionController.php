<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json([
            'message' => 'subscription fetched successfully',
            'subscriptions' => Subscription
                ::latest()
                ->with('currency')
                ->paginate(10),
            'count' => Subscription::count()
        ]);
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
