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
	        $table->integer('subject_id')->default(0)->index(); // Track by session
	        $table->integer('generated_image_id')->default(0);
	        $table->text('question_text');
	        $table->string('question_audio_path')->nullable();
	        $table->json('answers');
					$table->integer('difficulty_level')->default(1); // Track difficulty
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
