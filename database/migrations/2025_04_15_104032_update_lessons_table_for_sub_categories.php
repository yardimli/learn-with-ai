<?php

	use Illuminate\Database\Migrations\Migration;
	use Illuminate\Database\Schema\Blueprint;
	use Illuminate\Support\Facades\Schema;

	return new class extends Migration
	{
		public function up(): void
		{
			Schema::table('lessons', function (Blueprint $table) {

				$table->unsignedBigInteger('sub_category_id')->nullable()->after('generated_image_id');
			});
		}

		public function down(): void
		{
			Schema::table('lessons', function (Blueprint $table) {
				// Reverse the changes
				$table->dropColumn('sub_category_id');
			});
		}
	};
