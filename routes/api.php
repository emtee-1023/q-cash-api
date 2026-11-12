<?php

use App\Http\Controllers\Admin\Auth\AdminActivationController;
use App\Http\Controllers\Admin\Auth\AdminLoginController;
use App\Http\Controllers\Admin\BusinessController as AdminBusinessController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\AdvanceController;
use App\Http\Controllers\Api\AssessmentController;
use App\Http\Controllers\Api\BusinessController;
use App\Http\Controllers\Api\BusinessInvitationController;
use App\Http\Controllers\Api\BusinessMemberController;
use App\Http\Controllers\Api\BusinessTypeController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\OfferController;
use App\Http\Controllers\Api\PaymentAccountController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RepaymentController;
use App\Http\Controllers\Api\StkPushController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Test;
use App\Http\Controllers\Webhooks\MpesaB2cController;
use App\Http\Controllers\Webhooks\MpesaC2bController;
use App\Http\Controllers\Webhooks\MpesaStkController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) => $request->user())->name('user.show');
    Route::patch('/user', [ProfileController::class, 'update'])->name('user.update');
    Route::post('/user/email/verification-code', [ProfileController::class, 'sendEmailVerification'])
        ->middleware('throttle:6,1')
        ->name('user.email.verification-code');
    Route::post('/user/email/verify', [ProfileController::class, 'verifyEmail'])
        ->middleware('throttle:6,1')
        ->name('user.email.verify');
});

Route::get('/test', [Test::class, 'test']);
Route::post('/testsms', [Test::class, 'sendSms']);

Route::prefix('webhooks')->name('webhooks.')->group(function () {
    Route::post('validation', [MpesaC2bController::class, 'validation'])->name('validation');
    Route::post('confirmation', [MpesaC2bController::class, 'confirmation'])->name('confirmation');
    Route::post('stk', MpesaStkController::class)->name('stk');
    Route::post('b2c/result', [MpesaB2cController::class, 'result'])->name('b2c.result');
    Route::post('b2c/timeout', [MpesaB2cController::class, 'timeout'])->name('b2c.timeout');
});

Route::post('auth/register', [RegisterController::class, 'register']);

Route::post('business-invitations/activate', [BusinessInvitationController::class, 'activate'])
    ->middleware('throttle:6,1')
    ->name('business-invitations.activate');
Route::post('business-invitations/resend-otp', [BusinessInvitationController::class, 'resendOtp'])
    ->middleware('throttle:6,1')
    ->name('business-invitations.resend-otp');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::post('login', [AdminLoginController::class, 'login'])
        ->middleware('throttle:6,1')
        ->name('login');

    Route::post('activate', [AdminActivationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('activate');

    Route::post('activate/resend-otp', [AdminActivationController::class, 'resendOtp'])
        ->middleware('throttle:6,1')
        ->name('activate.resend-otp');

    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::get('me', [AdminLoginController::class, 'me'])->name('me');
        Route::post('logout', [AdminLoginController::class, 'logout'])->name('logout');

        Route::get('businesses', [AdminBusinessController::class, 'index'])->name('businesses.index');
        Route::get('businesses/{business}', [AdminBusinessController::class, 'show'])->name('businesses.show');
        Route::patch('businesses/{business}', [AdminBusinessController::class, 'update'])->name('businesses.update');
        Route::patch('businesses/{business}/status', [AdminBusinessController::class, 'updateStatus'])
            ->name('businesses.status');
        Route::patch('businesses/{business}/kyc-status', [AdminBusinessController::class, 'updateKycStatus'])
            ->name('businesses.kyc-status');

        Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
        Route::patch('users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::post('users/{user}/grant-admin', [AdminUserController::class, 'grant'])
            ->name('users.grant-admin');
        Route::post('users/{user}/revoke-admin', [AdminUserController::class, 'revoke'])
            ->name('users.revoke-admin');
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('business-types', [BusinessTypeController::class, 'index']);

    Route::get('locations/counties', [LocationController::class, 'counties']);
    Route::get('locations/counties/{county}/sub-counties', [LocationController::class, 'subCounties']);
    Route::get('locations/sub-counties/{subCounty}/wards', [LocationController::class, 'wards']);

    Route::apiResource('businesses', BusinessController::class)->only(['index', 'store', 'show']);

    Route::get('businesses/{business}/members', [BusinessMemberController::class, 'index'])
        ->name('businesses.members.index');
    Route::post('businesses/{business}/members', [BusinessMemberController::class, 'store'])
        ->name('businesses.members.store');
    Route::patch('businesses/{business}', [BusinessController::class, 'update'])->name('businesses.update');

    Route::post('businesses/{business}/payment-accounts', [PaymentAccountController::class, 'store']);
    Route::post('businesses/{business}/stk-push', [StkPushController::class, 'store'])
        ->name('businesses.stk-push.store');
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
