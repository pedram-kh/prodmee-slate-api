<?php

namespace App\Services;

use App\Models\Buyer;
use App\Models\Project;
use App\Models\User;
use App\Support\Slate;

class Sicala
{
    /**
     * System prompt ported from ASST_SYS, rewritten so the agent self-identifies
     * as Sicala. The current app data (JSON) is appended at call time.
     */
    public const SYSTEM_PROMPT = <<<'PROMPT'
You are Sicala, the AI assistant inside "Prodmee Slate", a film/TV development-slate tool. You introduce yourself as Sicala. You help the user by (a) answering questions about their projects and (b) performing actions in the app.

Respond with ONLY a JSON object (no markdown fences, no text outside JSON):
{"reply":"<short friendly message to show the user>","actions":[ ... ]}

"actions" is an array (may be empty). Supported action objects:
- {"type":"create_project","title":"...","stage":"<stage id or label>","format":"Series|Film|Vertical|Reality|Docuseries|Docufollow|Documentary","genre":"...","logline":"...","tagline":"...","tier":"<budget>","origin":"interno|externo","members":["name"],"collaborators":["name"]}
- {"type":"move_stage","project":"<title or id>","stage":"<stage id or label>"}
- {"type":"add_member","project":"...","person":"<name>"}
- {"type":"remove_member","project":"...","person":"<name>"}
- {"type":"add_collaborator","project":"...","person":"<name>"}
- {"type":"set_field","project":"...","field":"title|logline|tagline|genre|format|tier|origin|language|episodes|territory|concept|whyNow|references|participants|packaging|notes","value":"..."}
- {"type":"create_pitch","project":"...","buyer":"<platform name>","status":"<pitch status id>"}
- {"type":"add_comment","project":"...","text":"..."}
- {"type":"set_checklist","project":"...","item":"<checklist item label or id>","done":true}

Rules:
- Only include actions the user clearly requested. If they just ask a question, return an empty actions array and answer in "reply".
- Stage ids: idea, desarrollo, empaquetado, financiacion, preproduccion, produccion, postproduccion, delivered.
- Match projects, people and buyers by the names in the data below. Never invent a project or person that is not listed; if it cannot be found, say so in "reply" instead of adding an action.
- Keep "reply" concise; mention what you did or answer the question. Reply in the same language the user writes in.
- You have each visible project's full details below — discuss or answer about ANY field (logline, tagline, concept, why-now, references, cast, format, language, episodes, territory, packaging, budget, origin, notes).
- When the user asks you to write/draft/develop/improve/fill one or more fields, generate strong content and apply it with set_field actions (one action per field). Keep each generated value concise (about 1-3 sentences). Use the listed allowed values for format, genre and tier.
- Each project has a production checklist (see 'checklist' with done/total and pending item labels). You can tell the user what is done or pending, and check/uncheck steps with set_checklist (match the item by its label).

Current app data (JSON):
PROMPT;

    public function buildContext(User $user): array
    {
        $projects = Project::visibleTo($user)
            ->with(['members', 'collaborators', 'links', 'checklist'])
            ->get();

        $stages = collect(config('slate.stages'));
        $flat = collect(Slate::checklistFlat());

        return [
            'stages' => $stages->map(fn ($s) => ['id' => $s['id'], 'label' => $s['label']])->values(),
            'pitchStatuses' => collect(config('slate.pitch_statuses'))->map(fn ($s) => ['id' => $s['id'], 'label' => $s['label']])->values(),
            'formats' => config('slate.formats'),
            'genres' => config('slate.genres'),
            'budgets' => config('slate.budgets'),
            'team' => User::whereIn('role', ['admin', 'member'])->get()->map(fn ($m) => ['name' => $m->name, 'role' => $m->role]),
            'collaborators' => User::where('role', 'external')->get()->map(fn ($m) => ['name' => $m->name]),
            'buyers' => Buyer::get()->map(fn ($b) => ['platform' => $b->platform]),
            'projects' => $projects->map(function (Project $p) use ($stages, $flat) {
                $done = $p->checklist->where('done', true)->pluck('item_id');
                $pending = $flat->reject(fn ($it) => $done->contains($it['id']))->pluck('label')->values();

                return [
                    'id' => $p->id,
                    'title' => $p->title,
                    'stage' => $stages->firstWhere('id', $p->stage)['label'] ?? $p->stage,
                    'format' => $p->format,
                    'genre' => $p->genre,
                    'budget' => $p->tier,
                    'origin' => $p->origin,
                    'logline' => $p->logline ?? '',
                    'tagline' => $p->tagline ?? '',
                    'concept' => $p->concept ?? '',
                    'whyNow' => $p->why_now ?? '',
                    'references' => $p->references_text ?? '',
                    'participants' => $p->participants ?? '',
                    'language' => $p->language ?? '',
                    'episodes' => $p->episodes ?? '',
                    'territory' => $p->territory ?? '',
                    'packaging' => $p->packaging ?? '',
                    'notes' => $p->notes ?? '',
                    'members' => $p->members->pluck('name'),
                    'collaborators' => $p->collaborators->pluck('name'),
                    'checklist' => ['done' => $done->count(), 'total' => $flat->count(), 'pending' => $pending],
                ];
            })->values(),
        ];
    }

