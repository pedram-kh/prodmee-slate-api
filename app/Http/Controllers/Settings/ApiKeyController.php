<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Services\Anthropic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiKeyController extends Controller
{
    public function __construct(private Anthropic $anthropic)
    {
    }

    /**
     * Write-only: never returns the key, only whether it's set and its last 4.
     */
    public function show(): JsonResponse
    {
        $key = $this->anthropic->key();

        return response()->json([
            'set' => ! empty($key),
            'last4' => $key ? substr($key, -4) : null,
            'source' => AppSetting::getEncrypted(Anthropic::KEY_SETTING) ? 'settings' : ($key ? 'env' : null),
            'model' => config('slate.ai.model'),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate(['key' => ['required', 'string', 'min:10', 'max:255']]);
        AppSetting::putEncrypted(Anthropic::KEY_SETTING, trim($data['key']));

        return response()->json(['set' => true, 'last4' => substr(trim($data['key']), -4)]);
    }

    /**
     * Verify the stored (or just-submitted) key with a tiny live call.
     */
    public function test(Request $request): JsonResponse
    {
        try {
            $this->anthropic->messages([['role' => 'user', 'content' => 'ping']], null, 8);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'message' => 'Connection successful.']);
    }
}
