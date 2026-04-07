<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirects authenticated users with the force_password_change flag to the password change screen.
 */
class ForcePasswordChange
{
    /**
     * Intercept the request and redirect to the password change route if the flag is set.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @param  Closure  $next  The next middleware handler in the pipeline.
     * @return Response A redirect to the password change route if the flag is set, otherwise the next response.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->force_password_change && ! $request->routeIs('password.change', 'password.change.update', 'logout')) {
            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
