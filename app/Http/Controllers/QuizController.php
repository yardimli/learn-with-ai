<?php

	namespace App\Http\Controllers;

	use App\Helpers\MyHelper;
	use App\Models\GeneratedImage;
	use App\Models\Quiz;
	use App\Models\Subject;
	use App\Models\UserAnswer;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Log;
	use Illuminate\Support\Facades\Session;
	use Illuminate\Support\Facades\Storage;
	use Illuminate\Support\Facades\Validator;
	use Illuminate\Support\Str;

	class QuizController extends Controller
	{

		/**
		 * Get the video URL for a specific lesson part.
		 */
		private function getPartVideoUrl(Subject $subject, int $partIndex): ?string
		{
			$lessonParts = is_array($subject->lesson_parts) ? $subject->lesson_parts : json_decode($subject->lesson_parts, true);
			if (isset($lessonParts[$partIndex]['video_path']) && $lessonParts[$partIndex]['video_path']) {
				// Check if path starts with 'public/', remove if so for Storage::url
				$path = $lessonParts[$partIndex]['video_path'];
				if (Str::startsWith($path, 'public/')) {
					$path = Str::substr($path, 7);
				}
				return Storage::disk('public')->url($path);
			}
			return null;
		}

		/**
		 * Get the text content for a specific lesson part.
		 */
		private function getPartText(Subject $subject, int $partIndex): ?string
		{
			$lessonParts = is_array($subject->lesson_parts) ? $subject->lesson_parts : json_decode($subject->lesson_parts, true);
			return $lessonParts[$partIndex]['text'] ?? null;
		}


		private function getAllPartIntros(Subject $subject): array
		{
			$intros = [];
			$lessonParts = is_array($subject->lesson_parts) ? $subject->lesson_parts : json_decode($subject->lesson_parts, true);
			$totalParts = is_array($lessonParts) ? count($lessonParts) : 0;

			for ($i = 0; $i < $totalParts; $i++) {
				$intros[$i] = [
					'title' => $lessonParts[$i]['title'] ?? null,
					'text' => $this->getPartText($subject, $i),
					'videoUrl' => $this->getPartVideoUrl($subject, $i),
				];
			}
			return $intros;
		}

		/**
		 * Displays the main interactive quiz interface.
		 * Determines the starting state based on user progress.
		 *
		 * @param Subject $subject Route model binding via session_id
		 * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
		 */
		public function showQuizInterface(Subject $subject)
		{
			Log::info("Loading quiz interface for Subject Session: {$subject->session_id} (ID: {$subject->id})");

			$state = $this->calculateCurrentState($subject->id);
			$totalParts = is_array($subject->lesson_parts) ? count($subject->lesson_parts) : 0;

			$allPartIntros = $this->getAllPartIntros($subject);

			// Add intro text/video for the starting part
			$state['currentPartIntroText'] = null; // Default to null
			$state['currentPartVideoUrl'] = null; // Default to null
			if ($state['status'] !== 'completed' && $state['partIndex'] >= 0 && $state['partIndex'] < $totalParts) {
				$state['currentPartIntroText'] = $this->getPartText($subject, $state['partIndex']);
				$state['currentPartVideoUrl'] = $this->getPartVideoUrl($subject, $state['partIndex']);
			}


			// We pass null for the initial quiz now. JS handles loading.
			$quiz = null;
			Log::info("Initial State for Subject ID {$subject->id}: ", $state);

			return view('quiz_interface', compact('subject', 'quiz', 'state', 'totalParts', 'allPartIntros'));
		}

		/**
		 * Calculates the user's current progress state within a lesson.
		 *
		 * @param int $subjectId
		 * @return array [partIndex, difficulty, correctCounts, status]
		 */
		private function calculateCurrentState(int $subjectId): array
		{
			$totalParts = Subject::find($subjectId)->lesson_parts ? count(Subject::find($subjectId)->lesson_parts) : 0;

			for ($part = 0; $part < $totalParts; $part++) {
				// Get all quizzes for this part
				$totalQuestions = Quiz::where('subject_id', $subjectId)
					->where('lesson_part_index', $part)
					->count();

				if ($totalQuestions === 0) {
					continue; // Skip empty parts
				}

				// Get completed quizzes (any correct answer)
				$correctlyAnsweredCount = UserAnswer::where('subject_id', $subjectId)
					->where('was_correct', true)
					->whereHas('quiz', function ($query) use ($part) {
						$query->where('lesson_part_index', $part);
					})
					->distinct('quiz_id')
					->count('quiz_id');

				// Get first-attempt correct answers (for progress calculation)
				$firstAttemptCorrectCount = UserAnswer::where('subject_id', $subjectId)
					->where('was_correct', true)
					->where('attempt_number', 1)
					->whereHas('quiz', function ($query) use ($part) {
						$query->where('lesson_part_index', $part);
					})
					->distinct('quiz_id')
					->count('quiz_id');

				// If not all questions correctly answered, this is the current part
				if ($correctlyAnsweredCount < $totalQuestions) {
					return [
						'partIndex' => $part,
						'totalQuestions' => $totalQuestions,
						'correctCount' => $correctlyAnsweredCount,
						'firstAttemptCorrectCount' => $firstAttemptCorrectCount,
						'status' => 'inprogress',
					];
				}
			}

			// If all parts are complete
			return [
				'partIndex' => $totalParts - 1,
				'totalQuestions' => 0,
				'correctCount' => 0,
				'firstAttemptCorrectCount' => 0,
				'status' => 'completed',
			];
		}


		/**
		 * AJAX endpoint to get all questions for a specific part and difficulty.
		 *
		 * @param Request $request
		 * @param Subject $subject Route model binding via session_id
		 * @return \Illuminate\Http\JsonResponse
		 */
		public function getPartQuestionsAjax(Request $request, Subject $subject)
		{
			$validator = Validator::make($request->all(), [
				'partIndex' => 'required|integer|min:0',
			]);

			if ($validator->fails()) {
				return response()->json(['success' => false, 'message' => 'Invalid part index.', 'errors' => $validator->errors()], 422);
			}

			$partIndex = $request->input('partIndex');

			Log::info("AJAX request for ALL questions for Subject Session: {$subject->session_id}, Part: {$partIndex}");

			try {
				// Get all quizzes for this part (regardless of difficulty)
				$quizzes = Quiz::where('subject_id', $subject->id)
					->where('lesson_part_index', $partIndex)
					->with('generatedImage')
					->get();

				if ($quizzes->isEmpty()) {
					Log::warning("No quizzes found for Subject {$subject->id}, Part {$partIndex}.");
					return response()->json([
						'success' => true,
						'quizzes' => [],
						'message' => 'No questions found for this section.'
					]);
				}

				// Process each quiz with attempt information
				$processedQuizzes = $quizzes->map(function ($quiz) use ($subject) {
					// Get latest attempt info for this quiz
					$lastAttempt = UserAnswer::where('quiz_id', $quiz->id)
						->where('subject_id', $subject->id)
						->orderBy('attempt_number', 'desc')
						->first();

					$nextAttemptNumber = $lastAttempt ? $lastAttempt->attempt_number + 1 : 1;

					// Check if last attempt was correct with no wrong answers in the same attempt
					$wasCorrectLastAttempt = false;
					$hadNoWrongAnswers = true;

					if ($lastAttempt) {
						$wasCorrectLastAttempt = $lastAttempt->was_correct;

						// Check if there were any wrong answers in this attempt
						$wrongAnswersInLastAttempt = UserAnswer::where('quiz_id', $quiz->id)
							->where('subject_id', $subject->id)
							->where('attempt_number', $lastAttempt->attempt_number)
							->where('was_correct', false)
							->exists();

						$hadNoWrongAnswers = !$wrongAnswersInLastAttempt;
					}

					// Should this question be skipped?
					$shouldSkip = $wasCorrectLastAttempt && $hadNoWrongAnswers;

					// Process answer audio URLs
					$processedAnswers = $quiz->answers;
					if ($processedAnswers && is_array($processedAnswers)) {
						foreach ($processedAnswers as $index => &$answer) {
							$answer['answer_audio_url'] = $quiz->getAnswerAudioUrl($index);
							$answer['feedback_audio_url'] = $quiz->getFeedbackAudioUrl($index);
						}
						unset($answer);
					}

					return [
						'id' => $quiz->id,
						'question_text' => $quiz->question_text,
						'question_audio_url' => $quiz->question_audio_url,
						'image_url' => $quiz->generatedImage?->mediumUrl,
						'answers' => $processedAnswers,
						'difficulty_level' => $quiz->difficulty_level,
						'lesson_part_index' => $quiz->lesson_part_index,
						'next_attempt_number' => $nextAttemptNumber,
						'should_skip' => $shouldSkip,
					];
				});

				Log::info("Found {$processedQuizzes->count()} quizzes for Part {$partIndex}.");

				return response()->json([
					'success' => true,
					'quizzes' => $processedQuizzes,
				]);
			} catch (\Exception $e) {
				Log::error("Error fetching part questions for Subject ID {$subject->id}, Part {$partIndex}: " . $e->getMessage());
				return response()->json([
					'success' => false,
					'message' => 'An error occurred while loading questions. Please try again.'
				], 500);
			}
		}


		/**
		 * Handles submitting a user's answer to a quiz.
		 * Modified to return state change info.
		 *
		 * @param Request $request
		 * @param Quiz $quiz Route model binding
		 * @return \Illuminate\Http\JsonResponse
		 */
		public function submitAnswer(Request $request, Quiz $quiz)
		{
			$validator = Validator::make($request->all(), [
				'selected_index' => 'required|integer|min:0|max:3',
				'attempt_number' => 'required|integer|min:1', // Added to validate the attempt number
			]);

			if ($validator->fails()) {
				return response()->json(['success' => false, 'message' => 'Invalid answer selection.', 'errors' => $validator->errors()], 422);
			}

			$subject_id = $quiz->subject_id;
			$selectedIndex = $request->input('selected_index');
			$attemptNumber = $request->input('attempt_number');  // Get from request

			Log::info("Submitting answer for Quiz ID: {$quiz->id}, Index: {$selectedIndex}, Subject ID: {$subject_id}, Attempt: {$attemptNumber}");

			$answers = $quiz->answers;
			if (!isset($answers[$selectedIndex])) {
				Log::error("Invalid answer index {$selectedIndex} submitted for Quiz ID {$quiz->id}. Answers:", $answers);
				return response()->json(['success' => false, 'message' => 'Invalid answer index provided.'], 400);
			}

			// Process answer data
			$processedAnswers = $quiz->answers;
			$correctIndex = -1;

			if ($processedAnswers && is_array($processedAnswers)) {
				foreach ($processedAnswers as $index => &$answer) {
					$answer['answer_audio_url'] = $quiz->getAnswerAudioUrl($index);
					$answer['feedback_audio_url'] = $quiz->getFeedbackAudioUrl($index);
					if ($answer['is_correct'] === true) {
						$correctIndex = $index;
					}
				}
				unset($answer);
			} else {
				Log::error("Answers data is missing or invalid when processing submit for Quiz ID: {$quiz->id}");
				return response()->json(['success' => false, 'message' => 'Error processing quiz answers.'], 500);
			}

			$selectedAnswer = $processedAnswers[$selectedIndex];
			$wasCorrect = $selectedAnswer['is_correct'] === true;
			$feedbackText = $selectedAnswer['feedback'];
			$feedbackAudioUrl = $selectedAnswer['feedback_audio_url'] ?? null;

			// Save user answer with specified attempt number
			UserAnswer::create([
				'quiz_id' => $quiz->id,
				'subject_id' => $subject_id,
				'selected_answer_index' => $selectedIndex,
				'was_correct' => $wasCorrect,
				'attempt_number' => $attemptNumber,
			]);

			Log::info("User answer saved for Quiz ID {$quiz->id}. Attempt: {$attemptNumber}. Correct: " . ($wasCorrect ? 'Yes' : 'No'));

			// Calculate part completion
			$newState = $this->calculateCurrentState($subject_id);
			$partCompleted = $this->isPartCompleted($subject_id, $quiz->lesson_part_index);

			return response()->json([
				'success' => true,
				'was_correct' => $wasCorrect,
				'correct_index' => $correctIndex,
				'feedback_text' => $feedbackText,
				'feedback_audio_url' => $feedbackAudioUrl,
				'newState' => $newState,
				'part_completed' => $partCompleted,
				'lesson_completed' => $newState['status'] === 'completed',
			]);
		}

// Helper method to check if a part is completed
		private function isPartCompleted(int $subjectId, int $partIndex): bool
		{
			$totalQuestions = Quiz::where('subject_id', $subjectId)
				->where('lesson_part_index', $partIndex)
				->count();

			if ($totalQuestions === 0) {
				return true; // No questions means part is "complete"
			}

			$correctlyAnsweredCount = UserAnswer::where('subject_id', $subjectId)
				->where('was_correct', true)
				->whereHas('quiz', function ($query) use ($partIndex) {
					$query->where('lesson_part_index', $partIndex);
				})
				->distinct('quiz_id')
				->count('quiz_id');

			return $correctlyAnsweredCount >= $totalQuestions;
		}

	}
