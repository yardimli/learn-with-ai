<?php

	use Illuminate\Database\Migrations\Migration;
	use Illuminate\Database\Schema\Blueprint;
	use Illuminate\Support\Facades\Schema;

	return new class extends Migration
	{
		public function up(): void
		{
			Schema::create('main_categories', function (Blueprint $table) {
				$table->id();
				$table->string('name')->unique(); // Main category names must be unique
				$table->timestamps();
			});
		}

		public function down(): void
		{
			Schema::dropIfExists('main_categories');
		}
	};
