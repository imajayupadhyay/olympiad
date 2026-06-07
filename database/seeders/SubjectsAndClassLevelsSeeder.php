<?php

namespace Database\Seeders;

use App\Models\ClassLevel;
use App\Models\Subject;
use Illuminate\Database\Seeder;

class SubjectsAndClassLevelsSeeder extends Seeder
{
    public function run(): void
    {
        $subjects = [
            ['name' => 'Mathematics',    'slug' => 'mathematics',    'icon' => '📐', 'color' => '#1E3A8A', 'sort_order' => 1],
            ['name' => 'Science',        'slug' => 'science',        'icon' => '🔬', 'color' => '#16A34A', 'sort_order' => 2],
            ['name' => 'English',        'slug' => 'english',        'icon' => '📖', 'color' => '#7C3AED', 'sort_order' => 3],
            ['name' => 'Computer Science','slug' => 'computer',      'icon' => '💻', 'color' => '#0891B2', 'sort_order' => 4],
            ['name' => 'General Knowledge','slug' => 'gk',           'icon' => '🌍', 'color' => '#D97706', 'sort_order' => 5],
            ['name' => 'Logical Reasoning','slug' => 'reasoning',    'icon' => '🧩', 'color' => '#EC4899', 'sort_order' => 6],
            ['name' => 'Hindi',          'slug' => 'hindi',          'icon' => '🅗',  'color' => '#EA580C', 'sort_order' => 7],
            ['name' => 'Social Science', 'slug' => 'social_science', 'icon' => '🏛️', 'color' => '#0D9488', 'sort_order' => 8],
        ];

        foreach ($subjects as $s) {
            Subject::updateOrCreate(['slug' => $s['slug']], $s);
        }

        for ($level = 1; $level <= 12; $level++) {
            ClassLevel::updateOrCreate(
                ['level' => $level],
                ['label' => "Class $level", 'sort_order' => $level, 'is_active' => true]
            );
        }
    }
}
