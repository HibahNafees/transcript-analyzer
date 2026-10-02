<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transcripts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('original_filename');
            $table->string('status')->default('parsed'); // parsed | analyzed | partial | failed
            $table->string('model')->nullable();
            $table->text('analysis_error')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('speakers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transcript_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('line_count')->default(0);
            $table->unsignedInteger('word_count')->default(0);
            $table->unsignedInteger('speaking_ms')->default(0);
            $table->text('summary')->nullable();
            $table->text('contribution')->nullable();
            $table->text('analysis_error')->nullable();
            $table->timestamps();

            $table->unique(['transcript_id', 'name']);
        });

        Schema::create('utterances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transcript_id')->constrained()->cascadeOnDelete();
            $table->foreignId('speaker_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('cue_identifier')->nullable();
            $table->unsignedInteger('start_ms');
            $table->unsignedInteger('end_ms');
            $table->text('text');
            $table->unsignedInteger('word_count');
            $table->boolean('shared_connection_flag')->default(false);
            $table->string('flag_reason')->nullable();
            $table->timestamps();

            $table->index(['transcript_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('utterances');
        Schema::dropIfExists('speakers');
        Schema::dropIfExists('transcripts');
    }
};
