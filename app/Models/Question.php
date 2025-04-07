<?php

	namespace App\Models;

	use App\Helpers\MyHelper;
	use Illuminate\Database\Eloquent\Factories\HasFactory;
	use Illuminate\Database\Eloquent\Model;
	use Illuminate\Support\Facades\Log;
	use Illuminate\Support\Facades\Storage;
	use Illuminate\Support\Str;

	// For URL accessor

	class Question extends Model
	{
		use HasFactory;

		protected $fillable = [
			'subject_id',
			'image_prompt_idea',
			'image_search_keywords',
			'generated_image_id',
			'question_text',
			'question_audio_path',
			'answers', // JSON
			'difficulty_level',
			'lesson_part_index',
			'order',
		];

		protected $casts = [
			'answers' => 'array', // Automatically encode/decode JSON
			'generated_image_id' => 'integer',
			'order' => 'integer',
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

		public function getQuestionAudioUrlAttribute(): ?string
		{
			if ($this->question_audio_path && Storage::disk('public')->exists($this->question_audio_path)) {
				return Storage::disk('public')->url($this->question_audio_path);
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
			// Check for URL directly (if stored by processAnswersWithTTS)
			if (isset($this->answers[$index]['feedback_audio_url']) && !empty($this->answers[$index]['feedback_audio_url'])) {
				return $this->answers[$index]['feedback_audio_url'];
			}
			return null;
		}

		public function getAnswerAudioUrl(int $index): ?string
		{
			$path = $this->answers[$index]['answer_audio_path'] ?? null;
			if ($path && Storage::disk('public')->exists($path)) {
				return Storage::disk('public')->url($path);
			}
			// Check for URL directly
			if (isset($this->answers[$index]['answer_audio_url']) && !empty($this->answers[$index]['answer_audio_url'])) {
				return $this->answers[$index]['answer_audio_url'];
			}
			return null;
		}

		/**
		 * Process answers array, generating TTS for text and feedback using the specified engine.
		 *
		 * @param array $answers The original answers array from LLM.
		 * @param int $questionId The ID of the question for logging/uniqueness.
		 * @param string $filenamePrefix Prefix including path segments (e.g., 'audio/question_sX_pY_qZ').
		 * @param string $ttsEngine The TTS engine ('google' or 'openai').
		 * @param string $ttsVoice The voice name specific to the chosen engine.
		 * @param string $languageCode Language code (primarily for Google).
		 * @return array The processed answers array with audio paths/URLs.
		 */
		public static function processAnswersWithTTS(
			array  $answers,
			int    $questionId, // Keep questionId for logging
			string $filenamePrefix, // Use this as the base for filenames
			string $ttsEngine,
			string $ttsVoice,
			string $languageCode = 'en-US'
		): array
		{
			//dd($answers, $questionId, $filenamePrefix, $ttsEngine, $ttsVoice, $languageCode);
			$processedAnswers = $answers;
			foreach ($processedAnswers as $index => &$answer) {
				$baseFilename = Str::slug(Str::limit($answer['text'], 20)) . '_' . $index; // Base on answer text + index

				// --- Generate TTS for ANSWER TEXT ---
				$answerFilename = $filenamePrefix . '_ans_' . $baseFilename; // e.g., audio/question_sX_pY_qZ_ans_answer-text_0
				$answerTtsResult = MyHelper::text2speech(
					$answer['text'] ?? '',
					$ttsVoice,
					$languageCode,
					$answerFilename, // Unique filename
					$ttsEngine
				);
				if ($answerTtsResult && isset($answerTtsResult['storage_path'], $answerTtsResult['fileUrl'])) {
					$answer['answer_audio_path'] = $answerTtsResult['storage_path'];
					$answer['answer_audio_url'] = $answerTtsResult['fileUrl']; // Store URL directly
					Log::info("Generated TTS for Question {$questionId} Answer {$index}: " . $answerTtsResult['storage_path']);
				} else {
					Log::warning("Failed to generate TTS for Question {$questionId} Answer {$index}");
					$answer['answer_audio_path'] = null;
					$answer['answer_audio_url'] = null;
				}

				// --- Generate TTS for FEEDBACK TEXT ---
				$feedbackFilename = $filenamePrefix . '_fb_' . $baseFilename; // e.g., audio/question_sX_pY_qZ_fb_answer-text_0
				$feedbackTtsResult = MyHelper::text2speech(
					$answer['feedback'] ?? '',
					$ttsVoice,
					$languageCode,
					$feedbackFilename, // Unique filename
					$ttsEngine
				);
				if ($feedbackTtsResult && isset($feedbackTtsResult['storage_path'], $feedbackTtsResult['fileUrl'])) {
					$answer['feedback_audio_path'] = $feedbackTtsResult['storage_path'];
					$answer['feedback_audio_url'] = $feedbackTtsResult['fileUrl']; // Store URL directly
					Log::info("Generated TTS for Question {$questionId} Feedback {$index}: " . $feedbackTtsResult['storage_path']);
				} else {
					Log::warning("Failed to generate TTS for Question {$questionId} Feedback {$index}");
					$answer['feedback_audio_path'] = null;
					$answer['feedback_audio_url'] = null;
				}
			}
			unset($answer);
			return $processedAnswers;
		}

	}
