<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    private const RELATIONS = ['members', 'collaborators', 'links', 'files', 'comments.user', 'checklist'];

    // Maps SPA payload keys to DB columns.
    private const FIELD_MAP = [
        'title' => 'title', 'logline' => 'logline', 'tagline' => 'tagline',
        'format' => 'format', 'genre' => 'genre', 'origin' => 'origin', 'tier' => 'tier',
        'language' => 'language', 'episodes' => 'episodes', 'territory' => 'territory',
        'concept' => 'concept', 'whyNow' => 'why_now', 'references' => 'references_text',
        'participants' => 'participants', 'packaging' => 'packaging', 'notes' => 'notes',
    ];

    public function index(Request $request): JsonResponse
    {
        $projects = Project::visibleTo($request->user())
            ->with(self::RELATIONS)
            ->latest()
            ->get();

        return response()->json(['data' => ProjectResource::collection($projects)]);
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);
        $project->load(self::RELATIONS);

        return response()->json(['data' => new ProjectResource($project)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Project::class);
        $data = $this->validatePayload($request);

        $project = new Project();
        $project->fill($this->mapFields($data));
        $project->title = $data['title'];
        $project->stage = $data['stage'] ?? 'idea';
        $project->save();

        // Creator becomes a member (mirrors createProject in the prototype).
        $project->users()->attach($request->user()->id, ['relation' => 'member']);

        $project->load(self::RELATIONS);

        return response()->json(['data' => new ProjectResource($project)], 201);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);
        $data = $this->validatePayload($request, false);

        $project->fill($this->mapFields($data));
        if (array_key_exists('stage', $data)) {
            $project->stage = $data['stage'];
        }
        $project->save();
        $project->load(self::RELATIONS);

        return response()->json(['data' => new ProjectResource($project)]);
    }

    public function destroy(Request $request, Project $project): JsonResponse
    {
        $this->authorize('delete', $project);
        $project->delete();

        return response()->json(['message' => 'Project deleted.']);
    }

    public function setStage(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);
        $data = $request->validate([
            'stage' => ['required', Rule::in(collect(config('slate.stages'))->pluck('id'))],
        ]);
        $project->update(['stage' => $data['stage']]);
        $project->load(self::RELATIONS);

        return response()->json(['data' => new ProjectResource($project)]);
    }

    public function attachUser(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'relation' => ['required', Rule::in(['member', 'external'])],
        ]);
        $project->users()->syncWithoutDetaching([
            $data['user_id'] => ['relation' => $data['relation']],
        ]);
        $project->load(self::RELATIONS);

        return response()->json(['data' => new ProjectResource($project)]);
    }

    public function detachUser(Request $request, Project $project, User $user): JsonResponse
    {
        $this->authorize('update', $project);
        $project->users()->detach($user->id);
        $project->load(self::RELATIONS);

        return response()->json(['data' => new ProjectResource($project)]);
    }

    private function validatePayload(Request $request, bool $requireTitle = true): array
    {
        return $request->validate([
            'title' => [$requireTitle ? 'required' : 'sometimes', 'string', 'max:255'],
            'logline' => ['sometimes', 'nullable', 'string'],
            'tagline' => ['sometimes', 'nullable', 'string'],
            'format' => ['sometimes', 'nullable', 'string', 'max:255'],
            'genre' => ['sometimes', 'nullable', 'string', 'max:255'],
            'stage' => ['sometimes', Rule::in(collect(config('slate.stages'))->pluck('id'))],
            'origin' => ['sometimes', Rule::in(['interno', 'externo'])],
            'tier' => ['sometimes', 'nullable', 'string', 'max:255'],
            'language' => ['sometimes', 'nullable', 'string', 'max:255'],
            'episodes' => ['sometimes', 'nullable', 'string', 'max:255'],
            'territory' => ['sometimes', 'nullable', 'string', 'max:255'],
            'concept' => ['sometimes', 'nullable', 'string'],
            'whyNow' => ['sometimes', 'nullable', 'string'],
            'references' => ['sometimes', 'nullable', 'string'],
            'participants' => ['sometimes', 'nullable', 'string'],
            'packaging' => ['sometimes', 'nullable', 'string'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);
    }

    private function mapFields(array $data): array
    {
        $out = [];
        foreach (self::FIELD_MAP as $in => $col) {
            if (array_key_exists($in, $data)) {
                $out[$col] = $data[$in];
            }
        }
        return $out;
    }
}
