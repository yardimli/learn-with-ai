<?php

	namespace App\Http\Controllers;

	use App\Helpers\LlmHelper;
	use App\Helpers\AudioImageHelper;

	use App\Models\Lesson;
	use App\Models\MainCategory;
	use App\Models\SubCategory;
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
			$llms = LlmHelper::checkLLMsJson();

			$mainCategories = MainCategory::with(['subCategories' => function ($query) {
				$query->orderBy('name');
			}])->orderBy('name')->get();


			// Eager load question count for potential display (optional)
			$lessons = Lesson::withCount('questions')->orderBy('created_at', 'desc')->get();
			return view('create_lesson', compact('llms', 'lessons', 'mainCategories'));
		}

		// --- Prompt for generating Lesson Structure ONLY ---
		private const SYSTEM_PROMPT_LESSON_STRUCTURE = <<<PROMPT
You are an AI assistant specialized in creating the structure for educational micro-lessons.
The user will provide a subject and potentially a list of existing MAIN category_management.
Create in the specified language, if no language is provided, use English.
You MUST generate the basic lesson plan structure as a single JSON object.

The JSON object MUST have the following structure:
{
    "main_title": "A concise and engaging main title for the entire lesson (max 15 words).",
    "image_prompt_idea": "A short phrase or idea (max 15 words) for a single, representative image for the whole lesson.",
    "lesson_parts": [
        {
            "title": "Title for Lesson Part 1 (e.g., 'Introduction to X')",
            "image_prompt_idea": "A short phrase or idea (max 15 words) for a single, representative image for this part.",
            "text": "Content for Lesson Part 1 (3-6 sentences, approx 50-120 words)."
        },
        {
            "title": "Title for Lesson Part 2 (e.g., 'How X Works')",
            "image_prompt_idea": "A short phrase or idea (max 15 words) for a single, representative image for this part.",
            "text": "Content for Lesson Part 2 (3-6 sentences, approx 50-120 words)."
        },
        {
            "title": "Title for Lesson Part 3 (e.g., 'Importance/Applications of X')",
            "image_prompt_idea": "A short phrase or idea (max 15 words) for a single, representative image for this part.",
            "text": "Content for Lesson Part 3 (3-6 sentences, approx 50-120 words)."
        }
    ],
    "suggested_main_category": "Based on the content, suggest a concise MAIN category (e.g., 'Science', 'History', max 5 words). If existing main category_management are provided, try to match one.",
    "suggested_sub_category": "Suggest a concise SUB-category name (e.g., 'Photosynthesis', 'World War II', max 5 words) that fits within the suggested main category."
}

