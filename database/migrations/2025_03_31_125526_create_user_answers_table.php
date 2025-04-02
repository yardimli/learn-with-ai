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
        Schema::create('user_answers', function (Blueprint $table) {
	        $table->id();
	        $table->integer('quiz_id')->default(0)->index();
	        $table->integer('selected_answer_index'); // 0, 1, 2, 3
	        $table->boolean('was_correct');
	        $table->integer('attempt_number')->default(1); // In case you allow multiple tries per question later
	        $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_answers');
    }
};
