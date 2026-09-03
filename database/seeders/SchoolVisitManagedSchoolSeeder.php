<?php

namespace Database\Seeders;

use App\Models\School;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SchoolVisitManagedSchoolSeeder extends Seeder
{
    /**
     * Seeds managed school records from database/data/school_visit_schools.jsonl.
     *
     * The JSON Lines file is generated once from SchoolVisit.xlsx and kept as the
     * lightweight source for repeatable local/production seeding. It is streamed
     * row-by-row so the import works under the app's normal PHP memory limit.
     * Re-running this seeder only refreshes imported identity/location fields;
     * operator-entered district, email, phone and coordinator data are untouched.
     */
    public function run(): void
    {
        $path = database_path('data/school_visit_schools.jsonl');

        if (! is_readable($path)) {
            $this->command?->warn("school_visit_schools.jsonl not found at {$path} - skipping SchoolVisitManagedSchoolSeeder.");

            return;
        }

        $now = now();
        $seen = [];
        $rows = [];
        $skipped = 0;
        $imported = 0;
        $lineNumber = 0;
        $handle = fopen($path, 'rb');

        while (($line = fgets($handle)) !== false) {
            $lineNumber++;
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $school = json_decode($line, true);

            if (! is_array($school)) {
                $skipped++;
                $this->command?->warn("Skipped invalid JSON on line {$lineNumber}.");

                continue;
            }

            $schoolCode = $this->clean($school['school_code'] ?? null);
            $name = $this->clean($school['name'] ?? null);
            $state = $this->clean($school['state'] ?? null);

            if ($schoolCode === null || $name === null || $state === null || isset($seen[$schoolCode])) {
                $skipped++;

                continue;
            }

            $seen[$schoolCode] = true;

            $rows[] = [
                'external_school_id' => $this->clean($school['external_school_id'] ?? null),
                'school_code' => $schoolCode,
                'name' => $name,
                'address' => $this->clean($school['address'] ?? null),
                'state' => $state,
                'city' => $this->clean($school['city'] ?? null),
                'pin_code' => $this->pinCode($school['pin_code'] ?? null),
                'is_active' => ! (bool) ($school['is_blocked'] ?? false),
                'is_managed' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) >= 1000) {
                $imported += $this->upsert($rows);
                $rows = [];
            }
        }

        fclose($handle);

        if ($rows !== []) {
            $imported += $this->upsert($rows);
        }

        $this->command?->info("Seeded/updated {$imported} managed schools from school_visit_schools.jsonl.");

        if ($skipped > 0) {
            $this->command?->warn("Skipped {$skipped} invalid or duplicate source rows.");
        }
    }

    private function clean(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '' || strtolower($value) === 'null') {
            return null;
        }

        return preg_replace('/\s+/', ' ', $value);
    }

    private function pinCode(mixed $value): ?string
    {
        $pin = $this->clean($value);

        return $pin !== null && preg_match('/\A\d{6}\z/', $pin) ? $pin : null;
    }

    private function upsert(array $rows): int
    {
        DB::table('schools')->upsert(
            $rows,
            ['school_code'],
            [
                'external_school_id',
                'name',
                'address',
                'state',
                'city',
                'pin_code',
                'is_active',
                'is_managed',
                'updated_at',
            ],
        );

        return count($rows);
    }
}
