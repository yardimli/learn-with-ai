<?php

	use Illuminate\Database\Migrations\Migration;
	use Illuminate\Database\Schema\Blueprint;
	use Illuminate\Support\Facades\Schema;

	return new class extends Migration
	{
		public function up(): void
		{
			Schema::create('sub_categories', function (Blueprint $table) {
				$table->id();
				$table->unsignedBigInteger('main_category_id');
				$table->string('name');
				$table->timestamps();

				// Foreign key constraint
				$table->foreign('main_category_id')
					->references('id')
					->on('main_categories')
					->onDelete('cascade'); // Or restrict if you prefer

				// Unique constraint for name within a main category
				$table->unique(['main_category_id', 'name']);
			});
		}

		public function down(): void
		{
			Schema::dropIfExists('sub_categories');
		}
	};
