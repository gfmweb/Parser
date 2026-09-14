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
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('yandex_url');
            $table->string('yandex_id', 64)->nullable();
            $table->string('name')->nullable();
            $table->text('address')->nullable();
            $table->decimal('rating', 3, 2)->nullable();
            $table->integer('rating_count')->default(0);
            $table->integer('review_count')->default(0);
            $table->string('parse_status', 32)->default('pending');
            $table->timestamp('last_parsed_at')->nullable();
            $table->text('parse_error')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'yandex_url']);
        });

        DB::statement("ALTER TABLE organizations ADD CONSTRAINT organizations_parse_status_check CHECK (parse_status IN ('pending', 'parsing', 'done', 'failed'))");
        DB::statement('ALTER TABLE organizations ADD CONSTRAINT organizations_rating_check CHECK (rating IS NULL OR (rating >= 0 AND rating <= 5))');
        DB::statement('ALTER TABLE organizations ADD CONSTRAINT organizations_rating_count_check CHECK (rating_count >= 0)');
        DB::statement('ALTER TABLE organizations ADD CONSTRAINT organizations_review_count_check CHECK (review_count >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
