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
        Schema::create('parse_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default('queued');
            $table->integer('total_reviews')->default(0);
            $table->integer('parsed_reviews')->default(0);
            $table->text('error_message')->nullable();
            $table->smallInteger('attempts')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE parse_jobs ADD CONSTRAINT parse_jobs_status_check CHECK (status IN ('queued', 'running', 'done', 'failed'))");
        DB::statement('ALTER TABLE parse_jobs ADD CONSTRAINT parse_jobs_total_reviews_check CHECK (total_reviews >= 0)');
        DB::statement('ALTER TABLE parse_jobs ADD CONSTRAINT parse_jobs_parsed_reviews_check CHECK (parsed_reviews >= 0)');
        DB::statement('ALTER TABLE parse_jobs ADD CONSTRAINT parse_jobs_attempts_check CHECK (attempts >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('parse_jobs');
    }
};
