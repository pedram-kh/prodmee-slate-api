<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Support\Slate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChecklistController extends Controller
{
    public function toggle(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);
        $validIds = collect(Slate::checklistFlat())->pluck('id');
        $data = $request->validate([
            'item_id' => ['required', Rule::in($validIds)],
            'done' => ['sometimes', 'boolean'],
        ]);

        $row = $project->checklist()->firstOrNew(['item_id' => $data['item_id']]);
        $row->done = array_key_exists('done', $data) ? $data['done'] : ! $row->done;
        $row->save();

        return response()->json(['data' => ['item_id' => $row->item_id, 'done' => $row->done]]);
    }
}
