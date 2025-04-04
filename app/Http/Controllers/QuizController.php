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

			// Ensure assets are generated (can be slow, consider background job or on-demand)
			// This might be better placed elsewhere or triggered differently
			// SubjectController::generateLessonAssets($subject);

			// 1. Determine Current State (Part Index, Difficulty, Correct Counts)
			$state = $this->calculateCurrentState($subject->id);

			// 2. Get the First/Next Quiz Based on State
			$quiz = $this->fetchQuizBasedOnState($subject->id, $state);

			if (!$quiz && $state['status'] !== 'completed') {
				Log::error("Could not fetch starting quiz for Subject ID {$subject->id} in state: ", $state);
				return redirect()->route('quiz.interface', $subject->session_id)
					->with('error', 'Could not load the quiz. Please try again later or contact support.');
			}

			// 3. Prepare Initial Data for the View
			$totalParts = is_array($subject->lesson_parts) ? count($subject->lesson_parts) : 0;

			// Add intro text/video for the starting part
			$state['currentPartIntroText'] = $this->getPartText($subject, $state['partIndex']);
			$state['currentPartVideoUrl'] = $this->getPartVideoUrl($subject, $state['partIndex']);

			// Load image relation if quiz exists
			$quiz?->load('generatedImage');


			Log::info("Initial State for Subject ID {$subject->id}: ", $state);
			Log::info("Initial Quiz ID for Subject ID {$subject->id}: " . ($quiz->id ?? 'None/Completed'));

			return view('quiz_interface', compact('subject', 'quiz', 'state', 'totalParts'));
		}

		/**
		 * Calculates the user's current progress state within a lesson.
		 *
		 * @param int $subjectId
		 * @return array [partIndex, difficulty, correctCounts, status]
		 */
		private function calculateCurrentState(int $subjectId): array
		{
			$difficulties = ['easy', 'medium', 'hard'];
			$requiredCorrect = 2; // Configurable: Number of correct answers needed per difficulty
			$totalParts = Subject::find($subjectId)->lesson_parts ? count(Subject::find($subjectId)->lesson_parts) : 0; // Assuming 3 parts


			for ($part = 0; $part < $totalParts; $part++) {
				$correctCounts = ['easy' => 0, 'medium' => 0, 'hard' => 0];
				foreach ($difficulties as $difficulty) {
					// Count distinct correctly answered quizzes for this part & difficulty
					$count = UserAnswer::where('subject_id', $subjectId)
						->where('was_correct', true)
						->whereHas('quiz', function ($query) use ($part, $difficulty) {
							$query->where('lesson_part_index', $part)
								->where('difficulty_level', $difficulty);
						})
						->distinct('quiz_id') // Count distinct quizzes answered correctly
						->count('quiz_id');

					$correctCounts[$difficulty] = $count;

					if ($count < $requiredCorrect) {
						// Found the current level the user is working on
						return [
							'partIndex' => $part,
							'difficulty' => $difficulty,
							'correctCounts' => $correctCounts, // Counts for the *current* part
							'status' => 'inprogress',
							'requiredCorrect' => $requiredCorrect,
						];
					}
				}
				// If loop completes for this part, the part is finished
			}

			// If all parts and difficulties are completed
			return [
				'partIndex' => $totalParts - 1, // Landed on the last part
				'difficulty' => 'hard', // Last completed difficulty
				'correctCounts' => ['easy' => $requiredCorrect, 'medium' => $requiredCorrect, 'hard' => $requiredCorrect], // Show all as complete
				'status' => 'completed',
				'requiredCorrect' => $requiredCorrect,
			];
		}


		/**
		 * Fetches the next appropriate quiz based on the current state.
		 * Prioritizes unanswered questions within the current difficulty/part.
		 *
		 * @param int $subjectId
		 * @param array $state The current state [partIndex, difficulty, correctCounts]
		 * @return Quiz|null The next Quiz model or null if completed or error.
		 */
		private function fetchQuizBasedOnState(int $subjectId, array $state): ?Quiz
		{
			if ($state['status'] === 'completed') {
				return null;
			}

			$partIndex = $state['partIndex'];
			$difficulty = $state['difficulty'];

			// Find quizzes for the current part and difficulty
			$potentialQuizzesQuery = Quiz::where('subject_id', $subjectId)
				->where('lesson_part_index', $partIndex)
				->where('difficulty_level', $difficulty);

			// Get IDs of quizzes already answered *correctly* by the user in this session/subject
			$correctlyAnsweredQuizIds = UserAnswer::where('subject_id', $subjectId)
				->where('was_correct', true)
				->whereHas('quiz', function($q) use ($partIndex, $difficulty) {
					$q->where('lesson_part_index', $partIndex)
						->where('difficulty_level', $difficulty);
				})
				->pluck('quiz_id')
				->unique()
				->toArray();

			// Try to find a quiz NOT answered correctly yet
			$nextQuiz = (clone $potentialQuizzesQuery) // Clone because we modify the query
			->whereNotIn('id', $correctlyAnsweredQuizIds)
				->orderBy('order') // Or orderBy('id') or random? Let's use order first.
				->first();


			if ($nextQuiz) {
				Log::debug("Found next quiz (not yet correct): ID {$nextQuiz->id} for Subject {$subjectId}, Part {$partIndex}, Difficulty {$difficulty}");
				return $nextQuiz;
			}

			// If all quizzes in this level HAVE been answered correctly at least once,
			// but the count ($state['correctCounts'][$difficulty]) is still less than requiredCorrect,
			// it means the user needs to re-answer one they previously got right (or there's a logic mismatch).
			// Let's just pick the first one in the level again in this case.
			// This handles the case where they got Q1 right, Q2 wrong, Q3 right - count is 2, level passed.
			// But if they got Q1 right, Q2 wrong, Q3 wrong - count is 1. Next time, they might get Q2 right. Count becomes 2.
			// If they got Q1 right, then Q1 wrong, then Q1 right again... the distinct count should still only be 1 for Q1.
			// The calculateCurrentState distinct count handles this. Fetching needs to find *any* quiz not in the *currently* correct list.
			// If we reach here, it implies all available quizzes for this difficulty *have* been marked correct at some point.
			// This should only happen if state calculation determined they *haven't* met the threshold (e.g., need 2 distinct correct, but only 1 distinct quiz was ever marked correct).
			// In this scenario, maybe re-present the one they *did* get correct? Or is there an issue?
			// Let's re-fetch ANY quiz from this level, excluding those *currently* meting the correct threshold if needed - simpler: just get the first one again.
			Log::warning("No quiz found that wasn't previously answered correctly for Subject {$subjectId}, Part {$partIndex}, Difficulty {$difficulty}. Re-fetching the first quiz.");
			$nextQuiz = $potentialQuizzesQuery->orderBy('order')->first(); // Get the first one overall

			if(!$nextQuiz) {
				Log::error("CRITICAL: No quizzes found at all for Subject {$subjectId}, Part {$partIndex}, Difficulty {$difficulty}. Check DB data.");
			}

			return $nextQuiz;

		}


		/**
		 * AJAX endpoint to get the next question data.
		 * Calculates the state AFTER the last question was (presumably) answered.
		 *
		 * @param Request $request
		 * @param Subject $subject Route model binding via session_id
		 * @return \Illuminate\Http\JsonResponse
		 */
		public function getNextQuestionAjax(Request $request, Subject $subject)
		{
			Log::info("AJAX request for next question for Subject Session: {$subject->session_id}");

			// 1. Recalculate the current state based on latest UserAnswers
			$state = $this->calculateCurrentState($subject->id);

			// 2. Fetch the appropriate quiz for this new state
			$quiz = $this->fetchQuizBasedOnState($subject->id, $state);

			// 3. Prepare response data
			if ($state['status'] === 'completed') {
				return response()->json([
					'success' => true,
					'status' => 'completed',
					'message' => 'Congratulations! You have completed all parts of this lesson.',
					'state' => $state, // Send final state
				]);
			}

			if (!$quiz) {
				Log::error("getNextQuestionAjax: Failed to fetch next quiz for Subject ID {$subject->id} in state", $state);
				return response()->json([
					'success' => false,
					'message' => 'Could not determine the next question. Please refresh or contact support.'
				], 500);
			}

			// Load necessary data for the quiz
			$quiz->load('generatedImage');
			// Ensure answers have audio URLs pre-processed or generate them here if needed
			// Assuming processAnswersWithTTS happened during creation or asset generation
			$processedAnswers = $quiz->answers; // Get potentially processed answers
			if ($processedAnswers && is_array($processedAnswers)) {
				foreach ($processedAnswers as $index => &$answer) {
					// Ensure URLs are present, using accessors as fallback
					$answer['answer_audio_url'] = $quiz->getAnswerAudioUrl($index);
					$answer['feedback_audio_url'] = $quiz->getFeedbackAudioUrl($index); // Assuming this exists now
				}
				unset($answer);
			}


			// Add intro text/video URL for the current part if state changed partIndex
			// Check if partIndex changed compared to previous state (if tracked) - simpler: always include it
			$state['currentPartIntroText'] = $this->getPartText($subject, $state['partIndex']);
			$state['currentPartVideoUrl'] = $this->getPartVideoUrl($subject, $state['partIndex']);


			Log::info("Next quiz fetched: ID {$quiz->id}. State: ", $state);


			return response()->json([
				'success' => true,
				'status' => $state['status'], // 'inprogress'
				'quiz' => [
					'id' => $quiz->id,
					'question_text' => $quiz->question_text,
					'question_audio_url' => $quiz->question_audio_url, // Accessor provides URL
					'image_url' => $quiz->generatedImage?->mediumUrl, // Use relationship + accessor
					'answers' => $processedAnswers, // Pass processed answers with URLs
					'difficulty_level' => $quiz->difficulty_level,
					'lesson_part_index' => $quiz->lesson_part_index,
				],
				'state' => $state, // Send the new state back to the client
			]);
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
				'selected_index' => 'required|integer|min:0|max:3', // Assuming 4 answers (0-3)
			]);

			if ($validator->fails()) {
				return response()->json(['success' => false, 'message' => 'Invalid answer selection.', 'errors' => $validator->errors()], 422);
			}

			$subject_id = $quiz->subject_id;
			$selectedIndex = $request->input('selected_index');

			// --- Check if this specific question was already correctly answered in THIS attempt session ---
			// We still allow re-answering if they are trying to meet the threshold (e.g. need 2 correct)
			// The core logic prevents re-answering *if the threshold for the difficulty level is already met*.
			// Let's rely on the getNextQuestion logic to not serve questions from completed levels.
			// We *do* need to record the attempt.

			Log::info("Submitting answer for Quiz ID: {$quiz->id}, Index: {$selectedIndex}, Subject ID: {$subject_id}");

			$answers = $quiz->answers; // Model casts to array
			if (!isset($answers[$selectedIndex])) {
				Log::error("Invalid answer index {$selectedIndex} submitted for Quiz ID {$quiz->id}. Answers:", $answers);
				return response()->json(['success' => false, 'message' => 'Invalid answer index provided.'], 400);
			}

			// Process answer data - Ensure audio URLs are present using accessors
			$processedAnswers = $quiz->answers;
			$correctIndex = -1;
			if ($processedAnswers && is_array($processedAnswers)) {
				foreach ($processedAnswers as $index => &$answer) {
					$answer['answer_audio_url'] = $quiz->getAnswerAudioUrl($index);
					$answer['feedback_audio_url'] = $quiz->getFeedbackAudioUrl($index); // Assuming this exists now
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
			// Correct index found in loop above

			$feedbackText = $selectedAnswer['feedback'];
			$feedbackAudioUrl = $selectedAnswer['feedback_audio_url'] ?? null;

			// --- Save User Answer ---
			// Find previous attempt for THIS quiz in THIS subject session
			$previousAttempt = UserAnswer::where('quiz_id', $quiz->id)
				->where('subject_id', $subject_id)
				->orderBy('attempt_number', 'desc')
				->first();
			$attemptNumber = $previousAttempt ? ($previousAttempt->attempt_number + 1) : 1;

			UserAnswer::create([
				'quiz_id' => $quiz->id,
				'subject_id' => $subject_id,
				'selected_answer_index' => $selectedIndex,
				'was_correct' => $wasCorrect,
				'attempt_number' => $attemptNumber,
			]);

			Log::info("User answer saved for Quiz ID {$quiz->id}. Attempt: {$attemptNumber}. Correct: " . ($wasCorrect ? 'Yes' : 'No'));

			// --- Calculate State AFTER this answer ---
			$newState = $this->calculateCurrentState($subject_id);

			// Determine if this answer caused a level up or part completion
			// We can know this if the newState's part/difficulty is different from the current quiz's part/difficulty
			$levelOrPartChanged = ($newState['partIndex'] !== $quiz->lesson_part_index || $newState['difficulty'] !== $quiz->difficulty_level);
			// Or if the state is now completed
			$isNowCompleted = $newState['status'] === 'completed';


			// Return feedback and the *new* state
			return response()->json([
				'success' => true,
				'was_correct' => $wasCorrect,
				'correct_index' => $correctIndex,
				'feedback_text' => $feedbackText,
				'feedback_audio_url' => $feedbackAudioUrl,
				'newState' => $newState, // Send the state *after* this answer
				'level_advanced' => ($levelOrPartChanged || $isNowCompleted) && $wasCorrect, // Flag if progress moved forward due to this correct answer
				'lesson_completed' => $isNowCompleted,
			]);
		}

	}
