<?php

namespace App\Services\Ussd;

use App\Enums\AdvanceStatus;
use App\Enums\AssessmentStatus;
use App\Enums\BusinessUserRole;
use App\Enums\BusinessUserStatus;
use App\Enums\UssdState;
use App\Exceptions\InsufficientWalletBalanceException;
use App\Models\Business;
use App\Models\EraAssessment;
use App\Models\User;
use App\Services\Advances\AdvanceExposureService;
use App\Services\Advances\AdvanceService;
use App\Services\Advances\OfferService;
use App\Services\Disbursements\WalletDisbursementService;
use App\Services\Transactions\MpesaStkPushService;
use App\Services\Wallets\BusinessWalletService;
use App\Services\Withdrawals\MpesaB2cWithdrawalService;
use App\Support\Money;
use App\Support\PhoneNumber;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Throwable;

class UssdMenuService
{
    private const BUSINESSES_PER_PAGE = 4;

    public function __construct(
        private readonly MpesaStkPushService $stkPush,
        private readonly WalletDisbursementService $walletDisbursements,
        private readonly MpesaB2cWithdrawalService $withdrawals,
        private readonly OfferService $offers,
        private readonly AdvanceService $advances,
        private readonly AdvanceExposureService $exposure,
        private readonly BusinessWalletService $wallets,
    ) {}

    public function handle(
        string $sessionId,
        string $serviceCode,
        string $phoneNumber,
        string $text,
    ): string {
        if (! $this->serviceCodeIsValid($serviceCode)) {
            return $this->end('This USSD service is not available.');
        }

        $phoneNumber = PhoneNumber::normalize($phoneNumber);

        if ($phoneNumber === '') {
            return $this->end('We could not read your phone number.');
        }

        $key = 'ussd:'.$sessionId;

        if (trim($text) === '') {
            Cache::forget($key);

            return $this->start($key, $phoneNumber);
        }

        $session = Cache::get($key);

        if (! is_array($session)) {
            return $this->end('Session expired. Please dial again.');
        }

        $user = User::query()->find($session['user_id'] ?? null);

        if ($user === null) {
            Cache::forget($key);

            return $this->end('Session expired. Please dial again.');
        }

        $business = isset($session['business_id'])
            ? Business::query()->find($session['business_id'])
            : null;

        if ($business !== null && ! $this->isOwner($user, $business)) {
            Cache::forget($key);

            return $this->end('You no longer have access to this business.');
        }

        try {
            return $this->dispatch(
                $key,
                $session,
                $user,
                $business,
                $this->currentInput($text),
            );
        } catch (InsufficientWalletBalanceException) {
            Cache::forget($key);

            return $this->end('Insufficient wallet balance.');
        } catch (ValidationException $exception) {
            Cache::forget($key);

            return $this->end((string) (
                collect($exception->errors())->flatten()->first()
                ?? 'Could not complete the request.'
            ));
        } catch (Throwable $exception) {
            report($exception);
            Cache::forget($key);

            return $this->end('Sorry, we could not complete the request. Please try again.');
        }
    }

