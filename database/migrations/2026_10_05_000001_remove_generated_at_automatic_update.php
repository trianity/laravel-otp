<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove database-managed TIMESTAMP behaviour while preserving stored values.
     */
    public function up(): void
    {
        Schema::table('otps', function (Blueprint $table) {
            $table->dateTime('generated_at')->change();
        });
    }

    /**
     * Intentionally irreversible: restoring TIMESTAMP could reintroduce an
     * implicit ON UPDATE clause on affected MySQL or MariaDB configurations.
     */
    public function down(): void
    {
        // Keep the safe DATETIME definition on rollback.
    }
};
