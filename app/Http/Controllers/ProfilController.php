<?php

namespace App\Http\Controllers;

use App\Models\Profil;
use Illuminate\Http\Request;

class ProfilController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json([
            'profils' => Profil::all(),
            'count' => Profil::count()
        ]);
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
