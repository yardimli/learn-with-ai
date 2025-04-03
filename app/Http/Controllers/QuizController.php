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
	use Illuminate\Support\Facades\Validator;
	use Illuminate\Support\Str;

	class QuizController extends Controller
	{
		public function show($sessionId)
		{
			$subject = Subject::where('session_id', $sessionId)->first();

			// Check if subject exists
			if (!$subject) {
				Log::warning("Subject not found for session ID: {$sessionId}");
				return redirect()->route('home')->with('error', 'Content not found or session expired.');
			}

			$subject_id = $subject->id;

			// Find the LATEST quiz associated with this subject
			$quiz = Quiz::where('subject_id', $subject_id)
				->orderBy('created_at', 'desc')
				->first();

			if (!$quiz) {
				Log::warning("No quiz found for subject ID {$subject_id} and session {$sessionId}. Redirecting to content.");
				return redirect()->route('content.show', $subject_id)->with('info', 'Generate the quiz first.');
			}

			$isAlreadyAnsweredCorrectly = UserAnswer::where('quiz_id', $quiz->id)
				->where('subject_id', $subject_id) // Check within the same session
				->where('was_correct', true)
				->exists();

			$subject->load('generatedImage');

			// dd($quiz, $subject); // For debugging if needed

			return view('quiz_display', compact('subject', 'quiz', 'isAlreadyAnsweredCorrectly'));
		}

		public function submitAnswer(Request $request, Quiz $quiz)
		{
			$validator = Validator::make($request->all(), [
				'selected_index' => 'required|integer|min:0|max:3', // Assuming 4 answers (0-3)
			]);

			if ($validator->fails()) {
				return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
			}

			$subject_id = $quiz->subject_id; // Get session ID from the quiz

			$selectedIndex = $request->input('selected_index');

			$alreadyAnsweredCorrectly = UserAnswer::where('quiz_id', $quiz->id)
				->where('subject_id', $subject_id)
				->where('was_correct', true) // Only prevent re-answering if *correct*
				->exists();

			if ($alreadyAnsweredCorrectly) {
				Log::warning("Attempt to re-answer already correctly answered Quiz ID {$quiz->id} by Subject ID {$subject_id}");
				// If already correct, maybe return the existing correct result?
				return response()->json(['success' => false, 'message' => 'Quiz already answered correctly in this session.'], 409); // Conflict
			}


			Log::info("Submitting answer for Quiz ID: {$quiz->id}, Index: {$selectedIndex}, Subject ID: {$subject_id}");

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

			// Save User Answer - handle potential retries
			// Find previous attempt for THIS quiz in THIS session
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
				'attempt_number' => $attemptNumber, // Track attempts
			]);

			Log::info("User answer saved for Quiz ID {$quiz->id}. Attempt: {$attemptNumber}. Correct: " . ($wasCorrect ? 'Yes' : 'No'));

			// Return feedback to frontend via JSON
			return response()->json([
				'success' => true,
				'was_correct' => $wasCorrect,
				'correct_index' => $correctIndex,
				'feedback_text' => $feedbackText,
				'feedback_audio_url' => $feedbackAudioUrl, // Send URL from processed answer
			]);
		}

		public function generateNextQuiz(Request $request, $sessionId)
		{
			$subject = Subject::where('session_id', $sessionId)->first();
			$llm = $request->input('llm', env('DEFAULT_LLM')); // Allow LLM override from request?

			if (!$subject) {
				Log::warning("Subject not found for session ID: {$sessionId}");
				return redirect()->route('home')->with('error', 'Content not found or session expired.');
			}

			// Find the last quiz created specifically for THIS subject
			$lastQuiz = Quiz::where('subject_id', $subject->id)
				->orderBy('created_at', 'desc')
				->first();

			if (!$lastQuiz) {
				Log::error("Could not find the last Quiz model for Subject {$subject->id}.");
				return response()->json(['success' => false, 'message' => 'Error retrieving previous quiz context.'], 500);
			}

			// --- Incorporate History & Difficulty ---
			// Get the MOST RECENT answer for this subject/session to determine the last outcome
			$hasUserAnswer = UserAnswer::where('subject_id', $subject->id)
				->where('quiz_id', $lastQuiz->id)
				->orderBy('created_at', 'desc')
				->first();

			if (!$hasUserAnswer) {
				Log::error("generateNextQuiz called for Subject {$subject->id} but no previous answers found.");
				return response()->json(['success' => false, 'message' => 'Cannot generate next quiz without previous history.'], 500);
			}


			Log::info("Generating next quiz question for Subject ID: {$subject->id}, Session: {$sessionId}");

			// Check if there is a wrong answer for this quiz in th user_answers
			$lastQuizHadIncorrectAnswer = UserAnswer::where('quiz_id', $lastQuiz->id)
				->where('subject_id', $subject->id)
				->where('was_correct', false)
				->exists();

			$lastAnswerCorrect = !$lastQuizHadIncorrectAnswer;

			// --- Determine Difficulty ---
			$questionHistory = [];
			$difficultyInstruction = "Generate a new multiple-choice question about the subject: '{$subject->name}'.";
			$currentDifficultyLevel = $lastQuiz->difficulty_level ?? 1; // Start from last quiz's level

			if ($lastAnswerCorrect) {
				$difficultyInstruction .= " The user answered the previous question correctly. Generate a question of SLIGHTLY HIGHER difficulty (level " . ($currentDifficultyLevel + 1) . ").";
				$currentDifficultyLevel++; // Target next level
			} else {
				$difficultyInstruction .= " The user answered the previous question INCORRECTLY. Generate a SIMPLER question (level " . max(1, $currentDifficultyLevel - 1) . ") focusing on the basics. Avoid similar pitfalls or clarify the concept related to that wrong answer.";

				$currentDifficultyLevel = max(1, $currentDifficultyLevel - 1); // Target lower level, min 1
			}


			// --- Add History Context ---
			$historyLimit = 2; // Limit history size
			$previousAnswersForHistory = UserAnswer::where('subject_id', $subject->id)
				->orderBy('created_at', 'desc')
				->with('quiz:id,question_text,answers')
				->take($historyLimit + 1)
				->get();


			// Exclude the very last answer itself from the history prompt context if needed
			// For simplicity, we include it for now. Adjust if needed.
			foreach ($previousAnswersForHistory->take($historyLimit) as $answer) {
				if ($answer->quiz) { // Check if quiz relation loaded
					$correctness = $answer->was_correct ? 'Correct' : 'Incorrect';
					$qTextShort = Str::limit($answer->quiz->question_text, 75);
					$qAnswerText = $answer->quiz->answers[$answer->selected_answer_index]['text'] ?? 'Unknown';
					$questionHistory[] = "[{$correctness}] Q: {$qTextShort} A: $qAnswerText (Attempt: {$answer->attempt_number})";
				}
			}

			if (!empty($questionHistory)) {
				$difficultyInstruction .= "\nPrevious interaction summary (most recent first):\n" . implode("\n", $questionHistory);
			}


			// --- LLM Prompt for Quiz Generation (Same structure as first quiz) ---
			$systemPromptQuizGen = <<<PROMPT
You are an AI quiz master creating educational multiple-choice questions based on a given subject and context.
The user is learning about: '{$subject->name}'.
The introductory text provided was: '{$subject->main_text}'

{$difficultyInstruction}

Your output MUST be a valid JSON object with the following structure:
{
  "question": "The text of the multiple-choice question?",
  "image_prompt_idea": "Short visual description related ONLY to the question itself (max 15 words).",
  "answers": [
    { "text": "Answer option 1", "is_correct": false, "feedback": "Brief explanation why this answer is wrong." },
    { "text": "Answer option 2", "is_correct": true,  "feedback": "Brief explanation why this answer is correct." },
    { "text": "Answer option 3", "is_correct": false, "feedback": "Brief explanation why this answer is wrong." },
    { "text": "Answer option 4", "is_correct": false, "feedback": "Brief explanation why this answer is wrong." }
  ]
}

RULES:
- The Question must be something explained in the introductory text.
- There must be exactly 4 answer options.
- Exactly ONE answer must have "is_correct": true.
- Keep question and answer text concise.
- Provide a relevant image_prompt_idea for the question (max 15 words).
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

			// --- Generate Image for the NEW Question ---
			$imagePromptIdea = $quizData['image_prompt_idea'];
			$nextIdentifier = 'next_' . Str::random(4); // Unique identifier for TTS/Image
			$questionImageId = 0;
			$questionImageUrl = null; // Store URL for response

			if (!empty($imagePromptIdea)) {
				Log::info("Generating image for next quiz question ({$nextIdentifier}) with prompt: '{$imagePromptIdea}'");
				$imageResult = MyHelper::makeImage( $imagePromptIdea, env('DEFAULT_IMAGE_MODEL', 'fal-ai/flux/schnell'),  'landscape_16_9');

				if ($imageResult['success'] && isset($imageResult['image_guid'])) {
					Log::info("Next quiz question image generated successfully. GUID: " . $imageResult['image_guid']);
					$questionImageModel = GeneratedImage::where('image_guid', $imageResult['image_guid'])->first();
					if ($questionImageModel) {
						$questionImageId = $questionImageModel->id;
						$questionImageUrl = $questionImageModel->mediumUrl; // Get URL for response
					}
				} else {
					Log::error("Next quiz question image generation failed: " . ($imageResult['message'] ?? 'Unknown error'));
				}
			} else {
				Log::warning("LLM did not provide an image_prompt_idea for the next quiz question ({$nextIdentifier}).");
			}


			// --- Generate TTS for the NEW Question ---
			$ttsEngine = env('DEFAULT_TTS_ENGINE', 'google');
			$ttsVoice = ($ttsEngine === 'openai')
				? env('OPENAI_TTS_VOICE', 'alloy')
				: env('DEFAULT_TTS_VOICE', 'en-US-Wavenet-A');
			$ttsLanguageCode = 'en-US'; // Primarily for Google

			$questionAudioUrl = null; // Store URL
			$nextIdentifier = 'next_' . Str::random(4); // Unique identifier
			$questionTtsResult = MyHelper::text2speech(
				$quizData['question'],
				$ttsVoice,           // Use determined voice
				$ttsLanguageCode,    // Pass language code
				'question_' . $subject->id . '_' . $nextIdentifier . '_' . Str::slug(Str::limit($quizData['question'], 20)),
				$ttsEngine           // Pass determined engine
			);

			if ($questionTtsResult && isset($questionTtsResult['fileUrl'])) {
				$questionAudioUrl = $questionTtsResult['fileUrl']; // Store the URL
				Log::info("Generated TTS for next question {$nextIdentifier}: {$questionAudioUrl}");
			} else {
				Log::warning("Failed to generate TTS for next question {$nextIdentifier}");
			}

			// --- Process Answers (Generate TTS for feedback & answers) ---
			$processedAnswers = Quiz::processAnswersWithTTS(
				$quizData['answers'],
				$subject->id,
				$nextIdentifier,
				$ttsEngine,         // Pass engine
				$ttsVoice,          // Pass voice
				$ttsLanguageCode    // Pass lang code
			);

			// --- Save NEW Quiz to Database ---
			$newQuiz = Quiz::create([
				'subject_id' => $subject->id,
				'generated_image_id' => $questionImageId,
				'question_text' => $quizData['question'],
				'question_audio_path' => $questionAudioUrl, // Store URL
				'answers' => $processedAnswers, // Store answers with TTS paths/urls
				'difficulty_level' => $currentDifficultyLevel, // Store calculated difficulty
			]);

			Log::info("Next Quiz record created with ID: {$newQuiz->id}");

			// --- Return NEW quiz data to frontend ---
			// We need to re-fetch or build the answer structure expected by the frontend JS
			$frontendAnswers = [];
			foreach ($processedAnswers as $index => $pa) {
				// The Quiz model's getAnswerAudioUrl accessor can generate URLs if needed,
				// but processAnswersWithTTS now adds 'answer_audio_url' directly.
				$frontendAnswers[] = [
					'text' => $pa['text'] ?? '',
					'answer_audio_url' => $pa['answer_audio_url'] ?? null, // Send URL
				];
			}

			return response()->json([
				'success' => true,
				'message' => 'Next quiz question generated.',
				'quiz_id' => $newQuiz->id,
				'question_text' => $newQuiz->question_text,
				'question_audio_url' => $questionAudioUrl, // Send question audio URL
				'question_image_url' => $questionImageUrl, // Send question image URL
				'answers' => $frontendAnswers,
			]);
		}
	}
