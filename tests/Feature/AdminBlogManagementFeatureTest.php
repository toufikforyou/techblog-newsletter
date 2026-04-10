<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Blog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminBlogManagementFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_published_blog(): void
    {
        $this->signInAsAdmin();

        $response = $this->post(route('admin.blogs.store'), [
            'title' => 'New Blog Post',
            'slug' => '',
            'excerpt' => 'Short excerpt',
            'content' => 'Long form article content',
            'featured_image' => 'https://example.com/image.jpg',
            'category' => 'Tech',
            'read_time' => 6,
            'status' => 'published',
        ]);

        $response->assertRedirect(route('admin.blogs.index'));

        $blog = Blog::where('title', 'New Blog Post')->first();
        $this->assertNotNull($blog);
        $this->assertSame('new-blog-post', $blog->slug);
        $this->assertSame('published', $blog->status);
        $this->assertNotNull($blog->published_at);
    }

    public function test_admin_can_update_blog(): void
    {
        $this->signInAsAdmin();

        $blog = Blog::create([
            'title' => 'Old Title',
            'slug' => 'old-title',
            'excerpt' => 'Old excerpt',
            'content' => 'Old content',
            'category' => 'Tech',
            'author_name' => 'Initial Author',
            'author_role' => 'Admin',
            'read_time' => 5,
            'status' => 'draft',
        ]);

        $response = $this->put(route('admin.blogs.update', $blog), [
            'title' => 'Updated Title',
            'slug' => 'updated-title',
            'excerpt' => 'Updated excerpt',
            'content' => 'Updated content',
            'featured_image' => 'https://example.com/new-image.jpg',
            'category' => 'AI',
            'read_time' => 10,
            'status' => 'published',
        ]);

        $response->assertRedirect(route('admin.blogs.index'));

        $this->assertDatabaseHas('blogs', [
            'id' => $blog->id,
            'title' => 'Updated Title',
            'slug' => 'updated-title',
            'status' => 'published',
            'category' => 'AI',
        ]);
    }

    public function test_admin_can_delete_blog(): void
    {
        $this->signInAsAdmin();

        $blog = Blog::create([
            'title' => 'Delete Me',
            'slug' => 'delete-me',
            'excerpt' => 'Will be deleted',
            'content' => 'Delete content',
            'category' => 'Tech',
            'author_name' => 'Author',
            'author_role' => 'Admin',
            'read_time' => 4,
            'status' => 'draft',
        ]);

        $response = $this->delete(route('admin.blogs.destroy', $blog));

        $response->assertRedirect(route('admin.blogs.index'));
        $this->assertDatabaseMissing('blogs', ['id' => $blog->id]);
    }

    private function signInAsAdmin(): Admin
    {
        $admin = Admin::create([
            'name' => 'Test Admin',
            'email' => 'admin-' . Str::random(8) . '@techappupdate.com',
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
