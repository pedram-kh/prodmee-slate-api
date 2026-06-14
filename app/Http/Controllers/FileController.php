<?php

namespace App\Http\Controllers;

use App\Http\Resources\FileResource;
use App\Models\Project;
use App\Models\ProjectFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FileController extends Controller
{
    private const SLOTS = ['file', 'cover', 'script', 'bible', 'budget'];

    /**
     * Step 1: hand the browser a short-lived presigned PUT URL so it can upload
     * the file directly to S3 (the bytes never touch the API server).
     */
    public function presign(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);
        $data = $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            'content_type' => ['required', 'string', 'max:255'],
            'slot' => ['required', Rule::in(self::SLOTS)],
        ]);

        $safe = Str::slug(pathinfo($data['filename'], PATHINFO_FILENAME)) ?: 'file';
        $ext = pathinfo($data['filename'], PATHINFO_EXTENSION);
        $key = sprintf('projects/%d/%s/%s-%s%s', $project->id, $data['slot'], $safe, Str::random(8), $ext ? '.' . $ext : '');

        $disk = Storage::disk('s3');
        ['url' => $url, 'headers' => $headers] = $disk->temporaryUploadUrl(
            $key,
            now()->addMinutes(10),
            ['ContentType' => $data['content_type']]
        );

        return response()->json(['url' => $url, 'key' => $key, 'headers' => $headers]);
    }

    /**
     * Step 2: persist metadata after the browser finishes the S3 upload.
     */
    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);
        $data = $request->validate([
            'slot' => ['required', Rule::in(self::SLOTS)],
            'key' => ['required', 'string', 'max:1024'],
            'name' => ['required', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:255'],
            'mime_type' => ['nullable', 'string', 'max:255'],
            'size' => ['nullable', 'integer', 'min:0'],
        ]);

        // A project has at most one cover; replace the previous one.
        if ($data['slot'] === 'cover') {
            $project->files()->where('slot', 'cover')->get()->each(function (ProjectFile $f) {
                Storage::disk('s3')->delete($f->s3_key);
                $f->delete();
            });
            if ($project->cover_key) {
                Storage::disk('s3')->delete($project->cover_key);
            }
            $project->update(['cover_key' => $data['key']]);
        }

        $file = $project->files()->create([
            'slot' => $data['slot'],
            's3_key' => $data['key'],
            'name' => $data['name'],
            'label' => $data['label'] ?? null,
            'mime_type' => $data['mime_type'] ?? null,
            'size' => $data['size'] ?? null,
        ]);

        return response()->json(['data' => new FileResource($file)], 201);
    }

    public function destroy(Request $request, Project $project, ProjectFile $file): JsonResponse
    {
        $this->authorize('update', $project);
        abort_unless($file->project_id === $project->id, 404);

        Storage::disk('s3')->delete($file->s3_key);
        if ($file->slot === 'cover' && $project->cover_key === $file->s3_key) {
            $project->update(['cover_key' => null]);
        }
        $file->delete();

        return response()->json(['message' => 'File removed.']);
    }
}
