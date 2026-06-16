<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\UsageEvent;
use App\Services\Anthropic;
use App\Services\Sicala;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AiController extends Controller
{
    public function __construct(private Anthropic $anthropic, private Sicala $sicala)
    {
    }

    /**
     * Sicala chat: forwards the conversation with server-built context, parses
     * the JSON reply, executes any actions server-side, and logs token usage.
     */
    public function assistant(Request $request): JsonResponse
    {
        abort_unless($request->user()->isWriter(), 403, 'Sicala is available to the internal team.');

        $data = $request->validate([
            'messages' => ['required', 'array', 'min:1'],
            'messages.*.role' => ['required', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*.key' => ['required', 'string', 'max:1024'],
            'attachments.*.name' => ['required', 'string', 'max:255'],
            'attachments.*.mime_type' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $attachments = $data['attachments'] ?? [];
        $system = Sicala::SYSTEM_PROMPT . "\n" . json_encode($this->sicala->buildContext($user, $attachments));

        $messages = $this->injectAttachmentBlocks($data['messages'], $attachments);

        try {
            $result = $this->anthropic->messages($messages, $system, config('slate.ai.max_tokens'));
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        $this->logUsage($user->id, null, 'assistant', $result['usage']);

        $obj = json_decode($result['text'], true);
        if (! is_array($obj)) {
            $obj = ['reply' => $result['text'] ?: '(no response)', 'actions' => []];
        }

        $exec = $this->sicala->executeActions($user, $obj['actions'] ?? [], $attachments);

        return response()->json([
            'reply' => $obj['reply'] ?? 'Done.',
            'summaries' => $exec['summaries'],
            'changed' => $exec['changed'],
        ]);
    }

    /**
     * Presign a direct S3 PUT for a chat attachment. Not tied to a project yet —
     * the file lands under a temp prefix and is only persisted onto a project if
     * Sicala runs a place_file action.
     */
    public function presignAttachment(Request $request): JsonResponse
    {
        abort_unless($request->user()->isWriter(), 403, 'Sicala is available to the internal team.');

        $data = $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            'content_type' => ['required', 'string', 'max:255'],
        ]);

        $safe = Str::slug(pathinfo($data['filename'], PATHINFO_FILENAME)) ?: 'file';
        $ext = pathinfo($data['filename'], PATHINFO_EXTENSION);
        $key = sprintf('chat-attachments/%d/%s-%s%s', $request->user()->id, $safe, Str::random(10), $ext ? '.' . $ext : '');

        $disk = Storage::disk('s3');
        ['url' => $url, 'headers' => $headers] = $disk->temporaryUploadUrl(
            $key,
            now()->addMinutes(10),
            ['ContentType' => $data['content_type']]
        );

        return response()->json(['url' => $url, 'key' => $key, 'headers' => $headers]);
    }

    /**
     * Build Claude document/image blocks for readable attachments (PDF/image) and
     * prepend them to the latest user message so Sicala can read them.
     *
     * @param  array  $messages  [{role, content:string}]
     * @param  array  $attachments  [{key, name, mime_type}]
     */
    private function injectAttachmentBlocks(array $messages, array $attachments): array
    {
        $blocks = [];
        foreach (array_slice($attachments, 0, 5) as $a) {
            $mime = (string) ($a['mime_type'] ?? '');
            $isImage = str_starts_with($mime, 'image/');
            $isPdf = $mime === 'application/pdf';
            if (! $isImage && ! $isPdf) {
                continue;
            }
            try {
                $bytes = Storage::disk('s3')->get($a['key']);
            } catch (\Throwable $e) {
                continue;
            }
            $b64 = base64_encode($bytes);
            if ($isPdf) {
                $blocks[] = ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => $b64]];
            } else {
                $blocks[] = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mime, 'data' => $b64]];
            }
        }

        if (! $blocks) {
            return $messages;
        }

        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if (($messages[$i]['role'] ?? null) === 'user') {
                $text = (string) ($messages[$i]['content'] ?? '');
                $messages[$i]['content'] = array_merge($blocks, [['type' => 'text', 'text' => $text]]);
                break;
            }
        }

        return $messages;
    }

    /**
     * Auto-fill one-pager fields by reading a project's PDF/image documents.
     * Returns suggested field values for the client to review (does not save).
     */
    public function autofill(Request $request): JsonResponse
    {
        $data = $request->validate(['project_id' => ['required', 'exists:projects,id']]);
        $project = Project::findOrFail($data['project_id']);
        $this->authorize('update', $project);

        $docs = $project->files()
            ->whereIn('slot', ['script', 'bible', 'budget', 'file', 'cover'])
            ->get()
            ->filter(fn ($f) => str_starts_with((string) $f->mime_type, 'image/') || $f->mime_type === 'application/pdf')
            ->take(3);

        if ($docs->isEmpty()) {
            return response()->json(['message' => 'Upload a PDF or image to this project first, then try Auto-fill.'], 422);
        }

        $content = [];
        foreach ($docs as $f) {
            try {
                $bytes = Storage::disk('s3')->get($f->s3_key);
            } catch (\Throwable $e) {
                continue;
            }
            $b64 = base64_encode($bytes);
            if ($f->mime_type === 'application/pdf') {
                $content[] = ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => $b64]];
            } else {
                $content[] = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $f->mime_type, 'data' => $b64]];
            }
        }

        if (! $content) {
            return response()->json(['message' => 'Could not read the project documents.'], 422);
        }

        $content[] = ['type' => 'text', 'text' => $this->autofillPrompt()];

        try {
            $result = $this->anthropic->messages([['role' => 'user', 'content' => $content]], null, config('slate.ai.max_tokens'));
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        $this->logUsage($request->user()->id, $project->id, 'autofill', $result['usage']);

        $fields = json_decode($result['text'], true);
        if (! is_array($fields)) {
            return response()->json(['message' => 'Could not parse the document.'], 422);
        }

        $allowed = ['title', 'tagline', 'logline', 'concept', 'whyNow', 'references', 'participants', 'genre', 'language', 'episodes', 'territory', 'packaging'];
        $clean = [];
        foreach ($allowed as $k) {
            $v = trim((string) ($fields[$k] ?? ''));
            if ($v !== '') {
                $clean[$k] = $v;
            }
        }

        return response()->json(['fields' => $clean]);
    }

    private function autofillPrompt(): string
    {
        return "Extract one-pager fields for this film/TV project from the attached document(s). "
            . "Respond with ONLY a JSON object (no markdown, no commentary) with these string keys: "
            . "title, tagline, logline, concept, whyNow, references, participants, genre, language, episodes, territory, packaging. "
            . "Use the document's original language for values. 'tagline' = the hook line; 'concept' = the synopsis paragraph; "
            . "'references' = comparable titles separated by commas; 'participants' = key talent/cast/creatives; "
            . "'language','episodes','territory' = format details. If a field is not present, set it to an empty string. Keep values concise.";
    }

    private function logUsage(int $userId, ?int $projectId, string $feature, array $usage): void
    {
        $in = (int) ($usage['input_tokens'] ?? 0);
        $out = (int) ($usage['output_tokens'] ?? 0);

        UsageEvent::create([
            'user_id' => $userId,
            'project_id' => $projectId,
            'feature' => $feature,
            'model' => config('slate.ai.model'),
            'input_tokens' => $in,
            'output_tokens' => $out,
            'cost_estimate' => $this->anthropic->cost($in, $out),
            'created_at' => now(),
        ]);
    }
}
