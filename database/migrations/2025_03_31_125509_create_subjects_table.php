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
        Schema::create('subjects', function (Blueprint $table) {
	        $table->id();
	        $table->string('session_id')->index(); // Track by session
	        $table->string('name'); // User entered subject
	        $table->string('title')->nullable(); // Generated title
	        $table->text('image_prompt_idea')->nullable(); // Generated idea for image prompt
	        $table->unsignedBigInteger('generated_image_id')->default(0);
	        $table->json('lesson_parts')->nullable(); // Store structured lesson
	        $table->string('llm_used')->nullable(); // Track which LLM
	        $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};
