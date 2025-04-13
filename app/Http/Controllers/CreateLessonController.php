<?php

	namespace App\Http\Controllers;

	use App\Helpers\MyHelper;
	use App\Models\Category;
	use App\Models\Lesson;
	use App\Models\UserAnswer;
	use App\Models\UserAnswerArchive;
	use Illuminate\Http\Request;
	use Illuminate\Support\Carbon;
	use Illuminate\Support\Facades\DB;
	use Illuminate\Support\Facades\Log;
	use Illuminate\Support\Facades\Validator;
	use Illuminate\Support\Str;

	use Illuminate\Support\Facades\Http;
	use Intervention\Image\Laravel\Facades\Image as InterventionImage;

	// For image resizing

	use Exception;

	// Add Exception import

	class CreateLessonController extends Controller
	{
		/**
		 * Display the lesson input form (Home Page).
		 *
		 * @return \Illuminate\View\View
		 */
		public function index()
		{
			$llms = MyHelper::checkLLMsJson();
			$categories = Category::orderBy('name')->get(); // Fetch categories
			// Eager load question count for potential display (optional)
			$lessons = Lesson::withCount('questions')->orderBy('created_at', 'desc')->get();
			return view('create_lesson', compact('llms', 'lessons', 'categories')); // Pass categories
		}

		public function listLessons()
		{
			// Fetch lessons with eager-loaded category and question count
			$lessons = Lesson::with(['category']) // Eager load the category relationship
			->withCount('questions')
				// Order primarily to keep lessons within a category consistent
				->orderBy('created_at', 'desc')
				->get();

			// Group lessons by category ID. A null key will hold uncategorized lessons.
			$groupedLessons = $lessons->groupBy('category_id');

			// Get category names, ordered by name for display consistency in the view
			// We'll use this map to get category names from IDs later
			$categoryNames = Category::orderBy('name')->pluck('name', 'id')->all(); // Get ID=>Name array

			// Get the category IDs in the desired display order (alphabetical by name)
			// We will iterate through these IDs in the view
			$orderedCategoryIds = array_keys($categoryNames); // IDs ordered by category name

			Log::info("Fetched and grouped lessons for listing. Categories found: " . count($categoryNames));

			// Pass the grouped lessons, the category name map, and the ordered IDs
			return view('lessons_list', compact('groupedLessons', 'categoryNames', 'orderedCategoryIds'));
		}

		// --- Prompt for generating Lesson Structure ONLY ---
		private const SYSTEM_PROMPT_LESSON_STRUCTURE = <<<PROMPT
You are an AI assistant specialized in creating the structure for educational micro-lessons.
The user will provide a subject and potentially a list of existing categories.
Create in the specified language, if no language is provided, use English.

You MUST generate the basic lesson plan structure as a single JSON object.
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
  ],
  "suggested_category_name": "Based on the lesson content, suggest a concise category name (max 5 words). If existing categories are provided, try to match one of them. If none fit well, suggest a *new*, relevant category name."
}