    private function start(string $key, string $phoneNumber): string
    {
        $user = User::query()
            ->where('phone_number', $phoneNumber)
            ->first();

        if ($user === null) {
            return $this->end('This number is not registered for QCash.');
        }

        $businesses = $this->ownerBusinesses($user);

        if ($businesses->isEmpty()) {
            return $this->end('No active business is linked to this number.');
        }

        if ($businesses->count() === 1) {
            $business = $businesses->first();

            $this->save($key, [
                'user_id' => $user->id,
                'business_id' => $business->id,
                'state' => UssdState::MainMenu->value,
                'data' => [],
            ]);

            return $this->mainMenu($business);
        }

        $this->save($key, [
            'user_id' => $user->id,
            'business_id' => null,
            'state' => UssdState::SelectBusiness->value,
            'data' => ['page' => 0],
        ]);

        return $this->businessMenu($businesses, 0);
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function dispatch(
        string $key,
        array $session,
        User $user,
        ?Business $business,
        string $input,
    ): string {
        $state = UssdState::tryFrom((string) ($session['state'] ?? ''));

        if ($state === null) {
            Cache::forget($key);

            return $this->end('Session expired. Please dial again.');
        }

        if ($state === UssdState::SelectBusiness) {
            return $this->selectBusiness($key, $session, $user, $input);
        }

        if ($business === null) {
            Cache::forget($key);

            return $this->end('Session expired. Please dial again.');
        }

        return match ($state) {
            UssdState::MainMenu => $this->handleMainMenu($key, $session, $business, $input),
            UssdState::CollectPhone => $this->handleCollectPhone($key, $session, $input),
            UssdState::CollectAmount => $this->handleCollectAmount($key, $session, $input),
            UssdState::CollectConfirm => $this->handleCollectConfirm($key, $session, $business, $input),
            UssdState::AdvanceMenu => $this->handleAdvanceMenu($key, $session, $business, $input),
            UssdState::BorrowAmount => $this->handleBorrowAmount($key, $session, $business, $input),
            UssdState::BorrowConfirm => $this->handleBorrowConfirm($key, $session, $business, $input),
            UssdState::WithdrawAmount => $this->handleWithdrawAmount($key, $session, $business, $input),
            UssdState::WithdrawConfirm => $this->handleWithdrawConfirm($key, $session, $business, $input),
            default => $this->end('Session expired. Please dial again.'),
        };
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function selectBusiness(
        string $key,
        array $session,
        User $user,
        string $input,
    ): string {
        $businesses = $this->ownerBusinesses($user);
        $page = (int) ($session['data']['page'] ?? 0);
        $pages = $this->businessPageCount($businesses);

        if ($input === '9' && $page + 1 < $pages) {
            $session['data']['page'] = $page + 1;
            $this->save($key, $session);

            return $this->businessMenu($businesses, $page + 1);
        }

        if ($input === '8' && $page > 0) {
            $session['data']['page'] = $page - 1;
            $this->save($key, $session);

            return $this->businessMenu($businesses, $page - 1);
        }

        $business = $this->businessForInput($businesses, $page, $input);

        if ($business === null) {
            return $this->con('Invalid choice.'."\n\n".$this->businessMenuBody($businesses, $page));
        }

        $session['business_id'] = $business->id;
        $session['state'] = UssdState::MainMenu->value;
        $session['data'] = [];
        $this->save($key, $session);

        return $this->mainMenu($business);
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function handleMainMenu(
        string $key,
        array $session,
        Business $business,
        string $input,
    ): string {
        switch ($input) {
            case '1':
                $session['state'] = UssdState::CollectPhone->value;
                $session['data'] = [];
                $this->save($key, $session);

                return $this->con("Enter the customer's phone number");
            case '2':
                return $this->advanceMenu($key, $session, $business);
            case '3':
                return $this->withdrawMenu($key, $session, $business);
            case '4':
                Cache::forget($key);

                return $this->end('Thank you for using QCash.');
            default:
                return $this->con('Invalid choice.'."\n\n".$this->mainMenuBody($business));
        }
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function handleCollectPhone(
        string $key,
        array $session,
        string $input,
    ): string {
        $customerPhone = PhoneNumber::normalize($input);

        if (preg_match('/^254\d{9}$/', $customerPhone) !== 1) {
            return $this->con("Enter a valid phone number\nExample: 0712345678");
        }

        $session['data']['customer_phone'] = $customerPhone;
        $session['state'] = UssdState::CollectAmount->value;
        $this->save($key, $session);

        return $this->con('Enter amount to collect (KES)');
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function handleCollectAmount(
        string $key,
        array $session,
        string $input,
    ): string {
        $amountMinor = $this->parseAmount($input);

        if ($amountMinor === null) {
            return $this->con('Enter a valid amount in KES');
        }

        $session['data']['amount_minor'] = $amountMinor;
        $session['state'] = UssdState::CollectConfirm->value;
        $this->save($key, $session);

        $amount = Money::of($amountMinor, 'KES')->formatted('KES');
        $phone = $session['data']['customer_phone'];

        return $this->con("Send STK of {$amount} to {$phone}?\n1. Confirm\n2. Cancel");
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function handleCollectConfirm(
        string $key,
        array $session,
        Business $business,
        string $input,
    ): string {
        if ($input === '2') {
            $session['state'] = UssdState::MainMenu->value;
            $session['data'] = [];
            $this->save($key, $session);

            return $this->mainMenu($business);
        }

        if ($input !== '1') {
            return $this->con("1. Confirm\n2. Cancel");
        }

        $amountMinor = (int) ($session['data']['amount_minor'] ?? 0);
        $customerPhone = (string) ($session['data']['customer_phone'] ?? '');

        if ($amountMinor <= 0 || $customerPhone === '') {
            Cache::forget($key);

            return $this->end('Session expired. Please dial again.');
        }

        $this->stkPush->initiate(
            $business,
            $customerPhone,
            $amountMinor,
            description: 'QCash payment',
        );

        Cache::forget($key);

        return $this->end(
            'STK push sent to '.$customerPhone.'. Ask the customer to enter their M-Pesa PIN.'
        );
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function advanceMenu(
        string $key,
        array $session,
        Business $business,
    ): string {
        $assessment = $this->latestEligibleAssessment($business);

        if ($assessment === null) {
            $session['state'] = UssdState::MainMenu->value;
            $session['data'] = [];
            $this->save($key, $session);

            return $this->con("No eligible cash advance right now.\n\n".$this->mainMenuBody($business));
        }

        $availableMinor = $this->availableAdvanceMinor($business, $assessment);

        if ($availableMinor <= 0) {
            $session['state'] = UssdState::MainMenu->value;
            $session['data'] = [];
            $this->save($key, $session);

            return $this->con("No available cash advance right now.\n\n".$this->mainMenuBody($business));
        }

        $session['state'] = UssdState::AdvanceMenu->value;
        $session['data'] = ['assessment_id' => $assessment->id];
        $this->save($key, $session);

        $available = Money::of($availableMinor, 'KES')->formatted('KES');

        $outstanding = Money::of(
            (int) $business->advances()
                ->whereIn('status', [
                    AdvanceStatus::Active->value,
                    AdvanceStatus::Overdue->value,
                    AdvanceStatus::Disbursing->value,
                ])
                ->sum('outstanding_balance_minor'),
            'KES',
        )->formatted('KES');

        return $this->con(
            "Cash advance\nAvailable: {$available}\nOutstanding: {$outstanding}\n1. Borrow\n0. Back"
        );
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function handleAdvanceMenu(
        string $key,
        array $session,
        Business $business,
        string $input,
    ): string {
        if ($input === '0') {
            $session['state'] = UssdState::MainMenu->value;
            $session['data'] = [];
            $this->save($key, $session);

            return $this->mainMenu($business);
        }

        if ($input !== '1') {
            return $this->con("1. Borrow\n0. Back");
        }

        $assessment = $this->assessmentFromSession($business, $session);

        if ($assessment === null) {
            Cache::forget($key);

            return $this->end('No eligible cash advance right now.');
        }

        $availableMinor = $this->availableAdvanceMinor($business, $assessment);

        if ($availableMinor <= 0) {
            Cache::forget($key);

            return $this->end('No available cash advance right now.');
        }

        $session['state'] = UssdState::BorrowAmount->value;
        $this->save($key, $session);

        $available = Money::of($availableMinor, 'KES')->formatted('KES');

        return $this->con("Enter amount to borrow (max {$available})");
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function handleBorrowAmount(
        string $key,
        array $session,
        Business $business,
        string $input,
    ): string {
        $assessment = $this->assessmentFromSession($business, $session);

        if ($assessment === null) {
            Cache::forget($key);

            return $this->end('No eligible cash advance right now.');
        }

        $amountMinor = $this->parseAmount($input);
        $available = $this->availableAdvanceMinor($business, $assessment);

        if ($amountMinor === null || $amountMinor > $available) {
            $formatted = Money::of($available, 'KES')->formatted('KES');

            return $this->con(
                "Enter a valid amount up to {$formatted}"
            );
        }

        $session['data']['amount_minor'] = $amountMinor;
        $session['state'] = UssdState::BorrowConfirm->value;
        $this->save($key, $session);

        $amount = Money::of($amountMinor, 'KES')->formatted('KES');
        $feeMinor = $this->offers->accessFeeMinor($amountMinor);
        $receiveMinor = $amountMinor - $feeMinor;
        $fee = Money::of($feeMinor, 'KES')->formatted('KES');
        $receive = Money::of($receiveMinor, 'KES')->formatted('KES');
        $days = $this->offers->repaymentWindowDays((int) $assessment->maximum_repayment_days);
        $rate = rtrim(rtrim(number_format(((int) $assessment->recommended_recovery_rate_bps) / 100, 2), '0'), '.');

        return $this->con(
            "Borrow {$amount}?\nAccess fee: {$fee}\nYou receive: {$receive}\nRepay {$amount} over {$days} days\nRecovery: {$rate}%\n1. Confirm\n2. Cancel"
        );
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function handleBorrowConfirm(
        string $key,
        array $session,
        Business $business,
        string $input,
    ): string {
        if ($input === '2') {
            return $this->advanceMenu($key, $session, $business);
        }

        if ($input !== '1') {
            return $this->con("1. Confirm\n2. Cancel");
        }

        $assessment = $this->assessmentFromSession($business, $session);
        $amountMinor = (int) ($session['data']['amount_minor'] ?? 0);

        if ($assessment === null) {
            Cache::forget($key);

            return $this->end('No eligible cash advance right now.');
        }

        $available = $this->availableAdvanceMinor($business, $assessment);

        if ($amountMinor <= 0 || $amountMinor > $available) {
            Cache::forget($key);

            return $this->end('The requested amount is no longer available.');
        }

        $offer = $this->offers->createOffer($assessment, [
            'requested_amount_minor' => $amountMinor,
        ]);

        $advance = $this->advances->acceptOffer($offer);
        $this->advances->approve($advance);
        $this->walletDisbursements->disburse($advance);

        Cache::forget($key);

        return $this->end(
            'Advance of '.Money::of($amountMinor, 'KES')->formatted('KES')
                .' approved. '.Money::of($advance->principal_amount_minor, 'KES')->formatted('KES')
                .' credited to your QCash wallet.'
        );
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function withdrawMenu(
        string $key,
        array $session,
        Business $business,
    ): string {
        $session['state'] = UssdState::WithdrawAmount->value;
        $session['data'] = [];
        $this->save($key, $session);

        $balance = Money::of($this->wallets->balance($business), 'KES')->formatted('KES');

        return $this->con("Wallet balance: {$balance}\nEnter amount to withdraw");
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function handleWithdrawAmount(
        string $key,
        array $session,
        Business $business,
        string $input,
    ): string {
        $amountMinor = $this->parseAmount($input);
        $balance = $this->wallets->balance($business);

        if ($amountMinor === null || $amountMinor > $balance) {
            $formatted = Money::of($balance, 'KES')->formatted('KES');

            return $this->con("Enter a valid amount up to {$formatted}");
        }

        $session['data']['amount_minor'] = $amountMinor;
        $session['state'] = UssdState::WithdrawConfirm->value;
        $this->save($key, $session);

        $amount = Money::of($amountMinor, 'KES')->formatted('KES');

        return $this->con("Withdraw {$amount} to your M-Pesa?\n1. Confirm\n2. Cancel");
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function handleWithdrawConfirm(
        string $key,
        array $session,
        Business $business,
        string $input,
    ): string {
        if ($input === '2') {
            $session['state'] = UssdState::MainMenu->value;
            $session['data'] = [];
            $this->save($key, $session);

            return $this->mainMenu($business);
        }

        if ($input !== '1') {
            return $this->con("1. Confirm\n2. Cancel");
        }

        $amountMinor = (int) ($session['data']['amount_minor'] ?? 0);

        if ($amountMinor <= 0) {
            Cache::forget($key);

            return $this->end('Session expired. Please dial again.');
        }

        $this->withdrawals->withdraw($business, $amountMinor);

        Cache::forget($key);

        return $this->end(
            'Withdrawal of '.Money::of($amountMinor, 'KES')->formatted('KES')
                .' requested. You will receive an SMS when it is complete.'
        );
    }

    private function mainMenu(Business $business): string
    {
        return $this->con($this->mainMenuBody($business));
    }

    private function mainMenuBody(Business $business): string
    {
        return $business->business_name
            ."\n1. Collect payment\n2. Cash advance\n3. Withdraw\n4. Exit";
    }

    /**
     * @param  Collection<int, Business>  $businesses
     */
    private function businessMenu(Collection $businesses, int $page): string
    {
        return $this->con($this->businessMenuBody($businesses, $page));
    }

    /**
     * @param  Collection<int, Business>  $businesses
     */
    private function businessMenuBody(Collection $businesses, int $page): string
    {
        $pages = $this->businessPageCount($businesses);
        $page = max(0, min($page, $pages - 1));

        $slice = $businesses
            ->slice($page * self::BUSINESSES_PER_PAGE, self::BUSINESSES_PER_PAGE)
            ->values();

        $lines = ['Select business'];

        foreach ($slice as $index => $business) {
            $lines[] = ($index + 1).'. '.$business->business_name;
        }

        if ($page > 0) {
            $lines[] = '8. Previous';
        }

        if ($page + 1 < $pages) {
            $lines[] = '9. More';
        }

        return implode("\n", $lines);
    }

    /**
     * @param  Collection<int, Business>  $businesses
     */
    private function businessForInput(Collection $businesses, int $page, string $input): ?Business
    {
        if (preg_match('/^[1-4]$/', $input) !== 1) {
            return null;
        }

        $offset = $page * self::BUSINESSES_PER_PAGE + ((int) $input - 1);

        return $businesses->values()->get($offset);
    }

    /**
     * @param  Collection<int, Business>  $businesses
     */
    private function businessPageCount(Collection $businesses): int
    {
        return max(1, (int) ceil($businesses->count() / self::BUSINESSES_PER_PAGE));
    }

    /**
     * @return Collection<int, Business>
     */
    private function ownerBusinesses(User $user): Collection
    {
        return $user->businesses()
            ->wherePivot('role', BusinessUserRole::Owner->value)
            ->wherePivot('status', BusinessUserStatus::Active->value)
            ->orderBy('business_name')
            ->get();
    }

    private function isOwner(User $user, Business $business): bool
    {
        return $business->businessUsers()
            ->where('user_id', $user->id)
            ->where('role', BusinessUserRole::Owner->value)
            ->where('status', BusinessUserStatus::Active->value)
            ->exists();
    }

    private function availableAdvanceMinor(Business $business, EraAssessment $assessment): int
    {
        return $this->exposure->availableExposureMinor(
            $business,
            (int) $assessment->recommended_advance_amount_minor,
        );
    }

    private function latestEligibleAssessment(Business $business): ?EraAssessment
    {
        return $business->assessments()
            ->where('assessment_status', AssessmentStatus::Eligible->value)
            ->where('recommended_advance_amount_minor', '>', 0)
            ->latest('assessment_date')
            ->latest('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function assessmentFromSession(Business $business, array $session): ?EraAssessment
    {
        $assessment = EraAssessment::query()->find($session['data']['assessment_id'] ?? null);

        if ($assessment === null
            || $assessment->business_id !== $business->id
            || $assessment->assessment_status !== AssessmentStatus::Eligible) {
            return null;
        }

        return $assessment;
    }

    private function parseAmount(string $input): ?int
    {
        $input = trim($input);

        if (preg_match('/^\d+(?:\.\d{1,2})?$/', $input) !== 1) {
            return null;
        }

        try {
            $amountMinor = Money::fromMajor($input, 'KES')->minor;
        } catch (Throwable) {
            return null;
        }

        return $amountMinor > 0 ? $amountMinor : null;
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function save(string $key, array $session): void
    {
        $ttl = max(1, (int) config('services.africastalking.ussd_session_ttl_minutes', 5));

        Cache::put($key, $session, now()->addMinutes($ttl));
    }

    private function currentInput(string $text): string
    {
        $parts = explode('*', trim($text));

        return trim((string) end($parts));
    }

    private function serviceCodeIsValid(string $serviceCode): bool
    {
        $expected = trim((string) config('services.africastalking.ussd_service_code', ''));

        return $expected === '' || trim($serviceCode) === $expected;
    }

    private function con(string $message): string
    {
        return 'CON '.$message;
    }

    private function end(string $message): string
    {
        return 'END '.$message;
    }
}
