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
				'difficulty' => 'required|string|in:easy,medium,hard',
			]);

			if ($validator->fails()) {
				return response()->json(['success' => false, 'message' => 'Invalid part or difficulty.', 'errors' => $validator->errors()], 422);
			}

			$partIndex = $request->input('partIndex');
			$difficulty = $request->input('difficulty');

			Log::info("AJAX request for ALL questions for Subject Session: {$subject->session_id}, Part: {$partIndex}, Difficulty: {$difficulty}");

			try {
				$quizzes = Quiz::where('subject_id', $subject->id)
					->where('lesson_part_index', $partIndex)
					->where('difficulty_level', $difficulty)
					->with('generatedImage') // Eager load image
					->orderBy('order') // Or 'id'
					->get();

				if ($quizzes->isEmpty()) {
					Log::warning("No quizzes found for Subject {$subject->id}, Part {$partIndex}, Difficulty {$difficulty}.");
					// Return success true but empty array, JS should handle this
					return response()->json([
						'success' => true,
						'quizzes' => [],
						'message' => 'No questions found for this section.'
					]);
				}

				// Process each quiz to include necessary URLs
				$processedQuizzes = $quizzes->map(function ($quiz) {
					$processedAnswers = $quiz->answers; // Assume cast to array
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
						'question_audio_url' => $quiz->question_audio_url, // Accessor
						'image_url' => $quiz->generatedImage?->mediumUrl, // Relationship + Accessor
						'answers' => $processedAnswers,
						'difficulty_level' => $quiz->difficulty_level,
						'lesson_part_index' => $quiz->lesson_part_index,
					];
				});

				Log::info("Found {$processedQuizzes->count()} quizzes for Part {$partIndex}, Difficulty {$difficulty}.");

				return response()->json([
					'success' => true,
					'quizzes' => $processedQuizzes,
				]);

			} catch (\Exception $e) {
				Log::error("Error fetching part questions for Subject ID {$subject->id}, Part {$partIndex}, Difficulty {$difficulty}: " . $e->getMessage());
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
