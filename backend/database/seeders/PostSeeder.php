<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Post;
use App\Models\User;
use App\Models\Category;
use App\Models\Tag;

class PostSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $author = User::where('email', 'tuantu@journo.com')->first();
        $tech   = Category::where('slug', 'technology')->first();
        $tags   = Tag::whereIn('slug', ['laravel', 'php'])->get();

        $post = Post::create([
            'user_id'      => $author->id,
            'category_id'  => $tech->id,
            'title'        => 'Getting Started with Laravel',
            'slug'         => 'getting-started-with-laravel',
            'excerpt'      => 'A beginner guide to Laravel framework.',
            'content'      => 'Laravel is a PHP framework that makes web development enjoyable...',
            'status'       => 'published',
            'published_at' => now(),
        ]);

        $post->tags()->attach($tags);
    }
}
