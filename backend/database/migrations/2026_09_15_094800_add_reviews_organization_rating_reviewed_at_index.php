<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'CREATE INDEX reviews_organization_id_rating_reviewed_at_desc_index ON reviews (organization_id, rating, reviewed_at DESC)',
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS reviews_organization_id_rating_reviewed_at_desc_index');
    }
};