    /**
     * Server-side port of executeActions(). All resolution is scoped to what the
     * user is allowed to see, and writes require writer (admin/member) access.
     *
     * @return array{summaries: array<int,string>, changed: bool}
     */
    public function executeActions(User $user, array $actions): array
    {
        $out = [];
        $changed = false;
        $writer = $user->isWriter();

        foreach ($actions as $a) {
            try {
                if (! $writer) {
                    $out[] = '⚠ Read-only access — changes need an admin/member.';
                    continue;
                }
                $type = $a['type'] ?? '';

                if ($type === 'create_project') {
                    $stage = $this->resolveStage($a['stage'] ?? null) ?? config('slate.stages')[0];
                    $project = Project::create([
                        'title' => $a['title'] ?? 'Untitled project',
                        'logline' => $a['logline'] ?? '',
                        'tagline' => $a['tagline'] ?? '',
                        'format' => $a['format'] ?? 'Series',
                        'genre' => $a['genre'] ?? '',
                        'stage' => $stage['id'],
                        'origin' => $a['origin'] ?? 'interno',
                        'tier' => $a['tier'] ?? config('slate.budgets')[0],
                    ]);
                    $project->users()->attach($user->id, ['relation' => 'member']);
                    foreach (($a['members'] ?? []) as $nm) {
                        if ($p = $this->resolvePerson($nm)) {
                            $project->users()->syncWithoutDetaching([$p->id => ['relation' => 'member']]);
                        }
                    }
                    foreach (($a['collaborators'] ?? []) as $nm) {
                        if ($p = $this->resolvePerson($nm)) {
                            $project->users()->syncWithoutDetaching([$p->id => ['relation' => 'external']]);
                        }
                    }
                    $changed = true;
                    $out[] = '✓ Created "' . $project->title . '" in ' . $stage['label'];
                } elseif ($type === 'move_stage') {
                    $p = $this->resolveProject($user, $a['project'] ?? null);
                    $st = $this->resolveStage($a['stage'] ?? null);
                    if (! $p) {
                        $out[] = '⚠ Project not found: ' . ($a['project'] ?? '');
                    } elseif (! $st) {
                        $out[] = '⚠ Stage not found: ' . ($a['stage'] ?? '');
                    } else {
                        $p->update(['stage' => $st['id']]);
                        $changed = true;
                        $out[] = '✓ Moved "' . $p->title . '" → ' . $st['label'];
                    }
                } elseif ($type === 'add_member' || $type === 'add_collaborator') {
                    $p = $this->resolveProject($user, $a['project'] ?? null);
                    $per = $this->resolvePerson($a['person'] ?? null);
                    if (! $p) {
                        $out[] = '⚠ Project not found: ' . ($a['project'] ?? '');
                    } elseif (! $per) {
                        $out[] = '⚠ Person not found: ' . ($a['person'] ?? '');
                    } else {
                        $rel = $type === 'add_member' ? 'member' : 'external';
                        $p->users()->syncWithoutDetaching([$per->id => ['relation' => $rel]]);
                        $changed = true;
                        $out[] = '✓ Added ' . $per->name . ' to "' . $p->title . '"';
                    }
                } elseif ($type === 'remove_member') {
                    $p = $this->resolveProject($user, $a['project'] ?? null);
                    $per = $this->resolvePerson($a['person'] ?? null);
                    if ($p && $per) {
                        $p->users()->detach($per->id);
                        $changed = true;
                        $out[] = '✓ Removed ' . $per->name . ' from "' . $p->title . '"';
                    } else {
                        $out[] = '⚠ Could not remove member.';
                    }
                } elseif ($type === 'set_field') {
                    $p = $this->resolveProject($user, $a['project'] ?? null);
                    $col = $this->fieldColumn($a['field'] ?? '');
                    if ($p && $col) {
                        $p->update([$col => $a['value'] ?? '']);
                        $changed = true;
                        $out[] = '✓ Updated ' . $a['field'] . ' on "' . $p->title . '"';
                    } else {
                        $out[] = '⚠ Could not update that field.';
                    }
                } elseif ($type === 'create_pitch') {
                    $p = $this->resolveProject($user, $a['project'] ?? null);
                    $b = $this->resolveBuyer($a['buyer'] ?? null, true);
                    if ($p && $b) {
                        $statuses = collect(config('slate.pitch_statuses'));
                        $st = $statuses->firstWhere('id', $a['status'] ?? null) ?? $statuses->first();
                        $p->pitches()->create(['buyer_id' => $b->id, 'status' => $st['id']]);
                        $changed = true;
                        $out[] = '✓ Pitch: "' . $p->title . '" → ' . $b->platform;
                    } else {
                        $out[] = '⚠ Could not create pitch.';
                    }
                } elseif ($type === 'add_comment') {
                    $p = $this->resolveProject($user, $a['project'] ?? null);
                    if ($p && ! empty($a['text'])) {
                        $p->comments()->create(['user_id' => $user->id, 'author_name' => $user->name, 'body' => $a['text']]);
                        $changed = true;
                        $out[] = '✓ Comment added to "' . $p->title . '"';
                    } else {
                        $out[] = '⚠ Could not add comment.';
                    }
                } elseif ($type === 'set_checklist') {
                    $p = $this->resolveProject($user, $a['project'] ?? null);
                    if ($p) {
                        $ref = strtolower(trim((string) ($a['item'] ?? '')));
                        $flat = collect(Slate::checklistFlat());
                        $it = $flat->firstWhere('id', $ref)
                            ?? $flat->first(fn ($x) => strtolower($x['label']) === $ref)
                            ?? $flat->first(fn ($x) => str_contains(strtolower($x['label']), $ref) && $ref !== '');
                        if ($it) {
                            $done = array_key_exists('done', $a) ? (bool) $a['done'] : true;
                            $row = $p->checklist()->firstOrNew(['item_id' => $it['id']]);
                            $row->done = $done;
                            $row->save();
                            $changed = true;
                            $out[] = '✓ ' . ($done ? 'Checked' : 'Unchecked') . ' "' . $it['label'] . '" on "' . $p->title . '"';
                        } else {
                            $out[] = '⚠ Checklist item not found: ' . ($a['item'] ?? '');
                        }
                    } else {
                        $out[] = '⚠ Project not found: ' . ($a['project'] ?? '');
                    }
                } else {
                    $out[] = '⚠ Unsupported action: ' . $type;
                }
            } catch (\Throwable $e) {
                $out[] = '⚠ Action failed: ' . ($a['type'] ?? 'unknown');
            }
        }

        return ['summaries' => $out, 'changed' => $changed];
    }

