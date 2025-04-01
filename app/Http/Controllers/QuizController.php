<?php

	namespace App\Http\Controllers;

	use App\Helpers\MyHelper;
	use App\Models\Quiz;
	use App\Models\Subject;
	use App\Models\UserAnswer;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Log;
	use Illuminate\Support\Facades\Session;
	use Illuminate\Support\Facades\Validator;
	use Illuminate\Support\Str;

	class QuizController extends Controller
	{
		/**
		 * Display the current quiz question for the subject.
		 * Finds the latest quiz for the subject and session.
		 *
		 * @param Subject $subject Route model binding
		 * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
		 */
		public function show(Subject $subject)
		{
			$sessionId = Session::getId();
			// Ensure the subject belongs to the current session
			if ($subject->session_id !== $sessionId) {
				Log::warning("Attempt to access quiz for subject ID {$subject->id} from different session.");
				return redirect()->route('home')->with('error', 'Quiz not found or session expired.');
			}

			// Find the LATEST quiz associated with this subject AND session
			$quiz = Quiz::where('subject_id', $subject->id)
				->where('session_id', $sessionId)
				->orderBy('created_at', 'desc')
				->first();

			// What if no quiz exists yet? Redirect back to content page?
			if (!$quiz) {
				Log::warning("No quiz found for subject ID {$subject->id} and session {$sessionId}. Redirecting to content.");
				// Maybe the first quiz generation failed, redirect to content page to try again
				return redirect()->route('content.show', $subject->id)->with('info', 'Generate the quiz first.');
			}

			// Eager load subject's image for fallback visuals in quiz
			$subject->load('generatedImage');
			//dd($quiz);
			return view('quiz_display', compact('subject', 'quiz'));
		}

		/**
		 * Submit an answer for a specific quiz (AJAX).
		 *
		 * @param Request $request
		 * @param Quiz $quiz Route model binding
		 * @return \Illuminate\Http\JsonResponse
		 */
		public function submitAnswer(Request $request, Quiz $quiz)
		{
			$validator = Validator::make($request->all(), [
				// quiz_id comes from the route model binding ($quiz)
				'selected_index' => 'required|integer|min:0|max:3', // Assuming 4 answers (0-3)
			]);

			if ($validator->fails()) {
				return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
			}

			$selectedIndex = $request->input('selected_index');
			$sessionId = Session::getId();

			// Security check: Ensure quiz belongs to the current session
			if ($quiz->session_id !== $sessionId) {
				Log::warning("Session mismatch for Quiz ID {$quiz->id}. Expected {$quiz->session_id}, got {$sessionId}");
				return response()->json(['success' => false, 'message' => 'Invalid session for this quiz.'], 403); // Forbidden
			}

			// Prevent answering the same quiz multiple times in a session
			$alreadyAnswered = UserAnswer::where('quiz_id', $quiz->id)
				->where('session_id', $sessionId)
				->exists();

			if ($alreadyAnswered) {
				Log::warning("Attempt to re-answer Quiz ID {$quiz->id} by Session {$sessionId}");
				// Return previous result? Or just an error? Error is simpler.
				return response()->json(['success' => false, 'message' => 'Quiz already answered in this session.'], 409); // Conflict
			}

			Log::info("Submitting answer for Quiz ID: {$quiz->id}, Index: {$selectedIndex}, Session: {$sessionId}");

			// Retrieve answers WITH audio URLs/paths from the Quiz model's processing
			$answers = $quiz->answers; // Model casts to array

			if (!isset($answers[$selectedIndex])) {
				return response()->json(['success' => false, 'message' => 'Invalid answer index provided.'], 400);
			}

			$selectedAnswer = $answers[$selectedIndex];

			// Determine correctness and find the correct index
			$wasCorrect = $selectedAnswer['is_correct'] === true;
			$correctIndex = -1;
			foreach ($answers as $index => $answer) {
				if ($answer['is_correct'] === true) {
					$correctIndex = $index;
					break;
				}
			}

			// Feedback text and audio URL from the processed answer data
			$feedbackText = $selectedAnswer['feedback'];
			// Use the URL stored in the 'answers' array (added by processAnswersWithTTS)
			$feedbackAudioUrl = $selectedAnswer['feedback_audio_url'] ?? null;


			// Save User Answer
			UserAnswer::create([
				'quiz_id' => $quiz->id,
				'subject_id' => $quiz->subject_id, // Store subject link
				'session_id' => $sessionId,
				'selected_answer_index' => $selectedIndex,
				'was_correct' => $wasCorrect,
				'attempt_number' => 1, // Basic attempt tracking
			]);

			Log::info("User answer saved for Quiz ID {$quiz->id}. Correct: " . ($wasCorrect ? 'Yes' : 'No'));

			// Return feedback to frontend via JSON
			return response()->json([
				'success' => true,
				'was_correct' => $wasCorrect,
				'correct_index' => $correctIndex,
				'feedback_text' => $feedbackText,
				'feedback_audio_url' => $feedbackAudioUrl, // Send URL from processed answer
			]);
		}

		/**
		 * Generate the NEXT quiz question based on history and difficulty (AJAX).
		 *
		 * @param Request $request
		 * @param Subject $subject Route model binding
		 * @return \Illuminate\Http\JsonResponse
		 */
		public function generateNextQuiz(Request $request, Subject $subject)
		{
			$sessionId = Session::getId();
			$llm = $request->input('llm', env('DEFAULT_LLM')); // Allow LLM override from request?

			// Security check
			if ($subject->session_id !== $sessionId) {
				Log::warning("Attempt to get next quiz for subject ID {$subject->id} from different session.");
				return response()->json(['success' => false, 'message' => 'Invalid session for this subject.'], 403);
			}

			Log::info("Generating next quiz question for Subject ID: {$subject->id}, Session: {$sessionId}");

			// --- Incorporate History & Difficulty ---
			$previousAnswers = UserAnswer::where('subject_id', $subject->id)
				->where('session_id', $sessionId)
				->orderBy('created_at', 'desc')
				->get();

			$lastAnswerCorrect = null;
			$lastQuiz = null; // Initialize lastQuiz

			if ($previousAnswers->isNotEmpty()) {
				$lastUserAnswer = $previousAnswers->first();
				$lastAnswerCorrect = $lastUserAnswer->was_correct;
				// Find the specific Quiz model related to the last answer
				$lastQuiz = Quiz::find($lastUserAnswer->quiz_id);
			} else {
				// This case shouldn't happen if called via "Next" button, but handle defensively
				Log::error("generateNextQuiz called for Subject {$subject->id} but no previous answers found.");
				return response()->json(['success' => false, 'message' => 'Cannot generate next quiz without previous history.'], 500);
			}

			$questionHistory = [];
			$difficultyInstruction = "Generate a new multiple-choice question about the subject: '{$subject->name}'.";
			$currentDifficultyLevel = 1; // Base difficulty

			if ($lastQuiz) {
				// Check if we have a previous quiz context
				$currentDifficultyLevel = $lastQuiz->difficulty_level ?? 1;

				if ($lastAnswerCorrect === true) {
					$difficultyInstruction .= " The user answered the previous question correctly. Generate a question of SLIGHTLY HIGHER difficulty (level " . ($currentDifficultyLevel + 1) . ").";
					$currentDifficultyLevel++; // Target next level
				} elseif ($lastAnswerCorrect === false) {
					$difficultyInstruction .= " The user answered the previous question INCORRECTLY. Generate a SIMPLER question (level " . max(1, $currentDifficultyLevel - 1) . ") focusing on the basics.";
					// Add context about the first wrong answer on the *previous* quiz attempt
					$firstUserAnswerForLastQuiz = UserAnswer::where('quiz_id', $lastQuiz->id)
						->where('session_id', $sessionId)
						->orderBy('created_at', 'asc') // Get the first attempt
						->first();

					if ($firstUserAnswerForLastQuiz && !$firstUserAnswerForLastQuiz->was_correct) {
						$firstWrongIndex = $firstUserAnswerForLastQuiz->selected_answer_index;
						$lastQuizAnswers = $lastQuiz->answers; // Get the answers array from the last quiz model
						// Ensure answers array and index exist before accessing
						if (isset($lastQuizAnswers[$firstWrongIndex]['text'])) {
							$firstWrongAnswerText = $lastQuizAnswers[$firstWrongIndex]['text'];
							$firstWrongAnswerTextEscaped = addslashes($firstWrongAnswerText); // Basic escaping for prompt
							$difficultyInstruction .= " The user's first incorrect selection for that question was: '{$firstWrongAnswerTextEscaped}'. Avoid similar pitfalls or clarify the concept related to that wrong answer.";
						}
					}
					$currentDifficultyLevel = max(1, $currentDifficultyLevel - 1); // Target lower level, min 1
				}

				// Add some history context (limit size)
				$historyLimit = 2;
				foreach ($previousAnswers->take($historyLimit) as $answer) {
					$quizForHistory = Quiz::find($answer->quiz_id); // Fetch specifically for history if needed
					if ($quizForHistory) {
						$correctness = $answer->was_correct ? 'Correct' : 'Incorrect';
						$qTextShort = Str::limit($quizForHistory->question_text, 75);
						$questionHistory[] = "[{$correctness}] Q: {$qTextShort}";
					}
				}
				if (!empty($questionHistory)) {
					$difficultyInstruction .= "\nPrevious interaction summary (most recent first):\n" . implode("\n", $questionHistory);
				}
			} else {
				// Should not happen if logic is correct, but prevents errors
				$difficultyInstruction .= " This seems to be the first question (level 1 difficulty).";
			}


			// --- LLM Prompt for Quiz Generation (Same structure as first quiz) ---
			$systemPromptQuizGen = <<<PROMPT
You are an AI quiz master creating educational multiple-choice questions based on a given subject and context.
The user is learning about: '{$subject->name}'.
The introductory text provided was: '{$subject->main_text}' // You might want to omit this for later questions to avoid repetition

{$difficultyInstruction} // Inject the dynamic instructions here

Your output MUST be a valid JSON object with the following structure:
{
  "question": "The text of the multiple-choice question?",
  "answers": [
    { "text": "Answer option 1", "is_correct": false, "feedback": "Brief explanation why this answer is wrong." },
    { "text": "Answer option 2", "is_correct": true, "feedback": "Brief explanation why this answer is correct." },
    { "text": "Answer option 3", "is_correct": false, "feedback": "Brief explanation why this answer is wrong." },
    { "text": "Answer option 4", "is_correct": false, "feedback": "Brief explanation why this answer is wrong." }
  ]
}

RULES:
- There must be exactly 4 answer options.
- Exactly ONE answer must have "is_correct": true.
- Keep question and answer text concise.
- Feedback should be helpful and educational (1-2 sentences).
- Ensure the entire output is ONLY the JSON object, nothing before or after.
PROMPT;
			$chatHistoryQuizGen = [];
			$quizResult = MyHelper::llm_no_tool_call($llm, $systemPromptQuizGen, $chatHistoryQuizGen, true);

			// --- Validate LLM Response ---
			if (!MyHelper::isValidQuizResponse($quizResult)) { // Use helper
				$errorMsg = $quizResult['error'] ?? 'LLM did not return a valid quiz structure.';
				Log::error("LLM Next Quiz Gen Error: " . $errorMsg);
				return response()->json(['success' => false, 'message' => 'Failed to generate next quiz question. ' . $errorMsg], 500);
			}

			Log::info("Next quiz question generated successfully.");
			$quizData = $quizResult;

			// --- Generate TTS for the NEW Question ---
			$questionAudioUrl = null; // Store URL
			$nextIdentifier = 'next_' . Str::random(4); // Unique identifier
			$questionTtsResult = MyHelper::text2speech(
				$quizData['question'],
				env('DEFAULT_TTS_VOICE', 'en-US-Wavenet-A'),
				'en-US',
				'question_' . $subject->id . '_' . $nextIdentifier . '_' . Str::slug(Str::limit($quizData['question'], 20))
			);
			if ($questionTtsResult && isset($questionTtsResult['fileUrl'])) {
				$questionAudioUrl = $questionTtsResult['fileUrl']; // Store the URL
				Log::info("Generated TTS for next question {$nextIdentifier}: {$questionAudioUrl}");
			} else {
				Log::warning("Failed to generate TTS for next question {$nextIdentifier}");
			}

			// --- Process Answers (Generate TTS for feedback & answers) ---
			$processedAnswers = Quiz::processAnswersWithTTS($quizData['answers'], $subject->id, $nextIdentifier);

			// --- Save NEW Quiz to Database ---
			$newQuiz = Quiz::create([
				'subject_id' => $subject->id,
				'question_text' => $quizData['question'],
				'question_audio_path' => $questionAudioUrl, // Store URL
				'answers' => $processedAnswers, // Store answers with TTS paths/urls
				'difficulty_level' => $currentDifficultyLevel, // Store calculated difficulty
				'session_id' => $sessionId, // Link to session
			]);

			Log::info("Next Quiz record created with ID: {$newQuiz->id}");

			// --- Return NEW quiz data to frontend ---
			// We need to re-fetch or build the answer structure expected by the frontend JS
			$frontendAnswers = [];
			foreach ($processedAnswers as $index => $pa) {
				$frontendAnswers[] = [
					'text' => $pa['text'] ?? '',
					'answer_audio_url' => $pa['answer_audio_url'] ?? null, // Send URL
					// Don't send feedback/correctness info for the *next* question yet
				];
			}


			return response()->json([
				'success' => true,
				'message' => 'Next quiz question generated.',
				'quiz_id' => $newQuiz->id,
				'question_text' => $newQuiz->question_text,
				'question_audio_url' => $questionAudioUrl, // Send question audio URL
				'answers' => $frontendAnswers, // Send only necessary fields for display
			]);
		}
	}
