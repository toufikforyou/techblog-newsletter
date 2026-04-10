<?php

namespace Tests\Feature;

use App\Mail\ContactSuccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_form_submits_and_queues_confirmation_mail(): void
    {
        Mail::fake();

        $response = $this->post(route('contact.store'), [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'subject' => 'Need support',
            'message' => 'Please help me with my issue.',
        ]);

        $response
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('contacts', [
            'email' => 'jane@example.com',
            'subject' => 'Need support',
            'status' => 'open',
        ]);

        Mail::assertQueued(ContactSuccess::class);
    }

    public function test_contact_form_requires_mandatory_fields(): void
    {
        $response = $this->from(route('contact'))->post(route('contact.store'), []);

        $response
            ->assertRedirect(route('contact'))
            ->assertSessionHasErrors([
                'first_name',
                'last_name',
                'email',
                'subject',
                'message',
            ]);
    }
}
