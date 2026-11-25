<?php

namespace App\Modules\Fpa\Services;

use App\Ai\Guards\NumberGuard;

/**
 * Generates number-guarded FP&A commentary on monthly budget variances and what-if scenarios.
 * Validates any LLM draft against code-computed figures (via NumberGuard when available and
 * deterministic numeric grounding), falling back to a code-generated template on any ungrounded
 * number. FR-FPA-05, FR-FPA-06
 */
class FpaCommentaryService
{
    public const SOURCE = 'ledger_and_fpa_code';

    /**
     * Produce grounded commentary for a monthly budget-vs-actual variance report.
     *
     * @param  array{
     *   month?: string,
     *   rows?: list<array{account_code?: string, account_name?: string, budget?: int, actual?: int, variance?: int, variance_pct?: float|null, favorable?: bool}>,
     *   total_budget?: int,
     *   total_actual?: int,
     *   total_variance?: int
     * }  $variance
     * @return array{text: string, used_fallback: bool, source: string}
     */
    public function commentOnVariance(array $variance, ?string $llmDraft = null): array
    {
        $fallback = $this->buildVarianceFallback($variance);

        if ($llmDraft === null || trim($llmDraft) === '') {
            return [
                'text' => $fallback,
                'used_fallback' => true,
                'source' => self::SOURCE,
            ];
        }

        $allowed = $this->extractVarianceNumbers($variance);
        $ignoreLiterals = isset($variance['month']) && is_string($variance['month'])
            ? [$variance['month']]
            : [];

        if (! $this->passesNumberGuard($llmDraft, $allowed, $ignoreLiterals)) {
            return [
                'text' => $fallback,
                'used_fallback' => true,
                'source' => self::SOURCE,
            ];
        }

        return [
            'text' => trim($llmDraft),
            'used_fallback' => false,
            'source' => self::SOURCE,
        ];
    }

    /**
     * Produce grounded commentary for a what-if scenario simulation.
     *
     * @param  array{
     *   assumptions?: array<string, float|int>,
     *   baseline?: array<string, int>,
     *   projected?: array<string, int>,
     *   profit_delta?: int
     * }  $simulation
     * @return array{text: string, used_fallback: bool, source: string}
     */
    public function commentOnScenario(array $simulation, ?string $llmDraft = null): array
    {
        $fallback = $this->buildScenarioFallback($simulation);

        if ($llmDraft === null || trim($llmDraft) === '') {
            return [
                'text' => $fallback,
                'used_fallback' => true,
                'source' => self::SOURCE,
            ];
        }

        $allowed = $this->extractScenarioNumbers($simulation);

        if (! $this->passesNumberGuard($llmDraft, $allowed)) {
            return [
                'text' => $fallback,
                'used_fallback' => true,
                'source' => self::SOURCE,
            ];
        }

        return [
            'text' => trim($llmDraft),
            'used_fallback' => false,
            'source' => self::SOURCE,
        ];
    }

    /**
     * Validate text through App\Ai\Guards\NumberGuard (when implemented) and deterministic numeric grounding.
     *
     * @param  list<float>  $allowedNumbers
     * @param  list<string>  $ignoreLiterals
     */
    private function passesNumberGuard(string $draft, array $allowedNumbers, array $ignoreLiterals = []): bool
    {
        $scrubbed = $draft;
        foreach ($ignoreLiterals as $literal) {
            if ($literal !== '') {
                $scrubbed = str_replace($literal, '', $scrubbed);
            }
        }

        if (class_exists(NumberGuard::class)) {
            $guard = app(NumberGuard::class);
            foreach (['passes', 'verify', 'check', 'validate'] as $method) {
                if (method_exists($guard, $method)) {
                    $result = $guard->{$method}($scrubbed, $allowedNumbers);
                    if (is_bool($result) && ! $result) {
                        return false;
                    }
                    if (is_array($result) && (
                        (array_key_exists('passed', $result) && ! $result['passed'])
                        || (array_key_exists('valid', $result) && ! $result['valid'])
                        || (array_key_exists('ok', $result) && ! $result['ok'])
                    )) {
                        return false;
                    }
                    break;
                }
            }
        }

        preg_match_all('/-?\d+(?:,\d{3})*(?:\.\d+)?/', $scrubbed, $matches);
        foreach ($matches[0] as $token) {
            $value = (float) str_replace(',', '', $token);
            if (! $this->isAllowedNumber($value, $allowedNumbers)) {
                return false;
            }
        }

        return true;
    }

