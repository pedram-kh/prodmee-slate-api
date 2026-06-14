<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);
        $data = $request->validate(['text' => ['required', 'string']]);
        $user = $request->user();

        $comment = $project->comments()->create([
            'user_id' => $user->id,
            'author_name' => $user->name,
            'body' => $data['text'],
        ]);

        return response()->json(['data' => [
            'id' => $comment->id,
            'authorId' => $comment->user_id,
            'author' => $comment->author_name,
            'text' => $comment->body,
            'ts' => $comment->created_at?->valueOf(),
        ]], 201);
    }

    public function destroy(Request $request, Project $project, Comment $comment): JsonResponse
    {
        abort_unless($comment->project_id === $project->id, 404);
        $user = $request->user();
        // Admins may delete any; otherwise only the author (mirrors prototype).
        abort_unless($user->isAdmin() || $comment->user_id === $user->id, 403);
        $comment->delete();

        return response()->json(['message' => 'Comment deleted.']);
    }
}
