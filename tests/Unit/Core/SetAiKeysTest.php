<?php

use App\Core\Console\SetAiKeys;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Run ai:set-keys against a temporary .env with JSON on stdin.
 *
 * @return array{0: int, 1: string, 2: string}
 */
function runSetAiKeys(string $env, array|string $values): array
{
    $path = (string) tempnam(sys_get_temp_dir(), 'env');
    file_put_contents($path, $env);

    $command = app(SetAiKeys::class);
    $command->setLaravel(app());
    $tester = new CommandTester($command);
    $tester->setInputs([is_string($values) ? $values : json_encode($values)]);
    $code = $tester->execute(['--path' => $path]);

    return [$code, (string) file_get_contents($path), $tester->getDisplay()];
}

test('the deploy writes AI keys into .env, replacing or adding lines', function () {
    [$code, $env, $out] = runSetAiKeys(
        "APP_NAME=Minivora\nGROQ_API_KEYS=\nDB_PASSWORD=secret\n",
        ['GROQ_API_KEYS' => 'gsk_new', 'OPENROUTER_API_KEYS' => 'sk-or-v1-abc', 'GEMINI_API_KEYS' => ''],
    );

    expect($code)->toBe(0)
        ->and($env)->toContain("GROQ_API_KEYS=gsk_new\n")
        ->and($env)->toContain("OPENROUTER_API_KEYS=sk-or-v1-abc\n")
        ->and($env)->toContain('DB_PASSWORD=secret')
        ->and(substr_count($env, 'GROQ_API_KEYS='))->toBe(1)
        ->and($out)->toContain('GROQ_API_KEYS, OPENROUTER_API_KEYS')
        ->and($out)->not->toContain('gsk_new'); // names only, never values
});

test('empty secrets never wipe a key that was set by hand', function () {
    [, $env] = runSetAiKeys("GROQ_API_KEYS=by_hand\n", ['GROQ_API_KEYS' => '']);

    expect($env)->toBe("GROQ_API_KEYS=by_hand\n");
});

test('only AI settings can be changed, and values cannot inject lines', function () {
    [, $env] = runSetAiKeys(
        "DB_PASSWORD=secret\n",
        ['DB_PASSWORD' => 'hacked', 'GROQ_API_KEYS' => "x\nDB_PASSWORD=hacked", 'GROQ_MODEL' => 'llama-x'],
    );

    expect($env)->toBe("DB_PASSWORD=secret\nGROQ_MODEL=llama-x\n");
});

test('bad input fails without touching .env', function () {
    [$code, $env] = runSetAiKeys("A=1\n", 'not json');

    expect($code)->toBe(1)->and($env)->toBe("A=1\n");
});
