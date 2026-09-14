<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->decimal('rating', 3, 2)->nullable();
            $table->integer('rating_count')->nullable();
            $table->integer('review_count')->nullable();
            $table->timestamp('snapshot_at')->useCurrent();
            $table->jsonb('diff')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_snapshots');
    }
};
