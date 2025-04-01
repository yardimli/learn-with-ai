<?php

	namespace App\Models;

	use Illuminate\Database\Eloquent\Factories\HasFactory;
	use Illuminate\Database\Eloquent\Model;
	use Illuminate\Support\Facades\Storage; // For URL accessor

	class Quiz extends Model
	{
		use HasFactory;

		protected $fillable = [
			'subject_id',
			'question_text',
			'question_video_path',
			'question_video_url',
			'answers', // JSON
			'difficulty_level',
			'session_id',
		];

		protected $casts = [
			'answers' => 'array', // Automatically encode/decode JSON
		];

		public function subject()
		{
			return $this->belongsTo(Subject::class);
		}

		public function userAnswers()
		{
			return $this->hasMany(UserAnswer::class);
		}

		// Accessor for video URL
		public function getQuestionVideoUrlAttribute()
		{
			if ($this->question_video_path && Storage::disk('public')->exists($this->question_video_path)) {
				return Storage::disk('public')->url($this->question_video_path);
			}
			return null;
		}

		// Helper to get feedback audio URL for a specific answer index
		public function getFeedbackAudioUrl(int $index): ?string
		{
			$path = $this->answers[$index]['feedback_audio_path'] ?? null;
			if ($path && Storage::disk('public')->exists($path)) {
				return Storage::disk('public')->url($path);
			}
			return null;
		}
	}
