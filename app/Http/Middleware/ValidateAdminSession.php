<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ValidateAdminSession
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if admin is authenticated
        if (Auth::guard('admin')->check()) {
            // Get fresh admin data from database
            $admin = Auth::guard('admin')->user();
            $freshAdmin = \App\Models\Admin::find($admin->id);
            
            // Get the current session token stored when user logged in
            $sessionToken = $request->session()->get('admin_session_token');
            
            // Compare session token with database token
            if (!$sessionToken || !$freshAdmin || $sessionToken !== $freshAdmin->remember_token) {
                // Token mismatch - session was invalidated from another device
                Auth::guard('admin')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                
                return redirect()->route('admin.login')
                    ->with('error', 'Your session has been terminated because you logged in from another device.');
            }
        }
        
        return $next($request);
    }
}
