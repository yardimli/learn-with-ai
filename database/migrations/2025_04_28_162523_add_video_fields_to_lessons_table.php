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
				$table->string('youtube_video_id')->nullable()->after('preferredLlm');
				$table->string('video_api_host')->nullable()->after('youtube_video_id');
				$table->longText('video_api_response')->nullable()->after('video_api_host');
				$table->string('video_path')->nullable()->after('video_api_response');
				$table->text('video_subtitles')->nullable()->after('video_path');
				$table->text('video_subtitles_text')->nullable()->after('video_subtitles');
			});
		}

		/**
		 * Reverse the migrations.
		 */
		public function down(): void
		{
			Schema::table('lessons', function (Blueprint $table) {
				$table->dropColumn([
					'youtube_video_id',
					'video_api_host',
					'video_api_response',
					'video_path',
					'video_subtitles',
					'video_subtitles_text',
				]);
			});
		}
	};
