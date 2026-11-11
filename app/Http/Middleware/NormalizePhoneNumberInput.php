<?php

namespace App\Http\Middleware;

use App\Support\PhoneNumber;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormalizePhoneNumberInput
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $phoneNumber = $request->input('phone_number');

        if (is_string($phoneNumber) && trim($phoneNumber) !== '') {
            $request->merge([
                'phone_number' => PhoneNumber::normalize($phoneNumber),
            ]);
        }

        return $next($request);
    }
}
