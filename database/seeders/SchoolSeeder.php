<?php

namespace Database\Seeders;

use App\Models\School;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;

class SchoolSeeder extends Seeder
{
    /**
     * Seeds the schools table from database/data/schools.json (extracted from the
     * "School name and address.xlsx" list — the JSON is the single source of truth,
     * so seeding has no dependency on the spreadsheet). Idempotent: clears and
     * re-inserts, so re-running always mirrors the JSON exactly.
     */
    public function run(): void
    {
        $path = database_path('data/schools.json');

        if (! is_readable($path)) {
            $this->command?->warn("schools.json not found at {$path} — skipping SchoolSeeder.");

            return;
        }

        $schools = json_decode(file_get_contents($path), true);

        if (! is_array($schools) || $schools === []) {
            $this->command?->warn('schools.json is empty or invalid — skipping SchoolSeeder.');

            return;
        }

        $now = now();
        $rows = [];

        foreach ($schools as $s) {
            $name = trim($s['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $rows[] = [
                'name'       => $name,
                'address'    => trim($s['address'] ?? '') ?: null,
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($rows) {
            School::query()->delete();

            foreach (array_chunk($rows, 200) as $chunk) {
                School::insert($chunk);
            }
        });

        $this->command?->info('Seeded '.count($rows).' schools from schools.json.');
    }
}
