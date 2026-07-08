<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // The homepage "register" CTA/form section is replaced by a "contact" section.
        // Drop the stale row so it no longer appears in the admin Content editor;
        // HomepageSection::ensureDefaults() seeds the fresh "contact" row on next load.
        DB::table('homepage_sections')->where('key', 'register')->delete();

        // The FAQ "Talk to us" link pointed at the removed #register anchor. Repoint any
        // existing faq row to the new #contact anchor.
        $faq = DB::table('homepage_sections')->where('key', 'faq')->first();
        if ($faq) {
            $content = json_decode($faq->content, true) ?: [];
            if (($content['contact_url'] ?? null) === '#register') {
                $content['contact_url'] = '#contact';
                DB::table('homepage_sections')->where('id', $faq->id)->update([
                    'content'    => json_encode($content),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // No-op: ensureDefaults() will re-seed sections from code as needed.
    }
};
