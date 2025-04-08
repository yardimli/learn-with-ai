<?php

	namespace App\Http\Controllers;

	use App\Helpers\MyHelper;
	use App\Models\GeneratedImage;
	use App\Models\Question;
	use App\Models\Lesson;
	use App\Models\UserAnswer;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Log;
	use Illuminate\Support\Facades\Session;
	use Illuminate\Support\Facades\Storage;
	use Illuminate\Support\Facades\Validator;
	use Illuminate\Support\Str;

	class LessonController extends Controller
	{

		/**
		 * Displays the main interactive question interface.
		 * Determines the starting state based on user progress.
		 *
		 * @param Lesson $lesson Route model binding via session_id
		 * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
		 */
		public function showQuestionInterface(Lesson $lesson)
		{
			Log::info("Loading question interface for Lesson Session: {$lesson->session_id} (ID: {$lesson->id})");

			$state = $this->calculateCurrentState($lesson->id);
			$totalParts = is_array($lesson->lesson_parts) ? count($lesson->lesson_parts) : 0;

			$allPartIntros = $this->getAllPartIntros($lesson);

			// Add intro text/video for the starting part
			$state['currentPartIntroText'] = null; // Default to null
			$state['currentPartVideoUrl'] = null; // Default to null
			if ($state['status'] !== 'completed' && $state['partIndex'] >= 0 && $state['partIndex'] < $totalParts) {
				$state['currentPartIntroText'] = $this->getPartText($lesson, $state['partIndex']);
				$state['currentPartVideoUrl'] = $this->getPartVideoUrl($lesson, $state['partIndex']);
			}


			// We pass null for the initial question now. JS handles loading.
			$question = null;
			Log::info("Initial State for Lesson ID {$lesson->id}: ", $state);

			return view('lesson_interface', compact('lesson', 'question', 'state', 'totalParts', 'allPartIntros'));
		}

		/**
		 * Get the video URL for a specific lesson part.
		 */
		private function getPartVideoUrl(Lesson $lesson, int $partIndex): ?string
		{
			$lessonParts = is_array($lesson->lesson_parts) ? $lesson->lesson_parts : json_decode($lesson->lesson_parts, true);
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
		private function getPartText(Lesson $lesson, int $partIndex): ?string
		{
			$lessonParts = is_array($lesson->lesson_parts) ? $lesson->lesson_parts : json_decode($lesson->lesson_parts, true);
			return $lessonParts[$partIndex]['text'] ?? null;
		}


		private function getAllPartIntros(Lesson $lesson): array
		{
			$intros = [];
			$lessonParts = is_array($lesson->lesson_parts) ? $lesson->lesson_parts : json_decode($lesson->lesson_parts, true);
			$totalParts = is_array($lessonParts) ? count($lessonParts) : 0;

			for ($i = 0; $i < $totalParts; $i++) {
				$intros[$i] = [
					'title' => $lessonParts[$i]['title'] ?? null,
					'text' => $this->getPartText($lesson, $i),
					'videoUrl' => $this->getPartVideoUrl($lesson, $i),
				];
			}
			return $intros;
		}


		/**
		 * Calculates the user's current progress state within a lesson.
		 *
		 * @param int $lessonId
		 * @return array [partIndex, difficulty, correctCounts, status]
		 */
		private function calculateCurrentState(int $lessonId): array
		{
			$lesson = Lesson::find($lessonId); // Get lesson once
			if (!$lesson) {
				Log::error("Lesson not found for ID: {$lessonId} in calculateCurrentState");
				// Return a default error state or handle as appropriate
				return [
					'totalParts' => 0,
					'partIndex' => -1,
					'status' => 'error',
					'overallTotalQuestions' => 0,
					'overallCorrectCount' => 0,
					'currentPartTotalQuestions' => 0,
					'currentPartCorrectCount' => 0,
				];
			}
			$lessonParts = $lesson->lesson_parts ?? [];
			$totalParts = is_array($lessonParts) ? count($lessonParts) : 0;

			$overallTotalQuestions = 0;
			$overallCorrectCount = 0;
			$currentPartIndex = -1; // Initialize to -1 (no active part found yet)
			$status = 'completed'; // Assume complete initially
			$activePartTotalQuestions = 0;
			$activePartCorrectCount = 0;


			for ($part = 0; $part < $totalParts; $part++) {
				$partTotalQuestions = Question::where('lesson_id', $lessonId)
					->where('lesson_part_index', $part)->count();

				$overallTotalQuestions += $partTotalQuestions; // Add to overall total

				if ($partTotalQuestions === 0) {
					continue; // Skip empty parts for first-attempt calculation logic below
				}

				$correctAnsweredCount = UserAnswer::where('lesson_id', $lessonId)
					->whereHas('question', function ($query) use ($part) {
						$query->where('lesson_part_index', $part);
					})
					->select('question_id')
					->groupBy('attempt_number', 'question_id')
					->havingRaw('COUNT(*) = 1') // Only one attempt total
					->havingRaw('SUM(CASE WHEN was_correct = 1 THEN 1 ELSE 0 END) = 1') // That attempt was correct
					->count();
				Log::info("Part {$part} - Total Questions: {$partTotalQuestions}, Correctly Answered: {$correctAnsweredCount}");

				$overallCorrectCount += $correctAnsweredCount; // Add to overall correct count

				// If this part is not fully completed on first attempt *and* we haven't found the active part yet
				if ($correctAnsweredCount < $partTotalQuestions && $currentPartIndex === -1) {
					$currentPartIndex = $part;
					$status = 'inprogress';

					// Store counts for the *current* active part
					$activePartTotalQuestions = $partTotalQuestions;
					$activePartCorrectCount = $correctAnsweredCount;
				}
			}

			// If loop finishes and status is still 'completed', set partIndex to the last one
			if ($status === 'completed' && $totalParts > 0) {
				$currentPartIndex = $totalParts - 1;
			} elseif ($totalParts === 0) {
				$currentPartIndex = -1; // Handle case with no parts
				$status = 'empty'; // Or 'completed' depending on desired behavior for empty lessons
			}

			// If status is completed, ensure the active part counts reflect the last part (if any)
			// or remain 0 if the lesson was empty.
			if ($status === 'completed' && $currentPartIndex !== -1) {
				$lastPartQuestionIds = Question::where('lesson_id', $lessonId)
					->where('lesson_part_index', $currentPartIndex)
					->pluck('id');
				$activePartTotalQuestions = $lastPartQuestionIds->count();

				$activePartCorrectCount = UserAnswer::where('lesson_id', $lessonId)
					->whereHas('question', function ($query) use ($currentPartIndex) {
						$query->where('lesson_part_index', $currentPartIndex);
					})
					->select('question_id')
					->groupBy('attempt_number', 'question_id')
					->havingRaw('COUNT(*) = 1') // Only one attempt total
					->havingRaw('SUM(CASE WHEN was_correct = 1 THEN 1 ELSE 0 END) = 1') // That attempt was correct
					->count();

				$activePartCorrectlyAnsweredCount = UserAnswer::where('lesson_id', $lessonId)
					->whereIn('question_id', $lastPartQuestionIds)
					->where('was_correct', true)
					->distinct('question_id')
					->count('question_id');
			}


			Log::info("Calculated State for Lesson {$lessonId}: ", [
				'totalParts' => $totalParts,
				'partIndex' => $currentPartIndex,
				'status' => $status,
				'overallTotalQuestions' => $overallTotalQuestions,
				'overallCorrectCount' => $overallCorrectCount,
				'currentPartTotalQuestions' => $activePartTotalQuestions, // Renamed for consistency
				'currentPartCorrectCount' => $activePartCorrectCount, // Renamed for consistency
			]);

			return [
				'totalParts' => $totalParts,
				'partIndex' => $currentPartIndex, // The first incomplete part index, or last if complete/empty
				'status' => $status,
				// Overall progress metrics
				'overallTotalQuestions' => $overallTotalQuestions,
				'overallCorrectCount' => $overallCorrectCount,
				// Counts for the *current* active part (or last part if completed)
				'currentPartTotalQuestions' => $activePartTotalQuestions,
				'currentPartCorrectCount' => $activePartCorrectCount,
			];
		}


		/**
		 * AJAX endpoint to get all questions for a specific part and difficulty.
		 *
		 * @param Request $request
		 * @param Lesson $lesson Route model binding via session_id
		 * @return \Illuminate\Http\JsonResponse
		 */
		public function getPartQuestionsAjax(Request $request, Lesson $lesson)
		{
			$validator = Validator::make($request->all(), [
				'partIndex' => 'required|integer|min:0',
			]);

			if ($validator->fails()) {
				return response()->json(['success' => false, 'message' => 'Invalid part index.', 'errors' => $validator->errors()], 422);
			}

			$partIndex = $request->input('partIndex');

			Log::info("AJAX request for ALL questions for Lesson Session: {$lesson->session_id}, Part: {$partIndex}");

			try {
				// Get all questions for this part (regardless of difficulty)
				$questions = Question::where('lesson_id', $lesson->id)
					->where('lesson_part_index', $partIndex)
					->with('generatedImage')
					->get();

				if ($questions->isEmpty()) {
					Log::warning("No questions found for Lesson {$lesson->id}, Part {$partIndex}.");
					return response()->json([
						'success' => true,
						'questions' => [],
						'message' => 'No questions found for this section.'
					]);
				}

				// Process each question with attempt information
				$processedQuestions = $questions->map(function ($question) use ($lesson) {
					// Get latest attempt info for this question
					$lastAttempt = UserAnswer::where('question_id', $question->id)
						->where('lesson_id', $lesson->id)
						->orderBy('attempt_number', 'desc')
						->first();

					$nextAttemptNumber = $lastAttempt ? $lastAttempt->attempt_number + 1 : 1;

					// Check if last attempt was correct with no wrong answers in the same attempt
					$wasCorrectLastAttempt = false;
					$hadNoWrongAnswers = true;

					if ($lastAttempt) {
						$wasCorrectLastAttempt = $lastAttempt->was_correct;

						// Check if there were any wrong answers in this attempt
						$wrongAnswersInLastAttempt = UserAnswer::where('question_id', $question->id)
							->where('lesson_id', $lesson->id)
							->where('attempt_number', $lastAttempt->attempt_number)
							->where('was_correct', false)
							->exists();

						$hadNoWrongAnswers = !$wrongAnswersInLastAttempt;
					}

					// Should this question be skipped?
					$shouldSkip = $wasCorrectLastAttempt && $hadNoWrongAnswers;

					// Process answer audio URLs
					$processedAnswers = $question->answers;
					if ($processedAnswers && is_array($processedAnswers)) {
						foreach ($processedAnswers as $index => &$answer) {
							$answer['index'] = $index;
							$answer['answer_audio_url'] = $question->getAnswerAudioUrl($index);
							$answer['feedback_audio_url'] = $question->getFeedbackAudioUrl($index);
						}
						unset($answer);
					}

					//shuffle answers
					shuffle($processedAnswers);

					return [
						'id' => $question->id,
						'question_text' => $question->question_text,
						'question_audio_url' => $question->question_audio_url,
						'image_url' => $question->generatedImage?->mediumUrl,
						'answers' => $processedAnswers,
						'difficulty_level' => $question->difficulty_level,
						'lesson_part_index' => $question->lesson_part_index,
						'next_attempt_number' => $nextAttemptNumber,
						'should_skip' => $shouldSkip,
					];
				});

				Log::info("Found {$processedQuestions->count()} questions for Part {$partIndex}.");

				return response()->json([
					'success' => true,
					'questions' => $processedQuestions,
				]);
			} catch (\Exception $e) {
				Log::error("Error fetching part questions for Lesson ID {$lesson->id}, Part {$partIndex}: " . $e->getMessage());
				return response()->json([
					'success' => false,
					'message' => 'An error occurred while loading questions. Please try again.'
				], 500);
			}
		}


		/**
		 * Handles submitting a user's answer to a question.
		 * Modified to return state change info.
		 *
		 * @param Request $request
		 * @param Question $question Route model binding
		 * @return \Illuminate\Http\JsonResponse
		 */
		public function submitAnswer(Request $request, Question $question)
		{
			$validator = Validator::make($request->all(), [
				'selected_index' => 'required|integer|min:0|max:3',
				'attempt_number' => 'required|integer|min:1', // Added to validate the attempt number
			]);

			if ($validator->fails()) {
				return response()->json(['success' => false, 'message' => 'Invalid answer selection.', 'errors' => $validator->errors()], 422);
			}

			$lesson_id = $question->lesson_id;
			$selectedIndex = $request->input('selected_index');
			$attemptNumber = $request->input('attempt_number');  // Get from request

			Log::info("Submitting answer for Question ID: {$question->id}, Index: {$selectedIndex}, Lesson ID: {$lesson_id}, Attempt: {$attemptNumber}");

			$answers = $question->answers;
			if (!isset($answers[$selectedIndex])) {
				Log::error("Invalid answer index {$selectedIndex} submitted for Question ID {$question->id}. Answers:", $answers);
				return response()->json(['success' => false, 'message' => 'Invalid answer index provided.'], 400);
			}

			// Process answer data
			$processedAnswers = $question->answers;
			$correctIndex = -1;

			if ($processedAnswers && is_array($processedAnswers)) {
				foreach ($processedAnswers as $index => &$answer) {
					$answer['answer_audio_url'] = $question->getAnswerAudioUrl($index);
					$answer['feedback_audio_url'] = $question->getFeedbackAudioUrl($index);
					if ($answer['is_correct'] === true) {
						$correctIndex = $index;
					}
				}
				unset($answer);
			} else {
				Log::error("Answers data is missing or invalid when processing submit for Question ID: {$question->id}");
				return response()->json(['success' => false, 'message' => 'Error processing question answers.'], 500);
			}

			$selectedAnswer = $processedAnswers[$selectedIndex];
			$wasCorrect = $selectedAnswer['is_correct'] === true;
			$feedbackText = $selectedAnswer['feedback'];
			$feedbackAudioUrl = $selectedAnswer['feedback_audio_url'] ?? null;

			// Save user answer with specified attempt number
			UserAnswer::create([
				'question_id' => $question->id,
				'lesson_id' => $lesson_id,
				'selected_answer_index' => $selectedIndex,
				'was_correct' => $wasCorrect,
				'attempt_number' => $attemptNumber,
			]);

			Log::info("User answer saved for Question ID {$question->id}. Attempt: {$attemptNumber}. Correct: " . ($wasCorrect ? 'Yes' : 'No'));

			// Calculate part completion
			$newState = $this->calculateCurrentState($lesson_id);
			$partCompleted = $this->isPartCompleted($lesson_id, $question->lesson_part_index);

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
		private function isPartCompleted(int $lessonId, int $partIndex): bool
		{
			$totalQuestions = Question::where('lesson_id', $lessonId)
				->where('lesson_part_index', $partIndex)
				->count();

			if ($totalQuestions === 0) {
				return true; // No questions means part is "complete"
			}

			$correctAnsweredCount = UserAnswer::where('lesson_id', $lessonId)
				->whereHas('question', function ($query) use ($partIndex) {
					$query->where('lesson_part_index', $partIndex);
				})
				->select('question_id')
				->groupBy('attempt_number', 'question_id')
				->havingRaw('COUNT(*) = 1') // Only one attempt total
				->havingRaw('SUM(CASE WHEN was_correct = 1 THEN 1 ELSE 0 END) = 1') // That attempt was correct
				->count();

			return $correctAnsweredCount >= $totalQuestions;
		}

	}
