<?php

	namespace App\Models;

	use Illuminate\Database\Eloquent\Factories\HasFactory;
	use Illuminate\Database\Eloquent\Model;
	use Illuminate\Support\Facades\Storage; // Needed for accessor

	class Lesson extends Model
	{
		use HasFactory;

		protected $fillable = [
			'user_id',
			'title',
			'subject',
			'user_title',
			'notes',
			'image_prompt_idea',
			'generated_image_id',
			'selected_main_category_id',
			'sub_category_id',
			'language',
			'lesson_parts',
			'ttsEngine',
			'ttsVoice',
			'ttsLanguageCode',
			'preferredLlm',
			'ai_generated',
			'category_selection_mode',
			'month',
			'year',
			'week',
			'youtube_video_id',
			'video_api_host',
			'video_api_response',
			'video_path',
			'video_subtitles',
			'video_subtitles_text',
		];

		protected $casts = [
			'user_id' => 'integer',
			'lesson_parts' => 'array',
			'generated_image_id' => 'integer',
			'selected_main_category_id' => 'integer',
			'sub_category_id' => 'integer',
			'ai_generated' => 'boolean',
			'month' => 'integer',
			'year' => 'integer',
			'week' => 'integer',
			'video_subtitles_text' => 'string',
			'video_api_response' => 'array',
		];

		public function user()
		{
			return $this->belongsTo(User::class);
		}


		public function generatedImage()
		{
			return $this->belongsTo(GeneratedImage::class, 'generated_image_id');
		}

		// Define the relationship to Category
		public function subCategory() {
			return $this->belongsTo(SubCategory::class);
		}

		public function mainCategory() {
			return $this->belongsTo(MainCategory::class, 'selected_main_category_id');
		}


		public function questions()
		{
			// Order questions maybe by difficulty then ID?
			return $this->hasMany(Question::class)->orderByRaw("FIELD(difficulty_level, 'easy', 'medium', 'hard')")->orderBy('id');
		}

		public function userAnswers()
		{
			return $this->hasMany(UserAnswer::class);
		}

		public function getVideoUrlAttribute(): ?string
		{
			if ($this->video_path && Storage::disk('public')->exists($this->video_path)) {
				return Storage::disk('public')->url($this->video_path);
			}
			return null;
		}
	}
