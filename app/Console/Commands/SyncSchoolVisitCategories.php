<?php

namespace App\Console\Commands;

use App\Models\School;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class SyncSchoolVisitCategories extends Command
{
    protected $signature = 'schools:sync-visit-categories
        {--source= : JSONL source path. Defaults to database/data/school_visit_schools.jsonl}
        {--chunk=1000 : Number of source rows to process per chunk}
        {--dry-run : Preview counts without updating schools}
        {--keep-samples : Do not delete source rows marked as sample/test/demo/dummy}
        {--keep-local-samples : Do not delete existing managed schools whose names look like sample/test/demo/dummy}';

    protected $description = 'Backfill school categories from the SchoolVisit JSONL source and remove sample/test source schools.';

    public function handle(): int
    {
        if (! Schema::hasColumn('schools', 'category')) {
            $this->error('The schools.category column does not exist. Run php artisan migrate first.');

            return self::FAILURE;
        }

        $source = $this->option('source') ?: database_path('data/school_visit_schools.jsonl');
        if (! is_readable($source)) {
            $this->error("SchoolVisit source file is not readable: {$source}");

            return self::FAILURE;
        }

        $chunkSize = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');
        $keepSamples = (bool) $this->option('keep-samples');
        $keepLocalSamples = (bool) $this->option('keep-local-samples');

        $seen = [];
        $rows = [];
        $sampleSchoolCodes = [];
        $sampleExternalIds = [];
        $stats = [
            'source_rows' => 0,
            'invalid_rows' => 0,
            'duplicate_rows' => 0,
            'sample_rows' => 0,
            'matched_schools' => 0,
            'missing_schools' => 0,
            'updated_schools' => 0,
            'deleted_samples' => 0,
        ];

        $handle = fopen($source, 'rb');

        while (($line = fgets($handle)) !== false) {
            $stats['source_rows']++;
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $school = json_decode($line, true);
            if (! is_array($school)) {
                $stats['invalid_rows']++;

                continue;
            }

            $schoolCode = $this->clean($school['school_code'] ?? null);
            $externalSchoolId = $this->clean($school['external_school_id'] ?? null);

            if ($this->isSampleSchool($school)) {
                if ($schoolCode !== null) {
                    $sampleSchoolCodes[] = $schoolCode;
                }

                if ($externalSchoolId !== null) {
                    $sampleExternalIds[] = $externalSchoolId;
                }

                $stats['sample_rows']++;

                continue;
            }

            if ($schoolCode === null) {
                $stats['invalid_rows']++;

                continue;
            }

            if (isset($seen[$schoolCode])) {
                $stats['duplicate_rows']++;

                continue;
            }

            $seen[$schoolCode] = true;

            $rows[] = [
                'school_code' => $schoolCode,
                'external_school_id' => $externalSchoolId,
                'category' => $this->category($school['category'] ?? null),
            ];

            if (count($rows) >= $chunkSize) {
                $this->syncRows($rows, $dryRun, $stats);
                $rows = [];
            }
        }

        fclose($handle);

        if ($rows !== []) {
            $this->syncRows($rows, $dryRun, $stats);
        }

        if (! $keepSamples) {
            $stats['deleted_samples'] = $this->deleteSamples($sampleSchoolCodes, $sampleExternalIds, $dryRun, $keepLocalSamples);
        }

        $mode = $dryRun ? 'Dry run complete' : 'School categories synced';
        $this->info($mode.'.');
        $this->table(
            ['Metric', 'Count'],
            collect($stats)->map(fn (int $count, string $metric): array => [$metric, $count])->values()->all(),
        );

        return self::SUCCESS;
    }

    private function syncRows(array $rows, bool $dryRun, array &$stats): void
    {
        $codes = array_column($rows, 'school_code');
        $schools = School::query()
            ->managed()
            ->whereIn('school_code', $codes)
            ->get(['id', 'school_code', 'external_school_id', 'category'])
            ->keyBy('school_code');

        foreach ($rows as $row) {
            /** @var School|null $school */
            $school = $schools->get($row['school_code']);

            if ($school === null) {
                $stats['missing_schools']++;

                continue;
            }

            $stats['matched_schools']++;

            if ($school->category === $row['category'] && $school->external_school_id === $row['external_school_id']) {
                continue;
            }

            $stats['updated_schools']++;

            if (! $dryRun) {
                $school->forceFill([
                    'external_school_id' => $row['external_school_id'],
                    'category' => $row['category'],
                ])->save();
            }
        }
    }

    private function deleteSamples(array $schoolCodes, array $externalSchoolIds, bool $dryRun, bool $keepLocalSamples): int
    {
        $schoolCodes = array_values(array_unique(array_filter($schoolCodes)));
        $externalSchoolIds = array_values(array_unique(array_filter($externalSchoolIds)));

        if ($schoolCodes === [] && $externalSchoolIds === [] && $keepLocalSamples) {
            return 0;
        }

        $query = School::query()
            ->managed()
            ->where(function ($query) use ($schoolCodes, $externalSchoolIds, $keepLocalSamples): void {
                if ($schoolCodes !== []) {
                    $query->whereIn('school_code', $schoolCodes);
                }

                if ($externalSchoolIds !== []) {
                    $method = $schoolCodes === [] ? 'whereIn' : 'orWhereIn';
                    $query->{$method}('external_school_id', $externalSchoolIds);
                }

                if (! $keepLocalSamples) {
                    $query->orWhere(function ($query): void {
                        foreach (['sample', 'test', 'dummy', 'demo'] as $word) {
                            $query
                                ->orWhereRaw('LOWER(name) = ?', [$word])
                                ->orWhereRaw('LOWER(name) LIKE ?', ["{$word} %"])
                                ->orWhereRaw('LOWER(name) LIKE ?', ["% {$word}"])
                                ->orWhereRaw('LOWER(name) LIKE ?', ["% {$word} %"]);
                        }
                    });
                }
            });

        $count = (clone $query)->count();

        if (! $dryRun) {
            $query->delete();
        }

        return $count;
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

    private function category(mixed $value): ?string
    {
        $category = $this->clean($value);

        return $category === null ? null : strtoupper($category);
    }

    private function isSampleSchool(array $school): bool
    {
        $name = strtolower((string) ($school['name'] ?? ''));
        $remarks = strtolower((string) ($school['blacklisted_remarks'] ?? ''));

        return preg_match('/\b(sample|test|dummy|demo)\b/', $name) === 1
            || preg_match('/\b(sample|test|dummy|demo)\b/', $remarks) === 1;
    }
}
