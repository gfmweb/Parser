<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('yandex_review_id', 128);
            $table->string('author_name')->nullable();
            $table->text('author_url')->nullable();
            $table->smallInteger('rating')->nullable();
            $table->text('text')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['organization_id', 'yandex_review_id']);
        });

        DB::statement('CREATE INDEX reviews_organization_id_reviewed_at_desc_index ON reviews (organization_id, reviewed_at DESC)');
        DB::statement('ALTER TABLE reviews ADD CONSTRAINT reviews_rating_check CHECK (rating IS NULL OR (rating >= 1 AND rating <= 5))');
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
