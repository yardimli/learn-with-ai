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
			Schema::create('user_answer_archives', function (Blueprint $table) {
				$table->id();
				$table->uuid('archive_batch_id')->index();
				$table->unsignedBigInteger('original_user_answer_id')->index()->comment('Reference to the original ID before archiving');
				$table->foreignId('question_id')->constrained()->onDelete('cascade');
				$table->foreignId('subject_id')->constrained()->onDelete('cascade');
				$table->integer('selected_answer_index');
				$table->boolean('was_correct');
				$table->integer('attempt_number');
				$table->timestamp('archived_at')->useCurrent(); // Record when it was archived
				$table->timestamps(); // original created_at/updated_at from UserAnswer
			});
		}

		/**
		 * Reverse the migrations.
		 */
		public function down(): void
		{
			Schema::dropIfExists('user_answer_archives');
		}
	};
