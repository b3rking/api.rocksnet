<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Currency;
use App\Models\Role;

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

    public function users(): JsonResponse
    {
        return response()->json(User::latest()->with('role')->paginate(10));
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
}