Constraints:
- The output MUST be ONLY the valid JSON object described above. No introductory text, explanations, or markdown formatting outside the JSON structure.
- Ensure exactly 3 `lesson_parts`.
- All text content (titles, lesson text) should be clear, concise, and factually accurate based on the provided subject.
- Generate content suitable for a general audience learning about the subject for the first time.
- The `suggested_category_name` field MUST always be included, even if you are reusing an existing category name.
PROMPT;

		public static function generateLessonStructure(string $llm, string $userLesson, bool $autoDetectCategory = false, $lessonLanguage = 'English', int $maxRetries = 1): array
		{
			$systemPrompt = self::SYSTEM_PROMPT_LESSON_STRUCTURE;
			$userMessageContent = $userLesson;
			$userMessageContent .= "\nThe language is: " . $lessonLanguage;

			if ($autoDetectCategory) {
				$existingCategories = Category::pluck('name')->toArray();
				if (!empty($existingCategories)) {
					$userMessageContent .= "\n\nExisting Categories: " . implode(', ', $existingCategories);
					Log::info("Providing existing categories for auto-detection: " . implode(', ', $existingCategories));
				} else {
					Log::info("No existing categories found to provide for auto-detection.");
				}
				// Note: The instruction to suggest a category is now permanently in the system prompt.
				// We just add the *list* of existing ones to the user message for context.
			}

			$chatHistoryLessonStructGen = [['role' => 'user', 'content' => $userMessageContent]];
			Log::info("Requesting lesson structure generation for lesson: '{$userLesson}' using LLM: {$llm}. Auto-detect category: " . ($autoDetectCategory ? 'Yes' : 'No'));

			return MyHelper::llm_no_tool_call($llm, $systemPrompt, $chatHistoryLessonStructGen, true, $maxRetries);
		}

		public static function isValidLessonStructureResponse(?array $planData): bool
		{
			if (empty($planData) || !is_array($planData)) return false;
			if (!isset($planData['main_title']) || !is_string($planData['main_title'])) return false;
			if (!isset($planData['image_prompt_idea']) || !is_string($planData['image_prompt_idea'])) return false;
			if (!isset($planData['lesson_parts']) || !is_array($planData['lesson_parts']) || count($planData['lesson_parts']) !== 3) return false;

			// Add check for the suggested category name
			if (!isset($planData['suggested_category_name']) || !is_string($planData['suggested_category_name'])) return false;

			foreach ($planData['lesson_parts'] as $part) {
				if (!is_array($part) || !isset($part['title']) || !is_string($part['title']) || !isset($part['text']) || !is_string($part['text']) || !isset($part['image_prompt_idea']) || !is_string($part['image_prompt_idea'])) {
					return false;
				}
				// DO NOT check for 'questions' key here
			}
			return true; // All checks passed
		}

		public function generatePlanPreview(Request $request)
		{
			$validator = Validator::make($request->all(), [
				'lesson' => 'required|string|max:1024',
				'llm' => 'required|string|max:100',
				'category_id' => ['required', 'string'], // Can be 'auto' or an ID
				'language' => 'required|string|max:30', // Validate language early
			]);

			if ($validator->fails()) {
				return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
			}

			$userLesson = $request->input('lesson');
			$llm = $request->input('llm');
			$categoryIdInput = $request->input('category_id');
			$language = $request->input('language'); // Get language
			$maxRetries = 1;

			// Check if selected category ID is valid (if not 'auto')
			if ($categoryIdInput !== 'auto' && !Category::where('id', $categoryIdInput)->exists()) {
				return response()->json(['success' => false, 'message' => 'Invalid category selected.'], 422);
			}

			$autoDetectCategory = ($categoryIdInput === 'auto');

			Log::info("AJAX request received for plan preview. Lesson: '{$userLesson}', LLM: {$llm}, Category Input: {$categoryIdInput}, Language: {$language}");

			Log::info("Generating lesson structure...");
			// Pass autoDetect flag to the structure generator
			$planStructureResult = self::generateLessonStructure($llm, $userLesson, $autoDetectCategory, $language, $maxRetries);

			if (isset($planStructureResult['error'])) {
				$errorMsg = $planStructureResult['error'];
				Log::error("LLM Structure Gen Error: " . $errorMsg, ['lesson' => $userLesson, 'llm' => $llm]);
				return response()->json(['success' => false, 'message' => 'Failed to generate lesson structure: ' . $errorMsg]);
			}

			if (!self::isValidLessonStructureResponse($planStructureResult)) {
				$errorMsg = 'LLM returned an invalid lesson structure (check includes category).';
				Log::error($errorMsg, ['lesson' => $userLesson, 'llm' => $llm, 'response' => $planStructureResult]);
				return response()->json(['success' => false, 'message' => $errorMsg . ' Please try refining your lesson or using a different model.']);
			}

			Log::info("Lesson structure generated successfully for preview (no questions). Suggested Category: " . $planStructureResult['suggested_category_name']);

			// Prepare response data
			$responseData = [
				'success' => true,
				'plan' => $planStructureResult,
				'language_selected' => $language, // Pass language back
				'category_input' => $categoryIdInput, // Pass original input ('auto' or ID) back
				'suggested_category_name' => $planStructureResult['suggested_category_name'] ?? null // Include suggested name from LLM
			];

			return response()->json($responseData);
		}


		public function createLesson(Request $request)
		{
			// Validation rules updated
			$validator = Validator::make($request->all(), [
				'lesson_name' => 'required|string|max:512',
				'preferred_llm' => 'required|string|max:100',
				'tts_engine' => 'required|string|in:google,openai',
				'tts_voice' => 'required|string|max:100',
				'tts_language_code' => 'required|string|max:10',
				'language' => 'required|string|max:10', // Validate saved language
				'category_input' => ['required', 'string'], // 'auto' or numeric ID string
				'suggested_category_name' => ['nullable', 'string', 'max:255'], // Provided if category_input was 'auto'
				'plan' => 'required|array',
				'plan.main_title' => 'required|string',
				'plan.image_prompt_idea' => 'required|string',
				'plan.lesson_parts' => 'required|array|size:3',
				'plan.lesson_parts.*.title' => 'required|string',
				'plan.lesson_parts.*.text' => 'required|string',
				'plan.lesson_parts.*.image_prompt_idea' => 'required|string',
				// No need to validate plan.suggested_category_name here, it's handled below
			]);

			if ($validator->fails()) {
				Log::error('Invalid data received for lesson creation.', ['errors' => $validator->errors()->toArray(), 'data' => $request->all()]);
				return response()->json(['success' => false, 'message' => 'Invalid data received for lesson creation. ' . $validator->errors()->first()], 422);
			}

			$userLesson = $request->input('lesson_name');
			$preferredLlm = $request->input('preferred_llm');
			$ttsEngine = $request->input('tts_engine');
			$ttsVoice = $request->input('tts_voice');
			$ttsLanguageCode = $request->input('tts_language_code');
			$language = $request->input('language');
			$categoryInput = $request->input('category_input');
			$suggestedCategoryName = $request->input('suggested_category_name');
			$plan = $request->input('plan');

			// Re-validate the core plan structure (excluding suggested_category_name which is part of the LLM output validation)
			$corePlanData = $plan;
			unset($corePlanData['suggested_category_name']); // Temporarily remove for structure check
			if (!self::isValidLessonStructureResponse(array_merge($corePlanData, ['suggested_category_name' => 'placeholder']))) { // Add placeholder to pass validation format check
				Log::error('Invalid final plan structure received on createLesson endpoint.', ['plan' => $plan]);
				return response()->json(['success' => false, 'message' => 'Invalid lesson plan structure received during final check.'], 400);
			}


			$sessionId = Str::uuid()->toString();
			Log::info("Confirmed creation request received. Session ID: {$sessionId}, Lesson: '{$userLesson}', Lang: {$language}, CatInput: {$categoryInput}");

			// --- Determine Final Category ID ---
			$finalCategoryId = null;
			if ($categoryInput === 'auto' && !empty($suggestedCategoryName)) {
				Log::info("Handling auto-detected category: '{$suggestedCategoryName}'");
				// Try to find existing category (case-insensitive)
				$category = Category::whereRaw('LOWER(name) = ?', [strtolower($suggestedCategoryName)])->first();
				if ($category) {
					$finalCategoryId = $category->id;
					Log::info("Found existing category ID: {$finalCategoryId} for '{$suggestedCategoryName}'");
				} else {
					// Create new category
					try {
						$newCategory = Category::create(['name' => $suggestedCategoryName]);
						$finalCategoryId = $newCategory->id;
						Log::info("Created new category '{$suggestedCategoryName}' with ID: {$finalCategoryId}");
					} catch (\Exception $e) {
						Log::error("Failed to create new category '{$suggestedCategoryName}': " . $e->getMessage());
						// Decide fallback: proceed without category or return error?
						// Let's proceed without category for now, but log error.
						$finalCategoryId = null;
					}
				}
			} elseif (is_numeric($categoryInput)) {
				// Use the explicit ID provided, check if it exists
				$category = Category::find($categoryInput);
				if ($category) {
					$finalCategoryId = $category->id;
					Log::info("Using explicitly selected category ID: {$finalCategoryId}");
				} else {
					Log::warning("Explicitly selected category ID {$categoryInput} not found. Saving lesson without category.");
					$finalCategoryId = null;
				}
			} else {
				Log::warning("Invalid category input '{$categoryInput}'. Saving lesson without category.");
				$finalCategoryId = null;
			}

			// --- Create Lesson Record ---
			$lesson = Lesson::create([
				'name' => $userLesson,
				'title' => $plan['main_title'],
				'image_prompt_idea' => $plan['image_prompt_idea'],
				'lesson_parts' => $plan['lesson_parts'],
				'session_id' => $sessionId,
				'preferredLlm' => $preferredLlm,
				'ttsEngine' => $ttsEngine,
				'ttsVoice' => $ttsVoice,
				'ttsLanguageCode' => $ttsLanguageCode,
				'language' => $language, // Save selected language
				'category_id' => $finalCategoryId, // Save determined category ID
			]);

			Log::info("Lesson record created with ID: {$lesson->id}, SessionID: {$sessionId}, CategoryID: {$finalCategoryId}. No questions created at this stage.");

			return response()->json([
				'success' => true,
				'message' => 'Lesson created! Edit questions and generate assets.',
				'redirectUrl' => route('lesson.edit', ['lesson' => $sessionId])
			]);
		}

		public function archiveProgress(Lesson $lesson)
		{
			Log::info("Archive request received for Lesson Session: {$lesson->session_id} (ID: {$lesson->id})");

			$userAnswers = UserAnswer::where('lesson_id', $lesson->id)->get();

			if ($userAnswers->isEmpty()) {
				Log::info("No user answers found to archive for Lesson ID: {$lesson->id}.");
				return response()->json(['success' => true, 'message' => 'No progress found to archive.'], 200);
			}

			DB::beginTransaction();
			try {
				$archiveTimestamp = Carbon::now();
				$archiveBatchId = Str::uuid()->toString();

				$archiveData = [];
				foreach ($userAnswers as $answer) {
					$archiveData[] = [
						'original_user_answer_id' => $answer->id,
						'question_id' => $answer->question_id,
						'lesson_id' => $answer->lesson_id,
						'selected_answer_index' => $answer->selected_answer_index,
						'was_correct' => $answer->was_correct,
						'attempt_number' => $answer->attempt_number,
						'archived_at' => $archiveTimestamp,
						'archive_batch_id' => $archiveBatchId, // If using batch ID
						'created_at' => $answer->created_at, // Preserve original timestamps
						'updated_at' => $answer->updated_at, // Preserve original timestamps
					];
				}

				// Bulk insert for efficiency
				UserAnswerArchive::insert($archiveData);
				Log::info("Successfully inserted {$userAnswers->count()} records into user_answer_archives for Lesson ID: {$lesson->id}.");

				// Delete original answers
				$deletedCount = UserAnswer::where('lesson_id', $lesson->id)->delete();
				Log::info("Successfully deleted {$deletedCount} original user answers for Lesson ID: {$lesson->id}.");

				DB::commit();
				Log::info("Archiving completed for Lesson ID: {$lesson->id}.");

				return response()->json(['success' => true, 'message' => 'Progress archived successfully.'], 200);

			} catch (\Exception $e) {
				DB::rollBack();
				Log::error("Error archiving progress for Lesson ID: {$lesson->id} - " . $e->getMessage());
				return response()->json(['success' => false, 'message' => 'Failed to archive progress. Please try again.'], 500);
			}
		}

	} // End of CreateLessonController
