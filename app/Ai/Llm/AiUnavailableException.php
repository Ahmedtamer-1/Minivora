<?php

namespace App\Ai\Llm;

use RuntimeException;

/**
 * Thrown when all configured LLM providers and API keys are disabled or exhausted.
 * Allows callers to fall back gracefully without disrupting storefront or dashboard. V-14
 */
class AiUnavailableException extends RuntimeException {}
