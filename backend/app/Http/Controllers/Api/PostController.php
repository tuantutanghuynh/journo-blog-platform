<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PostController extends Controller
{
    // Get all published posts
    public function index()
    {
        // Get 10 posts per page, with author, category and tags info
        $posts = Post::with('author', 'category', 'tags')
            ->where('status', 'published')
            ->orderBy('published_at', 'desc')
            ->paginate(10);

        return response()->json($posts, 200);
    }

    // Get a single post by ID
    public function show(string $id)
    {
        // Find post by ID
        $post = Post::with('author', 'category', 'tags', 'comments')
            ->where('status', 'published')
            ->find($id);

        // Check if post exists
        if (!$post) {
            return response()->json([
                'message' => 'Post not found',
            ], 404);
        }

        return response()->json($post, 200);
    }

    // Create a new post (must be logged in)
    public function store(Request $request)
    {
        // Step 1: Validate incoming data
        $request->validate([
            'title'       => 'required|string|max:255',
            'content'     => 'required|string',
            'excerpt'     => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'status'      => 'nullable|in:draft,published',
        ]);

        // Step 2: Create new post
        $post = new Post();
        $post->user_id     = $request->user()->id;
        $post->category_id = $request->category_id;
        $post->title       = $request->title;
        $post->slug        = Str::slug($request->title); // "Hello World" → "hello-world"
        $post->content     = $request->content;
        $post->excerpt     = $request->excerpt;
        $post->status      = $request->status ?? 'draft'; // default to draft if not provided

        // If status is published, save the publish time
        if ($post->status === 'published') {
            $post->published_at = now();
        }

        $post->save();

        return response()->json($post, 201);
    }

    // Update a post (can only edit your own posts)
    public function update(Request $request, string $id)
    {
        // Step 1: Find post by ID
        $post = Post::find($id);

        // Step 2: Check if post exists
        if (!$post) {
            return response()->json([
                'message' => 'Post not found',
            ], 404);
        }

        // Step 3: Check if this post belongs to the logged in user
        if ($post->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You do not have permission to edit this post',
            ], 403);
        }

        // Step 4: Validate incoming data
        $request->validate([
            'title'       => 'nullable|string|max:255',
            'content'     => 'nullable|string',
            'excerpt'     => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'status'      => 'nullable|in:draft,published',
        ]);

        // Step 5: Update each field if it was sent in the request
        if ($request->title) {
            $post->title = $request->title;
            $post->slug  = Str::slug($request->title);
        }

        if ($request->content) {
            $post->content = $request->content;
        }

        if ($request->excerpt) {
            $post->excerpt = $request->excerpt;
        }

        if ($request->category_id) {
            $post->category_id = $request->category_id;
        }

        if ($request->status) {
            $post->status = $request->status;

            // If just switched to published, save the publish time
            if ($request->status === 'published' && !$post->published_at) {
                $post->published_at = now();
            }
        }

        $post->save();

        return response()->json($post, 200);
    }

    // Delete a post (can only delete your own posts)
    public function destroy(Request $request, string $id)
    {
        // Step 1: Find post by ID
        $post = Post::find($id);

        // Step 2: Check if post exists
        if (!$post) {
            return response()->json([
                'message' => 'Post not found',
            ], 404);
        }

        // Step 3: Check if this post belongs to the logged in user
        if ($post->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You do not have permission to delete this post',
            ], 403);
        }

        // Step 4: Delete the post
        $post->delete();

        return response()->json([
            'message' => 'Post deleted successfully',
        ], 200);
    }
}
