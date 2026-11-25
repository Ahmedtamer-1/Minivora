<?php

namespace App\Ai\Agents;

/**
 * Base descriptor for each member of Minivora's 9-agent AI Management Team. FR-AI-05
 */
abstract class ExecutiveAgent
{
    abstract public function key(): string;

    abstract public function title(): string;

    abstract public function titleAr(): string;

    abstract public function domain(): string;

    /** @return list<string> */
    abstract public function functions(): array;

    /** @return list<string> */
    public function proactiveCadences(): array
    {
        return ['daily'];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key(),
            'title' => $this->title(),
            'title_ar' => $this->titleAr(),
            'domain' => $this->domain(),
            'functions' => $this->functions(),
            'proactive_cadences' => $this->proactiveCadences(),
        ];
    }
}
