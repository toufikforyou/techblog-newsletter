<?php

namespace Tests\Feature;

use App\Mail\NewsletterEmail;
use App\Models\Admin;
use App\Models\Subscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminSubscriberManagementFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_add_subscriber(): void
    {
        $this->signInAsAdmin();

        $response = $this->post(route('admin.subscribers.store'), [
            'email' => 'new@subscriber.com',
            'name' => 'New Subscriber',
            'status' => 'active',
        ]);

        $response->assertRedirect(route('admin.subscribers.index'));
        $this->assertDatabaseHas('subscribers', [
            'email' => 'new@subscriber.com',
            'status' => 'active',
        ]);
    }

    public function test_destroy_toggles_active_and_bounced_status(): void
    {
        $this->signInAsAdmin();

        $subscriber = Subscriber::create([
            'email' => 'toggle@subscriber.com',
            'name' => 'Toggle User',
            'status' => 'active',
            'subscribed_at' => now(),
        ]);

        $this->delete(route('admin.subscribers.destroy', $subscriber))
            ->assertRedirect(route('admin.subscribers.index'));

        $this->assertDatabaseHas('subscribers', [
            'id' => $subscriber->id,
            'status' => 'bounced',
        ]);

        $this->delete(route('admin.subscribers.destroy', $subscriber->fresh()))
            ->assertRedirect(route('admin.subscribers.index'));

        $this->assertDatabaseHas('subscribers', [
            'id' => $subscriber->id,
            'status' => 'active',
        ]);
    }

    public function test_destroy_deletes_unsubscribed_subscriber(): void
    {
        $this->signInAsAdmin();

        $subscriber = Subscriber::create([
            'email' => 'delete@subscriber.com',
            'name' => 'Delete User',
            'status' => 'unsubscribed',
            'subscribed_at' => now()->subDays(20),
            'unsubscribed_at' => now()->subDay(),
        ]);

        $this->delete(route('admin.subscribers.destroy', $subscriber))
            ->assertRedirect(route('admin.subscribers.index'));

        $this->assertDatabaseMissing('subscribers', ['id' => $subscriber->id]);
    }

    public function test_admin_can_send_newsletter_to_active_subscribers_only(): void
    {
        $this->signInAsAdmin();
        Mail::fake();

        Subscriber::create([
            'email' => 'active@subscriber.com',
            'name' => 'Active User',
            'status' => 'active',
            'subscribed_at' => now(),
        ]);

        Subscriber::create([
            'email' => 'bounced@subscriber.com',
            'name' => 'Bounced User',
            'status' => 'bounced',
            'subscribed_at' => now(),
        ]);

        $response = $this->post(route('admin.newsletter.send'), [
            'subject' => 'Weekly Update',
            'body' => 'This is the newsletter body.',
            'recipient_type' => 'active',
        ]);

        $response->assertRedirect(route('admin.subscribers.index'));

        Mail::assertQueued(NewsletterEmail::class, 1);
        Mail::assertQueued(NewsletterEmail::class, function (NewsletterEmail $mail) {
            return $mail->hasTo('active@subscriber.com');
        });
        Mail::assertNotQueued(NewsletterEmail::class, function (NewsletterEmail $mail) {
            return $mail->hasTo('bounced@subscriber.com');
        });
    }

    private function signInAsAdmin(): Admin
    {
        $admin = Admin::create([
            'name' => 'Subscriber Admin',
            'email' => 'subscriber-admin-' . Str::random(8) . '@techappupdate.com',
            'password' => 'password123',
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $admin->forceFill(['remember_token' => Str::random(60)])->save();

        $this->actingAs($admin, 'admin');
        $this->withSession([
            'admin_session_token' => $admin->remember_token,
        ]);

        return $admin;
    }
}
