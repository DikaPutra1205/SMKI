<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Check if supabase_realtime publication exists (e.g. Supabase Postgres instance)
            $pub = DB::select("SELECT 1 FROM pg_publication WHERE pubname = 'supabase_realtime'");
            if (! empty($pub)) {
                try {
                    DB::statement('ALTER PUBLICATION supabase_realtime ADD TABLE notifications;');
                } catch (Throwable $e) {
                    // Ignore if already added
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $pub = DB::select("SELECT 1 FROM pg_publication WHERE pubname = 'supabase_realtime'");
            if (! empty($pub)) {
                try {
                    DB::statement('ALTER PUBLICATION supabase_realtime DROP TABLE notifications;');
                } catch (Throwable $e) {
                    // Ignore if not present
                }
            }
        }
    }
};
