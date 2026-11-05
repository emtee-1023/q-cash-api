<?php

use App\Enums\TransactionCategory;
use App\Models\Transaction;
use App\Services\Transactions\TransactionClassificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a transaction can be classified', function () {
    $transaction = Transaction::factory()->create();

    $classification = app(TransactionClassificationService::class)->classify(
        $transaction,
        'v1.0.0',
        TransactionCategory::Sale,
        true,
    );

    expect($classification->category)->toBe(TransactionCategory::Sale);
    expect($classification->is_current)->toBeTrue();
    expect($classification->classifier_version)->toBe('v1.0.0');
});

test('a new classification supersedes the previous current one', function () {
    $transaction = Transaction::factory()->create();
    $service = app(TransactionClassificationService::class);

    $first = $service->classify($transaction, 'v1.0.0', TransactionCategory::Sale, true);
    $second = $service->classify($transaction, 'v2.0.0', TransactionCategory::Refund, false);

    $current = $transaction->classifications()->where('is_current', true)->get();

    expect($current)->toHaveCount(1);
    expect($current->first()->id)->toBe($second->id);

    expect($transaction->classifications()->count())->toBe(2);
    expect($first->refresh()->is_current)->toBeFalse();
});

test('classifier versions are retained as history', function () {
    $transaction = Transaction::factory()->create();
    $service = app(TransactionClassificationService::class);

    $service->classify($transaction, 'v1.0.0', TransactionCategory::Unknown, false);
    $service->classify($transaction, 'v1.1.0', TransactionCategory::Sale, true);
    $service->classify($transaction, 'v2.0.0', TransactionCategory::Sale, true);

    expect($transaction->classifications()->count())->toBe(3);
});
