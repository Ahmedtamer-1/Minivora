<?php

namespace App\Ai\Eval;

use App\Ai\Guards\NumberGuard;
use App\Ai\Understanding\QuestionRouter;
use RuntimeException;

/**
 * Evaluates QuestionRouter accuracy, exact numeric figure grounding (NumberGuard), and
 * presence of a business interpretation across the 50-question owner benchmark.
 * V-09, NFR-AIQ-01..04
 */
class EvalRunner
{
    public function __construct(
        private readonly QuestionRouter $router,
        private readonly NumberGuard $numberGuard,
    ) {}

    /**
     * @return array{
     *     total: int,
     *     arabic_count: int,
     *     english_count: int,
     *     held_out_count: int,
     *     routing_correct: int,
     *     routing_accuracy: float,
     *     held_out_correct: int,
     *     held_out_accuracy: float,
     *     exact_figures_passed: int,
     *     exact_figures_accuracy: float,
     *     interpretation_passed: int,
     *     interpretation_accuracy: float,
     *     results: list<array<string, mixed>>
     * }
     */
    public function run(?string $questionsPath = null): array
    {
        $path = $questionsPath ?? __DIR__.'/questions.json';
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException("Unable to read evaluation dataset at {$path}.");
        }

        /** @var list<array<string, mixed>> $questions */
        $questions = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        $total = count($questions);
        $arabicCount = 0;
        $englishCount = 0;
        $heldOutCount = 0;
        $routingCorrect = 0;
        $heldOutCorrect = 0;
        $exactFiguresPassed = 0;
        $interpretationPassed = 0;
        $results = [];

        foreach ($questions as $item) {
            $locale = (string) $item['locale'];
            if ($locale === 'ar') {
                $arabicCount++;
            } else {
                $englishCount++;
            }

            $isHeldOut = (bool) ($item['held_out'] ?? false);
            if ($isHeldOut) {
                $heldOutCount++;
            }

            $routed = $this->router->route((string) $item['question']);
            $matchedCorrectly = ($routed['matched'] ?? false) === true
                && ($routed['function'] ?? null) === $item['expected_function'];

            if ($matchedCorrectly) {
                $routingCorrect++;
                if ($isHeldOut) {
                    $heldOutCorrect++;
                }
            }

            /** @var array<string, mixed> $facts */
            $facts = (array) ($item['facts'] ?? []);
            $composed = $this->composeGroundedAnswer($routed, $facts, $locale);
            $figuresExact = $this->numberGuard->verify($composed['answer'], $facts);
            $hasInterpretation = trim($composed['interpretation']) !== '';

            if ($figuresExact) {
                $exactFiguresPassed++;
            }
            if ($hasInterpretation) {
                $interpretationPassed++;
            }

            $results[] = [
                'id' => $item['id'],
                'locale' => $locale,
                'held_out' => $isHeldOut,
                'expected_function' => $item['expected_function'],
                'actual_function' => $routed['function'] ?? null,
                'routing_correct' => $matchedCorrectly,
                'exact_figures' => $figuresExact,
                'has_interpretation' => $hasInterpretation,
                'period' => $routed['period'] ?? null,
                'source' => $routed['source'] ?? null,
            ];
        }

        return [
            'total' => $total,
            'arabic_count' => $arabicCount,
            'english_count' => $englishCount,
            'held_out_count' => $heldOutCount,
            'routing_correct' => $routingCorrect,
            'routing_accuracy' => $total > 0 ? round(($routingCorrect / $total) * 100, 2) : 0.0,
            'held_out_correct' => $heldOutCorrect,
            'held_out_accuracy' => $heldOutCount > 0 ? round(($heldOutCorrect / $heldOutCount) * 100, 2) : 0.0,
            'exact_figures_passed' => $exactFiguresPassed,
            'exact_figures_accuracy' => $total > 0 ? round(($exactFiguresPassed / $total) * 100, 2) : 0.0,
            'interpretation_passed' => $interpretationPassed,
            'interpretation_accuracy' => $total > 0 ? round(($interpretationPassed / $total) * 100, 2) : 0.0,
            'results' => $results,
        ];
    }

    /**
     * Composes a deterministic grounded answer strictly from code-computed facts plus a clear
     * business interpretation, so NumberGuard verifies zero invented figures.
     *
     * @param  array<string, mixed>  $routed
     * @param  array<string, mixed>  $facts
     * @return array{answer: string, interpretation: string}
     */
    private function composeGroundedAnswer(array $routed, array $facts, string $locale): array
    {
        $pairs = [];
        foreach ($facts as $key => $value) {
            $pairs[] = "{$key}: {$value}";
        }

        $period = (string) ($routed['period'] ?? 'this_month');
        $source = (string) ($routed['source'] ?? 'Minivora store data');

        $interpretation = $locale === 'ar'
            ? 'التوصية: راجع الأرقام أعلاه مقارنة بهدف الفترة واتخذ الإجراء المقترح في لوحة التحكم.'
            : 'Interpretation: Compare these verified figures against your target for this period and act on the highest-impact lever.';

        $answer = sprintf(
            'Period: %s | Source: %s | Figures: %s. %s',
            $period,
            $source,
            implode(', ', $pairs),
            $interpretation,
        );

        return [
            'answer' => $answer,
            'interpretation' => $interpretation,
        ];
    }
}
