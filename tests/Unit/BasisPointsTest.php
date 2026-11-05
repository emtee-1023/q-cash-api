<?php

use App\Support\BasisPoints;

test('percentages convert to basis points', function () {
    expect(BasisPoints::fromPercent('5')->value)->toBe(500);
    expect(BasisPoints::fromPercent('10')->value)->toBe(1000);
    expect(BasisPoints::fromPercent('30')->value)->toBe(3000);
    expect(BasisPoints::fromPercent('100')->value)->toBe(10000);
});

test('decimal rates convert to basis points', function () {
    expect(BasisPoints::fromRate('0.10')->value)->toBe(1000);
});

test('basis points apply to minor units with half-up rounding', function () {
    expect(BasisPoints::of(1000)->applyToMinor(10000))->toBe(1000);
    expect(BasisPoints::of(1000)->applyToMinor(10050))->toBe(1005);
});

test('basis points convert back to a percentage string', function () {
    expect(BasisPoints::of(1000)->toPercent())->toBe('10.00');
});
