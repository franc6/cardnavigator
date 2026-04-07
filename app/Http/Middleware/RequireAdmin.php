<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware that restricts the wrapped route group to authenticated users with the is_admin flag.
 */
class RequireAdmin
{
    /**
     * Abort with 403 unless the authenticated user has admin privileges.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @param  Closure(Request): (Response)  $next  The next middleware handler in the pipeline.
     * @return Response The downstream response when the user is admin; aborts 403 otherwise.
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->is_admin, 403);

        return $next($request);
    }
}
