<?php

	namespace App\Models;

	use Illuminate\Database\Eloquent\Factories\HasFactory;
	use Illuminate\Database\Eloquent\Model;
	use Illuminate\Support\Facades\Storage; // Needed for accessor

	class Lesson extends Model
	{
		use HasFactory;

		protected $fillable = [
			'session_id',
			'name',
			'title',
			'image_prompt_idea',
			'generated_image_id',
			'lesson_parts',
			'ttsEngine',
			'ttsVoice',
			'ttsLanguageCode',
			'preferredLlm',

		];

		protected $casts = [
			'lesson_parts' => 'array', // << Cast JSON column to array
			'generated_image_id' => 'integer', // Ensure cast if using relationship checks
		];

		public function getRouteKeyName()
		{
			return 'session_id';
		}


		public function generatedImage()
		{
			return $this->belongsTo(GeneratedImage::class, 'generated_image_id');
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
	}
