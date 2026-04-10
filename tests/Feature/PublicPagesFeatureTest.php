<?php

namespace Tests\Feature;

use App\Models\Blog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPagesFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_are_accessible(): void
    {
        $blog = Blog::create([
            'title' => 'Published Blog',
            'slug' => 'published-blog',
            'excerpt' => 'Excerpt',
            'content' => 'Content body',
            'category' => 'Tech',
            'author_name' => 'Admin',
            'author_role' => 'Editor',
            'read_time' => 5,
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->get('/')->assertOk();
        $this->get('/about')->assertOk();
        $this->get('/contact')->assertOk();
        $this->get('/subscribe')->assertOk();
        $this->get(route('blog.index'))->assertOk();
        $this->get(route('blog.show', $blog))->assertOk();
    }

    public function test_unpublished_blog_detail_returns_not_found(): void
    {
        $draftBlog = Blog::create([
            'title' => 'Draft Blog',
            'slug' => 'draft-blog',
            'excerpt' => 'Excerpt',
            'content' => 'Draft content',
            'category' => 'Tech',
            'author_name' => 'Admin',
            'author_role' => 'Editor',
            'read_time' => 4,
            'status' => 'draft',
        ]);

        $this->get(route('blog.show', $draftBlog))->assertNotFound();
    }
}
