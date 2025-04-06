<?php

	namespace App\Http\Controllers;

	use App\Helpers\MyHelper;
	use App\Models\GeneratedImage;
	use App\Models\Question; // Keep Question model import
	use App\Models\Subject;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Log;
	use Illuminate\Support\Facades\Storage;
	use Illuminate\Support\Facades\Validator;
	use Illuminate\Support\Str;

	use Illuminate\Support\Facades\Http;
	use Intervention\Image\Laravel\Facades\Image as InterventionImage; // For image resizing
	use Illuminate\Http\UploadedFile; // For type hinting
	use Illuminate\Support\Facades\DB; // For transactions

	use Exception; // Add Exception import

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
			// Eager load question count for potential display (optional)
			$subjects = Subject::withCount('questions')->orderBy('created_at', 'desc')->get();
			return view('subject_input', compact('llms', 'subjects'));
		}

		// --- Prompt for generating Lesson Structure ONLY ---
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

		// --- MODIFIED: Prompt for generating 3 Questions for a SINGLE lesson part and difficulty ---
		private const SYSTEM_PROMPT_QUIZ_GENERATION = <<<PROMPT
You are an AI assistant specialized in creating integrated questions for educational micro-lessons.
The user will provide:
1. The title and text content for ONE specific lesson part.
2. The target difficulty level ('easy', 'medium', or 'hard').
3. A list of question questions already generated for OTHER parts/difficulties of the lesson.

You MUST generate a JSON array containing exactly 3 question questions ONLY for the CURRENT lesson part's content and the SPECIFIED difficulty.

The JSON output MUST be ONLY an array of 3 objects, like this:
{
	"questions" : [
	  { // Question 1 (matching target difficulty)
	    "question": "Question 1 text based ONLY on the provided lesson part content and targeted difficulty.",
	    "image_prompt_idea": "Very short visual cue for this specific question (max 10 words)",
	    "image_search_keywords": "Keywords for image search (max 3 words)",
	    "answers": [
	      {"text": "Answer 1 (Correct)", "is_correct": true, "feedback": "Correct! Explanation..."},
	      {"text": "Answer 2 (Incorrect)", "is_correct": false, "feedback": "Incorrect. Explanation..."},
	      {"text": "Answer 3 (Incorrect)", "is_correct": false, "feedback": "Incorrect. Explanation..."},
	      {"text": "Answer 4 (Incorrect)", "is_correct": false, "feedback": "Incorrect. Explanation..."}
	    ]
	  },
	  // ... 2 more question objects (total 3) matching the target difficulty ...
	]
}

