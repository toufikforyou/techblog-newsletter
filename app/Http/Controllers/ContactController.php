<?php

namespace App\Http\Controllers;

use App\Mail\ContactSuccess;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ContactController extends Controller
{
    public function store(Request $request)
    {
        $rules = [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ];

        // Only require Turnstile in production
        if (app()->environment('production')) {
            $rules['cf-turnstile-response'] = 'required';
        }

        $validated = $request->validate($rules, [
            'cf-turnstile-response.required' => 'Please complete the security verification.',
        ]);

        // Verify Turnstile token (skip in local/development environment)
        if (app()->environment('production')) {
            $turnstileResponse = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => config('services.turnstile.secret_key'),
                'response' => $request->input('cf-turnstile-response'),
                'remoteip' => $request->ip(),
            ]);

            if (!$turnstileResponse->successful() || !$turnstileResponse->json('success')) {
                return back()->withErrors(['cf-turnstile-response' => 'Security verification failed. Please try again.'])->withInput();
            }
        }

        $validated['ticket_id'] = 'TICKET-' . strtoupper(Str::random(8));

        $contact = Contact::create($validated);

        // Queue email notification
        Mail::to($contact->email)->queue(new ContactSuccess($contact));

        return back()->with('success', 'Thank you for contacting us! Your Ticket ID is ' . $contact->ticket_id . '. We will get back to you shortly.');
    }
}
