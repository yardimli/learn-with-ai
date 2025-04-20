<?php

	use Illuminate\Database\Migrations\Migration;
	use Illuminate\Database\Schema\Blueprint;
	use Illuminate\Support\Facades\Schema;

	return new class extends Migration {
		/**
		 * Run the migrations.
		 */
		public function up(): void
		{
			Schema::table('lessons', function (Blueprint $table) {
				$table->string('category_selection_mode')->default('ai_decide')->after('ai_generated');
				$table->unsignedBigInteger('selected_main_category_id')->nullable()->after('category_selection_mode');
			});
		}

		/**
		 * Reverse the migrations.
		 */
		public function down(): void
		{
			Schema::table('lessons', function (Blueprint $table) {
				$table->dropColumn('category_selection_mode');
				$table->dropColumn('selected_main_category_id');
			});
		}
	};
