<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Models\ShiftOpening;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Nobody walks past the drawer count.
 *
 * Without this, a cashier can go a whole day without ever saying what was in
 * the till, and the end-of-shift variance then compares a counted drawer
 * against cash the books think was collected - which reports a huge false
 * shortage every single time there is a starting float.
 *
 * The shift screen and logout stay reachable, so nobody is ever locked out of
 * the app by a prompt they cannot answer.
 */
class EnsureShiftIsOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Unauthenticated requests are the login page's business, not ours.
        if (! $user) {
            return $next($request);
        }

        // The way in, and the way out.
        if ($request->routeIs('shift.*', 'logout')) {
            return $next($request);
        }

        $shift = ShiftOpening::currentFor($user, $this->branchId($user));

        // Reuse the lookup on the page instead of asking for the same shift
        // a second time when the dashboard renders.
        $request->attributes->set('current_shift', $shift);

        if ($shift) {
            return $next($request);
        }

        // Carry where they were actually headed, so a session that expired
        // mid-task lands them back on the drawer count and then back on the
        // page they wanted. getRequestUri() is path and query only - never a
        // host - so this cannot be turned into an off-site redirect.
        return redirect()
            ->route('shift.open')
            ->with('intended_url', $request->getRequestUri());
    }

    /**
     * Owner/Admin have no branch_id of their own; the pharmacy is the single
     * branch the install was set up with.
     */
    private function branchId($user): ?int
    {
        return $user->branch_id ?: Branch::query()->orderBy('id')->value('id');
    }
}
