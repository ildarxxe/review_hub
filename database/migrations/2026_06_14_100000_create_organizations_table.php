<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('source_url');
            $table->string('external_id')->nullable()->index();
            $table->string('name')->nullable();
            $table->decimal('rating', 2, 1)->nullable();
            $table->unsignedInteger('ratings_count')->nullable();
            $table->unsignedInteger('reviews_count')->nullable();
            $table->string('sync_status')->default('pending');
            $table->text('sync_error')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
