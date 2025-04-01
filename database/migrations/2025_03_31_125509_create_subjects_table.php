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
	        $table->string('name'); // User entered subject
	        $table->string('title')->nullable(); // Generated title
	        $table->text('main_text')->nullable(); // Generated main text
	        $table->text('image_prompt_idea')->nullable(); // Generated idea for image prompt
	        $table->unsignedBigInteger('generated_image_id')->nullable()->constrained('generated_images')->nullOnDelete(); // Link to the specific image record
	        $table->string('initial_video_path')->nullable(); // Relative storage path for video file
	        $table->string('initial_video_url')->nullable(); // Public URL (optional, can derive)
	        $table->string('session_id')->index(); // Track by session
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
