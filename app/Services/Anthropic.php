<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class Anthropic
{
    public const KEY_SETTING = 'anthropic_api_key';

    /**
     * Resolve the company key: encrypted app_settings first, env fallback.
     */
    public function key(): ?string
    {
        return AppSetting::getEncrypted(self::KEY_SETTING) ?: (config('services.anthropic.key') ?: env('ANTHROPIC_API_KEY')) ?: null;
    }

    public function hasKey(): bool
    {
        return ! empty($this->key());
    }

    /**
     * Call the Messages API. Returns ['text' => string, 'usage' => [...], 'raw' => array].
     *
     * @param  array  $messages  Anthropic-format messages.
     */
    public function messages(array $messages, ?string $system = null, int $maxTokens = 1000): array
    {
        $key = $this->key();
        if (! $key) {
            throw new RuntimeException('No Anthropic API key configured. Set it in admin Settings.');
        }

        $payload = [
            'model' => config('slate.ai.model'),
            'max_tokens' => $maxTokens,
            'messages' => $messages,
        ];
        if ($system) {
            $payload['system'] = $system;
        }

        $resp = Http::withHeaders([
            'x-api-key' => $key,
            'anthropic-version' => config('slate.ai.version'),
            'content-type' => 'application/json',
        ])->timeout(60)->post(rtrim(config('slate.ai.base_url'), '/') . '/v1/messages', $payload);

        if (! $resp->successful()) {
            $msg = $resp->json('error.message') ?: ('Anthropic HTTP ' . $resp->status());
            throw new RuntimeException($msg);
        }

        $data = $resp->json();
        $text = collect($data['content'] ?? [])
            ->where('type', 'text')
            ->pluck('text')
            ->implode("\n");

        return [
            'text' => trim(preg_replace('/```json|```/', '', $text)),
            'usage' => $data['usage'] ?? ['input_tokens' => 0, 'output_tokens' => 0],
            'raw' => $data,
        ];
    }

    /**
     * Estimate USD cost from token usage using configured per-MTok pricing.
     */
    public function cost(int $inputTokens, int $outputTokens): float
    {
        $in = config('slate.ai.pricing.input_per_mtok');
        $out = config('slate.ai.pricing.output_per_mtok');

        return round(($inputTokens / 1_000_000) * $in + ($outputTokens / 1_000_000) * $out, 6);
    }
}
