<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->replaceInColumn('receipt_settings', 'receipt_prefix', 'NOH', 'NEO');
        $this->replaceInColumn('receipts', 'receipt_number', 'NOH', 'NEO');
    }

    public function down(): void
    {
        $this->replaceInColumn('receipt_settings', 'receipt_prefix', 'NEO', 'NOH');
        $this->replaceInColumn('receipts', 'receipt_number', 'NEO', 'NOH');
    }

    private function replaceInColumn(string $table, string $column, string $from, string $to): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)
            ->where($column, 'like', "%{$from}%")
            ->orderBy('id')
            ->select(['id', $column])
            ->chunkById(100, function ($rows) use ($table, $column, $from, $to): void {
                foreach ($rows as $row) {
                    DB::table($table)
                        ->where('id', $row->id)
                        ->update([
                            $column => str_replace($from, $to, (string) $row->{$column}),
                        ]);
                }
            });
    }
};