Constraints:
- The output MUST be ONLY the valid JSON array described above. No extra text, keys, or explanations outside the array structure.
- Generate exactly 3 questions in the array.
- Each question must have exactly 4 answers.
- Exactly one answer per question must have `"is_correct": true`.
- All questions, answers, and feedback MUST be directly based on the provided "Current Lesson Part Text" and "Current Lesson Part Title". Do NOT use external knowledge beyond interpreting the provided text.
- Generate questions appropriate for the requested 'Target Difficulty'.
- **CRITICAL**: Review the "Previously Generated Questions" list provided by the user. Do NOT generate questions that are identical or substantially similar in meaning to any question in that list.
- `image_prompt_idea` short, and descriptive.
- `image_search_keywords` short, and relevant to the question without hinting the answer.
PROMPT;

		/**
		 * Generates the basic lesson structure (no questions) using an LLM.
		 */
		public static function generateLessonStructure(string $llm, string $userSubject, int $maxRetries = 1): array
		{
			$chatHistoryLessonStructGen = [['role' => 'user', 'content' => $userSubject]];
			Log::info("Requesting lesson structure generation for subject: '{$userSubject}' using LLM: {$llm}");
			return MyHelper::llm_no_tool_call($llm, self::SYSTEM_PROMPT_LESSON_STRUCTURE, $chatHistoryLessonStructGen, true, $maxRetries);
		}

		/**
		 * MODIFIED: Generates 3 questions for a single lesson part and difficulty using an LLM.
		 *
		 * @param string $llm The LLM model ID.
		 * @param string $lessonPartTitle Title of the current lesson part.
		 * @param string $lessonPartText Text content of the current lesson part.
		 * @param string $difficulty The target difficulty ('easy', 'medium', 'hard').
		 * @param array $existingQuestionTexts Array of question texts already generated for the whole subject.
		 * @param int $maxRetries Maximum number of retries for the LLM call.
		 * @return array Result from llm_no_tool_call (JSON decoded array or error array).
		 */
		public static function generateQuestionsForPartDifficulty(string $llm, string $lessonPartTitle, string $lessonPartText, string $difficulty, array $existingQuestionTexts, int $maxRetries = 1): array
		{
			$userContent = "Current Lesson Part Title: " . $lessonPartTitle . "\n\n";
			$userContent .= "Current Lesson Part Text: " . $lessonPartText . "\n\n";
			$userContent .= "Target Difficulty: " . $difficulty . "\n\n"; // Add target difficulty
			$userContent .= "Previously Generated Questions (Avoid Duplicates):\n";
			if (empty($existingQuestionTexts)) {
				$userContent .= "- None yet";
			} else {
				foreach ($existingQuestionTexts as $qText) {
					$userContent .= "- " . $qText . "\n";
				}
			}

			$chatHistoryQuestionGen = [['role' => 'user', 'content' => $userContent]];
			Log::info("Requesting {$difficulty} question generation for part '{$lessonPartTitle}' using LLM: {$llm}");
			// Log::debug("Existing questions provided to LLM for duplication check:", $existingQuestionTexts); // Optional

			// Expecting a flat array of 5 question objects now
			return MyHelper::llm_no_tool_call($llm, self::SYSTEM_PROMPT_QUIZ_GENERATION, $chatHistoryQuestionGen, true, $maxRetries);
		}


		/**
		 * Validates the structure of the basic lesson plan (no questions).
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
				// DO NOT check for 'questions' key here
			}
			return true; // All checks passed
		}

		/**
		 * RENAMED & MODIFIED: Validates the structure of a list of question questions (e.g., a batch of 5).
		 *
		 * @param array|null $questionListData The decoded JSON data (should be an array of question objects).
		 * @return bool True if the structure is valid, false otherwise.
		 */
		public static function isValidQuestionListResponse(?array $questionListData): bool
		{
			if (empty($questionListData) || !is_array($questionListData)) {
				Log::warning('Invalid Question List: Data is empty or not an array.', ['data' => $questionListData]);
				return false;
			}

			foreach ($questionListData as $index => $question) {
				if (!self::isValidSingleQuestionStructure($question)) {
					Log::warning("Invalid question structure found within question list (Question index {$index}).", ['question_data' => $question]);
					return false;
				}
			}
			return true; // All checks passed
		}


		public static function isValidSingleQuestionStructure($data): bool
		{
			if (!is_array($data)) return false;
			if (!isset($data['question']) || !is_string($data['question'])) return false;

			if ((!isset($data['image_prompt_idea']) || !is_string($data['image_prompt_idea']))) return false;
			if (!isset($data['image_search_keywords']) || !is_string($data['image_search_keywords'])) return false;

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
		 * MODIFIED: Handle AJAX request to generate lesson structure preview (NO questions).
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

			// --- Generate Lesson Structure ONLY ---
			Log::info("Generating lesson structure...");
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

			Log::info("Lesson structure generated successfully for preview (no questions).");

			// Return the structure data
			return response()->json(['success' => true, 'plan' => $planStructureResult]);
		}

		/**
		 * MODIFIED: Handle the actual creation after user confirmation.
		 * Saves ONLY the lesson structure. Questions are generated later via edit screen.
		 *
		 * @param Request $request
		 * @return \Illuminate\Http\JsonResponse | \Illuminate\Http\RedirectResponse
		 */
		public function createLesson(Request $request)
		{
			// Validation rules simplified - only structure needed
			$validator = Validator::make($request->all(), [
				'subject_name' => 'required|string|max:150',
				'llm_used' => 'required|string|max:100',
				'plan' => 'required|array',
				'plan.main_title' => 'required|string',
				'plan.image_prompt_idea' => 'required|string',
				'plan.lesson_parts' => 'required|array|size:3',
				'plan.lesson_parts.*.title' => 'required|string',
				'plan.lesson_parts.*.text' => 'required|string',
				'plan.lesson_parts.*.image_prompt_idea' => 'required|string',
				// No 'questions' validation needed here
			]);

			if ($validator->fails()) {
				Log::error('Invalid data received for lesson creation.', ['errors' => $validator->errors()->toArray(), 'data' => $request->input('plan', [])]);
				return response()->json(['success' => false, 'message' => 'Invalid data received for lesson creation. ' . $validator->errors()->first()], 422);
			}

			$userSubject = $request->input('subject_name');
			$llm = $request->input('llm_used');
			$plan = $request->input('plan');

			// Double-check plan validity using the structure validator
			if (!self::isValidLessonStructureResponse($plan)) { // Use the simpler validator
				Log::error('Invalid final plan structure received on createLesson endpoint.', ['plan' => $plan]);
				return response()->json(['success' => false, 'message' => 'Invalid lesson plan structure received during final check.'], 400);
			}

			$sessionId = Str::uuid()->toString(); // Generate a unique ID for this lesson session
			Log::info("Confirmed creation request received. Saving structure ONLY. Session ID: {$sessionId}, Subject: '{$userSubject}'");

			// --- 1. Create Subject Record (Store structured plan, NO QUIZZES) ---
			$subject = Subject::create([
				'name' => $userSubject,
				'title' => $plan['main_title'],
				'image_prompt_idea' => $plan['image_prompt_idea'],
				'lesson_parts' => $plan['lesson_parts'], // Store parts array (no questions inside yet)
				'session_id' => $sessionId,
				'llm_used' => $llm,
			]);

			Log::info("Subject record created with ID: {$subject->id}, SessionID: {$sessionId}. No questions created at this stage.");

			// --- 2. NO Question Records Created Here ---

			// --- 3. Respond / Redirect ---
			// Assets (video, audio, images) will be generated on first access or via edit screen/background job.
			Log::info("Lesson structure saved successfully. Redirecting user to edit screen.");
			return response()->json([
				'success' => true,
				'message' => 'Lesson structure saved! Please use the edit screen to add questions and generate assets.',
				// Redirect to EDIT screen instead of question interface
				'redirectUrl' => route('lesson.edit', ['subject' => $sessionId])
			]);
		}


		// ==============================================
		// EDITING AND ON-DEMAND GENERATION METHODS
		// ==============================================

		/**
		 * Show the lesson edit page.
		 */
		public function edit(Subject $subject)
		{
			// Eager load questions and their associated images
			// Order them by part, then difficulty, then their own order/id
			$subject->load(['questions' => function ($query) {
				$query->orderBy('lesson_part_index', 'asc')
					->orderByRaw("FIELD(difficulty_level, 'easy', 'medium', 'hard')")
					->orderBy('order', 'asc') // Use the order field
					->orderBy('id', 'asc');
			}, 'questions.generatedImage']);

			Log::info("Showing edit page for Subject ID: {$subject->id} (Session: {$subject->session_id})");

			// Group questions by part and difficulty for easier display in the view
			$groupedQuestions = [];
			foreach ($subject->questions as $question) {
				$partIndex = $question->lesson_part_index;
				$difficulty = $question->difficulty_level;
				if (!isset($groupedQuestions[$partIndex])) {
					$groupedQuestions[$partIndex] = ['easy' => [], 'medium' => [], 'hard' => []];
				}
				if (!isset($groupedQuestions[$partIndex][$difficulty])) {
					$groupedQuestions[$partIndex][$difficulty] = []; // Ensure difficulty array exists
				}
				$groupedQuestions[$partIndex][$difficulty][] = $question;
			}

			// Decode lesson parts if needed (it should be auto-decoded by cast)
			$lessonParts = $subject->lesson_parts;
			if (is_string($lessonParts)) {
				$lessonParts = json_decode($lessonParts, true);
			}
			// Ensure it's an array for the view
			$subject->lesson_parts = is_array($lessonParts) ? $lessonParts : [];

			// Get the default LLM (needed if generating questions on this page)
			$llm = $subject->llm_used ?: env('DEFAULT_LLM');

			return view('lesson_edit', [
				'subject' => $subject,
				'groupedQuestions' => $groupedQuestions,
				'llm' => $llm, // Pass LLM to view for generation calls
			]);
		}


		/**
		 * NEW: AJAX endpoint to generate a batch of 3 questions for a specific part and difficulty.
		 *
		 * @param Request $request
		 * @param Subject $subject
		 * @param int $partIndex
		 * @param string $difficulty ('easy', 'medium', 'hard')
		 * @return \Illuminate\Http\JsonResponse
		 */
		public function generateQuestionBatchAjax(Request $request, Subject $subject, int $partIndex, string $difficulty)
		{
			Log::info("AJAX request to generate '{$difficulty}' question batch for Subject ID: {$subject->id}, Part Index: {$partIndex}");

			// Validate difficulty
			if (!in_array($difficulty, ['easy', 'medium', 'hard'])) {
				Log::error("Invalid difficulty '{$difficulty}' requested.");
				return response()->json(['success' => false, 'message' => 'Invalid difficulty level provided.'], 400);
			}

			// Retrieve and decode lesson parts
			$lessonParts = is_array($subject->lesson_parts) ? $subject->lesson_parts : json_decode($subject->lesson_parts, true);

			// Validate partIndex and get part data
			if (!is_array($lessonParts) || !isset($lessonParts[$partIndex])) {
				Log::error("Invalid part index ({$partIndex}) or lesson parts data for Subject ID: {$subject->id}.");
				return response()->json(['success' => false, 'message' => 'Invalid lesson part index.'], 400);
			}
			$partData = $lessonParts[$partIndex];
			$partTitle = $partData['title'] ?? 'Lesson Part ' . ($partIndex + 1);
			$partText = $partData['text'] ?? '';

			if(empty($partText)){
				Log::error("Cannot generate questions for part {$partIndex}: Text is empty.");
				return response()->json(['success' => false, 'message' => 'Lesson part text is empty.'], 400);
			}

			// Get LLM
			$llm = $subject->llm_used ?: env('DEFAULT_LLM');
			if (empty($llm)) {
				Log::error("No LLM configured for subject {$subject->id} or as default.");
				return response()->json(['success' => false, 'message' => 'AI model configuration error.'], 500);
			}

			// Fetch ALL existing question texts for THIS subject to prevent duplicates
			$existingQuestionTexts = $subject->questions()->pluck('question_text')->toArray();
			Log::debug("Found " . count($existingQuestionTexts) . " existing questions for subject {$subject->id}");

			$maxRetries = 1;
			$questionResult = self::generateQuestionsForPartDifficulty(
				$llm,
				$partTitle,
				$partText,
				$difficulty, // Pass difficulty
				$existingQuestionTexts,
				$maxRetries
			);

			Log::info("LLM Question Gen Result for Part {$partIndex}, Difficulty '{$difficulty}': ", $questionResult);

			if (isset($questionResult['error'])) {
				$errorMsg = $questionResult['error'];
				$logMsg = "LLM Question Gen Error for Part {$partIndex}, Difficulty '{$difficulty}': " . $errorMsg;
				Log::error($logMsg, ['subject' => $subject->id, 'llm' => $llm, 'part_title' => $partTitle]);
				return response()->json(['success' => false, 'message' => "Failed to generate {$difficulty} questions: " . $errorMsg], 500);
			}


			// Validate the generated list of questions
			if (!self::isValidQuestionListResponse($questionResult['questions'])) {
				$errorMsg = "LLM returned an invalid {$difficulty} question structure for lesson part '{$partTitle}'.";
				Log::error($errorMsg, ['subject' => $subject->id, 'llm' => $llm, 'part_title' => $partTitle, 'response' => $questionResult]);
				return response()->json(['success' => false, 'message' => $errorMsg . ' Please try again.'], 500);
			}

			// Save the new questions
			$createdQuestionsData = [];
			// Determine the next order number
			$maxOrder = Question::where('subject_id', $subject->id)->max('order') ?? -1;
			$nextOrder = $maxOrder + 1;

			try {
				foreach ($questionResult['questions'] as $questionQuestionData) {
					// Prepare answers array *without* audio paths
					$answersToStore = [];
					foreach ($questionQuestionData['answers'] as $answer) {
						$answersToStore[] = [
							'text' => $answer['text'],
							'is_correct' => $answer['is_correct'],
							'feedback' => $answer['feedback'],
							// audio paths omitted
						];
					}

					$newQuestion = Question::create([
						'subject_id' => $subject->id,
						'image_prompt_idea' => $questionQuestionData['image_prompt_idea'] ?? null,
						'image_search_keywords' => $questionQuestionData['image_search_keywords'] ?? null,
						'question_text' => $questionQuestionData['question'],
						'answers' => $answersToStore,
						'difficulty_level' => $difficulty,
						'lesson_part_index' => $partIndex,
						'order' => $nextOrder++,
					]);
					// Load the image relationship in case it was somehow set (unlikely here)
					// $newQuestion->load('generatedImage');
					$createdQuestionsData[] = $newQuestion->toArray() + ['question_audio_url' => null]; // Add null audio URL initially
				}
				Log::info("Created " . count($createdQuestionsData) . " new '{$difficulty}' question records for Subject ID: {$subject->id}, Part: {$partIndex}");

				// Return the data for the newly created questions so the frontend can render them
				return response()->json([
					'success' => true,
					'message' => "Successfully generated 5 {$difficulty} questions!",
					'questions' => $createdQuestionsData // Send back data for JS rendering
				]);

			} catch (Exception $e) {
				Log::error("Database error saving new questions for Subject ID {$subject->id}: " . $e->getMessage());
				return response()->json(['success' => false, 'message' => 'Failed to save generated questions.'], 500);
			}
		}

		/**
		 * NEW: AJAX endpoint to delete a specific question.
		 *
		 * @param Question $question Route model binding
		 * @return \Illuminate\Http\JsonResponse
		 */
		public function deleteQuestionAjax(Question $question)
		{
			$questionId = $question->id;
			$subjectId = $question->subject_id;
			Log::info("AJAX request to delete Question ID: {$questionId} from Subject ID: {$subjectId}");

			DB::beginTransaction(); // Use transaction for safety
			try {
				// Optional: Add authorization check here if needed

				// --- Asset Cleanup ---
				// 1. Audio Files
				if ($question->question_audio_path && Storage::disk('public')->exists($question->question_audio_path)) {
					Storage::disk('public')->delete($question->question_audio_path);
					Log::info("Deleted question audio file: {$question->question_audio_path}");
				}
				if (is_array($question->answers)) {
					foreach ($question->answers as $answer) {
						// Safely access keys
						$answerAudioPath = $answer['answer_audio_path'] ?? null;
						$feedbackAudioPath = $answer['feedback_audio_path'] ?? null;

						if ($answerAudioPath && Storage::disk('public')->exists($answerAudioPath)) {
							Storage::disk('public')->delete($answerAudioPath);
							Log::info("Deleted answer audio file: {$answerAudioPath}");
						}
						if ($feedbackAudioPath && Storage::disk('public')->exists($feedbackAudioPath)) {
							Storage::disk('public')->delete($feedbackAudioPath);
							Log::info("Deleted feedback audio file: {$feedbackAudioPath}");
						}
					}
				}

				// 2. Associated Image File (ONLY if source is 'upload' or 'freepik')
				if ($question->generated_image_id) {
					$image = GeneratedImage::find($question->generated_image_id);
					if ($image && in_array($image->source, ['upload', 'freepik'])) {
						Log::info("Deleting storage files for GeneratedImage ID: {$image->id} (Source: {$image->source}) linked to Question ID: {$questionId}");
						$image->deleteStorageFiles();
						// Optionally delete the GeneratedImage record itself if it's guaranteed not to be reused
						// $image->delete();
						// For now, just delete files to be safer. Question deletion breaks the link.
					} elseif ($image) {
						Log::info("Keeping GeneratedImage ID: {$image->id} (Source: {$image->source}) as it might be shared or managed elsewhere.");
					}
				}

				// --- Delete Question Record ---
				$question->delete();
				DB::commit(); // Commit transaction

				Log::info("Successfully deleted Question ID: {$questionId}");
				return response()->json(['success' => true, 'message' => 'Question deleted successfully.']);

			} catch (Exception $e) {
				DB::rollBack(); // Rollback on error
				Log::error("Error deleting Question ID {$questionId}: " . $e->getMessage());
				return response()->json(['success' => false, 'message' => 'Failed to delete question.'], 500);
			}
		}

		public function generatePartVideoAjax(Request $request, Subject $subject, int $partIndex)
		{
			// ... (Keep existing implementation) ...
			Log::info("AJAX request to generate video for Subject ID: {$subject->id}, Part Index: {$partIndex}");
			// Retrieve and decode lesson parts
			$lessonParts = is_array($subject->lesson_parts) ? $subject->lesson_parts : json_decode($subject->lesson_parts, true);
			// Validate partIndex
			if (!is_array($lessonParts) || !isset($lessonParts[$partIndex])) {
				Log::error("Invalid part index ({$partIndex}) or lesson parts data for Subject ID: {$subject->id}.");
				return response()->json(['success' => false, 'message' => 'Invalid lesson part index.'], 400);
			}
			// Check if video already exists for this part
			if (!empty($lessonParts[$partIndex]['video_path']) && !empty($lessonParts[$partIndex]['video_url'])) {
				$relativePath = $lessonParts[$partIndex]['video_path'];
				$videoUrl = $lessonParts[$partIndex]['video_url'];
				// Ensure file actually exists before claiming success
				if (Storage::disk('public')->exists($relativePath)) {
					Log::warning("Video already exists for Subject ID: {$subject->id}, Part Index: {$partIndex}. Path: {$relativePath}");
					return response()->json([
						'success' => true, // Indicate it exists
						'message' => 'Video already exists for this part.',
						'video_url' => $videoUrl,
						'video_path' => $relativePath
					], 200); // 200 OK is fine here
				} else {
					Log::warning("Video path/URL recorded but file missing for Subject ID: {$subject->id}, Part Index: {$partIndex}. Path: {$relativePath}. Will attempt regeneration.");
					// Allow generation to proceed
				}
			}

			// Get text for video generation
			$partData = $lessonParts[$partIndex];
			$videoText = ($partData['title'] ?? 'Lesson Part') . ". \n" . ($partData['text'] ?? 'No content.');
			$defaultFaceUrl = env('DEFAULT_FACE_URL', 'https://elooi.com/video/video1.mp4');
			$videoResult = null;
			try {
				$useV2 = (stripos(env('APP_URL', ''), 'localhost') === false); // Prefer v2 unless on localhost
				Log::info("Attempting video generation. Using " . ($useV2 ? "text2videov2 (OpenAI TTS + Gooey Lipsync)" : "text2video (Gooey Lipsync+Google TTS)"));

				if ($useV2) {
					$videoResult = MyHelper::text2videov2($videoText, $defaultFaceUrl);
				} else {
					// Note: text2video might need specific Google voice/config from env
					$googleVoice = env('GOOGLE_TTS_VOICE', 'en-US-Studio-O'); // Example Google Voice
					$videoResult = MyHelper::text2video($videoText, $defaultFaceUrl, $googleVoice);
				}

				if ($videoResult && $videoResult['success'] && isset($videoResult['video_path'])) {
					// Ensure relative path for storage URL
					$relativePath = $videoResult['video_path'];
					if (strpos($relativePath, 'public/') === 0) {
						$relativePath = substr($relativePath, strlen('public/'));
					}
					$videoUrl = Storage::disk('public')->url($relativePath); // Generate public URL

					// Update the specific lesson part in the array
					$lessonParts[$partIndex]['video_path'] = $relativePath; // Store relative path
					$lessonParts[$partIndex]['video_url'] = $videoUrl; // Store generated URL

					// Save the modified array back to the model
					$subject->lesson_parts = $lessonParts;
					$subject->save();

					Log::info("Part video generated and saved. Subject ID: {$subject->id}, Part Index: {$partIndex}. Path: {$relativePath}, URL: {$videoUrl}");
					return response()->json([
						'success' => true,
						'message' => 'Video generated successfully!',
						'video_url' => $videoUrl,
						'video_path' => $relativePath
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

		public function generateQuestionAudioAjax(Question $question)
		{
			// ... (Keep existing implementation) ...
			Log::info("AJAX request to generate question audio for Question ID: {$question->id}");

			if (!empty($question->question_audio_path) && !empty($question->question_audio_url)) {
				// Verify file existence before claiming success
				if (Storage::disk('public')->exists($question->question_audio_path)) {
					Log::warning("Question audio already exists for Question ID: {$question->id}. Path: {$question->question_audio_path}");
					return response()->json([
						'success' => true, // Indicate it exists
						'message' => 'Question audio already exists.',
						'audio_url' => $question->question_audio_url,
						'audio_path' => $question->question_audio_path
					], 200); // 200 OK
				} else {
					Log::warning("Question audio path/URL recorded but file missing for Question ID: {$question->id}. Path: {$question->question_audio_path}. Will attempt regeneration.");
					// Allow generation to proceed
				}
			}

			if (empty($question->question_text)) {
				Log::error("Cannot generate question audio for Question ID {$question->id}: Question text is empty.");
				return response()->json(['success' => false, 'message' => 'Question text is empty.'], 400);
			}

			try {
				$ttsEngine = env('DEFAULT_TTS_ENGINE', 'google');
				$ttsVoice = ($ttsEngine === 'openai') ? env('OPENAI_TTS_VOICE', 'alloy') : env('GOOGLE_TTS_VOICE', 'en-US-Studio-O');
				$languageCode = 'en-US';
				// More robust unique identifier
				$questionIdentifier = "s{$question->subject_id}_p{$question->lesson_part_index}_q{$question->id}";
				$outputFilenameBase = 'audio/question_q_' . $questionIdentifier; // Include path segment

				$audioResult = MyHelper::text2speech(
					$question->question_text,
					$ttsVoice,
					$languageCode,
					$outputFilenameBase,
					$ttsEngine
				);

				if ($audioResult['success'] && isset($audioResult['storage_path'])) {
					$question->question_audio_path = $audioResult['storage_path'];
					$question->save(); // Save the path, accessor will generate URL

					// We need to get the URL generated by the accessor to return it
					$generatedUrl = $question->fresh()->question_audio_url; // Refresh model and get URL

					Log::info("Question audio generated for Question ID: {$question->id}. Path: {$question->question_audio_path}, URL: {$generatedUrl}");
					return response()->json([
						'success' => true,
						'message' => 'Question audio generated successfully!',
						'audio_url' => $generatedUrl, // Return the generated URL
						'audio_path' => $question->question_audio_path,
					]);
				} else {
					throw new \Exception($audioResult['message'] ?? 'TTS generation failed');
				}
			} catch (\Exception $e) {
				Log::error("Exception during question audio generation for Question ID {$question->id}: " . $e->getMessage());
				return response()->json(['success' => false, 'message' => 'Server error during audio generation: ' . $e->getMessage()], 500);
			}
		}

		public function generateAnswerAudioAjax(Question $question)
		{
			Log::info("AJAX request to generate answer/feedback audio for Question ID: {$question->id}");
			$currentAnswers = $question->answers ?? [];

			if (empty($currentAnswers) || !is_array($currentAnswers)) {
				Log::error("Cannot generate answer audio for Question ID {$question->id}: Answers data is missing or invalid.");
				return response()->json(['success' => false, 'message' => 'Question answers data is missing or invalid.'], 400);
			}

			// Check if audio seems to exist already (e.g., first answer has BOTH paths/URLs and files exist)
			$audioExists = false;
			if(isset($currentAnswers[0]['answer_audio_path'], $currentAnswers[0]['feedback_audio_path'])) {
				if(Storage::disk('public')->exists($currentAnswers[0]['answer_audio_path']) &&
					Storage::disk('public')->exists($currentAnswers[0]['feedback_audio_path'])) {
					$audioExists = true;
				} else {
					Log::warning("Answer/Feedback audio paths recorded but files missing for Question ID: {$question->id}. Will attempt regeneration.");
				}
			}

			if ($audioExists) {
				Log::warning("Answer/feedback audio seems to already exist and files are present for Question ID: {$question->id}.");
				// Return the existing data so JS can potentially update button states if needed
				return response()->json([
					'success' => true, // Indicate it exists
					'message' => 'Answer/feedback audio appears to already exist.',
					'answers' => $question->answers, // Return current answer data
				], 200);
			}

			try {
				$ttsEngine = env('DEFAULT_TTS_ENGINE', 'google');
				$ttsVoice = ($ttsEngine === 'openai') ? env('OPENAI_TTS_VOICE', 'alloy') : env('GOOGLE_TTS_VOICE', 'en-US-Studio-O');
				$languageCode = 'en-US';
				$questionIdentifier = "s{$question->subject_id}_p{$question->lesson_part_index}_q{$question->id}";
				$filenamePrefix = 'audio/question_' . $questionIdentifier; // Include path segment

				// Process answers using the static method in Question model
				$processedAnswers = Question::processAnswersWithTTS(
					$currentAnswers,
					$question->id, // Pass Question ID for potential use inside, although identifier is main now
					$filenamePrefix, // Identifier for filenames
					$ttsEngine,
					$ttsVoice,
					$languageCode
				);

				// Update the question's answers column
				$question->answers = $processedAnswers;
				$question->save();

				Log::info("Answer/feedback audio generation complete for Question ID: {$question->id}");
				return response()->json([
					'success' => true,
					'message' => 'Answer and feedback audio generated successfully!',
					'answers' => $processedAnswers // Return updated answers array
				]);
			} catch (\Exception $e) {
				Log::error("Exception during answer/feedback audio generation for Question ID {$question->id}: " . $e->getMessage());
				return response()->json(['success' => false, 'message' => 'Server error during audio generation: ' . $e->getMessage()], 500);
			}
		}

		public function generateQuestionImageAjax(Request $request, Question $question)
		{
			$newPrompt = $request->input('new_prompt'); // Get potential new prompt

			if ($newPrompt) {
				Log::info("AJAX request to *regenerate* image for Question ID: {$question->id} with new prompt.");
				$validator = Validator::make(['new_prompt' => $newPrompt], [
					'new_prompt' => 'required|string|max:500'
				]);
				if ($validator->fails()) {
					Log::error("Invalid prompt provided for image regeneration. Question ID: {$question->id}", ['errors' => $validator->errors()]);
					return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
				}
				$question->image_prompt_idea = $newPrompt;
				$question->save();
				Log::info("Updated image prompt for Question ID: {$question->id}. Proceeding with regeneration.");

			} else {
				Log::info("AJAX request to generate image for Question ID: {$question->id}.");
				// --- Standard Generation - Check Existence ---
				if (!empty($question->generated_image_id)) {
					$existingImage = GeneratedImage::find($question->generated_image_id);
					if ($existingImage && $existingImage->original_url && Storage::disk('public')->exists($existingImage->image_original_path)) {
						Log::warning("Image already exists and file found for Question ID: {$question->id}. Image ID: {$question->generated_image_id}");
						return response()->json([
							'success' => true, // Indicate it exists
							'message' => 'Image already exists for this question.',
							'image_id' => $question->generated_image_id,
							'image_urls' => [
								'small' => $existingImage->small_url,
								'medium' => $existingImage->medium_url,
								'large' => $existingImage->large_url,
								'original' => $existingImage->original_url,
							],
							'prompt' => $question->image_prompt_idea // Return current prompt
						], 200); // 200 OK
					} else {
						Log::warning("Image ID {$question->generated_image_id} linked to Question {$question->id}, but image record or file missing. Will attempt regeneration.");
						// Reset link and allow generation to proceed
						$question->generated_image_id = null;
						$question->save();
					}
				}
				if (empty($question->image_prompt_idea)) {
					Log::error("Cannot generate image for Question ID {$question->id}: Image prompt is empty.");
					return response()->json(['success' => false, 'message' => 'Image prompt is empty.'], 400);
				}
			}

			// --- Common Generation Call ---
			try {
				$promptToUse = $question->image_prompt_idea;
				if (empty($promptToUse)) {
					throw new \Exception('Image prompt is unexpectedly empty.');
				}

				$imageModel = env('DEFAULT_IMAGE_MODEL', 'fal-ai/flux/schnell');
				$imageSize = 'square_hd'; // Or 'square' etc.

				Log::info("Calling makeImage for Question {$question->id}. Model: {$imageModel}, Size: {$imageSize}, Prompt: '{$promptToUse}'");

				$imageResult = MyHelper::makeImage(
					$promptToUse,
					$imageModel,
					$imageSize
				);

				// Check for success and *image_id* which is now preferred over guid lookup
				if ($imageResult['success'] && isset($imageResult['image_id'], $imageResult['image_urls'])) {
					// Link the generated image to the question
					$question->generated_image_id = $imageResult['image_id'];
					$question->save(); // Save the question with the new image ID

					Log::info("Image " . ($newPrompt ? "regenerated" : "generated") . " and linked for Question ID: {$question->id}. Image ID: {$imageResult['image_id']}");
					return response()->json([
						'success' => true,
						'message' => 'Image ' . ($newPrompt ? "regenerated" : "generated") . ' successfully!',
						'image_id' => $imageResult['image_id'],
						'image_guid' => $imageResult['image_guid'] ?? null, // Include GUID if available
						'image_urls' => $imageResult['image_urls'],
						'prompt' => $promptToUse // Return the prompt used
					]);
				} else {
					// Handle potential case where GUID is returned but not ID (older helper version?)
					if ($imageResult['success'] && isset($imageResult['image_guid'])) {
						$imageModel = GeneratedImage::where('image_guid', $imageResult['image_guid'])->first();
						if ($imageModel) {
							$question->generated_image_id = $imageModel->id;
							$question->save();
							Log::info("Image " . ($newPrompt ? "regenerated" : "generated") . " and linked (fallback lookup by GUID) for Question ID: {$question->id}. Image ID: {$imageModel->id}");
							$imageUrls = [
								'small' => $imageModel->small_url,
								'medium' => $imageModel->medium_url,
								'large' => $imageModel->large_url,
								'original' => $imageModel->original_url,
							];
							return response()->json([
								'success' => true,
								'message' => 'Image ' . ($newPrompt ? "regenerated" : "generated") . ' successfully!',
								'image_id' => $imageModel->id,
								'image_guid' => $imageResult['image_guid'],
								'image_urls' => $imageUrls,
								'prompt' => $promptToUse
							]);
						} else {
							Log::error("Image generation reported success with GUID {$imageResult['image_guid']} but GeneratedImage record not found. Question ID {$question->id}");
							throw new \Exception('Image generation succeeded but failed to find/link the image record.');
						}
					}
					// If it truly failed or returned unexpected structure
					throw new \Exception($imageResult['message'] ?? 'Image generation failed');
				}
			} catch (\Exception $e) {
				Log::error("Exception during image generation/regeneration for Question ID {$question->id}: " . $e->getMessage(), ['exception' => $e]);
				// Revert prompt change if regeneration failed? Maybe not, user might want to try again.
				if ($newPrompt) {
					Log::warning("Image regeneration failed, but the new prompt '{$newPrompt}' remains saved for Question ID {$question->id}.");
				}
				return response()->json(['success' => false, 'message' => 'Server error during image generation: ' . $e->getMessage()], 500);
			}
		}

		public function uploadQuestionImageAjax(Request $request, Question $question)
		{
			$validator = Validator::make($request->all(), [
				'question_image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB Max
			]);

			if ($validator->fails()) {
				return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
			}

			Log::info("AJAX request to upload image for Question ID: {$question->id}");

			/** @var UploadedFile $uploadedFile */
			$uploadedFile = $request->file('question_image');
			$originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
			$baseDir = 'uploads/question_images/' . $question->subject_id; // Organize by subject
			$baseName = Str::slug($originalFilename) . '_' . time(); // Unique base name

			DB::beginTransaction();
			try {
				// --- Process and Save Image ---
				$imagePaths = MyHelper::handleImageProcessing($uploadedFile, $baseDir, $baseName);

				if (!$imagePaths) {
					throw new Exception('Failed to process and save the uploaded image.');
				}

				// --- Create GeneratedImage Record ---
				$newImage = GeneratedImage::create([
					'image_type' => 'question',
					'image_guid' => Str::uuid(), // Unique GUID for image set
					'source' => 'upload',
					'prompt' => 'User Upload: ' . $uploadedFile->getClientOriginalName(),
					'image_model' => 'upload', // Or null
					'image_size_setting' => $uploadedFile->getSize(), // Store original size
					'image_original_path' => $imagePaths['original_path'],
					'image_large_path' => $imagePaths['large_path'],
					'image_medium_path' => $imagePaths['medium_path'],
					'image_small_path' => $imagePaths['small_path'],
					'api_response_data' => ['original_filename' => $uploadedFile->getClientOriginalName()],
				]);

				// --- Clean up old image files if replaced ---
				if ($question->generated_image_id) {
					$oldImage = GeneratedImage::find($question->generated_image_id);
					if ($oldImage && in_array($oldImage->source, ['upload', 'freepik'])) {
						Log::info("Deleting old image files (ID: {$oldImage->id}) replaced by upload for Question ID: {$question->id}");
						$oldImage->deleteStorageFiles();
						// Optionally delete the old GeneratedImage record itself
						// $oldImage->delete();
					}
				}

				// --- Link to Question ---
				$question->generated_image_id = $newImage->id;
				$question->save();

				DB::commit();

				// --- Return Success Response ---
				// Refresh image model to get URLs from accessors
				$newImage->refresh();
				$imageUrls = [
					'small' => $newImage->small_url,
					'medium' => $newImage->medium_url,
					'large' => $newImage->large_url,
					'original' => $newImage->original_url,
				];

				Log::info("Image uploaded and linked for Question ID: {$question->id}. New Image ID: {$newImage->id}");
				return response()->json([
					'success' => true,
					'message' => 'Image uploaded successfully!',
					'image_id' => $newImage->id,
					'image_urls' => $imageUrls,
					'prompt' => $question->image_prompt_idea
				]);

			} catch (Exception $e) {
				DB::rollBack();
				Log::error("Exception during image upload for Question ID {$question->id}: " . $e->getMessage(), ['exception' => $e]);
				return response()->json(['success' => false, 'message' => 'Server error during image upload: ' . $e->getMessage()], 500);
			}
		}


	} // End of SubjectController
