<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Tag;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tag = ['PHP', 'Laravel', 'JavaScript', 'Vue.js', 'React', 'CSS', 'HTML', 'MySQL', 'PostgreSQL', 'MongoDB'];

        foreach ($tag as $name){
            Tag::create([
                'name' => $name,
                'slug' => strtolower(str_replace([' ', '.'],'-', $name)),
            ]);
        }
    }
}