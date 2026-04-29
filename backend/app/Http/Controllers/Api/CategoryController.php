<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Post;

class CategoryController extends Controller
{
    //Get all categories:
    public function index()
    {
        //get all categories from database:
        $categories = Category::all();

        return response()->json($categories, 200);
    }

    //get a single category by ID
    public function show(string $id)
    {
        //find category by ID
        $category = Category::find($id);

        //check if category exsits
        if (!$category) {
            return response()->json([
                'message' => 'Category not found'
            ], 404);
        }
        return response()->json($category, 200);
    }

    //get  all published posts in a category
    public function posts(string $id)
    {
        //find category by id
        $category = Category::find($id);

        //check if category exist
        if (!$category) {
            return response()->json([
                'message' => 'Category not found'
            ], 404);
        }

        //get all published post in this category
        $posts =  Post::with('author', 'tag')
            ->where('category_id', $id)
            ->where('status', 'published')
            ->orderedById('published_at', 'desc')
            ->paginate(10);

        return response()->json($posts, 200);
    }
}
