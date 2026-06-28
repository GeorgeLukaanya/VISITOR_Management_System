<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticate the Africa's Talking USSD callback with a shared secret.
 *
 * AT's exact callback-authentication mechanism is still to be confirmed
 * (README "Before live testing"). Until then this accepts the secret in the
 * two realistic ways without committing to either:
 *   - a query parameter on the registered callback URL (…/ussd?secret=…), or
 *   - an `X-Callback-Secret` request header.
 *
 * Enforcement is OPT-IN: with AT_CALLBACK_SECRET empty (the default) the
 * callback stays open, exactly as before — dev/sandbox and the local USSD
 * simulator keep working untouched. Set the secret in production and any
 * forged callback is rejected with 403. When the confirmed AT mechanism is
 * known, only the extraction below needs to change.
 */
class VerifyUssdCallback
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) (config('services.africastalking.callback_secret') ?? '');

        // Not configured -> not enforced (documented default).
        if ($expected === '') {
            return $next($request);
        }

        $provided = (string) ($request->header('X-Callback-Secret')
            ?? $request->query('secret', ''));

        // hash_equals is constant-time, so a wrong secret leaks no timing info.
        if (! hash_equals($expected, $provided)) {
            // A forged/unauthenticated caller is a genuine rejection, not a USSD
            // user error — so 403 is correct here. (Real USSD user errors still
            // get 200 + an END body from the controller.)
            abort(403, 'Invalid USSD callback secret.');
        }

        return $next($request);
    }
}
