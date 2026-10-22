<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Area guard. Usage: ->middleware('area:staff') for the back office, 'area:portal' for the dealer portal.
 * Inactive users are logged out. Fine-grained access uses permissions (->middleware('can:payments.verify')).
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string $area = 'staff'): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            auth()->logout();

            return redirect()->route('login')->withErrors(['email' => 'Your account is inactive.']);
        }

        if ($area === 'portal') {
            abort_unless($user->isDealer() && $user->customer_id, 403, 'The dealer portal is for dealer accounts.');
        } elseif ($user->isDealer()) {
            abort_unless(\Illuminate\Support\Facades\Route::has('portal.dashboard'), 403, 'Dealer accounts use the dealer portal.');

            return redirect()->route('portal.dashboard');
        }

        return $next($request);
    }
}
