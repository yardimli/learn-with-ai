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
	        $table->text('image_prompt_idea')->nullable(); // Generated idea for image prompt
	        $table->integer('generated_image_id')->default(0);
	        $table->text('question_text');
	        $table->string('question_audio_path')->nullable();
	        $table->json('answers');
					$table->string('difficulty_level')->default('easy'); // Track difficulty
	        $table->integer('lesson_part_index')->default(0);
	        $table->integer('order')->default(0);
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
