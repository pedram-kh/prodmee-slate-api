<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\UsageEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UsageController extends Controller
{
    /**
     * Token-usage analytics. Aggregates usage_events by day/week/month using
     * Postgres date_trunc (with a sqlite strftime fallback for local dev).
     */
    public function index(Request $request): JsonResponse
    {
        $range = $request->query('range', 'daily');
        $unit = ['daily' => 'day', 'weekly' => 'week', 'monthly' => 'month'][$range] ?? 'day';
        $since = match ($unit) {
            'week' => now()->subWeeks(12),
            'month' => now()->subMonths(12),
            default => now()->subDays(30),
        };

        $driver = DB::connection()->getDriverName();
        $bucket = $this->bucketExpression($driver, $unit);

        $rows = UsageEvent::query()
            ->where('created_at', '>=', $since)
            ->selectRaw("$bucket as bucket")
            ->selectRaw('sum(input_tokens) as input_tokens')
            ->selectRaw('sum(output_tokens) as output_tokens')
            ->selectRaw('sum(cost_estimate) as cost')
            ->selectRaw('count(*) as calls')
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get();

        $byFeature = UsageEvent::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('feature')
            ->selectRaw('sum(input_tokens + output_tokens) as tokens')
            ->selectRaw('sum(cost_estimate) as cost')
            ->selectRaw('count(*) as calls')
            ->groupBy('feature')
            ->get();

        return response()->json([
            'range' => $range,
            'series' => $rows->map(fn ($r) => [
                'bucket' => $this->formatBucket($r->bucket),
                'inputTokens' => (int) $r->input_tokens,
                'outputTokens' => (int) $r->output_tokens,
                'totalTokens' => (int) $r->input_tokens + (int) $r->output_tokens,
                'cost' => round((float) $r->cost, 4),
                'calls' => (int) $r->calls,
            ]),
            'byFeature' => $byFeature->map(fn ($r) => [
                'feature' => $r->feature,
                'tokens' => (int) $r->tokens,
                'cost' => round((float) $r->cost, 4),
                'calls' => (int) $r->calls,
            ]),
            'totals' => [
                'inputTokens' => (int) UsageEvent::where('created_at', '>=', $since)->sum('input_tokens'),
                'outputTokens' => (int) UsageEvent::where('created_at', '>=', $since)->sum('output_tokens'),
                'cost' => round((float) UsageEvent::where('created_at', '>=', $since)->sum('cost_estimate'), 4),
                'calls' => UsageEvent::where('created_at', '>=', $since)->count(),
            ],
        ]);
    }

    private function bucketExpression(string $driver, string $unit): string
    {
        if ($driver === 'pgsql') {
            return "to_char(date_trunc('$unit', created_at), 'YYYY-MM-DD')";
        }
        // sqlite / mysql fallback using strftime-like formatting.
        if ($driver === 'sqlite') {
            return match ($unit) {
                'week' => "strftime('%Y-%W', created_at)",
                'month' => "strftime('%Y-%m', created_at)",
                default => "strftime('%Y-%m-%d', created_at)",
            };
        }

        // mysql
        return match ($unit) {
            'week' => "date_format(created_at, '%x-%v')",
            'month' => "date_format(created_at, '%Y-%m')",
            default => "date_format(created_at, '%Y-%m-%d')",
        };
    }

    private function formatBucket($bucket): string
    {
        return (string) $bucket;
    }
}
