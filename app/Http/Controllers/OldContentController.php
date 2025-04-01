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
	use Illuminate\Database\Eloquent\ModelNotFoundException;

	// Added for error handling


	class OldContentController extends Controller
	{
		// Display the subject input form
		public function showSubjectInput()
		{
			$llms = MyHelper::checkLLMsJson();
			return view('subject_input', compact('llms'));
		}

		// Handle subject submission, generate initial content, redirect to content view
		public function startLearning(Request $request)
		{
			$validator = Validator::make($request->all(), [
				'subject' => 'required|string|max:150',
				'llm' => 'nullable|string|max:100',
			]);

			if ($validator->fails()) {
				return redirect()->back()
					->withErrors($validator)
					->withInput();
			}

			$userSubject = $request->input('subject');
			$llm = $request->input('llm', env('DEFAULT_LLM'));
			$sessionId = Session::getId(); // Important for tracking session

			Log::info("Starting content generation for subject: '{$userSubject}' by session: {$sessionId}");

			// --- Generate Text ---
			$systemPromptTextGen = <<<PROMPT
        You are an AI assistant creating educational micro-content. Generate a short introductory text about the subject provided by the user. The output MUST be a JSON object containing:
        1. "title": A concise and engaging title (max 10 words).
        2. "main_text": A brief introductory paragraph (3-5 sentences, approx 50-100 words) explaining the core concept of the subject. Keep it simple and clear.
        3. "image_prompt_idea": A short phrase or idea (max 15 words) that captures the essence of the subject visually, suitable for an AI image generator. Example Input: Photosynthesis Example Output: { "title": "Photosynthesis: Plants Making Food", "main_text": "Photosynthesis is the amazing process plants use to turn sunlight, water, and carbon dioxide into their food (sugar) and oxygen. It happens in special parts called chloroplasts, using a green pigment called chlorophyll. This process is vital for life on Earth, providing food and the air we breathe.", "image_prompt_idea": "Sunlight hitting green leaves, energy conversion" } Ensure the output is ONLY the valid JSON object and nothing else.
        PROMPT;
			$chatHistoryTextGen = [['role' => 'user', 'content' => $userSubject]];
			$textResult = MyHelper::llm_no_tool_call($llm, $systemPromptTextGen, $chatHistoryTextGen, true);

			if (isset($textResult['error']) || !isset($textResult['title'], $textResult['main_text'], $textResult['image_prompt_idea'])) {
				$errorMsg = $textResult['error'] ?? 'LLM did not return the expected JSON structure for text generation.';
				Log::error("LLM Text Gen Error: " . $errorMsg);
				// Redirect back with error
				return redirect()->route('home')
					->with('error', 'Failed to generate initial text content: ' . $errorMsg);
			}

			Log::info("Initial text content generated successfully.");
			$title = $textResult['title'];
			$mainText = $textResult['main_text'];
			$imagePromptIdea = $textResult['image_prompt_idea'];

			// --- Generate Image ---
			$imageResult = MyHelper::makeImage($imagePromptIdea, env('DEFAULT_IMAGE_MODEL', 'fal-ai/flux/schnell'), 'landscape_16_9');
			$generatedImageModel = null;
			if ($imageResult['success'] && isset($imageResult['image_guid'])) {
				Log::info("Image generated successfully. GUID: " . $imageResult['image_guid']);
				// Fetch the model to get its ID
				$generatedImageModel = GeneratedImage::where('image_guid', $imageResult['image_guid'])->first();
			} else {
				Log::error("Image generation failed: " . ($imageResult['message'] ?? 'Unknown error'));
				// Optionally inform user image failed? For now, just log it.
			}

			// --- Save Subject ---
			$subject = Subject::create([
				'name' => $userSubject,
				'title' => $title,
				'main_text' => $mainText,
				'image_prompt_idea' => $imagePromptIdea,
				'session_id' => $sessionId, // Associate with session
				'generated_image_id' => $generatedImageModel?->id, // Link image if created
				'initial_video_path' => null, // Will be filled by video generation
				'initial_video_url' => null,
			]);

			// If image model was created, link it back to the subject
			if ($generatedImageModel && !$generatedImageModel->subject_id) {
				$generatedImageModel->subject_id = $subject->id;
				$generatedImageModel->save();
			}

			Log::info("Subject record created with ID: {$subject->id}");

			// --- Initiate Video Generation (Async - no waiting here) ---
			// Use job queue later for robustness, for now direct call
			$videoResult = MyHelper::text2video(
				$mainText,
				env('DEFAULT_FACE_URL'),
				env('DEFAULT_TTS_VOICE', 'en-US-Studio-O')
			);

			if ($videoResult['success'] && isset($videoResult['video_path'], $videoResult['video_url'])) {
				// Update subject immediately if path/URL available (sync generation assumed here)
				$subject->initial_video_path = $videoResult['video_path'];
				$subject->initial_video_url = $videoResult['video_url'];
				$subject->save();
				Log::info("Initial video path/URL saved for Subject ID: {$subject->id}");
			} else {
				// Log failure, but don't block the user
				Log::error("Failed to start/get initial text video generation: " . ($videoResult['message'] ?? 'Unknown error'));
			}


			// Redirect to the content display page
			return redirect()->route('content.show', $subject->id);
		}


		// Display the generated content
		public function showContent(Subject $subject)
		{
			// Ensure the subject belongs to the current session for security
			if ($subject->session_id !== Session::getId()) {
				Log::warning("Attempt to access subject ID {$subject->id} from different session.");
				// Decide action: show error, redirect home?
				return redirect()->route('home')->with('error', 'Content not found or session expired.');
			}

			// Eager load relations if needed often (optional optimization)
			$subject->load('generatedImage');

//			dd($subject);

			return view('content_display', compact('subject'));
		}


		// Generate the FIRST quiz for a subject and redirect to quiz view
		public function generateFirstQuiz(Subject $subject)
		{
			$sessionId = Session::getId();
			// Ensure the subject belongs to the current session
			if ($subject->session_id !== $sessionId) {
				Log::warning("Attempt to start quiz for subject ID {$subject->id} from different session.");
				return redirect()->route('content.show', $subject->id)->with('error', 'Cannot start quiz: Session mismatch.');
			}

			Log::info("Generating first quiz for Subject ID: {$subject->id}, Session: {$sessionId}");

			// Simplified context for the first question
			$difficultyInstruction = "Generate a new multiple-choice question about the subject: '{$subject->name}'. This is the first question (level 1 difficulty).";
			$llm = env('DEFAULT_LLM'); // Use default LLM for first quiz

			// --- LLM Prompt for Quiz Generation (similar to original generateQuiz) ---
			$systemPromptQuizGen = <<<PROMPT
         You are an AI quiz master creating educational multiple-choice questions based on a given subject and context.
         The user is learning about: '{$subject->name}'.
         The introductory text provided was: '{$subject->main_text}'

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

			$chatHistoryQuizGen = []; // No user message needed
			$quizResult = MyHelper::llm_no_tool_call($llm, $systemPromptQuizGen, $chatHistoryQuizGen, true);

			// --- Validate LLM Response ---
			if (!$this->isValidQuizResponse($quizResult)) {
				$errorMsg = $quizResult['error'] ?? 'LLM did not return a valid quiz structure.';
				Log::error("LLM First Quiz Gen Error: " . $errorMsg);
				return redirect()->route('content.show', $subject->id)->with('error', 'Failed to generate the first quiz question. ' . $errorMsg);
			}

			Log::info("First quiz question generated successfully.");
			$quizData = $quizResult;
			$currentDifficultyLevel = 1; // First question is level 1

			// --- Generate TTS for Question Text ---
			$questionAudioPath = null;
			$questionTtsResult = MyHelper::text2speech(
				$quizData['question'],
				env('DEFAULT_TTS_VOICE', 'en-US-Wavenet-A'), // Use appropriate voice
				'en-US',
				'question_' . $subject->id . '_initial_' . Str::slug(Str::limit($quizData['question'], 20))
			);
			if ($questionTtsResult && isset($questionTtsResult['fileUrl'])) {
				$questionAudioPath = $questionTtsResult['fileUrl'];
				Log::info("Generated TTS for initial question: {$questionAudioPath}");
			} else {
				Log::warning("Failed to generate TTS for initial question");
			}

			// --- Process Answers (Generate TTS for feedback) ---
			$processedAnswers = Quiz::processAnswersWithTTS($quizData['answers'], $subject->id, 'initial');

			// --- Save Quiz to Database ---
			$quiz = Quiz::create([
				'subject_id' => $subject->id,
				'question_text' => $quizData['question'],
				'question_audio_path' => $questionAudioPath,
				'answers' => array_map(function ($answer) { // Only send necessary fields
					return [
						'text' => $answer['text'],
						'answer_audio_url' => $answer['answer_audio_url'] ?? null,
					];
				}, $processedAnswers),
				'difficulty_level' => $currentDifficultyLevel,
				'session_id' => $sessionId, // Link quiz to session
			]);

			Log::info("First Quiz record created with ID: {$quiz->id}");

			// Redirect to the quiz display page
			return redirect()->route('quiz.show', $subject->id);
		}

		// Display the current quiz question for the subject
		public function showQuiz(Subject $subject)
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
				return redirect()->route('content.show', $subject->id)->with('info', 'Generate the quiz first.');
			}

			// Eager load subject's image for fallback visuals in quiz
			$subject->load('generatedImage');

			//dd($quiz);

			return view('quiz_display', compact('subject', 'quiz'));
		}

		// Submit an answer (AJAX)
		public function submitAnswer(Request $request, Quiz $quiz) // Use route model binding for Quiz
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

			$answers = $quiz->answers; // Already decoded by model casting
			if (!isset($answers[$selectedIndex])) {
				return response()->json(['success' => false, 'message' => 'Invalid answer index provided.'], 400);
			}

			$selectedAnswer = $answers[$selectedIndex];
			$wasCorrect = $selectedAnswer['is_correct'] === true;
			$feedbackText = $selectedAnswer['feedback'];

			// Use the accessor/helper to get the correct URL (handles potential null path)
			$feedbackAudioUrl = $quiz->getFeedbackAudioUrl($selectedIndex);


			$correctIndex = -1;
			foreach ($answers as $index => $answer) {
				if ($answer['is_correct'] === true) {
					$correctIndex = $index;
					break;
				}
			}

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

		// Generate the NEXT quiz question (AJAX)
		public function generateNextQuiz(Request $request, Subject $subject) // Use route model binding for Subject
		{
			$sessionId = Session::getId();
			$llm = $request->input('llm', env('DEFAULT_LLM')); // Allow LLM override from request?

			// Security check
			if ($subject->session_id !== $sessionId) {
				Log::warning("Attempt to get next quiz for subject ID {$subject->id} from different session.");
				return response()->json(['success' => false, 'message' => 'Invalid session for this subject.'], 403);
			}

			Log::info("Generating next quiz question for Subject ID: {$subject->id}, Session: {$sessionId}");

			// --- Incorporate History & Difficulty (Copied/adapted from original generateQuiz) ---
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

			if ($lastQuiz) { // Check if we have a previous quiz context
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
							$firstWrongAnswerTextEscaped = addslashes($firstWrongAnswerText);
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
         The introductory text provided was: '{$subject->main_text}'

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
			if (!$this->isValidQuizResponse($quizResult)) {
				$errorMsg = $quizResult['error'] ?? 'LLM did not return a valid quiz structure.';
				Log::error("LLM Next Quiz Gen Error: " . $errorMsg);
				return response()->json(['success' => false, 'message' => 'Failed to generate next quiz question. ' . $errorMsg], 500);
			}

			Log::info("Next quiz question generated successfully.");
			$quizData = $quizResult;

			// --- Generate TTS for the NEW Question ---
			$questionAudioPath = null;
			$nextIdentifier = 'next_' . Str::random(4); // Unique identifier
			$questionTtsResult = MyHelper::text2speech(
				$quizData['question'],
				env('DEFAULT_TTS_VOICE', 'en-US-Wavenet-A'),
				'en-US',
				'question_' . $subject->id . '_' . $nextIdentifier . '_' . Str::slug(Str::limit($quizData['question'], 20))
			);
			if ($questionTtsResult && isset($questionTtsResult['fileUrl'])) {
				$questionAudioPath = $questionTtsResult['fileUrl'];
				Log::info("Generated TTS for next question {$nextIdentifier}: {$questionAudioPath}");
			} else {
				Log::warning("Failed to generate TTS for next question {$nextIdentifier}");
			}

			// --- Process Answers (Generate TTS for feedback) ---
			$processedAnswers = Quiz::processAnswersWithTTS($quizData['answers'], $subject->id, $nextIdentifier);


			// --- Save NEW Quiz to Database ---
			$newQuiz = Quiz::create([
				'subject_id' => $subject->id,
				'question_text' => $quizData['question'],
				'question_audio_path' => $questionAudioPath,
				'answers' => $processedAnswers, // Store answers with TTS paths/urls
				'difficulty_level' => $currentDifficultyLevel, // Store calculated difficulty
				'session_id' => $sessionId, // Link to session
			]);

			Log::info("Next Quiz record created with ID: {$newQuiz->id}");

			$newQuiz->refresh();

			// --- Return NEW quiz data to frontend ---
			return response()->json([
				'success' => true,
				'message' => 'Next quiz question generated.',
				'quiz_id' => $newQuiz->id,
				'question_text' => $newQuiz->question_text,
				'answers' => array_map(function ($answer) { // Only send necessary fields
					return [
						'text' => $answer['text'],
						'answer_audio_url' => $answer['answer_audio_url'] ?? null,
					];
				}, $processedAnswers),
			]);
		}

		// --- Helper function to validate quiz structure ---
		private function isValidQuizResponse($quizResult): bool
		{
			if (
				!$quizResult || isset($quizResult['error']) ||
				!isset($quizResult['question']) || !is_string($quizResult['question']) ||
				!isset($quizResult['answers']) || !is_array($quizResult['answers']) ||
				count($quizResult['answers']) !== 4
			) {
				Log::debug("Basic quiz structure validation failed.", ['result' => $quizResult]);
				return false;
			}

			$correctCount = 0;
			foreach ($quizResult['answers'] as $answer) {
				if (
					!isset($answer['text']) || !is_string($answer['text']) ||
					!isset($answer['is_correct']) || !is_bool($answer['is_correct']) ||
					!isset($answer['feedback']) || !is_string($answer['feedback'])
				) {
					Log::debug("Quiz answer structure validation failed.", ['answer' => $answer]);
					return false; // Incomplete answer structure
				}
				if ($answer['is_correct'] === true) {
					$correctCount++;
				}
			}

			if ($correctCount !== 1) {
				Log::debug("Quiz validation failed: Incorrect number of correct answers.", ['count' => $correctCount, 'answers' => $quizResult['answers']]);
				return false; // Must have exactly one correct answer
			}

			return true; // Passed all checks
		}

	} // End of OldContentController class
