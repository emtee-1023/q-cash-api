<?php

use App\Support\PhoneNumber;

test('kenyan phone numbers are normalized to the 254 format', function (string $input, string $expected) {
    expect(PhoneNumber::normalize($input))->toBe($expected);
})->with([
    ['+254792314330', '254792314330'],
    ['254792314330', '254792314330'],
    ['0792314330', '254792314330'],
    ['792314330', '254792314330'],
    ['+254 792 314330', '254792314330'],
    ['00254792314330', '254792314330'],
    ['+254-792-314-330', '254792314330'],
]);

test('an empty phone number normalizes to an empty string', function () {
    expect(PhoneNumber::normalize(''))->toBe('');
});