    /** @param  list<float>  $allowedNumbers */
    private function isAllowedNumber(float $value, array $allowedNumbers): bool
    {
        foreach ($allowedNumbers as $allowed) {
            if (abs($value - $allowed) <= 1e-6) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $variance
     * @return list<float>
     */
    private function extractVarianceNumbers(array $variance): array
    {
        $numbers = [];
        $pushMoney = function (mixed $piasters) use (&$numbers): void {
            if (! is_int($piasters) && ! is_float($piasters)) {
                return;
            }
            $val = (float) $piasters;
            $egp = round($val / 100, 2);
            $numbers[] = $val;
            $numbers[] = abs($val);
            $numbers[] = $egp;
            $numbers[] = abs($egp);
        };

        $pushMoney($variance['total_budget'] ?? null);
        $pushMoney($variance['total_actual'] ?? null);
        $pushMoney($variance['total_variance'] ?? null);

        $rows = is_array($variance['rows'] ?? null) ? $variance['rows'] : [];
        $numbers[] = (float) count($rows);

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (isset($row['account_code']) && is_numeric($row['account_code'])) {
                $numbers[] = (float) $row['account_code'];
            }
            $pushMoney($row['budget'] ?? null);
            $pushMoney($row['actual'] ?? null);
            $pushMoney($row['variance'] ?? null);

            if (isset($row['variance_pct']) && (is_int($row['variance_pct']) || is_float($row['variance_pct']))) {
                $pct = (float) $row['variance_pct'];
                $numbers[] = $pct;
                $numbers[] = abs($pct);
            }
        }

        return array_values(array_unique($numbers));
    }

    /**
     * @param  array<string, mixed>  $simulation
     * @return list<float>
     */
    private function extractScenarioNumbers(array $simulation): array
    {
        $numbers = [];
        $pushMoney = function (mixed $piasters) use (&$numbers): void {
            if (! is_int($piasters) && ! is_float($piasters)) {
                return;
            }
            $val = (float) $piasters;
            $egp = round($val / 100, 2);
            $numbers[] = $val;
            $numbers[] = abs($val);
            $numbers[] = $egp;
            $numbers[] = abs($egp);
        };

        $pushMoney($simulation['profit_delta'] ?? null);

        foreach (['baseline', 'projected'] as $bucket) {
            $data = is_array($simulation[$bucket] ?? null) ? $simulation[$bucket] : [];
            if (isset($data['orders']) && is_numeric($data['orders'])) {
                $numbers[] = (float) $data['orders'];
            }
            foreach (['avg_order_value', 'cogs_per_order', 'shipping_per_order', 'revenue', 'cogs', 'shipping', 'ad_spend', 'fixed_expenses', 'net_profit'] as $field) {
                $pushMoney($data[$field] ?? null);
            }
        }

        $assumptions = is_array($simulation['assumptions'] ?? null) ? $simulation['assumptions'] : [];
        foreach ($assumptions as $pct) {
            if (is_int($pct) || is_float($pct)) {
                $numbers[] = (float) $pct;
                $numbers[] = abs((float) $pct);
            }
        }

        return array_values(array_unique($numbers));
    }

    /** @param  array<string, mixed>  $variance */
    private function buildVarianceFallback(array $variance): string
    {
        $month = (string) ($variance['month'] ?? '');
        $totalBudget = (int) ($variance['total_budget'] ?? 0);
        $totalActual = (int) ($variance['total_actual'] ?? 0);
        $totalVariance = (int) ($variance['total_variance'] ?? ($totalActual - $totalBudget));

        $lines = [
            sprintf(
                'Monthly variance summary for %s: total budget %s EGP, total actual %s EGP, total variance %s EGP.',
                $month,
                $this->formatEgp($totalBudget),
                $this->formatEgp($totalActual),
                $this->formatEgp($totalVariance),
            ),
        ];

        $rows = is_array($variance['rows'] ?? null) ? $variance['rows'] : [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $status = ! empty($row['favorable']) ? 'favorable' : 'unfavorable';
            $pctPart = isset($row['variance_pct']) && $row['variance_pct'] !== null
                ? sprintf(' (%s%%)', number_format((float) $row['variance_pct'], 1, '.', ''))
                : '';
            $lines[] = sprintf(
                '- %s %s: budget %s EGP, actual %s EGP, variance %s EGP%s [%s].',
                (string) ($row['account_code'] ?? ''),
                (string) ($row['account_name'] ?? ''),
                $this->formatEgp((int) ($row['budget'] ?? 0)),
                $this->formatEgp((int) ($row['actual'] ?? 0)),
                $this->formatEgp((int) ($row['variance'] ?? 0)),
                $pctPart,
                $status,
            );
        }

        return implode("\n", $lines);
    }

    /** @param  array<string, mixed>  $simulation */
    private function buildScenarioFallback(array $simulation): string
    {
        $baseline = is_array($simulation['baseline'] ?? null) ? $simulation['baseline'] : [];
        $projected = is_array($simulation['projected'] ?? null) ? $simulation['projected'] : [];

        $baseRevenue = (int) ($baseline['revenue'] ?? 0);
        $projRevenue = (int) ($projected['revenue'] ?? 0);
        $baseProfit = (int) ($baseline['net_profit'] ?? 0);
        $projProfit = (int) ($projected['net_profit'] ?? 0);
        $delta = (int) ($simulation['profit_delta'] ?? ($projProfit - $baseProfit));

        $direction = match (true) {
            $delta > 0 => 'increases',
            $delta < 0 => 'decreases',
            default => 'keeps unchanged',
        };

        return sprintf(
            'Scenario impact: revenue moves from %s EGP to %s EGP, and net profit %s from %s EGP to %s EGP (profit delta: %s EGP).',
            $this->formatEgp($baseRevenue),
            $this->formatEgp($projRevenue),
            $direction,
            $this->formatEgp($baseProfit),
            $this->formatEgp($projProfit),
            $this->formatEgp($delta),
        );
    }

    private function formatEgp(int $piasters): string
    {
        return number_format($piasters / 100, 2, '.', ',');
    }
}
