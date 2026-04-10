<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminProfileFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_profile_details_and_password(): void
    {
        $admin = $this->signInAsAdmin();

        $response = $this->post(route('admin.profile.update'), [
            'name' => 'Updated Admin',
            'email' => 'updated-admin@techappupdate.com',
            'role' => 'super_admin',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response
            ->assertRedirect()
            ->assertSessionHas('success');

        $admin->refresh();

        $this->assertSame('Updated Admin', $admin->name);
        $this->assertSame('updated-admin@techappupdate.com', $admin->email);
        $this->assertSame('super_admin', $admin->role);
        $this->assertTrue(Hash::check('newpassword123', $admin->password));
    }

    private function signInAsAdmin(): Admin
    {
        $admin = Admin::create([
            'name' => 'Profile Admin',
            'email' => 'profile-admin-' . Str::random(8) . '@techappupdate.com',
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
