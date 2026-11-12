<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Admin-typed schedule times used to be saved exactly as typed (India wall time) in
 * columns the app reads as UTC, so every exam really opened and closed 5 h 30 min
 * late. The app now stores the true instant (App\Support\LocalTime), so the values
 * typed before this change are converted once: "09:00" typed for India becomes
 * 03:30 UTC, which is 9:00 AM IST.
 *
 * Only the admin-typed columns are touched. System timestamps (attempt start,
 * deadline, submission, payments, created_at …) were always real UTC instants.
 *
 * Deploy this outside exam hours: a window that is "live" only because of the old
 * 5 h 30 min shift closes at its configured time once this runs. Attempts already
 * in progress keep their stored deadline.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'exams' => ['starts_at', 'ends_at', 'result_release_at'],
        'exam_enrollments' => ['window_starts_at', 'window_ends_at'],
    ];

    public function up(): void
    {
        $this->convert(fn (string $value, string $local) => Carbon::parse($value, $local)->setTimezone('UTC'));
    }

    public function down(): void
    {
        $this->convert(fn (string $value, string $local) => Carbon::parse($value, 'UTC')->setTimezone($local));
    }

    private function convert(callable $shift): void
    {
        $local = config('app.display_timezone') ?: 'Asia/Kolkata';

        DB::transaction(function () use ($shift, $local) {
            foreach (self::COLUMNS as $table => $columns) {
                DB::table($table)
                    ->where(fn ($q) => collect($columns)->each(fn ($column) => $q->orWhereNotNull($column)))
                    ->orderBy('id')
                    ->select(['id', ...$columns])
                    ->chunkById(500, function ($rows) use ($table, $columns, $shift, $local) {
                        foreach ($rows as $row) {
                            $update = [];
                            foreach ($columns as $column) {
                                if ($row->{$column} !== null) {
                                    $update[$column] = $shift($row->{$column}, $local)->format('Y-m-d H:i:s');
                                }
                            }
                            // Query builder: updated_at is left alone on purpose.
                            DB::table($table)->where('id', $row->id)->update($update);
                        }
                    });
            }
        });
    }
};
