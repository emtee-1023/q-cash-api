<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * Immutable representation of a percentage rate expressed as integer basis points.
 *
 * 100 basis points = 1%. Examples: 5% = 500, 10% = 1000, 30% = 3000, 100% = 10000.
 */
final readonly class BasisPoints
{
    private function __construct(public int $value) {}

    public static function of(int $basisPoints): self
    {
        return new self($basisPoints);
    }

    /**
     * Build basis points from a percentage string such as "10" or "10.5".
     */
    public static function fromPercent(string|int $percent): self
    {
        $value = BigDecimal::of((string) $percent)
            ->multipliedBy(100)
            ->toScale(0, RoundingMode::HalfUp)
            ->toInt();

        return new self($value);
    }

    /**
     * Build basis points from a decimal rate such as "0.10".
     */
    public static function fromRate(string|int $rate): self
    {
        $value = BigDecimal::of((string) $rate)
            ->multipliedBy(10000)
            ->toScale(0, RoundingMode::HalfUp)
            ->toInt();

        return new self($value);
    }

    /**
     * Return the rate as a decimal string, for example "0.1000".
     */
    public function toDecimal(): string
    {
        return (string) BigDecimal::of($this->value)->dividedBy(10000, 4, RoundingMode::Unnecessary);
    }

    /**
     * Return the rate as a percentage string, for example "10.00".
     */
    public function toPercent(): string
    {
        return (string) BigDecimal::of($this->value)->dividedBy(100, 2, RoundingMode::Unnecessary);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Apply this rate to an integer minor-unit amount, rounding half-up.
     */
    public function applyToMinor(int $minor): int
    {
        return BigInteger::of($minor)
            ->multipliedBy($this->value)
            ->toBigDecimal()
            ->dividedBy(10000, 0, RoundingMode::HalfUp)
            ->toInt();
    }

    public function assertValid(): void
    {
        if ($this->value < 0) {
            throw new InvalidArgumentException('Basis points cannot be negative.');
        }
    }
}
