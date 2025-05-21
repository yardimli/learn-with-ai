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
        Schema::create('lessons', function (Blueprint $table) {
	        $table->id();
	        $table->text('subject'); // User entered lesson
	        $table->string('title')->nullable(); // Generated title
	        $table->text('image_prompt_idea')->nullable(); // Generated idea for image prompt
	        $table->unsignedBigInteger('generated_image_id')->default(0);
	        $table->json('lesson_content')->nullable(); // Store structured lesson
	        $table->string('ttsEngine')->nullable();
	        $table->string('ttsVoice')->nullable();
	        $table->string('ttsLanguageCode')->nullable();
	        $table->string('preferredLlm')->nullable();

	        $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
