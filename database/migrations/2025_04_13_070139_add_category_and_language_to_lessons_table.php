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
			Schema::table('lessons', function (Blueprint $table) {
				$table->string('language', 10)->nullable()->after('lesson_parts'); // e.g., 'en', 'tr', 'de'
			});
		}

		/**
		 * Reverse the migrations.
		 */
		public function down(): void
		{
			Schema::table('lessons', function (Blueprint $table) {
				$table->dropColumn('language');
			});
		}
	};
