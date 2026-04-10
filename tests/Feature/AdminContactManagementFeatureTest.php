<?php

namespace Tests\Feature;

use App\Mail\ContactReply;
use App\Models\Admin;
use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminContactManagementFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_reply_to_contact_and_mark_it_resolved(): void
    {
        $this->signInAsAdmin();
        Mail::fake();

        $contact = Contact::create([
            'ticket_id' => 'TICKET-ABC12345',
            'first_name' => 'John',
            'last_name' => 'Smith',
            'email' => 'john@example.com',
            'subject' => 'Assistance needed',
            'message' => 'Please help me with an issue.',
            'status' => 'open',
        ]);

        $response = $this->post(route('admin.contacts.reply', $contact), [
            'reply_message' => 'We have reviewed your request and resolved it.',
        ]);

        $response->assertRedirect(route('admin.contacts.index'));

        $this->assertDatabaseHas('contacts', [
            'id' => $contact->id,
            'status' => 'resolved',
        ]);

        Mail::assertQueued(ContactReply::class);
    }

    public function test_admin_can_update_contact_status_and_notes(): void
    {
        $this->signInAsAdmin();

        $contact = Contact::create([
            'ticket_id' => 'TICKET-XYZ67890',
            'first_name' => 'Alice',
            'last_name' => 'Jones',
            'email' => 'alice@example.com',
            'subject' => 'General inquiry',
            'message' => 'I have a follow-up question.',
            'status' => 'open',
        ]);

        $this->patch(route('admin.contacts.status', $contact), [
            'status' => 'in_progress',
            'admin_notes' => 'Investigating now.',
        ])->assertRedirect();

        $this->assertDatabaseHas('contacts', [
            'id' => $contact->id,
            'status' => 'in_progress',
            'admin_notes' => 'Investigating now.',
        ]);
    }

    public function test_admin_can_delete_contact(): void
    {
        $this->signInAsAdmin();

        $contact = Contact::create([
            'ticket_id' => 'TICKET-DELETE1',
            'first_name' => 'Delete',
            'last_name' => 'Me',
            'email' => 'delete@example.com',
            'subject' => 'Delete request',
            'message' => 'Delete this contact.',
            'status' => 'open',
        ]);

        $this->delete(route('admin.contacts.destroy', $contact))
            ->assertRedirect(route('admin.contacts.index'));

        $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
    }

    private function signInAsAdmin(): Admin
    {
        $admin = Admin::create([
            'name' => 'Contact Admin',
            'email' => 'contact-admin-' . Str::random(8) . '@techappupdate.com',
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
