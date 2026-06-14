<?php

namespace App\Http\Controllers;

use App\Mail\LoginCodeMail;
use App\Models\LoginCode;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    /**
     * Step 1: request an OTP. Invite-gated: only emails that belong to an
     * active/invited user get a code. The response is always generic so we
     * never leak which emails exist.
     */
    public function requestCode(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $email = strtolower(trim($data['email']));

        $user = User::whereRaw('lower(email) = ?', [$email])
            ->whereIn('status', ['active', 'invited'])
            ->first();

        if ($user) {
            $ttl = (int) config('slate.otp.ttl_minutes');
            $length = (int) config('slate.otp.length');
            $code = str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);

            // Invalidate any previous unconsumed codes for this email.
            LoginCode::where('email', $email)->whereNull('consumed_at')->delete();

            LoginCode::create([
                'email' => $email,
                'code_hash' => Hash::make($code),
                'expires_at' => Carbon::now()->addMinutes($ttl),
            ]);

            Mail::to($email)->send(new LoginCodeMail($code, $ttl));
        }

        return response()->json([
            'message' => 'If that email is registered, a sign-in code has been sent.',
        ]);
    }

    /**
     * Step 2: verify the OTP and issue a Sanctum token.
     */
    public function verifyCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string'],
        ]);
        $email = strtolower(trim($data['email']));

        $record = LoginCode::where('email', $email)
            ->whereNull('consumed_at')
            ->latest()
            ->first();

        if (! $record || ! $record->isUsable()) {
            return response()->json(['message' => 'That code is invalid or has expired. Request a new one.'], 422);
        }

        $record->increment('attempts');

        if (! Hash::check($data['code'], $record->code_hash)) {
            return response()->json(['message' => 'Incorrect code.'], 422);
        }

        $record->update(['consumed_at' => Carbon::now()]);

        $user = User::whereRaw('lower(email) = ?', [$email])->firstOrFail();
        if ($user->status === 'disabled') {
            return response()->json(['message' => 'This account is disabled.'], 403);
        }

        $user->forceFill([
            'status' => 'active',
            'email_verified_at' => $user->email_verified_at ?? Carbon::now(),
            'last_login_at' => Carbon::now(),
        ])->save();

        $token = $user->createToken('spa')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Signed out.']);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'type' => $user->role,
        ];
    }
}
