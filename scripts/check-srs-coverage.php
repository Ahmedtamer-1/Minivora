#!/usr/bin/env php
<?php

declare(strict_types=1);

$rootDir = dirname(__DIR__);
$requirementsPath = $rootDir.'/docs/srs-requirements.json';
$testsDir = $rootDir.'/tests';

if (! is_file($requirementsPath)) {
    fwrite(STDERR, "Error: Requirements file not found at {$requirementsPath}\n");
    exit(1);
}

$rawJson = file_get_contents($requirementsPath);
if ($rawJson === false) {
    fwrite(STDERR, "Error: Unable to read {$requirementsPath}\n");
    exit(1);
}

$requirements = json_decode($rawJson, true);
if (! is_array($requirements)) {
    fwrite(STDERR, "Error: Invalid JSON in {$requirementsPath}\n");
    exit(1);
}

$showMissing = in_array('--missing', array_slice($argv, 1), true);

/**
 * Extract all FR-[A-Z]+-\d+ and NFR-[A-Z]+-\d+ IDs referenced in
 * Pest test(...)/it(...) descriptions or ->group(...) calls.
 *
 * @return array<string, bool>
 */
function extractCoveredRequirementIds(string $testsDir): array
{
    if (! is_dir($testsDir)) {
        return [];
    }

    $covered = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($testsDir, FilesystemIterator::SKIP_DOTS)
    );

    /** @var SplFileInfo $file */
    foreach ($iterator as $file) {
        if (! $file->isFile() || strtolower($file->getExtension()) !== 'php') {
            continue;
        }

        $content = file_get_contents($file->getPathname());
        if ($content === false) {
            continue;
        }

        foreach (extractIdsFromPhpSource($content) as $id) {
            $covered[$id] = true;
        }
    }

    return $covered;
}

/**
 * Tokenize PHP source and extract SRS IDs from test()/it() descriptions and ->group() calls.
 *
 * @return list<string>
 */
function extractIdsFromPhpSource(string $source): array
{
    $tokens = token_get_all($source);
    $count = count($tokens);
    $found = [];

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];
        if (! is_array($token) || $token[0] !== T_STRING) {
            continue;
        }

        $name = strtolower($token[1]);
        if ($name !== 'test' && $name !== 'it' && $name !== 'group') {
            continue;
        }

        $prev = previousSignificantToken($tokens, $i);
        $nextIndex = nextSignificantTokenIndex($tokens, $i);
        if ($nextIndex === null || $tokens[$nextIndex] !== '(') {
            continue;
        }

        $isGroupCall = $name === 'group'
            && is_array($prev)
            && in_array($prev[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR], true);

        $isTestCall = ($name === 'test' || $name === 'it')
            && ! (is_array($prev) && in_array($prev[0], [
                T_OBJECT_OPERATOR,
                T_NULLSAFE_OBJECT_OPERATOR,
                T_DOUBLE_COLON,
                T_FUNCTION,
                T_NEW,
            ], true));

        if (! $isGroupCall && ! $isTestCall) {
            continue;
        }

        $depth = 0;
        for ($j = $nextIndex; $j < $count; $j++) {
            $t = $tokens[$j];
            if ($t === '(') {
                $depth++;

                continue;
            }
            if ($t === ')') {
                $depth--;
                if ($depth === 0) {
                    break;
                }

                continue;
            }
            if ($isTestCall && $depth === 1 && $t === ',') {
                break;
            }

            if (is_array($t) && in_array($t[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)) {
                if (preg_match_all('/\b(?:FR|NFR)-[A-Z]+-\d+\b/', $t[1], $matches)) {
                    foreach ($matches[0] as $id) {
                        $found[] = $id;
                    }
                }
            }
        }
    }

    return $found;
}

/**
 * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
 * @return array{0: int, 1: string, 2: int}|string|null
 */
function previousSignificantToken(array $tokens, int $index): array|string|null
{
    for ($i = $index - 1; $i >= 0; $i--) {
        $token = $tokens[$i];
        if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        return $token;
    }

    return null;
}

/**
 * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
 */
function nextSignificantTokenIndex(array $tokens, int $index): ?int
{
    $count = count($tokens);
    for ($i = $index + 1; $i < $count; $i++) {
        $token = $tokens[$i];
        if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        return $i;
    }

    return null;
}

function formatPercent(int $covered, int $total): string
{
    if ($total === 0) {
        return '0.0%';
    }

    return sprintf('%.1f%%', ($covered / $total) * 100);
}

$coveredIds = extractCoveredRequirementIds($testsDir);

$frCategories = [];
$mustFrTotal = 0;
$mustFrCovered = 0;
$missingMustFrs = [];

$nfrTotal = 0;
$nfrCovered = 0;
$mustNfrTotal = 0;
$mustNfrCovered = 0;

foreach ($requirements as $req) {
    $id = (string) ($req['id'] ?? '');
    $category = (string) ($req['category'] ?? '');
    $description = (string) ($req['description'] ?? '');
    $priority = (string) ($req['priority'] ?? '');
    $isCovered = isset($coveredIds[$id]);

    if (str_starts_with($id, 'FR-')) {
        if (! isset($frCategories[$category])) {
            $frCategories[$category] = ['covered' => 0, 'total' => 0];
        }

        if ($priority === 'Must') {
            $frCategories[$category]['total']++;
            $mustFrTotal++;
            if ($isCovered) {
                $frCategories[$category]['covered']++;
                $mustFrCovered++;
            } else {
                $missingMustFrs[] = ['id' => $id, 'description' => $description];
            }
        }
    } elseif (str_starts_with($id, 'NFR-')) {
        $nfrTotal++;
        if ($isCovered) {
            $nfrCovered++;
        }
        if ($priority === 'Must') {
            $mustNfrTotal++;
            if ($isCovered) {
                $mustNfrCovered++;
            }
        }
    }
}

echo "SRS Must-FR Coverage by Category\n";
echo str_repeat('=', 46)."\n";
printf("%-12s | %7s | %5s | %10s\n", 'Category', 'Covered', 'Total', 'Coverage');
echo str_repeat('-', 46)."\n";

foreach ($frCategories as $category => $stats) {
    printf(
        "%-12s | %7d | %5d | %10s\n",
        $category,
        $stats['covered'],
        $stats['total'],
        formatPercent($stats['covered'], $stats['total'])
    );
}

echo str_repeat('-', 46)."\n";
printf(
    "%-12s | %7d | %5d | %10s\n",
    'TOTAL MUST',
    $mustFrCovered,
    $mustFrTotal,
    formatPercent($mustFrCovered, $mustFrTotal)
);
echo str_repeat('=', 46)."\n";
printf(
    "Overall Must FR Coverage : %d / %d (%s)\n",
    $mustFrCovered,
    $mustFrTotal,
    formatPercent($mustFrCovered, $mustFrTotal)
);
printf(
    "Overall NFR Coverage     : %d / %d (%s) [Must NFRs: %d / %d (%s)]\n",
    $nfrCovered,
    $nfrTotal,
    formatPercent($nfrCovered, $nfrTotal),
    $mustNfrCovered,
    $mustNfrTotal,
    formatPercent($mustNfrCovered, $mustNfrTotal)
);

if ($showMissing) {
    echo "\nUncovered Must FRs (".count($missingMustFrs)."):\n";
    echo str_repeat('-', 46)."\n";
    foreach ($missingMustFrs as $missing) {
        printf("%s: %s\n", $missing['id'], $missing['description']);
    }
}
