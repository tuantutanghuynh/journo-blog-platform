<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PostController extends Controller
{
    // Lấy danh sách tất cả bài viết đã published
    public function index()
    {
        // Lấy 10 bài mỗi trang, kèm thông tin tác giả, danh mục, tags
        $posts = Post::with('author', 'category', 'tags')
            ->where('status', 'published')
            ->orderBy('published_at', 'desc')
            ->paginate(10);

        return response()->json($posts, 200);
    }

    // Xem chi tiết 1 bài viết theo ID
    public function show(string $id)
    {
        // Tìm bài viết theo ID, nếu không có thì tự trả về 404
        $post = Post::with('author', 'category', 'tags', 'comments')
            ->where('status', 'published')
            ->find($id);

        // Kiểm tra bài viết có tồn tại không
        if (!$post) {
            return response()->json([
                'message' => 'Không tìm thấy bài viết',
            ], 404);
        }

        return response()->json($post, 200);
    }

    // Tạo bài viết mới (phải đăng nhập)
    public function store(Request $request)
    {
        // Bước 1: Kiểm tra dữ liệu gửi lên
        $request->validate([
            'title'       => 'required|string|max:255',
            'content'     => 'required|string',
            'excerpt'     => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'status'      => 'nullable|in:draft,published',
        ]);

        // Bước 2: Tạo bài viết mới
        $post = new Post();
        $post->user_id     = $request->user()->id;
        $post->category_id = $request->category_id;
        $post->title       = $request->title;
        $post->slug        = Str::slug($request->title); // "Hello World" → "hello-world"
        $post->content     = $request->content;
        $post->excerpt     = $request->excerpt;
        $post->status      = $request->status ?? 'draft'; // mặc định là draft nếu không truyền

        // Nếu status là published thì lưu thời gian đăng
        if ($post->status === 'published') {
            $post->published_at = now();
        }

        $post->save();

        return response()->json($post, 201);
    }

    // Cập nhật bài viết (chỉ được sửa bài của chính mình)
    public function update(Request $request, string $id)
    {
        // Bước 1: Tìm bài viết theo ID
        $post = Post::find($id);

        // Bước 2: Kiểm tra bài viết có tồn tại không
        if (!$post) {
            return response()->json([
                'message' => 'Không tìm thấy bài viết',
            ], 404);
        }

        // Bước 3: Kiểm tra bài viết có phải của user đang đăng nhập không
        if ($post->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Bạn không có quyền sửa bài viết này',
            ], 403);
        }

        // Bước 4: Kiểm tra dữ liệu gửi lên
        $request->validate([
            'title'       => 'nullable|string|max:255',
            'content'     => 'nullable|string',
            'excerpt'     => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'status'      => 'nullable|in:draft,published',
        ]);

        // Bước 5: Cập nhật từng field nếu có gửi lên
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

            // Nếu vừa chuyển sang published thì lưu thời gian đăng
            if ($request->status === 'published' && !$post->published_at) {
                $post->published_at = now();
            }
        }

        $post->save();

        return response()->json($post, 200);
    }

    // Xóa bài viết (chỉ được xóa bài của chính mình)
    public function destroy(Request $request, string $id)
    {
        // Bước 1: Tìm bài viết theo ID
        $post = Post::find($id);

        // Bước 2: Kiểm tra bài viết có tồn tại không
        if (!$post) {
            return response()->json([
                'message' => 'Không tìm thấy bài viết',
            ], 404);
        }

        // Bước 3: Kiểm tra bài viết có phải của user đang đăng nhập không
        if ($post->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Bạn không có quyền xóa bài viết này',
            ], 403);
        }

        // Bước 4: Xóa bài viết
        $post->delete();

        return response()->json([
            'message' => 'Xóa bài viết thành công',
        ], 200);
    }
}
