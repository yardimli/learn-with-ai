<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('quizzes', function (Blueprint $table) {
	        $table->id();
	        $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
	        $table->text('question_text');
	        $table->string('question_audio_url')->nullable();
	        $table->string('question_audio_path')->nullable();
	        $table->string('question_video_url')->nullable();
	        $table->string('question_video_path')->nullable();
	        $table->json('answers');
					$table->integer('difficulty_level')->default(1); // Track difficulty
	        $table->string('session_id')->index(); // Track by session
	        $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quizzes');
    }
};
