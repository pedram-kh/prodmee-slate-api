<?php

namespace App\Http\Controllers;

use App\Models\Buyer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BuyerController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Buyer::orderBy('platform')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureWriter($request);
        $data = $this->validatePayload($request);
        $buyer = Buyer::create($data);

        return response()->json(['data' => $buyer], 201);
    }

    public function update(Request $request, Buyer $buyer): JsonResponse
    {
        $this->ensureWriter($request);
        $buyer->update($this->validatePayload($request, false));

        return response()->json(['data' => $buyer]);
    }

    public function destroy(Request $request, Buyer $buyer): JsonResponse
    {
        $this->ensureWriter($request);
        $buyer->delete(); // cascades to pitches

        return response()->json(['message' => 'Buyer deleted.']);
    }

    private function ensureWriter(Request $request): void
    {
        abort_unless($request->user()->isWriter(), 403, 'Read-only access.');
    }

    private function validatePayload(Request $request, bool $require = true): array
    {
        return $request->validate([
            'platform' => [$require ? 'required' : 'sometimes', 'string', 'max:255'],
            'contact' => ['sometimes', 'nullable', 'string', 'max:255'],
            'role' => ['sometimes', 'nullable', 'string', 'max:255'],
            'territory' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);
    }
}
