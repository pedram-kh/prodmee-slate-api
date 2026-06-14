<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserAdminController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::orderByRaw("CASE role WHEN 'admin' THEN 0 WHEN 'member' THEN 1 ELSE 2 END")
            ->orderBy('name')
            ->get()
            ->map(fn ($u) => $this->payload($u));

        return response()->json(['data' => $users]);
    }

    /**
     * Invite a user: creates an account in 'invited' status. No code is sent —
     * the user requests an OTP from the login screen themselves.
     */
    public function invite(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'role' => ['required', Rule::in(['admin', 'member', 'external'])],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'role' => $data['role'],
            'status' => 'invited',
        ]);

        Invitation::create([
            'email' => $user->email,
            'role' => $user->role,
            'invited_by' => $request->user()->id,
            'user_id' => $user->id,
        ]);

        return response()->json(['data' => $this->payload($user)], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'role' => ['sometimes', Rule::in(['admin', 'member', 'external'])],
            'status' => ['sometimes', Rule::in(['invited', 'active', 'disabled'])],
        ]);

        // Don't allow an admin to lock themselves out of admin.
        if (($data['role'] ?? $user->role) !== 'admin' && $user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot remove your own admin role.'], 422);
        }

        $user->fill($data)->save();

        return response()->json(['data' => $this->payload($user)]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot deactivate your own account.'], 422);
        }
        // Deactivate (revoke tokens) rather than hard-delete to preserve history.
        $user->update(['status' => 'disabled']);
        $user->tokens()->delete();

        return response()->json(['message' => 'User deactivated.']);
    }

    private function payload(User $u): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->role,
            'status' => $u->status,
            'lastLogin' => $u->last_login_at?->toIso8601String(),
        ];
    }
}
