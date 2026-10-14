<?php

namespace App\Http\Middleware;

use App\Support\PersistentLogin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RestorePersistentLogin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $current = Auth::user();
            if ($current instanceof \App\Models\CyberKey && PersistentLogin::needsRefresh($request->cookie(PersistentLogin::COOKIE))) {
                PersistentLogin::queue($current);
            }

            return $next($request);
        }

        $user = PersistentLogin::userFromCookie(PersistentLogin::rawFromRequest());
        if ($user) {
            // Restore without session()->migrate() to avoid concurrent
            // request races (keep-alive + menu navigation) that cause 419/login redirects.
            $guard = Auth::guard();
            $guard->setUser($user);
            $request->session()->put($guard->getName(), $user->getAuthIdentifier());
            PersistentLogin::queue($user);

            return $next($request);
        }

        if (PersistentLogin::hasAnyCookie()) {
            PersistentLogin::forget();
        }

        return $next($request);
    }
}
