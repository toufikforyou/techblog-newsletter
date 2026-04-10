<?php

namespace Tests\Feature;

use App\Mail\AdminOtpMail;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminAuthFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_start_registration_and_receive_otp_mail(): void
    {
        Mail::fake();

        $response = $this->post(route('admin.register.submit'), [
            'email' => 'owner@techappupdate.com',
        ]);

        $response
            ->assertOk()
            ->assertViewIs('auth.admin-verify-otp');

        $this->assertDatabaseHas('admins', [
            'email' => 'owner@techappupdate.com',
        ]);

        Mail::assertQueued(AdminOtpMail::class);
    }

    public function test_admin_can_verify_otp_and_get_authenticated(): void
    {
        $admin = Admin::create([
            'email' => 'verify@techappupdate.com',
            'otp' => '123456',
            'otp_expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->post(route('admin.verify-otp'), [
            'email' => 'verify@techappupdate.com',
            'otp' => '123456',
            'name' => 'Verified Admin',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated('admin');

        $admin->refresh();
        $this->assertNotNull($admin->email_verified_at);
        $this->assertNull($admin->otp);
    }

    public function test_admin_can_login_and_logout(): void
    {
        Http::fake([
            'https://challenges.cloudflare.com/*' => Http::response(['success' => true], 200),
        ]);

        Admin::create([
            'name' => 'Login Admin',
            'email' => 'login@techappupdate.com',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $loginResponse = $this->post(route('admin.login.submit'), [
            'email' => 'login@techappupdate.com',
            'password' => 'secret123',
        ]);

        $loginResponse->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated('admin');

        $logoutResponse = $this->post(route('admin.logout'));

        $logoutResponse->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
    }

    public function test_dashboard_requires_admin_authentication(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));
    }
}
