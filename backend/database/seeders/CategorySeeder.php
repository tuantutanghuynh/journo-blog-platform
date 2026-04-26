<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Category::create([
            'name' => 'Technology',
            'slug' => 'technology',
            'description' => 'Latest news and updates in technology.',
            'color' => '#3B82F6'
        ]);

        Category::create([
            'name' => 'Health',
            'slug' => 'health',
            'description' => 'Health tips, news, and advice.',
            'color' => '#10B981'
        ]);

        Category::create([
            'name' => 'Travel',
            'slug' => 'travel',
            'description' => 'Travel guides, tips, and destination reviews.',
            'color' => '#F59E0B'
        ]);
    }
}