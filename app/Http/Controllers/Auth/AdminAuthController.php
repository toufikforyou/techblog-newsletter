<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\AdminOtpMail;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class AdminAuthController extends Controller
{
    // Show registration form
    public function showRegister()
    {
        return view('auth.admin-register');
    }

    // Handle registration - Step 1: Send OTP
    public function register(Request $request)
    {
        $rules = [
            'email' => 'required|email',
        ];

        // Only require Turnstile in production
        if (app()->environment('production')) {
            $rules['cf-turnstile-response'] = 'required';
        }

        $request->validate($rules, [
            'cf-turnstile-response.required' => 'Please complete the security verification.',
        ]);

        // Verify Turnstile token (skip in local/development environment)
        if (app()->environment('production')) {
            $turnstileResponse = \Illuminate\Support\Facades\Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => config('services.turnstile.secret_key'),
                'response' => $request->input('cf-turnstile-response'),
                'remoteip' => $request->ip(),
            ]);

            if (!$turnstileResponse->successful() || !$turnstileResponse->json('success')) {
                return back()->withErrors(['cf-turnstile-response' => 'Security verification failed. Please try again.'])->withInput();
            }
        }

        $allowedDomains = array_filter(array_map('trim', explode(',', strtolower(env('ADMIN_ALLOWED_DOMAINS', 'techappupdate.com')))));
        $emailDomain = strtolower(substr(strrchr($request->email, '@'), 1));

        // Allow any email belonging to the authorized domain(s)
        if (!in_array($emailDomain, $allowedDomains, true)) {
            return back()->withErrors(['email' => 'This email domain is not authorized for admin registration.']);
        }

        // Check if admin already exists and is verified
        $admin = Admin::where('email', $request->email)->first();
        if ($admin && $admin->isEmailVerified()) {
            return redirect()->route('admin.login')->with('error', 'This email is already registered. Please login.');
        }

        // Generate OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Create or update admin with OTP
        Admin::updateOrCreate(
            ['email' => $request->email],
            [
                'otp' => $otp,
                'otp_expires_at' => now()->addMinutes(10),
            ]
        );

        // Queue OTP email for async delivery
        Mail::to($request->email)->queue(new AdminOtpMail($otp));

        return view('auth.admin-verify-otp', ['email' => $request->email])
            ->with('success', 'OTP has been sent to your email. Please check your inbox.');
    }

    // Verify OTP and complete registration
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|digits:6',
            'name' => 'required|string|max:255',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $admin = Admin::where('email', $request->email)->first();

        if (!$admin) {
            return back()->withErrors(['email' => 'Invalid email address.']);
        }

        if (!$admin->isOtpValid($request->otp)) {
            return back()->withErrors(['otp' => 'Invalid or expired OTP.']);
        }

        // Generate new token for this session
        $newToken = \Illuminate\Support\Str::random(60);
        
        // Update admin details and verify email
        $admin->update([
            'name' => $request->name,
            'password' => Hash::make($request->password),
            'role' => 'admin',
            'email_verified_at' => now(),
            'otp' => null,
            'otp_expires_at' => null,
            'remember_token' => $newToken,
        ]);

        // Log the admin in
        Auth::guard('admin')->login($admin);
        
        // Regenerate session for security
        $request->session()->regenerate();
        
        // Store the token in session for validation
        $request->session()->put('admin_session_token', $newToken);
        
        // Force save the session to ensure token is persisted before redirect
        $request->session()->save();

        return redirect()->route('admin.dashboard')->with('success', 'Registration successful! Welcome to the admin panel.');
    }

    // Show login form
    public function showLogin()
    {
        return view('auth.admin-login');
    }

    // Handle login
    public function login(Request $request)
    {
        $rules = [
            'email' => 'required|email',
            'password' => 'required',
        ];

        // Only require Turnstile in production
        if (app()->environment('production')) {
            $rules['cf-turnstile-response'] = 'required';
        }

        $request->validate($rules, [
            'cf-turnstile-response.required' => 'Please complete the security verification.',
        ]);

        // Verify Turnstile token
        $turnstileResponse = \Illuminate\Support\Facades\Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'secret' => config('services.turnstile.secret_key'),
            'response' => $request->input('cf-turnstile-response'),
            'remoteip' => $request->ip(),
        ]);

        if (app()->environment('production')) {
            if (!$turnstileResponse->successful() || !$turnstileResponse->json('success')) {
                return back()->withErrors(['cf-turnstile-response' => 'Security verification failed. Please try again.'])->withInput();
            }
        }

        $admin = Admin::where('email', $request->email)->first();

        if (!$admin || !$admin->isEmailVerified()) {
            return back()->withErrors(['email' => 'No verified admin account found with this email.']);
        }

        if (Auth::guard('admin')->attempt(['email' => $request->email, 'password' => $request->password], $request->filled('remember'))) {
            // Regenerate session first to prevent fixation attacks
            $request->session()->regenerate();
            
            // Generate new token for this session
            $newToken = \Illuminate\Support\Str::random(60);
            
            // Get fresh admin from database and update token
            $authenticatedAdmin = Admin::find(Auth::guard('admin')->id());
            $authenticatedAdmin->remember_token = $newToken;
            $authenticatedAdmin->save();
            
            // Store the token in current session for validation AFTER regeneration
            $request->session()->put('admin_session_token', $newToken);
            
            // Force save the session to ensure token is persisted before redirect
            $request->session()->save();
            
            return redirect()->intended(route('admin.dashboard'));
        }

        return back()->withErrors(['email' => 'Invalid credentials.']);
    }

    // Handle logout
    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')->with('success', 'Logged out successfully.');
    }
}
