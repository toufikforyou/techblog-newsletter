@extends('layouts.app')

@section('title', 'Subscribe to TechNews Newsletter')

@section('content')
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-indigo-50/30 py-16 md:py-24">
        <div class="max-w-2xl mx-auto px-6">
            <div class="text-center mb-12">
                <h1 class="text-4xl md:text-5xl font-bold text-slate-900 mb-4">Subscribe to TechNews</h1>
                <p class="text-lg text-slate-600">Get weekly insights on software engineering, AI, and tech trends.</p>
            </div>

            <div class="bg-white rounded-2xl shadow-lg border border-slate-100 p-8 md:p-12">
                <form id="subscribeForm" class="space-y-6">
                    @csrf
                    <div>
                        <label for="email" class="block text-sm font-semibold text-slate-900 mb-3">Email Address</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="you@example.com"
                            class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition-all"
                            required
                        />
                    </div>

                    <!-- Cloudflare Turnstile -->
                    <div>
                        <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}" data-theme="light"></div>
                    </div>

                    <button
                        type="submit"
                        id="submitBtn"
                        class="w-full py-3 px-6 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl transition-colors shadow-lg shadow-blue-200"
                    >
                        Subscribe Now
                    </button>

                    <div id="messageBox" class="hidden p-4 rounded-xl"></div>
                </form>

                <div class="mt-8 pt-8 border-t border-slate-100 space-y-4">
                    <p class="text-sm text-slate-600">✓ No spam, unsubscribe anytime</p>
                    <p class="text-sm text-slate-600">✓ Join 50,000+ developers and engineers</p>
                    <p class="text-sm text-slate-600">✓ Get curated tech insights every week</p>
                </div>
            </div>
        </div>
    </div>

@push('subscription-scripts')
    <script>
        // Initialize subscription form
        initSubscriptionForm({
            formId: 'subscribeForm',
            buttonId: 'submitBtn',
            messageId: 'messageBox',
            apiUrl: '{{ route("subscribe.store") }}',
            loadingText: 'Subscribing...',
            theme: 'light'
        });
    </script>
@endpush
@endsection
