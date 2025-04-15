<?php

	use Illuminate\Database\Migrations\Migration;
	use Illuminate\Database\Schema\Blueprint;
	use Illuminate\Support\Facades\Schema;

	return new class extends Migration
	{
		public function up(): void
		{
			Schema::table('lessons', function (Blueprint $table) {

				$table->unsignedBigInteger('sub_category_id')->nullable()->after('generated_image_id'); // Or adjust position

				// 4. Add the new foreign key constraint
				$table->foreign('sub_category_id')
					->references('id')
					->on('sub_categories')
					->onDelete('set null'); // Set to null if sub-category is deleted
			});
		}

		public function down(): void
		{
			Schema::table('lessons', function (Blueprint $table) {
				// Reverse the changes
				$table->dropForeign(['sub_category_id']);
				$table->dropColumn('sub_category_id');
			});
		}
	};
