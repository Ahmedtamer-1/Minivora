<?php

namespace App\Core\Money;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Money value object: integer piasters (1 EGP = 100). Add, subtract, compare. Never floats.
 */
final class Piasters implements JsonSerializable
{
    public function __construct(public readonly int $amount) {}

    public static function of(int $amount): self
    {
        return new self($amount);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    /**
     * Parse a pounds string such as "1,250.50" or "99" into piasters without float maths.
     */
    public static function fromPounds(string $pounds): self
    {
        $clean = str_replace([',', ' '], '', trim($pounds));

        if (! preg_match('/^(-)?(\d+)(?:\.(\d{1,2}))?$/', $clean, $m)) {
            throw new InvalidArgumentException("Not a valid amount: {$pounds}");
        }

        $whole = (int) $m[2];
        $fraction = isset($m[3]) ? (int) str_pad($m[3], 2, '0') : 0;
        $value = $whole * 100 + $fraction;

        return new self($m[1] === '-' ? -$value : $value);
    }

    public function plus(self $other): self
    {
        return new self($this->amount + $other->amount);
    }

    public function minus(self $other): self
    {
        return new self($this->amount - $other->amount);
    }

    public function times(int $quantity): self
    {
        return new self($this->amount * $quantity);
    }

    /**
     * Percentage in basis points (1% = 100), rounded half up to the nearest piaster.
     */
    public function percent(int $basisPoints): self
    {
        return new self(intdiv($this->amount * $basisPoints + ($this->amount >= 0 ? 5000 : -5000), 10000));
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount;
    }

    public function greaterThan(self $other): bool
    {
        return $this->amount > $other->amount;
    }

    public function lessThan(self $other): bool
    {
        return $this->amount < $other->amount;
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function isNegative(): bool
    {
        return $this->amount < 0;
    }

    public function jsonSerialize(): int
    {
        return $this->amount;
    }
}
