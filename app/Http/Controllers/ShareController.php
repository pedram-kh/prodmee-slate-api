<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ShareController extends Controller
{
    /**
     * Public, unauthenticated, read-only one-pager. Returns ONLY creative fields
     * — never budget/tier, internal notes, comments, team or internal documents.
     */
    public function show(string $token): JsonResponse
    {
        $project = Project::where('share_token', $token)->firstOrFail();

        $cover = null;
        if ($project->cover_key) {
            try {
                $cover = Storage::disk('s3')->temporaryUrl($project->cover_key, now()->addHour());
            } catch (\Throwable $e) {
                $cover = null;
            }
        }

        return response()->json([
            'data' => [
                'title' => $project->title,
                'logline' => $project->logline,
                'tagline' => $project->tagline,
                'format' => $project->format,
                'genre' => $project->genre,
                'origin' => $project->origin,
                'concept' => $project->concept,
                'whyNow' => $project->why_now,
                'references' => $project->references_text,
                'participants' => $project->participants,
                'packaging' => $project->packaging,
                'language' => $project->language,
                'episodes' => $project->episodes,
                'territory' => $project->territory,
                'coverUrl' => $cover,
            ],
        ]);
    }

    public function enable(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);
        if (! $project->share_token) {
            $project->update(['share_token' => $this->token()]);
        }

        return $this->state($project, $request);
    }

    public function regenerate(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);
        $project->update(['share_token' => $this->token()]);

        return $this->state($project, $request);
    }

    public function revoke(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);
        $project->update(['share_token' => null]);

        return $this->state($project, $request);
    }

    private function token(): string
    {
        return 'shr_' . Str::random(24);
    }

    private function state(Project $project, Request $request): JsonResponse
    {
        $base = rtrim(config('app.frontend_url', env('FRONTEND_URL', '')), '/');

        return response()->json([
            'shareToken' => $project->share_token,
            'url' => $project->share_token ? $base . '/share/' . $project->share_token : null,
        ]);
    }
}
