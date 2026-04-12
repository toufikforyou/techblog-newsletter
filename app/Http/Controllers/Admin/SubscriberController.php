<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\NewsletterEmail;
use App\Models\Subscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class SubscriberController extends Controller
{
    public function index(Request $request)
    {
        $query = Subscriber::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $subscribers = $query->latest()->paginate(15);

        return view('admin.subscribers.index', compact('subscribers'));
    }

    public function create()
    {
        return view('admin.subscribers.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|unique:subscribers,email',
            'name' => 'nullable|string|max:255',
            'status' => 'required|in:active,unsubscribed,bounced',
        ]);

        Subscriber::create($validated);

        return redirect()->route('admin.subscribers.index')
            ->with('success', 'Subscriber added successfully.');
    }

    public function show(Subscriber $subscriber)
    {
        return view('admin.subscribers.show', compact('subscriber'));
    }

    public function edit(Subscriber $subscriber)
    {
        return view('admin.subscribers.edit', compact('subscriber'));
    }

    public function update(Request $request, Subscriber $subscriber)
    {
        $validated = $request->validate([
            'email' => 'required|email|unique:subscribers,email,' . $subscriber->id,
            'name' => 'nullable|string|max:255',
            'status' => 'required|in:active,unsubscribed,bounced',
        ]);

        if ($validated['status'] === 'unsubscribed' && $subscriber->status !== 'unsubscribed') {
            $validated['unsubscribed_at'] = now();
        }

        $subscriber->update($validated);

        return redirect()->route('admin.subscribers.index')
            ->with('success', 'Subscriber updated successfully.');
    }

    public function destroy(Subscriber $subscriber)
    {
        // If already unsubscribed, fully delete the record
        if ($subscriber->status === 'unsubscribed') {
            $subscriber->delete();
            return redirect()->route('admin.subscribers.index')
                ->with('success', 'Unsubscribed email deleted.');
        }

        // Toggle between bounced and active status
        if ($subscriber->status === 'bounced') {
            $subscriber->update([
                'status' => 'active',
            ]);
            $message = 'Subscriber activated.';
        } else {
            $subscriber->update([
                'status' => 'bounced',
            ]);
            $message = 'Subscriber marked as bounced.';
        }

        return redirect()->route('admin.subscribers.index')
            ->with('success', $message);
    }

    public function composeNewsletter()
    {
        $activeSubscribersCount = Subscriber::where('status', 'active')->count();
        $totalSubscribersCount = Subscriber::count();
        $bouncedSubscribersCount = Subscriber::where('status', 'bounced')->count();

        return view('admin.newsletter.compose', compact(
            'activeSubscribersCount',
            'totalSubscribersCount',
            'bouncedSubscribersCount'
        ));
    }

    public function sendNewsletter(Request $request)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'recipient_type' => 'required|in:active,all,test',
            'test_emails' => 'nullable|string',
        ]);

        if ($validated['recipient_type'] === 'test') {
            $emails = collect(preg_split('/[\s,]+/', (string) ($validated['test_emails'] ?? '')))
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (empty($emails)) {
                return back()
                    ->withInput()
                    ->withErrors(['test_emails' => 'Please enter at least one valid email address.']);
            }

            $invalidEmails = collect($emails)
                ->filter(fn (string $email) => ! filter_var($email, FILTER_VALIDATE_EMAIL));

            if ($invalidEmails->isNotEmpty()) {
                return back()
                    ->withInput()
                    ->withErrors(['test_emails' => 'Please enter valid email address(es).']);
            }

            $this->queueNewsletterEmails(
                array_map(fn ($email) => [
                    'email' => $email,
                    'name' => 'Test Recipient',
                ], $emails),
                $validated['subject'],
                $validated['body'],
                false
            );

            return back()
                ->withInput([
                    'subject' => $validated['subject'],
                    'body' => $validated['body'],
                    'recipient_type' => 'test',
                    'test_emails' => implode(', ', $emails),
                ])
                ->with('success', 'Test email sent successfully to ' . count($emails) . ' recipient(s).');
        }

        $subscribers = $this->getRecipientsByType($validated['recipient_type']);

        if ($subscribers->isEmpty()) {
            return redirect()->back()
                ->with('error', 'No subscribers found to send email to.');
        }

        $this->queueNewsletterEmails(
            $subscribers->map(fn ($subscriber) => [
                'email' => $subscriber->email,
                'name' => $subscriber->name ?? 'Subscriber',
            ])->all(),
            $validated['subject'],
            $validated['body']
        );

        return redirect()->route('admin.newsletter.compose')
            ->with('success', 'Email sent successfully to ' . $subscribers->count() . ' subscriber(s).');
    }

    public function sendTestNewsletter(Request $request)
    {
        $request->merge(['recipient_type' => 'test']);

        return $this->sendNewsletter($request);
    }

    private function getRecipientsByType(string $recipientType)
    {
        return $recipientType === 'active'
            ? Subscriber::where('status', 'active')->get()
            : Subscriber::where('status', '!=', 'bounced')->get();
    }

    private function queueNewsletterEmails(array $recipients, string $subject, string $body, bool $queue = true): void
    {
        $authorName = auth('admin')->user()->name ?? auth()->user()->name ?? config('mail.from.name', 'TechNews Team');

        foreach ($recipients as $recipient) {
            $mailable = new NewsletterEmail(
                $subject,
                $body,
                $recipient['name'] ?? 'Subscriber',
                $authorName
            );

            if ($queue) {
                Mail::to($recipient['email'])->queue($mailable);
            } else {
                Mail::to($recipient['email'])->send($mailable);
            }
        }
    }
}
