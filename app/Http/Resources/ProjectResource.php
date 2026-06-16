<?php

namespace App\Http\Resources;

use App\Support\Slate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $checklist = [];
        foreach ($this->whenLoaded('checklist', fn () => $this->checklist, fn () => collect()) as $row) {
            $checklist[$row->item_id] = (bool) $row->done;
        }
        $done = count(array_filter($checklist));
        $total = Slate::checklistTotal();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'logline' => $this->logline,
            'tagline' => $this->tagline,
            'format' => $this->format,
            'genre' => $this->genre,
            'stage' => $this->stage,
            'origin' => $this->origin,
            'tier' => $this->tier,
            'language' => $this->language,
            'episodes' => $this->episodes,
            'territory' => $this->territory,
            'concept' => $this->concept,
            'whyNow' => $this->why_now,
            'references' => $this->references_text,
            'participants' => $this->participants,
            'packaging' => $this->packaging,
            'notes' => $this->notes,
            'coverUrl' => $this->cover_key ? $this->coverUrl() : null,
            'shareToken' => $this->share_token,
            'members' => $this->whenLoaded('members', fn () => $this->members->map(fn ($u) => self::person($u))),
            'collaborators' => $this->whenLoaded('collaborators', fn () => $this->collaborators->map(fn ($u) => self::person($u))),
            'links' => $this->whenLoaded('links', fn () => $this->links),
            'files' => $this->whenLoaded('files', fn () => FileResource::collection($this->files)),
            'comments' => $this->whenLoaded('comments', fn () => $this->comments->map(fn ($c) => [
                'id' => $c->id,
                'authorId' => $c->user_id,
                'author' => $c->author_name,
                'text' => $c->body,
                'ts' => $c->created_at?->valueOf(),
            ])),
            'checkDone' => (object) $checklist,
            'checklist' => ['done' => $done, 'total' => $total, 'pct' => $total ? (int) round($done / $total * 100) : 0],
        ];
    }

    private function coverUrl(): ?string
    {
        try {
            return Storage::disk('s3')->temporaryUrl($this->cover_key, now()->addMinutes(30));
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function person($u): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'role' => $u->role,
            'type' => $u->role, // admin | member | external (matches prototype 'type')
        ];
    }
}
