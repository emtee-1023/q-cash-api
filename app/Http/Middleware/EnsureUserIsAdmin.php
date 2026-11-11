<?php

namespace App\Http\Middleware;

use App\Support\AdminToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        $token = $user->currentAccessToken();

        if (
            ! $user->isActivatedAdmin()
            || $token === null
            || $token->name !== AdminToken::NAME
            || ! $token->can(AdminToken::ABILITY)
        ) {
            abort(403);
        }

        return $next($request);
    }
}
