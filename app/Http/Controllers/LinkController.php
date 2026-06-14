<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LinkController extends Controller
{
    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:255'],
            'url' => ['required', 'string', 'max:2048'],
        ]);

        $url = trim($data['url']);
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        $link = $project->links()->create(['label' => $data['label'] ?? null, 'url' => $url]);

        return response()->json(['data' => $link], 201);
    }

    public function destroy(Request $request, Project $project, ProjectLink $link): JsonResponse
    {
        $this->authorize('update', $project);
        abort_unless($link->project_id === $project->id, 404);
        $link->delete();

        return response()->json(['message' => 'Link removed.']);
    }
}
