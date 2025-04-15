<?php

	use Illuminate\Database\Migrations\Migration;
	use Illuminate\Database\Schema\Blueprint;
	use Illuminate\Support\Facades\Schema;

	return new class extends Migration
	{
		public function up(): void
		{
			Schema::table('lessons', function (Blueprint $table) {


				// 2. Drop the old column if it exists
				if (Schema::hasColumn('lessons', 'category_id')) {
					$table->dropColumn('category_id');
				}


				// 3. Add the new column
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

				// Re-add the old column (assuming it was unsignedBigInteger and nullable)
				$table->unsignedBigInteger('category_id')->nullable()->after('generated_image_id');
				// Re-add the old foreign key constraint if you know its definition
				// $table->foreign('category_id')->references('id')->on('category_management')->onDelete('set null');
				// Note: You'd need the 'category_management' table back for this to work fully on rollback.
			});
		}
	};
