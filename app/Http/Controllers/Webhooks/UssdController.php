<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Ussd\UssdMenuService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * @tags USSD
 */
class UssdController extends Controller
{
    public function __construct(private readonly UssdMenuService $ussd) {}

    /**
     * Handle an Africa's Talking USSD callback
     *
     * Returns a CON response to keep the session open, or END to close it.
     */
    public function __invoke(Request $request): Response
    {
        $sessionId = trim((string) $request->input('sessionId', ''));
        $serviceCode = trim((string) $request->input('serviceCode', ''));
        $phoneNumber = trim((string) $request->input('phoneNumber', ''));
        $text = (string) $request->input('text', '');

        Log::info('USSD request received.', [
            'session_id' => $sessionId,
            'service_code' => $serviceCode,
            'phone_number' => $phoneNumber,
        ]);

        if ($sessionId === '' || $phoneNumber === '') {
            return $this->reply('END Invalid request.');
        }

        return $this->reply($this->ussd->handle(
            $sessionId,
            $serviceCode,
            $phoneNumber,
            $text,
        ));
    }

    private function reply(string $message): Response
    {
        return response($message, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
