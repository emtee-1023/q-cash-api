<?php

use App\Http\Controllers\Api\AdvanceController;
use App\Http\Controllers\Api\AssessmentController;
use App\Http\Controllers\Api\BusinessController;
use App\Http\Controllers\Api\OfferController;
use App\Http\Controllers\Api\PaymentAccountController;
use App\Http\Controllers\Api\RepaymentController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Test;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/test', [Test::class, 'test']);
Route::post('/testsms', [Test::class, 'sendSms']);

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('businesses', BusinessController::class)->only(['index', 'store', 'show']);

    Route::post('businesses/{business}/payment-accounts', [PaymentAccountController::class, 'store']);
    Route::get('businesses/{business}/transactions', [TransactionController::class, 'index']);
    Route::post('businesses/{business}/transactions', [TransactionController::class, 'store']);
    Route::post('businesses/{business}/assessments', [AssessmentController::class, 'store']);
    Route::post('businesses/{business}/offers', [OfferController::class, 'store']);
    Route::get('businesses/{business}/advances', [AdvanceController::class, 'index']);
    Route::post('businesses/{business}/repayments', [RepaymentController::class, 'store']);

    Route::post('offers/{offer}/accept', [OfferController::class, 'accept']);

    Route::get('advances/{advance}', [AdvanceController::class, 'show']);
    Route::post('advances/{advance}/approve', [AdvanceController::class, 'approve']);
    Route::post('advances/{advance}/disburse', [AdvanceController::class, 'disburse']);
});
