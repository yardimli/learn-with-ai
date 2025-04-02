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
        Schema::create('generated_images', function (Blueprint $table) {
	        $table->id();
	        $table->string('image_type')->default('generated'); // e.g., generated, uploaded
	        $table->uuid('image_guid')->unique(); // Keep GUID for referencing image set
	        $table->string('image_alt', 255)->nullable(); // Alt text
	        $table->text('prompt')->nullable(); // Original user prompt for image
	        $table->string('image_model')->nullable(); // e.g., fal-ai/flux/schnell
	        $table->string('image_size_setting')->nullable(); // e.g., square_hd
	        $table->string('image_original_path')->nullable(); // Relative path in storage/app/public
	        $table->string('image_large_path')->nullable();
	        $table->string('image_medium_path')->nullable();
	        $table->string('image_small_path')->nullable();
	        $table->json('api_response_data')->nullable(); // Store raw API response if needed
	        $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('generated_images');
    }
};
