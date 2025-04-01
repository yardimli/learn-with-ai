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

	class ContentController extends Controller
	{
		// Display the main view
		public function index()
		{
			// Fetch available LLMs for a dropdown (optional)
			$llms = MyHelper::checkLLMsJson();
			return view('welcome', compact('llms'));
		}

		// --- Step 1-4: Generate Initial Content ---
		public function generateInitialContent(Request $request)
		{
			$validator = Validator::make($request->all(), [
				'subject' => 'required|string|max:150',
				'llm' => 'nullable|string|max:100', // Optional: Let user choose LLM
			]);

			if ($validator->fails()) {
				return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
			}

			$userSubject = $request->input('subject');
			$llm = $request->input('llm', '');
			if ($llm === '') {
				$llm = env('DEFAULT_LLM');
			}
			$sessionId = Session::getId();

			Log::info("Starting content generation for subject: '{$userSubject}' by session: {$sessionId}");

			// --- Step 1 & 2: Generate Text using LLM ---
			$systemPromptTextGen = <<<PROMPT
You are an AI assistant creating educational micro-content.
Generate a short introductory text about the subject provided by the user.
The output MUST be a JSON object containing:
1.  "title": A concise and engaging title (max 10 words).
2.  "main_text": A brief introductory paragraph (3-5 sentences, approx 50-100 words) explaining the core concept of the subject. Keep it simple and clear.
3.  "image_prompt_idea": A short phrase or idea (max 15 words) that captures the essence of the subject visually, suitable for an AI image generator.

Example Input: Photosynthesis
Example Output:
{
  "title": "Photosynthesis: Plants Making Food",
  "main_text": "Photosynthesis is the amazing process plants use to turn sunlight, water, and carbon dioxide into their food (sugar) and oxygen. It happens in special parts called chloroplasts, using a green pigment called chlorophyll. This process is vital for life on Earth, providing food and the air we breathe.",
  "image_prompt_idea": "Sunlight hitting green leaves, energy conversion"
}

Ensure the output is ONLY the valid JSON object and nothing else.

PROMPT;

			$chatHistoryTextGen = [['role' => 'user', 'content' => $userSubject]];
			$textResult = MyHelper::llm_no_tool_call($llm, $systemPromptTextGen, $chatHistoryTextGen, true); // Expect JSON

			if (isset($textResult['error']) || !isset($textResult['title'], $textResult['main_text'], $textResult['image_prompt_idea'])) {
				$errorMsg = $textResult['error'] ?? 'LLM did not return the expected JSON structure for text generation.';
				Log::error("LLM Text Gen Error: " . $errorMsg);
				return response()->json(['success' => false, 'message' => 'Failed to generate initial text content. ' . $errorMsg], 500);
			}

			Log::info("Initial text content generated successfully.");
			$title = $textResult['title'];
			$mainText = $textResult['main_text'];
			$imagePromptIdea = $textResult['image_prompt_idea'];
			$textGenUsage = $textResult['_usage'] ?? ['prompt_tokens' => 0, 'completion_tokens' => 0];

			$imageResult = MyHelper::makeImage( $imagePromptIdea, env('DEFAULT_IMAGE_MODEL', 'fal-ai/flux/schnell'), 'landscape_16_9');

			$generatedImageModel = null;
			if ($imageResult['success'] && isset($imageResult['image_guid'])) {
				Log::info("Image generated successfully. GUID: " . $imageResult['image_guid']);

				$generatedImageModel = GeneratedImage::where('image_guid', $imageResult['image_guid'])->first();
				if (!$generatedImageModel) {
					Log::error("Image record not found in DB after generation for GUID: " . $imageResult['image_guid']);
					// Decide how to handle: proceed without image or fail? Let's proceed without saving link.
				}
			} else {
				Log::error("Image generation failed: " . ($imageResult['message'] ?? 'Unknown error'));
				// Proceed without image
			}

			// --- Step 4: Save Subject ---
			$subject = Subject::create([
				'name' => $userSubject,
				'title' => $title,
				'main_text' => $mainText,
				'image_prompt_idea' => $imagePromptIdea,
				'session_id' => $sessionId,
				'generated_image_id' => $generatedImageModel?->id,
				'initial_video_path' => null,
				'initial_video_url' => null,
			]);

			// If image model was created, link it back to the subject
			if ($generatedImageModel && !$generatedImageModel->subject_id) {
				$generatedImageModel->subject_id = $subject->id;
				$generatedImageModel->save();
			}


			Log::info("Subject record created with ID: {$subject->id}");

			// --- Step 3b: Initiate Video Generation (Async) ---
			$videoResult = MyHelper::text2video(
				$mainText,
				env('DEFAULT_FACE_URL'),
				env('DEFAULT_TTS_VOICE', 'en-US-Studio-O')
			);

			if ($videoResult['success']) {
				$subject->initial_video_path = $videoResult['video_path'] ?? null; // Store path if available
				$subject->initial_video_url = $videoResult['video_url'] ?? null; // Store URL if available
				$subject->save();
			} else {
				Log::error("Failed to start initial text video generation: " . ($videoResult['message'] ?? 'Unknown error'));
			}

			// --- Return initial data to frontend ---
			return response()->json([
				'success' => true,
				'message' => 'Initial content generated.',
				'subject_id' => $subject->id,
				'title' => $subject->title,
				'main_text' => $subject->main_text,
				'image_url' => $generatedImageModel?->getMediumUrlAttribute(),
				'initial_video_url' => $subject->initial_video_url,
				'usage' => [
					'text_gen' => $textGenUsage,
				]
			]);
		}


// --- Step 5, 6, 8: Generate Quiz Question ---
		public function generateQuiz(Request $request)
		{
			$validator = Validator::make($request->all(), [
				'subject_id' => 'required|exists:subjects,id',
				'llm' => 'nullable|string|max:100',
			]);

			if ($validator->fails()) {
				return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
			}

			$subjectId = $request->input('subject_id');
			$llm = $request->input('llm', '');
			if ($llm === '') {
				$llm = env('DEFAULT_LLM');
			}
			$sessionId = Session::getId();

			$subject = Subject::findOrFail($subjectId);
			if ($subject->session_id !== $sessionId) {
				return response()->json(['success' => false, 'message' => 'Subject does not belong to this session.'], 403);
			}


			Log::info("Generating quiz question for Subject ID: {$subjectId}, Session: {$sessionId}");

// --- Step 8: Incorporate History & Difficulty ---
			$previousAnswers = UserAnswer::where('subject_id', $subjectId)
				->where('session_id', $sessionId)
				->orderBy('created_at', 'desc')
				->get();

			$lastAnswerCorrect = $previousAnswers->isNotEmpty() ? $previousAnswers->first()->was_correct : null;
			$questionHistory = []; // Collect previous Q&A text for context
			$difficultyInstruction = "Generate a new multiple-choice question about the subject: '{$subject->name}'.";
			$currentDifficultyLevel = 1; // Base difficulty

			if ($previousAnswers->isNotEmpty()) {
				$lastQuiz = Quiz::find($previousAnswers->first()->quiz_id);
				$currentDifficultyLevel = $lastQuiz?->difficulty_level ?? 1;

				if ($lastAnswerCorrect === true) {
					$difficultyInstruction .= " The user answered the previous question correctly. Generate a question of SLIGHTLY HIGHER difficulty (level " . ($currentDifficultyLevel + 1) . ").";
					$currentDifficultyLevel++; // Target next level
				} elseif ($lastAnswerCorrect === false) {
					$difficultyInstruction .= " The user answered the previous question INCORRECTLY. Generate a SIMPLER question (level " . max(1, $currentDifficultyLevel - 1) . ") focusing on the basics.";
					$currentDifficultyLevel = max(1, $currentDifficultyLevel - 1); // Target lower level, min 1
				}

// Add some history context (limit size)
				$historyLimit = 2;
				foreach ($previousAnswers->take($historyLimit) as $answer) {
					$quiz = Quiz::find($answer->quiz_id);
					if ($quiz) {
						$correctness = $answer->was_correct ? 'Correct' : 'Incorrect';
						$questionHistory[] = "[{$correctness}] Q: {$quiz->question_text}";
					}
				}
				if (!empty($questionHistory)) {
					$difficultyInstruction .= "\nPrevious interaction summary:\n" . implode("\n", array_reverse($questionHistory)); // Show oldest first
				}

			} else {
				$difficultyInstruction .= " This is the first question (level 1 difficulty).";
			}


// --- LLM Prompt for Quiz Generation ---
			$systemPromptQuizGen = <<<PROMPT
	You are an AI quiz master creating educational multiple-choice questions based on a given subject and context.
	The user is learning about: '{$subject->name}'.
The introductory text provided was: '{$subject->main_text}'

{$difficultyInstruction}

Your output MUST be a valid JSON object with the following structure:
{
"question": "The text of the multiple-choice question?",
"answers": [
{
"text": "Answer option 1",
"is_correct": false,
"feedback": "Brief explanation why this answer is wrong."
},
{
"text": "Answer option 2",
"is_correct": true,
"feedback": "Brief explanation why this answer is correct."
},
{
"text": "Answer option 3",
"is_correct": false,
"feedback": "Brief explanation why this answer is wrong."
},
{
"text": "Answer option 4",
"is_correct": false,
"feedback": "Brief explanation why this answer is wrong."
}
]
}

RULES:
- There must be exactly 4 answer options.
- Exactly ONE answer must have "is_correct": true.
- Keep question and answer text concise.
- Feedback should be helpful and educational (1-2 sentences).
- Ensure the entire output is ONLY the JSON object, nothing before or after.
PROMPT;


			$chatHistoryQuizGen = []; // No user message needed as context is in system prompt
			$quizResult = MyHelper::llm_no_tool_call($llm, $systemPromptQuizGen, $chatHistoryQuizGen, true);

// --- Validate LLM Response ---
			if (isset($quizResult['error']) || !isset($quizResult['question']) || !isset($quizResult['answers']) || !is_array($quizResult['answers']) || count($quizResult['answers']) !== 4) {
				$errorMsg = $quizResult['error'] ?? 'LLM did not return the expected JSON structure for the quiz.';
				Log::error("LLM Quiz Gen Error: " . $errorMsg);
				Log::debug("Problematic Quiz Gen Response: ", $quizResult); // Log the faulty structure
				return response()->json(['success' => false, 'message' => 'Failed to generate quiz question. ' . $errorMsg], 500);
			}

// Further validation: Check for exactly one correct answer
			$correctCount = 0;
			foreach ($quizResult['answers'] as $answer) {
				if (!isset($answer['text'], $answer['is_correct'], $answer['feedback'])) {
					Log::error("LLM Quiz Gen Error: Answer structure is incomplete.");
					Log::debug("Problematic Quiz Answer Structure: ", $answer);
					return response()->json(['success' => false, 'message' => 'Generated quiz answers have an invalid structure.'], 500);
				}
				if ($answer['is_correct'] === true) {
					$correctCount++;
				}
			}

			if ($correctCount !== 1) {
				Log::error("LLM Quiz Gen Error: Generated quiz does not have exactly one correct answer (Found: {$correctCount}).");
				Log::debug("Problematic Quiz Answers Array: ", $quizResult['answers']);
				return response()->json(['success' => false, 'message' => 'Generated quiz has an invalid number of correct answers.'], 500);
			}


			Log::info("Quiz question generated successfully.");
			$quizData = $quizResult; // Contains question and answers array
			$quizUsage = $quizResult['_usage'] ?? ['prompt_tokens' => 0, 'completion_tokens' => 0];

// --- Step 9 (Deferred): Generate TTS for feedback and TTV for question ---
			$processedAnswers = $quizData['answers'];
			foreach ($processedAnswers as $index => &$answer) { // Use reference to modify
				$ttsResult = MyHelper::text2speech(
					$answer['feedback'],
					env('DEFAULT_TTS_VOICE', 'en-US-Wavenet-A'),
					'en-US',
					'feedback_' . $subjectId . '_' . Str::slug(Str::limit($answer['text'], 20))
				);
				if ($ttsResult && isset($ttsResult['storage_path'])) {
					$answer['feedback_audio_path'] = $ttsResult['storage_path']; // Store relative path
					$answer['feedback_audio_url'] = $ttsResult['fileUrl']; // Include URL for immediate use
					Log::info("Generated TTS for feedback {$index}: " . $ttsResult['storage_path']);
				} else {
					Log::warning("Failed to generate TTS for feedback {$index}");
					$answer['feedback_audio_path'] = null;
					$answer['feedback_audio_url'] = null;
				}
			}
			unset($answer); // Unset reference

// --- Step 6: Save Quiz to Database ---
			$quiz = Quiz::create([
				'subject_id' => $subjectId,
				'question_text' => $quizData['question'],
				'answers' => $processedAnswers, // Save answers with audio paths
				'difficulty_level' => $currentDifficultyLevel, // Save the calculated difficulty
				'session_id' => $sessionId,
				'question_video_path' => null,
				'question_video_url' => null,
			]);
			Log::info("Quiz record created with ID: {$quiz->id}");

// --- Initiate TTV for the Question (Async) ---
			$questionVideoResult = MyHelper::text2video(
				$quizData['question'],
				env('DEFAULT_FACE_URL'),
				env('DEFAULT_TTS_VOICE', 'en-US-Studio-O') // Use same voice or different?
			);

			if ($questionVideoResult['success']) {
				$quiz->question_video_path = $questionVideoResult['video_path'] ?? null;
				$quiz->question_video_url = $questionVideoResult['video_url'] ?? null;
				$quiz->save();
			} else {
				Log::error("Failed to start quiz question video: " . ($questionVideoResult['message'] ?? 'Unknown error'));
			}


// --- Return quiz data to frontend ---
			return response()->json([
				'success' => true,
				'message' => 'Quiz question generated.',
				'quiz_id' => $quiz->id,
				'question_text' => $quiz->question_text,
				'answers' => array_map(function ($answer) { // Only send necessary fields to frontend
					return [
						'text' => $answer['text'],
// 'is_correct' => $answer['is_correct'], // Don't send correct flag yet
						'feedback_audio_url' => $answer['feedback_audio_url'] ?? null, // Send URL directly
					];
				}, $processedAnswers),
				'question_video_status' => $quiz->question_video_status,
				'question_video_job_id' => $quiz->question_video_job_id, // For polling
				'usage' => $quizUsage,
			]);
		}

// --- Step 7: Submit User Answer ---
		public function submitAnswer(Request $request)
		{
			$validator = Validator::make($request->all(), [
				'quiz_id' => 'required|exists:quizzes,id',
				'selected_index' => 'required|integer|min:0|max:3',
			]);

			if ($validator->fails()) {
				return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
			}

			$quizId = $request->input('quiz_id');
			$selectedIndex = $request->input('selected_index');
			$sessionId = Session::getId();

			$quiz = Quiz::findOrFail($quizId);

// Security check: Ensure quiz belongs to the current session
			if ($quiz->session_id !== $sessionId) {
				return response()->json(['success' => false, 'message' => 'Invalid session for this quiz.'], 403);
			}

// Prevent answering the same quiz multiple times in a session (optional)
			$alreadyAnswered = UserAnswer::where('quiz_id', $quizId)
				->where('session_id', $sessionId)
				->exists();
			if ($alreadyAnswered) {
				Log::warning("Attempt to re-answer Quiz ID {$quizId} by Session {$sessionId}");
// You might return the previous result or an error
				return response()->json(['success' => false, 'message' => 'Quiz already answered.'], 409); // Conflict
			}


			Log::info("Submitting answer for Quiz ID: {$quizId}, Index: {$selectedIndex}, Session: {$sessionId}");

			$answers = $quiz->answers; // Already decoded by model casting
			if (!isset($answers[$selectedIndex])) {
				return response()->json(['success' => false, 'message' => 'Invalid answer index provided.'], 400);
			}

			$selectedAnswer = $answers[$selectedIndex];
			$wasCorrect = $selectedAnswer['is_correct'] === true;
			$feedbackText = $selectedAnswer['feedback'];
			$feedbackAudioUrl = $quiz->getFeedbackAudioUrl($selectedIndex); // Use accessor
			$correctIndex = -1;
			foreach ($answers as $index => $answer) {
				if ($answer['is_correct']) {
					$correctIndex = $index;
					break;
				}
			}

// --- Step 7: Save User Answer ---
			UserAnswer::create([
				'quiz_id' => $quizId,
				'subject_id' => $quiz->subject_id, // Store subject link
				'session_id' => $sessionId,
				'selected_answer_index' => $selectedIndex,
				'was_correct' => $wasCorrect,
				'attempt_number' => 1, // Assuming first attempt
			]);

			Log::info("User answer saved. Correct: " . ($wasCorrect ? 'Yes' : 'No'));

// --- Return feedback to frontend ---
			return response()->json([
				'success' => true,
				'was_correct' => $wasCorrect,
				'correct_index' => $correctIndex, // Let frontend know the right answer
				'feedback_text' => $feedbackText,
				'feedback_audio_url' => $feedbackAudioUrl,
			]);
		}
	}
