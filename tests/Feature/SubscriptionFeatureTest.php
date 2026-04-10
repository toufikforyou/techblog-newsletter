<?php

namespace Tests\Feature;

use App\Mail\SubscriptionConfirmation;
use App\Models\Subscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SubscriptionFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_subscriber_and_queues_confirmation_email(): void
    {
        Mail::fake();

        $response = $this->postJson(route('subscribe.store'), [
            'email' => 'newsubscriber@example.com',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('subscribers', [
            'email' => 'newsubscriber@example.com',
            'status' => 'active',
        ]);

        Mail::assertQueued(SubscriptionConfirmation::class);
    }

    public function test_it_rejects_duplicate_active_subscription(): void
    {
        Subscriber::create([
            'email' => 'already@example.com',
            'name' => 'Already',
            'status' => 'active',
            'subscribed_at' => now(),
        ]);

        $response = $this->postJson(route('subscribe.store'), [
            'email' => 'already@example.com',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'success' => false,
                'message' => 'You are already subscribed to our newsletter!',
            ]);
    }

    public function test_it_reactivates_unsubscribed_user(): void
    {
        Subscriber::create([
            'email' => 'reactivate@example.com',
            'name' => 'Old User',
            'status' => 'unsubscribed',
            'subscribed_at' => now()->subMonth(),
            'unsubscribed_at' => now()->subDays(10),
        ]);

        $response = $this->postJson(route('subscribe.store'), [
            'email' => 'reactivate@example.com',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('subscribers', [
            'email' => 'reactivate@example.com',
            'status' => 'active',
        ]);
    }
}