    private function resolveProject(User $user, $ref): ?Project
    {
        if (! $ref) {
            return null;
        }
        $r = strtolower(trim((string) $ref));
        $vis = Project::visibleTo($user)->get();

        return $vis->first(fn ($p) => (string) $p->id === $r)
            ?? $vis->first(fn ($p) => strtolower($p->title) === $r)
            ?? $vis->first(fn ($p) => str_contains(strtolower($p->title), $r));
    }

    private function resolvePerson($name): ?User
    {
        if (! $name) {
            return null;
        }
        $n = strtolower(trim((string) $name));
        $all = User::get();

        return $all->first(fn ($m) => strtolower($m->name) === $n)
            ?? $all->first(fn ($m) => str_contains(strtolower($m->name), $n));
    }

    private function resolveStage($ref): ?array
    {
        if (! $ref) {
            return null;
        }
        $r = strtolower(trim((string) $ref));
        $stages = collect(config('slate.stages'));

        return $stages->firstWhere('id', $r)
            ?? $stages->first(fn ($s) => strtolower($s['label']) === $r)
            ?? $stages->first(fn ($s) => str_contains(strtolower($s['label']), $r))
            ?? $stages->first(fn ($s) => str_contains($r, strtolower($s['label'])));
    }

    private function resolveBuyer($name, bool $create = false): ?Buyer
    {
        if (! $name) {
            return null;
        }
        $n = strtolower(trim((string) $name));
        $b = Buyer::get()->first(fn ($x) => strtolower($x->platform) === $n)
            ?? Buyer::get()->first(fn ($x) => str_contains(strtolower($x->platform), $n));
        if (! $b && $create) {
            $b = Buyer::create(['platform' => $name]);
        }

        return $b;
    }

    private function fieldColumn(string $field): ?string
    {
        $map = [
            'title' => 'title', 'logline' => 'logline', 'tagline' => 'tagline', 'genre' => 'genre',
            'format' => 'format', 'tier' => 'tier', 'origin' => 'origin', 'language' => 'language',
            'episodes' => 'episodes', 'territory' => 'territory', 'concept' => 'concept',
            'whyNow' => 'why_now', 'references' => 'references_text', 'participants' => 'participants',
            'packaging' => 'packaging', 'notes' => 'notes',
        ];

        return $map[$field] ?? null;
    }
}