Constraints:
- The output MUST be ONLY the valid JSON object described above. No introductory text, explanations, or markdown formatting outside the JSON structure.
- Ensure exactly 3 `lesson_parts`.
- All text content (titles, lesson text) should be clear, concise, and factually accurate.
- Generate content suitable for a general audience.
- The `suggested_main_category` and `suggested_sub_category` fields MUST always be included.
- Try to reuse an existing `suggested_main_category` and `suggested_sub_category` if appropriate, otherwise suggest a new one.
PROMPT;

		public static function generateLessonStructure(string $llm, string $userLesson, bool $autoDetectCategory = false, $lessonLanguage = 'English', int $maxRetries = 1): array
		{
			$systemPrompt = self::SYSTEM_PROMPT_LESSON_STRUCTURE;
			$userMessageContent = $userLesson;
			$userMessageContent .= "\nThe language is: " . $lessonLanguage;

			if ($autoDetectCategory) {
				// Provide existing MAIN category_management for context
				$mainCategories = MainCategory::with(['subCategories' => function ($query) {
					$query->orderBy('name');
				}])->orderBy('name')->get();
				$categoriesString = '';
				foreach ($mainCategories as $mainCategory) {
					foreach ($mainCategory->subCategories as $subCategory) {
						$categoriesString .= $mainCategory->name . ' - ' . $subCategory->name . ', ';
					}
				}
				if ($categoriesString !== '') {
					$categoriesString = rtrim($categoriesString, ', '); // Remove trailing comma
					$userMessageContent .= "\n\nnExisting Main and Sub Categories: " . $categoriesString;
					Log::info("Providing existing category_management for auto-detection: " . $categoriesString);
				} else {
					Log::info("No existing category_management found to provide for auto-detection.");
				}

//				$existingMainCategories = MainCategory::pluck('name')->toArray();
//				if (!empty($existingMainCategories)) {
//					$userMessageContent .= "\n\nExisting Main Categories: " . implode(', ', $existingMainCategories);
//					Log::info("Providing existing main category_management for auto-detection: " . implode(', ', $existingMainCategories));
//				} else {
//					Log::info("No existing main category_management found to provide for auto-detection.");
//				}
				// Note: The instruction to suggest category_management is in the system prompt.
			}

			$chatHistoryLessonStructGen = [['role' => 'user', 'content' => $userMessageContent]];
			Log::info("Requesting lesson structure generation for lesson: '{$userLesson}' using LLM: {$llm}. Auto-detect category: " . ($autoDetectCategory ? 'Yes' : 'No'));

			return LlmHelper::llm_no_tool_call($llm, $systemPrompt, $chatHistoryLessonStructGen, true, $maxRetries);
		}

		public static function isValidLessonStructureResponse(?array $planData): bool
		{
			if (empty($planData) || !is_array($planData)) return false;
			if (!isset($planData['main_title']) || !is_string($planData['main_title'])) return false;
			if (!isset($planData['image_prompt_idea']) || !is_string($planData['image_prompt_idea'])) return false;
			if (!isset($planData['lesson_parts']) || !is_array($planData['lesson_parts']) || count($planData['lesson_parts']) !== 3) return false;

			// Add check for the suggested category name
			if (!isset($planData['suggested_main_category']) || !is_string($planData['suggested_main_category'])) return false;
			if (!isset($planData['suggested_sub_category']) || !is_string($planData['suggested_sub_category'])) return false;

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
				'sub_category_id' => ['required', 'string'], // Can be 'auto' or an ID
				'language' => 'required|string|max:30', // Validate language early
			]);

			if ($validator->fails()) {
				return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
			}

			$userLesson = $request->input('lesson');
			$llm = $request->input('llm');
			$subCategoryIdInput  = $request->input('sub_category_id');
			$language = $request->input('language'); // Get language
			$maxRetries = 1;

			// Check if selected sub-category ID is valid (if not 'auto')
			if ($subCategoryIdInput !== 'auto' && !SubCategory::where('id', $subCategoryIdInput)->exists()) {
				return response()->json(['success' => false, 'message' => 'Invalid sub-category selected.'], 422);
			}

			$autoDetectCategory = ($subCategoryIdInput === 'auto');
			Log::info("AJAX request received for plan preview. Lesson: '{$userLesson}', LLM: {$llm}, SubCategory Input: {$subCategoryIdInput}, Language: {$language}"); // Updated log

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

			Log::info("Lesson structure generated successfully for preview. Suggested Main: " . ($planStructureResult['suggested_main_category'] ?? 'N/A') . ", Sub: " . ($planStructureResult['suggested_sub_category'] ?? 'N/A')); // Updated log

			// Prepare response data
			$responseData = [
				'success' => true,
				'plan' => $planStructureResult,
				'language_selected' => $language, // Pass language back
				'category_input' => $subCategoryIdInput, // Pass original sub-category input back
				'suggested_main_category' => $planStructureResult['suggested_main_category'] ?? null, // Include suggested main name
				'suggested_sub_category' => $planStructureResult['suggested_sub_category'] ?? null // Include suggested sub name
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
				'suggested_main_category' => ['nullable', 'string', 'max:255'], // Required if category_input is 'auto'
				'suggested_sub_category' => ['nullable', 'string', 'max:255'], // Required if category_input is 'auto'
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
			$categoryInput = $request->input('category_input'); // This is sub_category_id or 'auto'
			$suggestedMainCategoryName = $request->input('suggested_main_category');
			$suggestedSubCategoryName = $request->input('suggested_sub_category');
			$plan = $request->input('plan');

			// Re-validate the core plan structure (excluding suggested_category_name which is part of the LLM output validation)
			$corePlanData = $plan;

			// Use the names received from the request if available, else placeholders
			$mainCatNameToValidate = $suggestedMainCategoryName ?? 'placeholder_main';
			$subCatNameToValidate = $suggestedSubCategoryName ?? 'placeholder_sub';

			if (!self::isValidLessonStructureResponse(array_merge($corePlanData, [
				'suggested_main_category' => $mainCatNameToValidate,
				'suggested_sub_category' => $subCatNameToValidate
			]))) {
				Log::error('Invalid final plan structure received on createLesson endpoint.', ['plan' => $plan]);
				return response()->json(['success' => false, 'message' => 'Invalid lesson plan structure received.'], 400);
			}


			$sessionId = Str::uuid()->toString();
			Log::info("Confirmed creation request received. Session ID: {$sessionId}, Lesson: '{$userLesson}', Lang: {$language}, CatInput: {$categoryInput}");

			// --- Determine Final Sub-Category ID ---
			$finalSubCategoryId = null;
			if ($categoryInput === 'auto' && !empty($suggestedMainCategoryName) && !empty($suggestedSubCategoryName)) {
				Log::info("Handling auto-detected category: Main='{$suggestedMainCategoryName}', Sub='{$suggestedSubCategoryName}'");
				DB::beginTransaction(); // Use transaction for creating category_management
				try {
					// Find or create the Main Category (case-insensitive)
					$mainCategory = MainCategory::firstOrCreate(
						['name' => DB::raw("LOWER('{$suggestedMainCategoryName}')")], // Search condition
						['name' => $suggestedMainCategoryName]                     // Data to insert if not found
					);
					// If using case-sensitive collation, can simplify:
					// $mainCategory = MainCategory::firstOrCreate(
					//     ['name' => $suggestedMainCategoryName]
					// );


					if (!$mainCategory) {
						throw new Exception("Could not find or create main category.");
					}
					Log::info("Using Main Category ID: {$mainCategory->id} for '{$mainCategory->name}'");


					// Find or create the Sub Category under the Main Category (case-insensitive for name)
					$subCategory = SubCategory::firstOrCreate(
						[
							'main_category_id' => $mainCategory->id,
							'name' => DB::raw("LOWER('{$suggestedSubCategoryName}')") // Case-insensitive check depends on DB collation
							// For strict case-sensitive: 'name' => $suggestedSubCategoryName
						],
						[
							'name' => $suggestedSubCategoryName,
							'main_category_id' => $mainCategory->id // Ensure main_category_id is set on create
						]
					);

					// Simpler if using case-insensitive collation:
					// $subCategory = SubCategory::firstOrCreate(
					//    ['main_category_id' => $mainCategory->id, 'name' => $suggestedSubCategoryName]
					// );


					if (!$subCategory) {
						throw new Exception("Could not find or create sub category.");
					}
					$finalSubCategoryId = $subCategory->id;
					Log::info("Using Sub Category ID: {$finalSubCategoryId} for '{$subCategory->name}' under Main '{$mainCategory->name}'");

					DB::commit();
				} catch (\Exception $e) {
					DB::rollBack();
					Log::error("Failed to find/create category_management: Main='{$suggestedMainCategoryName}', Sub='{$suggestedSubCategoryName}'. Error: " . $e->getMessage());
					// Decide fallback: proceed without category or return error?
					// Let's return an error as category was requested via auto-detect.
					return response()->json(['success' => false, 'message' => 'Failed to automatically create or assign category_management. Please select manually or try again.'], 500);
				}

			} elseif (is_numeric($categoryInput)) {
				// Use the explicit Sub-Category ID provided, check if it exists
				$subCategory = SubCategory::find($categoryInput);
				if ($subCategory) {
					$finalSubCategoryId = $subCategory->id;
					Log::info("Using explicitly selected sub-category ID: {$finalSubCategoryId}");
				} else {
					Log::warning("Explicitly selected sub-category ID {$categoryInput} not found. Saving lesson without category.");
					$finalSubCategoryId = null; // Or return error?
				}
			} else {
				Log::warning("Invalid category input '{$categoryInput}'. Saving lesson without category.");
				$finalSubCategoryId = null;
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
				'sub_category_id' => $finalSubCategoryId,
			]);

			Log::info("Lesson record created with ID: {$lesson->id}, SessionID: {$sessionId}, SubCategoryID: {$finalSubCategoryId}.");

			return response()->json([
				'success' => true,
				'message' => 'Lesson created! Edit questions and generate assets.',
				'redirectUrl' => route('lesson.edit', ['lesson' => $sessionId])
			]);
		}

	}
