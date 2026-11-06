<?php

use App\Support\Money;

test('a whole amount is stored in minor units', function () {
    expect(Money::fromMajor('100.00')->minor)->toBe(10000);
});

test('a fractional amount is stored in minor units', function () {
    expect(Money::fromMajor('100.50')->minor)->toBe(10050);
});

test('a large amount is stored in minor units', function () {
    expect(Money::fromMajor('1250.75')->minor)->toBe(125075);
});

test('minor units are displayed correctly', function () {
    $money = Money::of(10050);

    expect($money->toMajor())->toBe('100.50');
    expect($money->formatted())->toBe('KSh 100.50');
});

test('money can be added and subtracted without floating point', function () {
    $a = Money::fromMajor('0.10');
    $b = Money::fromMajor('0.20');

    expect($a->add($b)->minor)->toBe(30);
    expect($b->subtract($a)->minor)->toBe(10);
});

test('money rejects operations across mixed currencies', function () {
    Money::of(100, 'KES')->add(Money::of(100, 'USD'));
})->throws(InvalidArgumentException::class);

test('basis points can be applied to minor units', function () {
    expect(Money::applyBasisPoints(10000, 1000))->toBe(1000);
});

test('an integer major unit is accepted', function () {
    expect(Money::fromMajor(100)->minor)->toBe(10000);
});
