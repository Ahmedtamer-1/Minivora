<?php

namespace App\Core\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\StreamableInputInterface;

/**
 * Writes the AI provider keys and models into the server's .env. Used by the deploy workflow, which
 * pipes them in from GitHub secrets as JSON on stdin (never on the command line, where other users
 * of a shared host could see them). Only the AI settings below can be changed; empty values are
 * skipped so a missing secret never wipes a key set by hand.
 */
class SetAiKeys extends Command
{
    public const ALLOWED = [
        'GROQ_API_KEYS', 'OPENROUTER_API_KEYS', 'GEMINI_API_KEYS',
        'GROQ_MODEL', 'OPENROUTER_MODEL', 'GEMINI_MODEL',
    ];

    protected $signature = 'ai:set-keys {--path= : .env file to update (defaults to the app\'s .env)}';

    protected $description = 'Update the AI keys and models in .env from a JSON object on stdin';

    public function handle(): int
    {
        $path = (string) ($this->option('path') ?: base_path('.env'));
        if (! is_file($path) || ! is_writable($path)) {
            $this->error("Cannot write {$path}.");

            return self::FAILURE;
        }

        $stream = $this->input instanceof StreamableInputInterface && $this->input->getStream() !== null ? $this->input->getStream() : STDIN;
        $values = json_decode((string) stream_get_contents($stream), true);
        if (! is_array($values)) {
            $this->error('Expected a JSON object on stdin.');

            return self::FAILURE;
        }

        $env = (string) file_get_contents($path);
        $changed = [];
        foreach (self::ALLOWED as $name) {
            $value = trim((string) ($values[$name] ?? ''));
            // One line, no characters that would break .env parsing.
            if ($value === '' || preg_match('/[\s"\'#\\\\$`]/', $value) === 1) {
                continue;
            }
            $line = "{$name}={$value}";
            $pattern = '/^'.preg_quote($name, '/').'=.*$/m';
            $env = preg_match($pattern, $env) === 1
                ? (string) preg_replace_callback($pattern, fn () => $line, $env, 1)
                : rtrim($env, "\n")."\n{$line}\n";
            $changed[] = $name;
        }

        file_put_contents($path, $env);
        $this->info($changed === [] ? 'No AI settings to change.' : 'Updated: '.implode(', ', $changed));

        return self::SUCCESS;
    }
}
