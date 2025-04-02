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
			'generated_image_id',
			'question_text',
			'question_audio_path',
			'answers', // JSON
			'difficulty_level',
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

		public function generatedImage()
		{
			return $this->belongsTo(GeneratedImage::class, 'generated_image_id');
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

		/**
		 * Process answers array, generating TTS for text and feedback using the specified engine.
		 *
		 * @param array $answers The original answers array from LLM.
		 * @param int $subjectId Subject ID for filename uniqueness.
		 * @param string $identifier Unique identifier (e.g., 'initial', 'next_xyz').
		 * @param string $ttsEngine The TTS engine ('google' or 'openai').
		 * @param string $ttsVoice The voice name specific to the chosen engine.
		 * @param string $languageCode Language code (primarily for Google).
		 * @return array The processed answers array with audio paths/URLs.
		 */
		public static function processAnswersWithTTS(
			array $answers,
			int $subjectId,
			string $identifier,
			string $ttsEngine, // Add engine
			string $ttsVoice,   // Add voice
			string $languageCode = 'en-US' // Keep for Google
		): array {
			$processedAnswers = $answers; // Start with the original array
			foreach ($processedAnswers as $index => &$answer) { // Use reference
				$baseFilename = Str::slug(Str::limit($answer['text'], 20)); // Create safer filename base

				// Generate TTS for ANSWER TEXT
				$answerTtsResult = MyHelper::text2speech(
					$answer['text'] ?? '', // Ensure text exists
					$ttsVoice,            // Pass the correct voice
					$languageCode,        // Pass language code
					'answer_' . $subjectId . '_' . $identifier . '_' . $baseFilename . '_' . $index,
					$ttsEngine            // Pass the engine
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
					$answer['feedback'] ?? '', // Ensure feedback exists
					$ttsVoice,               // Pass the correct voice
					$languageCode,           // Pass language code
					'feedback_' . $subjectId . '_' . $identifier . '_' . $baseFilename . '_' . $index,
					$ttsEngine               // Pass the engine
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
