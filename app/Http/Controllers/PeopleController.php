<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PeopleController extends Controller
{
    /**
     * Directory of people for access pickers and the Team view. Available to
     * writers (admin/member); external collaborators don't need it.
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->isWriter(), 403);

        $users = User::orderBy('name')->get()->map(fn ($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'role' => $u->role,
            'type' => $u->role,
            'email' => $u->email,
            'status' => $u->status,
        ]);

        return response()->json([
            'members' => $users->whereIn('role', ['admin', 'member'])->values(),
            'collaborators' => $users->where('role', 'external')->values(),
        ]);
    }
}
