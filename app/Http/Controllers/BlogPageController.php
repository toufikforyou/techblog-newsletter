<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BlogPageController extends Controller
{
    public function index(Request $request): View
    {
        $categories = Blog::where('status', 'published')
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        $selectedCategory = $request->query('category');

        $blogsQuery = Blog::where('status', 'published')
            ->whereNotNull('published_at')
            ->orderBy('published_at', 'desc');

        if (!empty($selectedCategory)) {
            $blogsQuery->where('category', $selectedCategory);
        }

        $blogs = $blogsQuery
            ->paginate(9)
            ->withQueryString();

        return view('blog', compact('blogs', 'categories', 'selectedCategory'));
    }
}
