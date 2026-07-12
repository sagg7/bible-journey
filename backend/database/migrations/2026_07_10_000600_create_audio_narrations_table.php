<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audio_narrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reading_block_id')->constrained('reading_blocks')->cascadeOnDelete();
            $table->foreignId('translation_id')->constrained('translations')->cascadeOnDelete();
            $table->string('provider', 40)->default('gemini');
            $table->string('voice', 80)->default('Charon');
            $table->string('model', 120);
            $table->string('locale', 12)->default('es');
            $table->string('prompt_version', 40)->default('charon-es-v1');
            $table->string('source_hash', 64);
            $table->string('prompt_hash', 64);
            $table->string('status', 24)->default('pending');
            $table->string('disk', 40)->default('public');
            $table->string('path', 500)->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedInteger('byte_size')->nullable();
            $table->decimal('duration_seconds', 10, 3)->nullable();
            $table->unsignedSmallInteger('segment_count')->default(0);
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['reading_block_id', 'translation_id', 'provider', 'voice', 'model', 'prompt_version'],
                'audio_narrations_identity_unique'
            );
            $table->index(['status', 'provider', 'voice']);
            $table->index(['source_hash', 'prompt_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audio_narrations');
    }
};
