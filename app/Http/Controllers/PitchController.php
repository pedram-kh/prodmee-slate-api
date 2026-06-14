<?php

namespace App\Http\Controllers;

use App\Models\Pitch;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PitchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Only pitches for projects the user can see (mirrors visiblePitches()).
        $visibleIds = Project::visibleTo($request->user())->pluck('id');
        $pitches = Pitch::whereIn('project_id', $visibleIds)
            ->with(['buyer', 'project:id,title,format'])
            ->get();

        return response()->json(['data' => $pitches]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureWriter($request);
        $data = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'buyer_id' => ['required', 'exists:buyers,id'],
            'status' => ['required', $this->statusRule()],
            'last_contact' => ['nullable', 'date'],
            'next' => ['nullable', 'string'],
        ]);
        $this->assertProjectVisible($request, $data['project_id']);
        $pitch = Pitch::create($data)->load(['buyer', 'project:id,title,format']);

        return response()->json(['data' => $pitch], 201);
    }

    public function update(Request $request, Pitch $pitch): JsonResponse
    {
        $this->ensureWriter($request);
        $this->assertProjectVisible($request, $pitch->project_id);
        $data = $request->validate([
            'buyer_id' => ['sometimes', 'exists:buyers,id'],
            'status' => ['sometimes', $this->statusRule()],
            'last_contact' => ['sometimes', 'nullable', 'date'],
            'next' => ['sometimes', 'nullable', 'string'],
        ]);
        $pitch->update($data);

        return response()->json(['data' => $pitch->load(['buyer', 'project:id,title,format'])]);
    }

    public function setStatus(Request $request, Pitch $pitch): JsonResponse
    {
        $this->ensureWriter($request);
        $this->assertProjectVisible($request, $pitch->project_id);
        $data = $request->validate(['status' => ['required', $this->statusRule()]]);
        $pitch->update($data);

        return response()->json(['data' => $pitch->load(['buyer', 'project:id,title,format'])]);
    }

    public function destroy(Request $request, Pitch $pitch): JsonResponse
    {
        $this->ensureWriter($request);
        $this->assertProjectVisible($request, $pitch->project_id);
        $pitch->delete();

        return response()->json(['message' => 'Pitch deleted.']);
    }

    private function statusRule(): \Illuminate\Validation\Rules\In
    {
        return Rule::in(collect(config('slate.pitch_statuses'))->pluck('id'));
    }

    private function ensureWriter(Request $request): void
    {
        abort_unless($request->user()->isWriter(), 403, 'Read-only access.');
    }

    private function assertProjectVisible(Request $request, int $projectId): void
    {
        $project = Project::findOrFail($projectId);
        abort_unless($project->isVisibleTo($request->user()), 403);
    }
}
