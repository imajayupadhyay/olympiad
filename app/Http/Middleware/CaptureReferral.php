<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stash a `?ref=CODE` referral code in the session the first time a guest lands
 * with one (from any page — register, login, public exams). Consumed at sign-up
 * by RegisteredUserController, mirroring the existing `pending_enroll_ids` flow.
 */
class CaptureReferral
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && $request->filled('ref')) {
            $code = trim((string) $request->query('ref'));

            // Stash for attribution at sign-up (guests only; mirrors pending_enroll_ids).
            if (! $request->user() && ! $request->session()->has('referral_code')) {
                $request->session()->put('referral_code', $code);
            }

            // Reward-on-open mode counts this link open for the referrer (de-duped per IP).
            // Counts guests and logged-in friends; the referrer's own opens are ignored.
            app(\App\Services\ReferralService::class)
                ->recordClickByCode($code, $request->ip(), $request->user()?->id);
        }

        return $next($request);
    }
}
