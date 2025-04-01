<?php

	namespace App\Models;

	use App\Helpers\MyHelper;
	use Illuminate\Database\Eloquent\Factories\HasFactory;
	use Illuminate\Database\Eloquent\Model;
	use Illuminate\Support\Facades\Log;
	use Illuminate\Support\Facades\Storage;
	use Illuminate\Support\Str;

	// For URL accessor

	class Quiz extends Model
	{
		use HasFactory;

		protected $fillable = [
			'subject_id',
			'question_text',
			'question_audio_path',
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


		// Helper to get feedback audio URL for a specific answer index
		public function getFeedbackAudioUrl(int $index): ?string
		{
			$path = $this->answers[$index]['feedback_audio_path'] ?? null;
			if ($path && Storage::disk('public')->exists($path)) {
				return Storage::disk('public')->url($path);
			}
			return null;
		}

		public function getAnswerAudioUrl(int $index): ?string
		{
			// Access the URL directly if stored, otherwise generate from path
			if (isset($this->answers[$index]['answer_audio_url'])) {
				return $this->answers[$index]['answer_audio_url']; // Return pre-generated URL
			}

			// Fallback: Generate URL from path if only path is stored (less ideal)
			$path = $this->answers[$index]['answer_audio_path'] ?? null;
			if ($path && Storage::disk('public')->exists($path)) {
				return Storage::disk('public')->url($path);
			}
			return null;
		}

		public static function processAnswersWithTTS(array $answers, int $subjectId, string $identifier): array
		{
			$processedAnswers = $answers; // Start with the original array
			foreach ($processedAnswers as $index => &$answer) { // Use reference
				$baseFilename = Str::slug(Str::limit($answer['text'], 20)); // Create safer filename base

				// Generate TTS for ANSWER TEXT
				$answerTtsResult = MyHelper::text2speech(
					$answer['text'],
					env('DEFAULT_TTS_VOICE', 'en-US-Wavenet-A'),
					'en-US',
					'answer_' . $subjectId . '_' . $identifier . '_' . $baseFilename . '_' . $index
				);

				if ($answerTtsResult && isset($answerTtsResult['storage_path'], $answerTtsResult['fileUrl'])) {
					$answer['answer_audio_path'] = $answerTtsResult['storage_path'];
					$answer['answer_audio_url'] = $answerTtsResult['fileUrl'];
					Log::info("Generated TTS for answer {$identifier}_{$index}: " . $answerTtsResult['storage_path']);
				} else {
					Log::warning("Failed to generate TTS for answer {$identifier}_{$index}");
					$answer['answer_audio_path'] = null;
					$answer['answer_audio_url'] = null;
				}

				// Generate TTS for FEEDBACK TEXT (existing logic)
				$feedbackTtsResult = MyHelper::text2speech(
					$answer['feedback'],
					env('DEFAULT_TTS_VOICE', 'en-US-Wavenet-A'),
					'en-US',
					'feedback_' . $subjectId . '_' . $identifier . '_' . $baseFilename . '_' . $index
				);

				if ($feedbackTtsResult && isset($feedbackTtsResult['storage_path'], $feedbackTtsResult['fileUrl'])) {
					$answer['feedback_audio_path'] = $feedbackTtsResult['storage_path'];
					$answer['feedback_audio_url'] = $feedbackTtsResult['fileUrl']; // Add URL directly
					Log::info("Generated TTS for feedback {$identifier}_{$index}: " . $feedbackTtsResult['storage_path']);
				} else {
					Log::warning("Failed to generate TTS for feedback {$identifier}_{$index}");
					$answer['feedback_audio_path'] = null;
					$answer['feedback_audio_url'] = null;
				}
			}
			unset($answer); // Unset reference after loop
			return $processedAnswers;
		}
	}
