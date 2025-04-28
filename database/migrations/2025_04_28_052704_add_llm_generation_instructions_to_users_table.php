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
			Schema::table('users', function (Blueprint $table) {
				// Add after the 'remember_token' column, or choose another suitable place
				$table->text('llm_generation_instructions')->nullable()->after('remember_token');
			});
		}

		/**
		 * Reverse the migrations.
		 */
		public function down(): void
		{
			Schema::table('users', function (Blueprint $table) {
				$table->dropColumn('llm_generation_instructions');
			});
		}
	};
