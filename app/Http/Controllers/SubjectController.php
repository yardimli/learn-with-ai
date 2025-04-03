<?php

	namespace App\Http\Controllers;

	use App\Helpers\MyHelper;
	use App\Models\GeneratedImage;
	use App\Models\Quiz; // <<< Make sure Quiz model is imported
	use App\Models\Subject;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Log;
// use Illuminate\Support\Facades\Session; // Session not used directly here
// use Illuminate\Support\Facades\Storage; // Storage not used directly here
	use Illuminate\Support\Facades\Storage;
	use Illuminate\Support\Facades\Validator;
	use Illuminate\Support\Str;

	class SubjectController extends Controller
	{
		/**
		 * Display the subject input form (Home Page).
		 *
		 * @return \Illuminate\View\View
		 */
		public function index()
		{
			$llms = MyHelper::checkLLMsJson();
			$subjects = Subject::orderBy('created_at', 'desc')->get(); // Fetch existing subjects
			return view('subject_input', compact('llms', 'subjects'));
		}

		// --- NEW: Prompt for generating Lesson Structure ONLY ---
		private const SYSTEM_PROMPT_LESSON_STRUCTURE = <<<PROMPT
You are an AI assistant specialized in creating the structure for educational micro-lessons.
The user will provide a subject. You MUST generate the basic lesson plan structure as a single JSON object.
The JSON object MUST have the following structure:
{
  "main_title": "A concise and engaging main title for the entire lesson (max 15 words).",
  "image_prompt_idea": "A short phrase or idea (max 15 words) for a single, representative image for the whole lesson.",
  "lesson_parts": [
    {
      "title": "Title for Lesson Part 1 (e.g., 'Introduction to X')",
      "image_prompt_idea": "A short phrase or idea (max 15 words) for a single, representative image for this part of the lesson.",
      "text": "Content for Lesson Part 1 (3-6 sentences, approx 50-120 words). Explain a key concept or aspect."
    },
    {
      "title": "Title for Lesson Part 2 (e.g., 'How X Works')",
      "image_prompt_idea": "A short phrase or idea (max 15 words) for a single, representative image for this part of the lesson.",
      "text": "Content for Lesson Part 2 (3-6 sentences, approx 50-120 words). Elaborate or cover a second aspect."
    },
    {
      "title": "Title for Lesson Part 3 (e.g., 'Importance/Applications of X')",
      "image_prompt_idea": "A short phrase or idea (max 15 words) for a single, representative image for this part of the lesson.",
      "text": "Content for Lesson Part 3 (3-6 sentences, approx 50-120 words). Cover a third aspect or conclusion."
    }
  ]
}

Constraints:
- The output MUST be ONLY the valid JSON object described above. No introductory text, explanations, or markdown formatting outside the JSON structure.
- Ensure exactly 3 `lesson_parts`.
- All text content (titles, lesson text) should be clear, concise, and factually accurate based on the provided subject.
- Generate content suitable for a general audience learning about the subject for the first time.
PROMPT;

		// --- NEW: Prompt for generating Quizzes for a SINGLE lesson part ---
		private const SYSTEM_PROMPT_QUIZ_GENERATION = <<<PROMPT
You are an AI assistant specialized in creating integrated quizzes for educational micro-lessons.
The user will provide the title and text content for ONE specific lesson part, along with a list of quiz questions already generated for OTHER parts of the lesson.
You MUST generate a JSON object containing quizzes (easy, medium, hard) ONLY for the CURRENT lesson part's content.

The JSON object MUST have the following structure:
{
  "easy": [ // 5 easy questions
    {
      "question": "Easy question 1 text based ONLY on the provided lesson part content.",
      "image_prompt_idea": "Optional: very short visual cue for this specific question (max 10 words)",
      "answers": [
        {"text": "Answer 1 (Correct)", "is_correct": true, "feedback": "Correct! Explanation..."},
        {"text": "Answer 2 (Incorrect)", "is_correct": false, "feedback": "Incorrect. Explanation..."},
        {"text": "Answer 3 (Incorrect)", "is_correct": false, "feedback": "Incorrect. Explanation..."},
        {"text": "Answer 4 (Incorrect)", "is_correct": false, "feedback": "Incorrect. Explanation..."}
      ]
    }, // ... 4 more easy questions
  ],
  "medium": [ // 5 medium questions
     // ... same structure ... 5 questions total
  ],
  "hard": [ // 5 hard questions
    // ... same structure ... 5 questions total
  ]
}

Constraints:
- The output MUST be ONLY the valid JSON object described above. No extra text.
- Generate exactly 5 questions in each difficulty category (`easy`, `medium`, `hard`).
- Each question must have exactly 4 answers.
- Exactly one answer per question must have `"is_correct": true`.
- All questions, answers, and feedback MUST be directly based on the provided "Current Lesson Part Text" and "Current Lesson Part Title". Do NOT use external knowledge beyond interpreting the provided text.
- **CRITICAL**: Review the "Previously Generated Questions" list provided by the user. Do NOT generate questions that are identical or substantially similar in meaning to any question in that list.
- `image_prompt_idea` fields are optional, short, and descriptive if included.
PROMPT;


		/**
		 * NEW: Generates the basic lesson structure (no quizzes) using an LLM.
		 *
		 * @param string $llm The LLM model ID.
		 * @param string $userSubject The subject provided by the user.
		 * @param int $maxRetries Maximum number of retries for the LLM call.
		 * @return array Result from llm_no_tool_call (JSON decoded or error array).
		 */
		public static function generateLessonStructure(string $llm, string $userSubject, int $maxRetries = 1): array
		{
			$chatHistoryLessonStructGen = [['role' => 'user', 'content' => $userSubject]];
			Log::info("Requesting lesson structure generation for subject: '{$userSubject}' using LLM: {$llm}");
			return MyHelper::llm_no_tool_call($llm, self::SYSTEM_PROMPT_LESSON_STRUCTURE, $chatHistoryLessonStructGen, true, $maxRetries);
		}

		/**
		 * NEW: Generates quizzes for a single lesson part using an LLM.
		 *
		 * @param string $llm The LLM model ID.
		 * @param string $lessonPartTitle Title of the current lesson part.
		 * @param string $lessonPartText Text content of the current lesson part.
		 * @param array $existingQuestionTexts Array of question texts already generated.
		 * @param int $maxRetries Maximum number of retries for the LLM call.
		 * @return array Result from llm_no_tool_call (JSON decoded or error array).
		 */
		public static function generateQuizzesForPart(string $llm, string $lessonPartTitle, string $lessonPartText, array $existingQuestionTexts, int $maxRetries = 1): array
		{
			$userContent = "Current Lesson Part Title: " . $lessonPartTitle . "\n\n";
			$userContent .= "Current Lesson Part Text: " . $lessonPartText . "\n\n";
			$userContent .= "Previously Generated Questions (Avoid Duplicates):\n";
			if (empty($existingQuestionTexts)) {
				$userContent .= "- None yet";
			} else {
				foreach($existingQuestionTexts as $qText) {
					$userContent .= "- " . $qText . "\n";
				}
			}

			$chatHistoryQuizGen = [['role' => 'user', 'content' => $userContent]];
			Log::info("Requesting quiz generation for part '{$lessonPartTitle}' using LLM: {$llm}");
			// Log::debug("Existing questions provided to LLM for duplication check:", $existingQuestionTexts); // Optional: Debugging
			return MyHelper::llm_no_tool_call($llm, self::SYSTEM_PROMPT_QUIZ_GENERATION, $chatHistoryQuizGen, true, $maxRetries);
		}

		/**
		 * NEW: Validates the structure of the basic lesson plan (no quizzes).
		 *
		 * @param array|null $planData The decoded JSON data.
		 * @return bool True if the structure is valid, false otherwise.
		 */
		public static function isValidLessonStructureResponse(?array $planData): bool
		{
			if (empty($planData) || !is_array($planData)) return false;
			if (!isset($planData['main_title']) || !is_string($planData['main_title'])) return false;
			if (!isset($planData['image_prompt_idea']) || !is_string($planData['image_prompt_idea'])) return false;
			if (!isset($planData['lesson_parts']) || !is_array($planData['lesson_parts']) || count($planData['lesson_parts']) !== 3) return false;

			foreach ($planData['lesson_parts'] as $part) {
				if (!is_array($part) || !isset($part['title']) || !is_string($part['title']) || !isset($part['text']) || !is_string($part['text']) || !isset($part['image_prompt_idea']) || !is_string($part['image_prompt_idea'])) {
					return false;
				}
				// Do NOT check for 'quizzes' key here
			}
			return true; // All checks passed
		}

		/**
		 * NEW: Validates the structure of a quiz batch (easy, medium, hard) for a single part.
		 *
		 * @param array|null $quizData The decoded JSON data for quizzes {easy:[], medium:[], hard:[]}.
		 * @return bool True if the structure is valid, false otherwise.
		 */
		public static function isValidQuizBatchResponse(?array $quizData): bool
		{
			if (empty($quizData) || !is_array($quizData)) {
				Log::warning('Invalid Quiz Batch: Data is empty or not an array.', ['data' => $quizData]);
				return false;
			}
			if (!isset($quizData['easy'], $quizData['medium'], $quizData['hard'])) {
				Log::warning('Invalid Quiz Batch: Missing easy, medium, or hard keys.', ['keys' => array_keys($quizData)]);
				return false;
			}
			if (!is_array($quizData['easy']) || count($quizData['easy']) !== 5) {
				Log::warning('Invalid Quiz Batch: Easy quizzes count is not 5.', ['count' => count($quizData['easy'] ?? [])]);
				return false;
			}
			if (!is_array($quizData['medium']) || count($quizData['medium']) !== 5) {
				Log::warning('Invalid Quiz Batch: Medium quizzes count is not 5.', ['count' => count($quizData['medium'] ?? [])]);
				return false;
			}
			if (!is_array($quizData['hard']) || count($quizData['hard']) !== 5) {
				Log::warning('Invalid Quiz Batch: Hard quizzes count is not 5.', ['count' => count($quizData['hard'] ?? [])]);
				return false;
			}

			$allPartQuizzes = array_merge($quizData['easy'], $quizData['medium'], $quizData['hard']);
			foreach ($allPartQuizzes as $index => $quiz) {
				// Use existing validation, image prompt is optional within quiz validation
				if (!self::isValidFullQuizResponse($quiz, false)) { // Set requireImagePrompt to false
					Log::warning("Invalid quiz structure found within quiz batch (Quiz index {$index}).", ['quiz_data' => $quiz]);
					return false;
				}
			}
			return true; // All checks passed
		}


		/**
		 * Validates the structure of the COMPLETE lesson plan JSON (including nested quizzes).
		 *
		 * @param array|null $planData The decoded JSON data.
		 * @return bool True if the structure is valid, false otherwise.
		 */
		public static function isValidLessonPlanResponse(?array $planData): bool
		{
			// Use the structure validator first
			if (!self::isValidLessonStructureResponse($planData)) {
				Log::warning('Complete plan validation failed: Invalid base structure.');
				return false;
			}

			// Now validate the quizzes within each part
			$allQuizQuestions = []; // Collect all for optional deeper checks later if needed
			foreach ($planData['lesson_parts'] as $index => $part) {
				if (!isset($part['quizzes']) || !is_array($part['quizzes'])) {
					Log::warning("Complete plan validation failed: Missing 'quizzes' key in lesson part {$index}.", ['part_data' => $part]);
					return false;
				}

				// Validate the batch of quizzes for this part
				if (!self::isValidQuizBatchResponse($part['quizzes'])) {
					Log::warning("Complete plan validation failed: Invalid quiz batch in lesson part {$index}.", ['part_quizzes' => $part['quizzes']]);
					return false;
				}

				// Merge quizzes from this part for potential cross-part checks (like duplication - though LLM handles this now)
				$allQuizQuestions = array_merge(
					$allQuizQuestions,
					$part['quizzes']['easy'],
					$part['quizzes']['medium'],
					$part['quizzes']['hard']
				);
			}

			// Optional: Add any further checks on $allQuizQuestions if needed

			return true; // All checks passed
		}

		/**
		 * Validates a single quiz question structure.
		 * (Modified to make image prompt optional by default)
		 */
		public static function isValidFullQuizResponse($data, $requireImagePrompt = false): bool {
			if (!is_array($data)) return false;
			if (!isset($data['question']) || !is_string($data['question'])) return false;

			// Make image_prompt_idea check conditional based on the flag
			if ($requireImagePrompt && (!isset($data['image_prompt_idea']) || !is_string($data['image_prompt_idea']))) return false;
			// Allow it to be missing if $requireImagePrompt is false
			if (!$requireImagePrompt && isset($data['image_prompt_idea']) && !is_string($data['image_prompt_idea'])) return false; // Still validate if present

			if (!isset($data['answers']) || !is_array($data['answers']) || count($data['answers']) !== 4) return false;

			$correctCount = 0;
			foreach ($data['answers'] as $answer) {
				if (!is_array($answer)) return false;
				if (!isset($answer['text']) || !is_string($answer['text'])) return false;
				if (!isset($answer['is_correct']) || !is_bool($answer['is_correct'])) return false;
				if (!isset($answer['feedback']) || !is_string($answer['feedback'])) return false;
				if ($answer['is_correct'] === true) {
					$correctCount++;
				}
			}
			return $correctCount === 1; // Exactly one correct answer
		}


		/**
		 * MODIFIED: Handle AJAX request to generate lesson plan preview (multi-stage).
		 *
		 * @param Request $request
		 * @return \Illuminate\Http\JsonResponse
		 */
		public function generatePlanPreview(Request $request)
		{
			$validator = Validator::make($request->all(), [
				'subject' => 'required|string|max:150',
				'llm' => 'nullable|string|max:100',
			]);

			if ($validator->fails()) {
				return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
			}

			$userSubject = $request->input('subject');
			$llm = $request->input('llm', env('DEFAULT_LLM'));
			if (empty($llm)) {
				$llm = env('DEFAULT_LLM');
			}
			$maxRetries = 1; // Or get from config/request

			Log::info("AJAX request received for plan preview. Subject: '{$userSubject}', LLM: {$llm}");

			// --- Stage 1: Generate Lesson Structure ---
			Log::info("Stage 1: Generating lesson structure...");
			$planStructureResult = self::generateLessonStructure($llm, $userSubject, $maxRetries);

			if (isset($planStructureResult['error'])) {
				$errorMsg = $planStructureResult['error'];
				Log::error("LLM Structure Gen Error: " . $errorMsg, ['subject' => $userSubject, 'llm' => $llm]);
				return response()->json(['success' => false, 'message' => 'Failed to generate lesson structure: ' . $errorMsg]);
			}

			// Validate the structure
			if (!self::isValidLessonStructureResponse($planStructureResult)) {
				$errorMsg = 'LLM returned an invalid lesson structure.';
				Log::error($errorMsg, ['subject' => $userSubject, 'llm' => $llm, 'response' => $planStructureResult]);
				return response()->json(['success' => false, 'message' => $errorMsg . ' Please try refining your subject or using a different model.']);
			}
			Log::info("Stage 1: Lesson structure generated and validated successfully.");
			$lessonPlan = $planStructureResult; // Start building the final plan
			$allGeneratedQuestionTexts = []; // Keep track of questions to avoid duplicates


			// --- Stage 2: Generate Quizzes Iteratively ---
			Log::info("Stage 2: Generating quizzes for each lesson part...");
			foreach ($lessonPlan['lesson_parts'] as $index => &$part) { // Use reference to modify $lessonPlan directly
				$partTitle = $part['title'];
				$partText = $part['text'];
				Log::info("Generating quizzes for Part " . ($index + 1) . ": '{$partTitle}'");

				$quizResult = self::generateQuizzesForPart($llm, $partTitle, $partText, $allGeneratedQuestionTexts, $maxRetries);

				if (isset($quizResult['error'])) {
					$errorMsg = $quizResult['error'];
					$logMsg = "LLM Quiz Gen Error for Part " . ($index + 1) . ": " . $errorMsg;
					Log::error($logMsg, ['subject' => $userSubject, 'llm' => $llm, 'part_title' => $partTitle]);
					return response()->json(['success' => false, 'message' => 'Failed to generate quizzes for "' . $partTitle . '": ' . $errorMsg]);
				}

				// Validate the generated quiz batch for this part
				if (!self::isValidQuizBatchResponse($quizResult)) {
					$errorMsg = 'LLM returned an invalid quiz structure for lesson part "' . $partTitle . '".';
					Log::error($errorMsg, ['subject' => $userSubject, 'llm' => $llm, 'part_title' => $partTitle, 'response' => $quizResult]);
					return response()->json(['success' => false, 'message' => $errorMsg . ' Please try again.']);
				}

				// Add quizzes to the part and update the list of generated questions
				$part['quizzes'] = $quizResult;
				$newQuestions = array_merge(
					array_column($quizResult['easy'] ?? [], 'question'),
					array_column($quizResult['medium'] ?? [], 'question'),
					array_column($quizResult['hard'] ?? [], 'question')
				);
				$allGeneratedQuestionTexts = array_merge($allGeneratedQuestionTexts, $newQuestions);

				Log::info("Quizzes generated and validated successfully for Part " . ($index + 1) . ".");
			}
			unset($part); // Unset reference

			// --- Final Validation & Response ---
			// Optional: Do a final validation of the *complete* assembled structure
			if (!self::isValidLessonPlanResponse($lessonPlan)) {
				$errorMsg = 'The final assembled lesson plan structure is invalid after quiz generation.';
				Log::error($errorMsg, ['subject' => $userSubject, 'llm' => $llm, 'final_plan' => $lessonPlan]);
				// This indicates an internal logic error likely, as individual parts were validated
				return response()->json(['success' => false, 'message' => $errorMsg . ' An internal error occurred.']);
			}

			Log::info("Lesson plan with all quizzes generated successfully for preview.");
			// Return the full plan data
			return response()->json(['success' => true, 'plan' => $lessonPlan]);
		}



		/**
		 * MODIFIED: Handle the actual creation after user confirmation.
		 * Saves lesson structure and quiz text data. Defers asset generation.
		 *
		 * @param Request $request
		 * @return \Illuminate\Http\JsonResponse | \Illuminate\Http\RedirectResponse
		 */
		public function createLesson(Request $request)
		{
			// Validation rules remain mostly the same, ensuring the plan structure is correct
			$validator = Validator::make($request->all(), [
				'subject_name' => 'required|string|max:150',
				'llm_used' => 'required|string|max:100',
				'plan' => 'required|array',
				'plan.main_title' => 'required|string',
				'plan.image_prompt_idea' => 'required|string',
				'plan.lesson_parts' => 'required|array|size:3',
				'plan.lesson_parts.*.title' => 'required|string',
				'plan.lesson_parts.*.text' => 'required|string',
				'plan.lesson_parts.*.quizzes' => 'required|array',
				'plan.lesson_parts.*.quizzes.easy' => 'required|array|size:5',
				'plan.lesson_parts.*.quizzes.medium' => 'required|array|size:5',
				'plan.lesson_parts.*.quizzes.hard' => 'required|array|size:5',
				// Don't need to validate audio/video paths here as they won't exist yet
			]);

			if ($validator->fails()) {
				Log::error('Invalid data received for lesson creation.', ['errors' => $validator->errors()->toArray(), 'data' => $request->input('plan', [])]);
				return response()->json(['success' => false, 'message' => 'Invalid data received for lesson creation. ' . $validator->errors()->first()], 422);
			}

			$userSubject = $request->input('subject_name');
			$llm = $request->input('llm_used');
			$plan = $request->input('plan');

			// Double-check plan validity using the comprehensive validator
			if (!self::isValidLessonPlanResponse($plan)) {
				Log::error('Invalid final plan structure received on createLesson endpoint.', ['plan' => $plan]);
				return response()->json(['success' => false, 'message' => 'Invalid lesson plan structure received during final check.'], 400);
			}

			$sessionId = Str::uuid()->toString(); // Generate a unique ID for this lesson session
			Log::info("Confirmed creation request received. Saving structure. Session ID: {$sessionId}, Subject: '{$userSubject}'");

			// --- 1. Create Subject Record (Store structured plan, no video path yet) ---
			$subject = Subject::create([
				'name' => $userSubject,
				'title' => $plan['main_title'],
				'image_prompt_idea' => $plan['image_prompt_idea'],
				'lesson_parts' => $plan['lesson_parts'],
				'session_id' => $sessionId,
				'llm_used' => $llm,
			]);
			Log::info("Subject record created with ID: {$subject->id}, SessionID: {$sessionId}");

			// --- 2. Create Quiz Records (Store text/structure only, no audio/images yet) ---
			$createdQuizCount = 0;
			$quizOrder = 0;
			foreach ($plan['lesson_parts'] as $partIndex => $lessonPart) {
				$quizData = $lessonPart['quizzes']; // Get quizzes for this specific part

				foreach (['easy', 'medium', 'hard'] as $difficulty) {
					if (!isset($quizData[$difficulty])) continue;

					foreach ($quizData[$difficulty] as $quizIndex => $quizQuestionData) {
						// Prepare answers array *without* audio paths
						$answersToStore = [];
						foreach($quizQuestionData['answers'] as $answer) {
							$answersToStore[] = [
								'text' => $answer['text'],
								'is_correct' => $answer['is_correct'],
								'feedback' => $answer['feedback'],
								// 'audio_path' and 'audio_url' are omitted here
							];
						}

						Quiz::create([
							'subject_id' => $subject->id,
							'image_prompt_idea' => $quizQuestionData['image_prompt_idea'] ?? null,
							'question_text' => $quizQuestionData['question'],
							'answers' => $answersToStore, // Store answers JSON without audio info
							'difficulty_level' => $difficulty,
							'lesson_part_index' => $partIndex,
							'order' => $quizOrder++,
						]);
						$createdQuizCount++;
					} // end foreach quiz in difficulty
				} // end foreach difficulty
			} // end foreach lesson_part
			Log::info("Created {$createdQuizCount} quiz records (structure only) for Subject ID: {$subject->id}");

			// --- 4. Respond / Redirect ---
			// Assets (video, audio) will be generated on first access or via a background job later.
			Log::info("Lesson structure saved successfully. Redirecting user.");
			return response()->json([
				'success' => true,
				'message' => 'Lesson structure saved! Generating assets may take a moment on the next page.', // Inform user
				'redirectUrl' => route('content.show', ['subject' => $sessionId])
			]);
		}




		/**
		 * NEW STATIC FUNCTION: Generates deferred assets (Video, TTS Audio, Quiz Images) for a Subject.
		 * Can be called on demand (e.g., first view) or by a background job.
		 *
		 * @param Subject $subject The subject model instance.
		 * @return bool True if assets were generated (or already existed), false on major error.
		 */
		public static function generateLessonAssets(Subject $subject): bool
		{
			Log::info("Starting asset generation for Subject ID: {$subject->id} (Session: {$subject->session_id})");
			$assetsGenerated = false; // Flag to track if any new asset was made
			$hasError = false; // Flag to track errors

			// Ensure lesson_parts is an array
			$lessonParts = is_array($subject->lesson_parts) ? $subject->lesson_parts : json_decode($subject->lesson_parts, true);
			if (!is_array($lessonParts)) {
				Log::error("Cannot generate assets - lesson_parts data is invalid for Subject ID: {$subject->id}.");
				return false;
			}

			// --- 2. Generate Quiz Audio and Images ---
			$ttsEngine = env('DEFAULT_TTS_ENGINE', 'google');
			$ttsVoice = ($ttsEngine === 'openai') ? env('OPENAI_TTS_VOICE', 'alloy') : env('GOOGLE_TTS_VOICE', 'en-US-Studio-O');
			$languageCode = 'en-US'; // Needed for Google TTS

			// Eager load quizzes to avoid N+1 queries in the loop
			$subject->load('quizzes.generatedImage'); // Load quizzes and their associated images

			Log::info("Processing quizzes for asset generation for Subject ID: {$subject->id}...");
			foreach ($subject->quizzes as $quiz) {
				$quizIdentifier = "s{$subject->id}_p{$quiz->lesson_part_index}_q{$quiz->id}"; // Unique ID

				// --- 2a. Generate Quiz Question Audio ---
				if (empty($quiz->question_audio_path)) {
					Log::info("Generating question audio for Quiz ID: {$quiz->id}...");
					try {
						$questionAudioResult = MyHelper::text2speech(
							$quiz->question_text,
							$ttsVoice,
							$languageCode, // Pass lang code
							'question_' . $quizIdentifier,
							$ttsEngine      // Pass engine
						);

						if ($questionAudioResult['success'] && isset($questionAudioResult['storage_path'])) {
							$quiz->question_audio_path = $questionAudioResult['storage_path'];
							// Generate URL correctly
							$quiz->question_audio_url = Storage::disk('public')->url($quiz->question_audio_path);
							$quiz->save();
							$assetsGenerated = true;
							Log::info("Question audio generated for Quiz ID: {$quiz->id}. Path: {$quiz->question_audio_path}, URL: {$quiz->question_audio_url}");
						} else {
							Log::error("Failed to generate question audio for Quiz ID {$quiz->id}: " . ($questionAudioResult['message'] ?? 'Error'));
							$hasError = true;
						}
					} catch (\Exception $e) {
						Log::error("Exception during question TTS for Quiz ID {$quiz->id}: " . $e->getMessage());
						$hasError = true;
					}
				} else {
					Log::debug("Question audio already exists for Quiz ID: {$quiz->id}. Skipping.");
				}

				// --- 2b. Generate Quiz Answer Audio ---
				$currentAnswers = $quiz->answers; // Get the array
				$needsAnswerAudio = false;
				if (!empty($currentAnswers) && is_array($currentAnswers)) {
					// Check if the first answer is missing an 'audio_path' key or if it's empty
					if (!isset($currentAnswers[0]['audio_path']) || empty($currentAnswers[0]['audio_path'])) {
						$needsAnswerAudio = true;
					}
				} else {
					Log::warning("Answers data is missing or invalid for Quiz ID: {$quiz->id}. Cannot generate answer audio.");
				}

				if ($needsAnswerAudio) {
					Log::info("Generating answer audio for Quiz ID: {$quiz->id}...");
					try {
						$processedAnswers = Quiz::processAnswersWithTTS(
							$currentAnswers, // Pass the existing array structure
							$quiz->id,       // Use Quiz ID for uniqueness
							"a_{$quizIdentifier}", // Base filename identifier
							$ttsEngine,
							$ttsVoice,
							$languageCode
						);
						$quiz->answers = $processedAnswers; // Update the answers JSON column
						$quiz->save();
						$assetsGenerated = true;
						Log::info("Answer audio generated for Quiz ID: {$quiz->id}");
					} catch (\Exception $e) {
						Log::error("Exception during answer TTS processing for Quiz ID {$quiz->id}: " . $e->getMessage());
						$hasError = true;
					}
				} else if (!empty($currentAnswers) && isset($currentAnswers[0]['audio_path'])){
					Log::debug("Answer audio already exists for Quiz ID: {$quiz->id}. Skipping.");
				}


				// --- 2c. Generate Quiz Image (if prompt exists and image not yet set) ---
				if (empty($quiz->generated_image_id) && !empty($quiz->image_prompt_idea)) {
					Log::info("Generating image for Quiz ID: {$quiz->id} using prompt: '{$quiz->image_prompt_idea}'");
					try {
						$quizImageResult = MyHelper::makeImage(
							$quiz->image_prompt_idea,
							env('DEFAULT_IMAGE_MODEL', 'fal-ai/flux/schnell'), // Use default or specify
							'square_hd' // Square format for quizzes often looks good
						);

						if ($quizImageResult['success'] && isset($quizImageResult['image_guid'])) {
							// Find the GeneratedImage model using the GUID
							$quizImageModel = GeneratedImage::where('image_guid', $quizImageResult['image_guid'])->first();
							if ($quizImageModel) {
								$quiz->generated_image_id = $quizImageModel->id;
								$quiz->save();
								$assetsGenerated = true;
								Log::info("Image generated and linked for Quiz ID: {$quiz->id}, Image ID: {$quizImageModel->id}");
							} else {
								Log::warning("Generated image record not found for GUID: " . $quizImageResult['image_guid'] . " for Quiz ID {$quiz->id}");
								// Image was generated but linking failed. Maybe retry later?
								$hasError = true; // Consider this an error state for linking
							}
						} else {
							Log::warning("Failed to generate image for Quiz ID {$quiz->id}: " . ($quizImageResult['message'] ?? 'Unknown error'));
							// Don't mark as major error if image gen fails, lesson can proceed
							// $hasError = true;
						}
					} catch (\Exception $e) {
						Log::error("Exception during quiz image generation for Quiz ID {$quiz->id}: " . $e->getMessage());
						// $hasError = true; // Don't stop everything for one image
					}
				} else if (!empty($quiz->generated_image_id)) {
					Log::debug("Image already exists for Quiz ID: {$quiz->id}. Skipping.");
				} else if (empty($quiz->image_prompt_idea)) {
					Log::debug("No image prompt for Quiz ID: {$quiz->id}. Skipping image generation.");
				}

			} // end foreach quiz

			Log::info("Asset generation process finished for Subject ID: {$subject->id}. Any new assets generated: " . ($assetsGenerated ? 'Yes' : 'No') . ". Errors encountered: " . ($hasError ? 'Yes' : 'No'));
			return !$hasError; // Return true if no *major* errors occurred during the process
		}



		// ==============================================
		// NEW METHODS FOR EDITING AND ASSET GENERATION
		// ==============================================

		/**
		 * Show the lesson edit page.
		 *
		 * @param Subject $subject
		 * @return \Illuminate\View\View
		 */
		public function edit(Subject $subject)
		{
			// Eager load quizzes and their associated images
			$subject->load(['quizzes.generatedImage']);
			Log::info("Showing edit page for Subject ID: {$subject->id} (Session: {$subject->session_id})");

			// Group quizzes by part and difficulty for easier display
			$groupedQuizzes = [];
			foreach ($subject->quizzes as $quiz) {
				$partIndex = $quiz->lesson_part_index;
				$difficulty = $quiz->difficulty_level;
				if (!isset($groupedQuizzes[$partIndex])) {
					$groupedQuizzes[$partIndex] = ['easy' => [], 'medium' => [], 'hard' => []];
				}
				if (!isset($groupedQuizzes[$partIndex][$difficulty])) {
					$groupedQuizzes[$partIndex][$difficulty] = []; // Ensure difficulty array exists
				}
				$groupedQuizzes[$partIndex][$difficulty][] = $quiz;
			}

			// Decode lesson parts if needed (it should be auto-decoded by cast)
			$lessonParts = $subject->lesson_parts;
			if (is_string($lessonParts)) {
				$lessonParts = json_decode($lessonParts, true);
			}
			// Ensure it's an array for the view
			$subject->lesson_parts = is_array($lessonParts) ? $lessonParts : [];


			return view('lesson_edit', [
				'subject' => $subject,
				'groupedQuizzes' => $groupedQuizzes
			]);
		}

		/**
		 * NEW: AJAX endpoint to generate video for a specific lesson part.
		 * Updates the 'lesson_parts' JSON array in the Subject model.
		 *
		 * @param Request $request
		 * @param Subject $subject
		 * @param int $partIndex The index (0-based) of the lesson part.
		 * @return \Illuminate\Http\JsonResponse
		 */
		public function generatePartVideoAjax(Request $request, Subject $subject, int $partIndex)
		{
			Log::info("AJAX request to generate video for Subject ID: {$subject->id}, Part Index: {$partIndex}");

			// Retrieve and decode lesson parts
			$lessonParts = is_array($subject->lesson_parts) ? $subject->lesson_parts : json_decode($subject->lesson_parts, true);

			// Validate partIndex
			if (!is_array($lessonParts) || !isset($lessonParts[$partIndex])) {
				Log::error("Invalid part index ({$partIndex}) or lesson parts data for Subject ID: {$subject->id}.");
				return response()->json(['success' => false, 'message' => 'Invalid lesson part index.'], 400);
			}

			// Check if video already exists for this part
			if (!empty($lessonParts[$partIndex]['video_path'])) {
				Log::warning("Video already exists for Subject ID: {$subject->id}, Part Index: {$partIndex}.");
				return response()->json(['success' => false, 'message' => 'Video already exists for this part.'], 409); // Conflict
			}

			// Get text for video generation
			$partData = $lessonParts[$partIndex];
			$videoText = ($partData['title'] ?? 'Lesson Part') . ". \n" . ($partData['text'] ?? 'No content.');

			$defaultFaceUrl = env('DEFAULT_FACE_URL', 'https://elooi.com/video/video1.mp4');
			$videoResult = null;

			try {
				// Determine which video generation method to use (same logic as before)
				if (stripos(env('APP_URL'), 'localhost') !== false) {
					Log::info("Using text2video (Gooey Lipsync+TTS) due to local flag.");
					$videoResult = MyHelper::text2video( $videoText, $defaultFaceUrl, env('DEFAULT_TTS_VOICE', 'en-US-Studio-O') ); // Example Google Voice
				} else {
					Log::info("Using text2videov2 (OpenAI TTS + Gooey Lipsync).");
					$videoResult = MyHelper::text2videov2( $videoText, $defaultFaceUrl );
				}


				if ($videoResult && $videoResult['success'] && isset($videoResult['video_path'])) {
					$relativePath = str_replace('public/', '', $videoResult['video_path']); // Ensure relative path for storage URL
					$videoUrl = Storage::disk('public')->url($relativePath); // Generate public URL

					// Update the specific lesson part in the array
					$lessonParts[$partIndex]['video_path'] = $relativePath; // Store relative path
					$lessonParts[$partIndex]['video_url'] = $videoUrl;       // Store generated URL

					// Save the modified array back to the model
					// Laravel's mutator/cast will handle JSON encoding
					$subject->lesson_parts = $lessonParts;
					$subject->save();

					Log::info("Part video generated and saved. Subject ID: {$subject->id}, Part Index: {$partIndex}. Path: {$relativePath}, URL: {$videoUrl}");

					return response()->json([
						'success' => true,
						'message' => 'Video generated successfully!',
						'video_url' => $videoUrl,
						'video_path' => $relativePath // Send back relative path too if needed
					]);
				} else {
					$errorMsg = $videoResult['message'] ?? 'Unknown video generation error';
					Log::error("Part video generation failed for Subject ID {$subject->id}, Part {$partIndex}: " . $errorMsg, ['result' => $videoResult]);
					return response()->json([
						'success' => false,
						'message' => 'Failed to generate video: ' . $errorMsg
					], 500);
				}
			} catch (\Exception $e) {
				Log::error("Exception during AJAX part video generation for Subject ID {$subject->id}, Part {$partIndex}: " . $e->getMessage());
				return response()->json(['success' => false, 'message' => 'Server error during video generation.'], 500);
			}
		}


		public function generateQuestionAudioAjax(Quiz $quiz)
		{
			Log::info("AJAX request to generate question audio for Quiz ID: {$quiz->id}");

			if (!empty($quiz->question_audio_path) && Storage::disk('public')->exists($quiz->question_audio_path)) {
				Log::warning("Question audio already exists for Quiz ID: {$quiz->id}. Path: {$quiz->question_audio_path}");
				// Return existing URL maybe? Or just indicate it exists.
				return response()->json([
					'success' => true, // Or false? Indicate no action taken
					'message' => 'Question audio already exists.',
					'audio_url' => $quiz->question_audio_url, // Accessor generates this
					'audio_path' => $quiz->question_audio_path
				], 200); // Or 409 Conflict? Let's use 200 for simplicity now.
			}

			if (empty($quiz->question_text)) {
				Log::error("Cannot generate question audio for Quiz ID {$quiz->id}: Question text is empty.");
				return response()->json(['success' => false, 'message' => 'Question text is empty.'], 400);
			}

			try {
				$ttsEngine = env('DEFAULT_TTS_ENGINE', 'google');
				$ttsVoice = ($ttsEngine === 'openai') ? env('OPENAI_TTS_VOICE', 'alloy') : env('GOOGLE_TTS_VOICE', 'en-US-Studio-O');
				$languageCode = 'en-US';
				$quizIdentifier = "s{$quiz->subject_id}_q{$quiz->id}"; // Unique base filename
				$outputFilenameBase = 'q_audio_' . $quizIdentifier;

				$audioResult = MyHelper::text2speech(
					$quiz->question_text,
					$ttsVoice,
					$languageCode,
					$outputFilenameBase,
					$ttsEngine
				);

				if ($audioResult['success'] && isset($audioResult['storage_path'], $audioResult['fileUrl'])) {
					$quiz->question_audio_path = $audioResult['storage_path'];
					// $quiz->question_audio_url = $audioResult['fileUrl']; // URL generated by accessor now
					$quiz->save();

					Log::info("Question audio generated for Quiz ID: {$quiz->id}. Path: {$quiz->question_audio_path}");
					return response()->json([
						'success' => true,
						'message' => 'Question audio generated successfully!',
						'audio_url' => $quiz->question_audio_url, // Use accessor
						'audio_path' => $quiz->question_audio_path,
					]);
				} else {
					throw new \Exception($audioResult['message'] ?? 'TTS generation failed');
				}
			} catch (\Exception $e) {
				Log::error("Exception during question audio generation for Quiz ID {$quiz->id}: " . $e->getMessage());
				return response()->json(['success' => false, 'message' => 'Server error during audio generation: ' . $e->getMessage()], 500);
			}
		}


		/**
		 * AJAX Endpoint to generate Answer TTS Audio (for all answers in the quiz).
		 *
		 * @param Quiz $quiz Route model binding
		 * @return \Illuminate\Http\JsonResponse
		 */
		public function generateAnswerAudioAjax(Quiz $quiz)
		{
			Log::info("AJAX request to generate answer/feedback audio for Quiz ID: {$quiz->id}");

			$currentAnswers = $quiz->answers ?? [];
			if (empty($currentAnswers) || !is_array($currentAnswers)) {
				Log::error("Cannot generate answer audio for Quiz ID {$quiz->id}: Answers data is missing or invalid.");
				return response()->json(['success' => false, 'message' => 'Quiz answers data is missing or invalid.'], 400);
			}

			// Check if audio seems to exist already (e.g., first answer has a path/url)
			if (!empty($currentAnswers[0]['answer_audio_path']) || !empty($currentAnswers[0]['answer_audio_url'])) {
				Log::warning("Answer audio seems to already exist for Quiz ID: {$quiz->id}.");
				return response()->json([
					'success' => true, // Or false? Indicate no action taken
					'message' => 'Answer audio appears to already exist.',
				], 200);
			}

			try {
				$ttsEngine = env('DEFAULT_TTS_ENGINE', 'google');
				$ttsVoice = ($ttsEngine === 'openai') ? env('OPENAI_TTS_VOICE', 'alloy') : env('GOOGLE_TTS_VOICE', 'en-US-Studio-O');
				$languageCode = 'en-US';
				$quizIdentifier = "s{$quiz->subject_id}_q{$quiz->id}"; // Unique base identifier

				// Process answers using the static method in Quiz model
				$processedAnswers = Quiz::processAnswersWithTTS(
					$currentAnswers,
					$quiz->id,
					'quiz_' . $quizIdentifier, // Identifier for filenames
					$ttsEngine,
					$ttsVoice,
					$languageCode
				);

				// Update the quiz's answers column
				$quiz->answers = $processedAnswers;
				$quiz->save();

				Log::info("Answer/feedback audio generation complete for Quiz ID: {$quiz->id}");
				return response()->json([
					'success' => true,
					'message' => 'Answer and feedback audio generated successfully!',
					// Optionally return the updated answers array if needed by frontend
					// 'answers' => $processedAnswers
				]);

			} catch (\Exception $e) {
				Log::error("Exception during answer/feedback audio generation for Quiz ID {$quiz->id}: " . $e->getMessage());
				return response()->json(['success' => false, 'message' => 'Server error during audio generation: ' . $e->getMessage()], 500);
			}
		}


		/**
		 * AJAX Endpoint to generate Quiz Image.
		 *
		 * @param Quiz $quiz Route model binding
		 * @return \Illuminate\Http\JsonResponse
		 */
		public function generateQuizImageAjax(Quiz $quiz)
		{
			Log::info("AJAX request to generate image for Quiz ID: {$quiz->id}");

			if (!empty($quiz->generated_image_id)) {
				Log::warning("Image already exists for Quiz ID: {$quiz->id}. Image ID: {$quiz->generated_image_id}");
				// Find existing image to return URLs
				$existingImage = GeneratedImage::find($quiz->generated_image_id);
				return response()->json([
					'success' => true, // Indicate no action taken, but success state
					'message' => 'Image already exists for this quiz.',
					'image_id' => $quiz->generated_image_id,
					'image_urls' => $existingImage ? [
						'small' => $existingImage->small_url,
						'medium' => $existingImage->medium_url,
						'large' => $existingImage->large_url,
						'original' => $existingImage->original_url,
					] : null,
				], 200);
			}

			if (empty($quiz->image_prompt_idea)) {
				Log::error("Cannot generate image for Quiz ID {$quiz->id}: Image prompt is empty.");
				return response()->json(['success' => false, 'message' => 'Image prompt is empty.'], 400);
			}

			try {
				$imageModel = env('DEFAULT_IMAGE_MODEL', 'fal-ai/flux/schnell');
				$imageSize = 'square_hd'; // Or get from request/config

				$imageResult = MyHelper::makeImage(
					$quiz->image_prompt_idea,
					$imageModel,
					$imageSize
				);

				if ($imageResult['success'] && isset($imageResult['image_guid'], $imageResult['image_id'])) {
					// Link the generated image to the quiz
					$quiz->generated_image_id = $imageResult['image_id'];
					$quiz->save();

					Log::info("Image generated and linked for Quiz ID: {$quiz->id}. Image ID: {$imageResult['image_id']}");
					return response()->json([
						'success' => true,
						'message' => 'Image generated successfully!',
						'image_id' => $imageResult['image_id'],
						'image_guid' => $imageResult['image_guid'],
						'image_urls' => $imageResult['image_urls'], // Pass URLs from helper
					]);
				} else {
					// Check if image was created but linking failed (e.g., helper didn't return ID)
					if ($imageResult['success'] && isset($imageResult['image_guid'])) {
						$imageModel = GeneratedImage::where('image_guid', $imageResult['image_guid'])->first();
						if ($imageModel) {
							$quiz->generated_image_id = $imageModel->id;
							$quiz->save();
							Log::info("Image generated and linked (fallback lookup) for Quiz ID: {$quiz->id}. Image ID: {$imageModel->id}");
							return response()->json([
								'success' => true,
								'message' => 'Image generated successfully!',
								'image_id' => $imageModel->id,
								'image_guid' => $imageResult['image_guid'],
								'image_urls' => [ // Reconstruct URLs
									'small' => $imageModel->small_url,
									'medium' => $imageModel->medium_url,
									'large' => $imageModel->large_url,
									'original' => $imageModel->original_url,
								]
							]);
						}
					}
					// If it truly failed
					throw new \Exception($imageResult['message'] ?? 'Image generation failed');
				}
			} catch (\Exception $e) {
				Log::error("Exception during image generation for Quiz ID {$quiz->id}: " . $e->getMessage());
				return response()->json(['success' => false, 'message' => 'Server error during image generation: ' . $e->getMessage()], 500);
			}
		}

	}
