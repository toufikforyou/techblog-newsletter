<?php

namespace App\Http\Controllers;

use App\Models\Subscriber;
use App\Mail\SubscriptionConfirmation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;

class SubscribeController extends Controller
{
    public function store(Request $request)
    {
        try {
            $rules = [
                'email' => 'required|email|max:255',
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
                    return response()->json([
                        'success' => false,
                        'message' => 'Security verification failed. Please try again.',
                    ], 200);
                }
            }

            $email = $validated['email'];

            // Use firstOrCreate to reduce queries and improve performance
            $subscriber = Subscriber::firstOrCreate(
                ['email' => $email],
                [
                    'name' => null,
                    'status' => 'active',
                    'subscribed_at' => now(),
                ]
            );

            // If subscriber already exists and is active, return already subscribed message
            if (!$subscriber->wasRecentlyCreated && $subscriber->status === 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'You are already subscribed to our newsletter!',
                ], 200);
            }

            // If reactivating an unsubscribed user
            if (!$subscriber->wasRecentlyCreated && $subscriber->status === 'unsubscribed') {
                $subscriber->update([
                    'status' => 'active',
                    'subscribed_at' => now(),
                ]);
            }

            // Queue the email asynchronously - returns immediately
            Mail::to($subscriber->email)->queue(new SubscriptionConfirmation($subscriber));

            return response()->json([
                'success' => true,
                'message' => 'Subscription successful! Check your email for confirmation.',
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->errors()['email'][0] ?? 'Invalid email address.',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later.',
            ], 200);
        }
    }
}
