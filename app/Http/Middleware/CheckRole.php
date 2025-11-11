<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $role
     * @return mixed
     */
    public function handle($request, Closure $next, $role)
    {
        if (!Auth::check()) {
            // User is not authenticated
            return redirect('login');
        }

        $user = Auth::user();

        if (!$user->hasRole($role)) {
            // User does not have the required role
            abort(403, 'Unauthorized action.');
        }

        return $next($request);
    }
}
