<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Comment;
use App\Models\Post;

class CommentController extends Controller
{
    //Get all approve comments of a post
    public function index(string $postId)
    {
        //Check if post exists
        $post = Post::find($postId);
        if (!$post) {
            return response()->json([
                'message' => 'Post not found',
            ], 404);
        }

        //get top level comments only(not replies)
        //parent_id = null meas it's a top-level comment not a reply
        $comments = Comment::with('user', 'replies.user')
            ->where('post_id', $postId)
            ->where('is_approved', true)
            ->whereNull('parent_id')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json($comments, 200);
    }

    //add comment to a post (must loggin)
    public function store(Request $request, string $postId)
    {
        //check post
        $post = Post::find($postId);

        if (!$post) {
            return response()->json([
                'message' => 'Post not found',
            ], 404);
        }

        //validate incomming data
        $request->validate([
            'content' => 'required|string',
            'parent_id' => 'nullable|exists:comments,id'
        ]);

        //create comment
        $comment = new Comment();
        $comment->post_id = $postId;
        $comment->user_id = $request->user()->id;
        $comment->content = $request->content;
        $comment->parent_id = $request->parent_id;
        $comment->is_approved = true;

        $comment->save();

        return response()->json($comment, 201);
    }

    //delete comment (only of that user)
    public function destroy(Request $request, string $id)
    {
        //find comment by id
        $comment = Comment::find($id);

        //check if comments exisst
        if (!$comment) {
            return response()->json([
                'message' => 'Comment not found',
            ], 404);
        }

        //check if this comment belongs to the logged in user
        if ($comment->user_id !== $request->user()->id) {
            return response()->json([
                'message' => " You do not have permission to delete this comment",
            ], 403);
        }

        //delete comment
        $comment->delete();

        return response()->json([
            'message' => 'Comment deleted successfully',
        ], 200);
    }
}
