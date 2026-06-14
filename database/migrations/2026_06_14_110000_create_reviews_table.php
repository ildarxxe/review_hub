<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->unsignedInteger('position');
            $table->string('author');
            $table->unsignedTinyInteger('rating');
            $table->text('text')->nullable();
            $table->timestamp('published_at');
            $table->timestamps();

            $table->unique(['organization_id', 'external_id']);
            $table->unique(['organization_id', 'position']);
            $table->index(['organization_id', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
