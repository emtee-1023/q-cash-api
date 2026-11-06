<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * Immutable monetary value object. All amounts are represented internally as an
 * integer count of the currency's minor units (for KES, 100 minor units = KSh 1.00).
 *
 * The number of minor units per major unit is NOT assumed to be 100. It is passed
 * explicitly wherever a conversion to or from a major unit is required.
 */
final readonly class Money
{
    private const DEFAULT_MINOR_UNITS_PER_MAJOR = 100;

    public readonly string $currency;

    private function __construct(
        public int $minor,
        string $currency,
    ) {
        $this->currency = strtoupper($currency);
    }

    public static function of(int $minor, string $currency = 'KES'): self
    {
        return new self($minor, $currency);
    }

    public static function zero(string $currency = 'KES'): self
    {
        return new self(0, $currency);
    }

    /**
     * Build a Money value from a major-unit string (for example "100.50").
     *
     * Only string or integer major units are accepted. Float input is rejected so
     * that binary floating point never participates in a money conversion.
     */
    public static function fromMajor(string|int $major, string $currency = 'KES', int $minorUnitsPerMajor = self::DEFAULT_MINOR_UNITS_PER_MAJOR): self
    {
        self::assertPositiveMinorUnits($minorUnitsPerMajor);

        $minor = BigDecimal::of((string) $major)
            ->multipliedBy($minorUnitsPerMajor)
            ->toScale(0, RoundingMode::HalfUp)
            ->toInt();

        return new self($minor, $currency);
    }

    public function toMajor(int $minorUnitsPerMajor = self::DEFAULT_MINOR_UNITS_PER_MAJOR): string
    {
        self::assertPositiveMinorUnits($minorUnitsPerMajor);

        return (string) BigDecimal::of($this->minor)
            ->dividedBy($minorUnitsPerMajor, self::decimalPlaces($minorUnitsPerMajor), RoundingMode::Unnecessary);
    }

    /**
     * Return a human-readable representation such as "KSh 1,250.75".
     */
    public function formatted(string $symbol = 'KSh', int $minorUnitsPerMajor = self::DEFAULT_MINOR_UNITS_PER_MAJOR): string
    {
        $major = $this->toMajor($minorUnitsPerMajor);

        [$whole, $fraction] = array_pad(explode('.', $major, 2), 2, '');

        $grouped = number_format((int) $whole, 0, '.', ',');

        if (str_contains($major, '-')) {
            $grouped = '-'.ltrim($grouped, '-');
        }

        $amount = $fraction === '' ? $grouped : $grouped.'.'.$fraction;

        return $symbol === '' ? $amount : $symbol.' '.$amount;
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor + $other->minor, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor - $other->minor, $this->currency);
    }

    public function multiply(int $factor): self
    {
        return new self($this->minor * $factor, $this->currency);
    }

    public function negate(): self
    {
        return new self(-$this->minor, $this->currency);
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function isPositive(): bool
    {
        return $this->minor > 0;
    }

    public function isNegative(): bool
    {
        return $this->minor < 0;
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->minor === $other->minor;
    }

    public function compareTo(self $other): int
    {
        $this->assertSameCurrency($other);

        return $this->minor <=> $other->minor;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException("Cannot operate on mixed currencies [{$this->currency}] and [{$other->currency}].");
        }
    }

    private static function assertPositiveMinorUnits(int $minorUnitsPerMajor): void
    {
        if ($minorUnitsPerMajor <= 0) {
            throw new InvalidArgumentException('Minor units per major unit must be a positive integer.');
        }
    }

    private static function decimalPlaces(int $minorUnitsPerMajor): int
    {
        return (int) floor(log10($minorUnitsPerMajor));
    }

    /**
     * Apply a basis-points rate to an integer minor-unit amount, rounding half-up.
     * For example, 10% (1000 bp) applied to 10000 minor units yields 1000.
     */
    public static function applyBasisPoints(int $minor, int $basisPoints): int
    {
        return BigInteger::of($minor)
            ->multipliedBy($basisPoints)
            ->toBigDecimal()
            ->dividedBy(10000, 0, RoundingMode::HalfUp)
            ->toInt();
    }
}
