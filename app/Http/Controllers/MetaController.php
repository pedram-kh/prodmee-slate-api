<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class MetaController extends Controller
{
    /**
     * Domain constants the SPA needs to render boards, filters and the checklist.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'stages' => config('slate.stages'),
            'pitchStatuses' => config('slate.pitch_statuses'),
            'formats' => config('slate.formats'),
            'genres' => config('slate.genres'),
            'budgets' => config('slate.budgets'),
            'checklist' => config('slate.checklist'),
        ]);
    }
}
