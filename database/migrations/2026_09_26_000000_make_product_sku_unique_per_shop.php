<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Intentionally empty. PartsBox deduplication is handled in the scraper
        // by its source ID, without changing product-table constraints.
    }

    public function down(): void
    {
        // Intentionally empty.
    }
};
