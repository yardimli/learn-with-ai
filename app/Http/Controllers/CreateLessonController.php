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
	use Illuminate\Support\Facades\Auth;
	use Illuminate\Support\Facades\DB;
	use Illuminate\Support\Facades\Log;
	use Illuminate\Support\Facades\Validator;
	use Illuminate\Support\Str;

	use Illuminate\Support\Facades\Http;
	use Illuminate\Validation\Rule;
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
			$mainCategories = Auth::user()->mainCategories()
				->with(['subCategories' => function ($query) {
					$query->where('user_id', Auth::id())->orderBy('name'); // Ensure subcategories are also user's
				}])
				->orderBy('name')->get();


			$lessons = Auth::user()->lessons()->withCount('questions')->orderBy('created_at', 'desc')->get();
			$llms = LlmHelper::checkLLMsJson();

			return view('create_lesson', compact('lessons', 'mainCategories', 'llms'));
		}

		public function createBasicLesson(Request $request)
		{
			$userId = Auth::id();

			$validator = Validator::make($request->all(), [
				'user_title' => 'required|string|max:255',
				'lesson_subject' => 'required|string|max:2048',
				'notes' => 'nullable|string|max:5000',
				'preferred_llm' => 'required|string|max:100',
				'tts_engine' => 'required|string|in:google,openai',
				'tts_voice' => 'required|string|max:100',
				'tts_language_code' => 'required|string|max:10',
				'language' => 'required|string|max:10',
				'category_selection_mode' => 'required|string|in:ai_decide,main_only,both',
				'main_category_id' => [
					'nullable',
					Rule::exists('main_categories', 'id')->where('user_id', $userId)
				],
				'sub_category_id' => [
					'nullable',
					Rule::exists('sub_categories', 'id')->where('user_id', $userId)
				],
			]);

			if ($validator->fails()) {
				Log::error('Invalid data received for basic lesson creation.', [
					'errors' => $validator->errors()->toArray(),
					'data' => $request->all()
				]);
				return response()->json([
					'success' => false,
					'message' => 'Invalid data received for lesson creation. ' . $validator->errors()->first()
				], 422);
			}

			$userTitle = $request->input('user_title');
			$lessonSubject = $request->input('lesson_subject');
			$notes = $request->input('notes');
			$preferredLlm = $request->input('preferred_llm');
			$ttsEngine = $request->input('tts_engine');
			$ttsVoice = $request->input('tts_voice');
			$ttsLanguageCode = $request->input('tts_language_code');
			$language = $request->input('language');
			$categorySelectionMode = $request->input('category_selection_mode');
			$mainCategoryId = $request->input('main_category_id');
			$subCategoryId = $request->input('sub_category_id');
			$sessionId = Str::uuid()->toString();

			Log::info("Creating basic lesson. Session ID: {$sessionId}, Lesson: '{$lessonSubject}', Lang: {$language}, CategoryMode: {$categorySelectionMode}");

			// Determine category mode flags for AI generation later
			$aiDetectMain = ($categorySelectionMode === 'ai_decide');
			$aiDetectSub = ($categorySelectionMode === 'ai_decide' || $categorySelectionMode === 'main_only');

			// For 'both' mode, verify that sub belongs to main
			if ($categorySelectionMode === 'both' && $mainCategoryId && $subCategoryId) {
				$subCategory = SubCategory::find($subCategoryId);
				if (!$subCategory || $subCategory->main_category_id != $mainCategoryId) {
					return response()->json([
						'success' => false,
						'message' => 'The selected sub-category does not belong to the selected main category.'
					], 422);
				}

				// Use the selected sub-category ID directly
				$finalSubCategoryId = $subCategoryId;
			} else {
				// For AI-decided categories or main-only, we'll defer the category assignment
				$finalSubCategoryId = null;
			}

			// Determine main category name for main-only mode (needed for AI generation)
			$selectedMainCategoryName = null;
			if ($categorySelectionMode === 'main_only' && $mainCategoryId) {
				$mainCategory = MainCategory::find($mainCategoryId);
				$selectedMainCategoryName = $mainCategory ? $mainCategory->name : null;
			}

			// --- Create Basic Lesson Record (without lesson_parts) ---
			$lesson = Lesson::create([
				'user_id' => $userId,
				'user_title' => $userTitle,
				'subject' => $lessonSubject,
				'notes' => $notes,
				'title' => null, // Will be populated by AI later
				'image_prompt_idea' => null, // Will be populated by AI later
				'lesson_parts' => '[]', // Will be populated by AI later
				'session_id' => $sessionId,
				'preferredLlm' => $preferredLlm,
				'ttsEngine' => $ttsEngine,
				'ttsVoice' => $ttsVoice,
				'ttsLanguageCode' => $ttsLanguageCode,
				'language' => $language,
				'sub_category_id' => $finalSubCategoryId,
				'ai_generated' => false, // Add a flag to track AI generation status
				'category_selection_mode' => $categorySelectionMode, // Store the selection mode
				'selected_main_category_id' => ($categorySelectionMode !== 'ai_decide') ? $mainCategoryId : null,
			]);


			Log::info("Basic lesson record created with ID: {$lesson->id}, SessionID: {$sessionId}, SubCategoryID: {$finalSubCategoryId}, Mode: {$categorySelectionMode}");

			return response()->json([
				'success' => true,
				'message' => 'Basic lesson created! Generate content with AI from the lessons list.',
				'redirectUrl' => route('lessons.list')
			]);
		}

		// --- Prompt for generating Lesson Structure ONLY ---
		private const SYSTEM_PROMPT_LESSON_STRUCTURE = <<<PROMPT
You are an AI assistant specialized in creating the structure for educational micro-lessons.
The user will provide a title and subject and potentially a list of existing MAIN category_management.
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

		public static function generateLessonStructure(
			string  $llm,
			string  $lessonSubject,
			bool    $autoDetectMain = true,
			        $lessonLanguage = 'English',
			int     $maxRetries = 1,
			string  $userTitle = '',
			string  $notes = '',
			?string $selectedMainCategoryName = null,
			bool    $autoDetectSub = true
		): array
		{
			$systemPrompt = self::SYSTEM_PROMPT_LESSON_STRUCTURE;

			if (!$autoDetectMain && $selectedMainCategoryName) {
				// Adjust system prompt for main_only mode
				$systemPrompt .= "\n\nIMPORTANT: The user has already selected the main category as '{$selectedMainCategoryName}'. You MUST use this exact main category name in the 'suggested_main_category' field. Only suggest an appropriate sub-category that fits within this main category. If the available sub categories are not suitable, suggest a new one.";
			}

			$userMessageContent = "Title: " . $userTitle . "\n";
			$userMessageContent .= "Subject: " . $lessonSubject . "\n";
			$userMessageContent .= "Language: " . $lessonLanguage . "\n";

			if (!empty($notes)) {
				$userMessageContent .= "Additional Notes: " . $notes . "\n";
			}

			if (!$autoDetectMain && $selectedMainCategoryName) {
				$userMessageContent .= "\nSelected Main Category: " . $selectedMainCategoryName . "\n";
			}

			if ($autoDetectMain || $autoDetectSub) {
				// Provide existing categories for context if we're auto-detecting either category level
				$mainCategories = Auth::user()->mainCategories()
					->with(['subCategories' => function ($query) {
						$query->where('user_id', Auth::id())->orderBy('name');
					}])
					->orderBy('name')->get();

				$categoriesString = '';
				foreach ($mainCategories as $mainCategory) {
					// If auto-detecting both main and sub, or we're in main_only mode and this is the selected main
					if ($autoDetectMain || (!$autoDetectMain && $mainCategory->name === $selectedMainCategoryName)) {
						foreach ($mainCategory->subCategories as $subCategory) {
							$categoriesString .= $mainCategory->name . ' - ' . $subCategory->name . ', ';
						}
					}
				}

				if ($categoriesString !== '') {
					$categoriesString = rtrim($categoriesString, ', '); // Remove trailing comma
					$userMessageContent .= "\n\nExisting Main and Sub Categories: " . $categoriesString;
					Log::info("Providing existing categories for auto-detection: " . $categoriesString);
				} else {
					Log::info("No existing categories found to provide for auto-detection.");
				}
			}

			$chatHistoryLessonStructGen = [['role' => 'user', 'content' => $userMessageContent]];

			Log::info("Requesting lesson structure generation for lesson: '{$lessonSubject}' using LLM: {$llm}. " .
				"Auto-detect main: " . ($autoDetectMain ? 'Yes' : 'No') .
				", Auto-detect sub: " . ($autoDetectSub ? 'Yes' : 'No') .
				($selectedMainCategoryName ? ", Selected main: {$selectedMainCategoryName}" : ""));

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

		public function generatePlanPreview(Request $request, Lesson $lesson)
		{
			$validator = Validator::make($request->all(), [
				'llm' => 'required|string|max:100',
				'user_title' => 'required|string|max:255',
				'subject' => 'required|string|max:1024',
				'notes' => 'nullable|string|max:5000',
				'auto_detect_category' => 'required|boolean',
			]);

			if ($validator->fails()) {
				return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
			}

			$llm = $request->input('llm');
			$userSubject = $request->input('subject');
			$userTitle = $request->input('user_title');
			$notes = $request->input('notes', '');

			$lesson->preferredLlm = $llm; // Update the preferred LLM in the lesson record
			$lesson->user_title = $userTitle; // Update the user title
			$lesson->subject = $userSubject; // Update the lesson name
			$lesson->notes = $notes; // Update the notes
			$lesson->save(); // Save the lesson record

			$categorySelectionMode = $lesson->category_selection_mode ?? 'ai_decide';
			$autoDetectMain = ($categorySelectionMode === 'ai_decide');
			$autoDetectSub = ($categorySelectionMode === 'ai_decide' || $categorySelectionMode === 'main_only');

			// If main category is pre-selected but sub is AI-decided, provide that context
			$selectedMainCategoryName = null;
			if ($categorySelectionMode === 'main_only' && $lesson->selected_main_category_id) {
				$mainCategory = MainCategory::find($lesson->selected_main_category_id);
				$selectedMainCategoryName = $mainCategory ? $mainCategory->name : null;
			}

			$language = $lesson->language ?? 'English';
			$maxRetries = 1;

			Log::info("Generating lesson structure... Mode: {$categorySelectionMode}");

			$planStructureResult = self::generateLessonStructure(
				$llm,
				$userSubject,
				$autoDetectMain,  // Whether to auto-detect main category
				$language,
				$maxRetries,
				$userTitle,
				$notes,
				$selectedMainCategoryName, // Pass the selected main category if in 'main_only' mode
				$autoDetectSub  // Whether to auto-detect sub-category
			);

			if (isset($planStructureResult['error'])) {
				$errorMsg = $planStructureResult['error'];
				Log::error("LLM Structure Gen Error: " . $errorMsg, ['lesson' => $userSubject, 'llm' => $llm]);
				return response()->json(['success' => false, 'message' => 'Failed to generate lesson structure: ' . $errorMsg]);
			}

			if (!self::isValidLessonStructureResponse($planStructureResult)) {
				$errorMsg = 'LLM returned an invalid lesson structure (check includes category).';
				Log::error($errorMsg, ['lesson' => $userSubject, 'llm' => $llm, 'response' => $planStructureResult]);
				return response()->json(['success' => false, 'message' => $errorMsg . ' Please try refining your lesson or using a different model.']);
			}

			Log::info("Lesson structure generated successfully for preview. Suggested Main: " .
				($planStructureResult['suggested_main_category'] ?? 'N/A') .
				", Sub: " . ($planStructureResult['suggested_sub_category'] ?? 'N/A'));


			// Prepare response data
			$responseData = [
				'success' => true,
				'plan' => $planStructureResult,
				'lesson_id' => $lesson->id,
				'session_id' => $lesson->session_id,
				'language_selected' => $language,
				'current_sub_category_id' => $lesson->sub_category_id,
				'category_selection_mode' => $categorySelectionMode,
				'selected_main_category_id' => $lesson->selected_main_category_id,
				'suggested_main_category' => $planStructureResult['suggested_main_category'] ?? null,
				'suggested_sub_category' => $planStructureResult['suggested_sub_category'] ?? null
			];

			return response()->json($responseData);
		}

		public function createLesson(Request $request)
		{
			// Validation rules updated
			$validator = Validator::make($request->all(), [
				'lesson_subject' => 'required|string|max:512',
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

			$lessonSubject = $request->input('lesson_subject');
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
			Log::info("Confirmed creation request received. Session ID: {$sessionId}, Lesson: '{$lessonSubject}', Lang: {$language}, CatInput: {$categoryInput}");

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
				'user_id' => Auth::id(),
				'subject' => $lessonSubject,
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

		public function applyGeneratedPlan(Request $request, Lesson $lesson)
		{
			$this->authorize('update', $lesson); // Ensure user owns the lesson
			$userId = Auth::id();

			$validator = Validator::make($request->all(), [
				'plan' => 'required|array',
				'plan.main_title' => 'required|string',
				'plan.image_prompt_idea' => 'required|string',
				'plan.lesson_parts' => 'required|array|size:3',
				'plan.lesson_parts.*.title' => 'required|string',
				'plan.lesson_parts.*.text' => 'required|string',
				'plan.lesson_parts.*.image_prompt_idea' => 'required|string',
			]);

			if ($validator->fails()) {
				Log::error('Invalid data received for applying plan to lesson.', [
					'errors' => $validator->errors()->toArray(),
					'data' => $request->all()
				]);
				return response()->json([
					'success' => false,
					'message' => 'Invalid data: ' . $validator->errors()->first()
				], 422);
			}

			$plan = $request->input('plan');

			// Get the suggested categories from the plan
			$suggestedMainCategoryName = $plan['suggested_main_category'] ?? null;
			$suggestedSubCategoryName = $plan['suggested_sub_category'] ?? null;

			// Determine category handling based on lesson's saved selection mode
			$categorySelectionMode = $lesson->category_selection_mode ?? 'ai_decide';
			$selectedMainCategoryId = $lesson->selected_main_category_id;

			// The final sub-category ID will be determined based on the selection mode
			$finalSubCategoryId = $lesson->sub_category_id; // Default to current

			// Handle category logic based on selection mode
			if ($categorySelectionMode === 'ai_decide' && !empty($suggestedMainCategoryName) && !empty($suggestedSubCategoryName)) {
				// AI deciding both categories
				DB::beginTransaction();
				try {
					$mainCategory = MainCategory::firstOrCreate(
						[
							'user_id' => $userId, // Add user_id to condition
							'name' => DB::raw("LOWER('{$suggestedMainCategoryName}')") // Case-insensitive check
						],
						[
							'name' => $suggestedMainCategoryName,
							'user_id' => $userId // Add user_id on creation
						]
					);

					if (!$mainCategory) {
						throw new Exception("Could not find or create main category.");
					}

					$subCategory = SubCategory::firstOrCreate(
						[
							'main_category_id' => $mainCategory->id,
							'user_id' => $userId, // Add user_id to condition
							'name' => DB::raw("LOWER('{$suggestedSubCategoryName}')") // Case-insensitive check
						],
						[
							'name' => $suggestedSubCategoryName,
							'main_category_id' => $mainCategory->id,
							'user_id' => $userId // Add user_id on creation
						]
					);

					if (!$subCategory) {
						throw new Exception("Could not find or create sub category.");
					}

					$finalSubCategoryId = $subCategory->id;
					Log::info("AI-decided categories: Main='{$mainCategory->name}' (ID:{$mainCategory->id}), Sub='{$subCategory->name}' (ID:{$finalSubCategoryId})");

					DB::commit();
				} catch (\Exception $e) {
					DB::rollBack();
					Log::error("Failed to find/create AI-decided categories: " . $e->getMessage());
					return response()->json([
						'success' => false,
						'message' => 'Failed to create or assign AI-suggested categories.'
					], 500);
				}
			}
			else if ($categorySelectionMode === 'main_only' && $selectedMainCategoryId && !empty($suggestedSubCategoryName)) {
				// User selected main, AI suggesting sub
				DB::beginTransaction();
				try {
					// Find the user's main category
					$mainCategory = MainCategory::where('id', $selectedMainCategoryId)
						->where('user_id', $userId)
						->first();
					if (!$mainCategory) { throw new Exception("Selected main category not found for user."); }

					// Find or create Sub Category for this user under the main category
					$subCategory = SubCategory::firstOrCreate(
						[
							'main_category_id' => $mainCategory->id,
							'user_id' => $userId, // Add user_id to condition
							'name' => DB::raw("LOWER('{$suggestedSubCategoryName}')")
						],
						[
							'name' => $suggestedSubCategoryName,
							'main_category_id' => $mainCategory->id,
							'user_id' => $userId // Add user_id on creation
						]
					);

					if (!$subCategory) {
						throw new Exception("Could not find or create sub category.");
					}

					$finalSubCategoryId = $subCategory->id;
					Log::info("User selected main '{$mainCategory->name}', AI suggested sub '{$subCategory->name}' (ID:{$finalSubCategoryId})");

					DB::commit();
				} catch (\Exception $e) {
					DB::rollBack();
					Log::error("Failed to find/create AI-suggested sub-category: " . $e->getMessage());
					return response()->json([
						'success' => false,
						'message' => 'Failed to create or assign AI-suggested sub-category.'
					], 500);
				}
			}
			// For 'both' mode, the sub-category was already selected and saved in the lesson record

			// Update the lesson with the generated plan
			try {
				$lesson->update([
					'title' => $plan['main_title'],
					'image_prompt_idea' => $plan['image_prompt_idea'],
					'lesson_parts' => $plan['lesson_parts'],
					'sub_category_id' => $finalSubCategoryId,
					'ai_generated' => true, // Mark as AI-generated
				]);

				Log::info("Successfully applied AI-generated plan to lesson ID: {$lesson->id}");

				return response()->json([
					'success' => true,
					'message' => 'Lesson content generated successfully!',
					'redirectUrl' => route('lesson.edit', ['lesson' => $lesson->session_id])
				]);
			} catch (\Exception $e) {
				Log::error("Error updating lesson with generated plan: " . $e->getMessage());
				return response()->json([
					'success' => false,
					'message' => 'Failed to update lesson with generated content: ' . $e->getMessage()
				], 500);
			}
		}

		public function showImportForm()
		{
			$mainCategories = Auth::user()->mainCategories()->orderBy('name')->get();
			$llms = LlmHelper::checkLLMsJson();
			return view('lesson_import', compact('mainCategories', 'llms'));
		}

		public function processImport(Request $request)
		{
			$userId = Auth::id();
			$validator = Validator::make($request->all(), [
				'main_category_id' => ['required', Rule::exists('main_categories', 'id')->where('user_id', $userId)],
				'preferred_llm' => 'required|string|max:100',
				'tts_engine' => 'required|string|in:google,openai',
				'tts_voice' => 'required|string|max:100',
				'tts_language_code' => 'required|string|max:10',
				'language' => 'required|string|max:10',
				'lessons_json' => 'required|string',
			]);

			if ($validator->fails()) {
				return redirect()->route('lesson.import.form')
					->withErrors($validator)
					->withInput();
			}

			$mainCategoryId = $request->input('main_category_id');
			$preferredLlm = $request->input('preferred_llm');
			$ttsEngine = $request->input('tts_engine');
			$ttsVoice = $request->input('tts_voice');
			$ttsLanguageCode = $request->input('tts_language_code');
			$language = $request->input('language');
			$jsonInput = $request->input('lessons_json');

			try {
				$lessonsData = json_decode($jsonInput, true, 512, JSON_THROW_ON_ERROR);
			} catch (JsonException $e) {
				Log::error('JSON Import Error: Invalid JSON provided.', ['error' => $e->getMessage()]);
				return redirect()->route('lesson.import.form')
					->with('error', 'Invalid JSON format: ' . $e->getMessage())
					->withInput();
			}

			if (!is_array($lessonsData)) {
				Log::error('JSON Import Error: JSON is not an array.');
				return redirect()->route('lesson.import.form')
					->with('error', 'Invalid JSON structure: The top level must be an array.')
					->withInput();
			}

			$importedCount = 0;
			$skippedCount = 0;
			$errors = [];

			DB::beginTransaction();
			try {
				foreach ($lessonsData as $index => $lessonData) {
					// Validate individual lesson structure
					if (!isset($lessonData['title']) || !is_string($lessonData['title']) || empty(trim($lessonData['title']))) {
						$errors[] = "Lesson at index {$index}: Missing or invalid 'title'.";
						$skippedCount++;
						continue;
					}
					if (!isset($lessonData['description']) || !is_string($lessonData['description']) || empty(trim($lessonData['description']))) {
						$errors[] = "Lesson at index {$index}: Missing or invalid 'description'.";
						$skippedCount++;
						continue;
					}
					// 'notes' is optional, but if present, should be a string
					if (isset($lessonData['notes']) && !is_string($lessonData['notes'])) {
						$errors[] = "Lesson at index {$index}: Invalid 'notes' format (must be a string).";
						$skippedCount++;
						continue;
					}
					// 'year' is optional, but if present, should be a integer
					if (isset($lessonData['year']) && !is_int($lessonData['year'])) {
						$errors[] = "Lesson at index {$index}: Invalid 'year' format (must be an integer).";
						$skippedCount++;
						continue;
					}

					// 'month' is optional, but if present, should be a integer
					if (isset($lessonData['month']) && !is_int($lessonData['month'])) {
						$errors[] = "Lesson at index {$index}: Invalid 'month' format (must be an integer).";
						$skippedCount++;
						continue;
					}

					// 'week' is optional, but if present, should be a integer
					if (isset($lessonData['week']) && !is_int($lessonData['week'])) {
						$errors[] = "Lesson at index {$index}: Invalid 'week' format (must be an integer).";
						$skippedCount++;
						continue;
					}


					$sessionId = Str::uuid()->toString();
					$notes = $lessonData['notes'] ?? null; // Store notes in notes if present
					$year = $lessonData['year'] ?? null; // Store year in year if present
					$month = $lessonData['month'] ?? null; // Store month in month if present
					$week = $lessonData['week'] ?? null; // Store week in week if present

					Lesson::create([
						'user_id' => $userId,
						'user_title' => trim($lessonData['title']),
						'subject' => trim($lessonData['description']),
						'notes' => $notes,
						'year' => $year,
						'month' => $month,
						'week' => $week,
						'title' => null, // Will be populated by AI later
						'image_prompt_idea' => null, // Will be populated by AI later
						'lesson_parts' => '[]', // Will be populated by AI later
						'session_id' => $sessionId,
						'preferredLlm' => $preferredLlm,
						'ttsEngine' => $ttsEngine,
						'ttsVoice' => $ttsVoice,
						'ttsLanguageCode' => $ttsLanguageCode,
						'language' => $language,
						'sub_category_id' => null, // Sub-category will be suggested by AI
						'ai_generated' => false, // Not generated yet
						'category_selection_mode' => 'main_only', // User selected main, AI suggests sub
						'selected_main_category_id' => $mainCategoryId,
					]);
					$importedCount++;
					Log::info("JSON Import: Created lesson '{$lessonData['title']}' with SessionID: {$sessionId}");
				}

				DB::commit();

				$message = "Successfully imported {$importedCount} lessons.";
				if ($skippedCount > 0) {
					$message .= " Skipped {$skippedCount} lessons due to errors.";
					Log::warning('JSON Import: Some lessons were skipped.', ['errors' => $errors]);
					// Optionally add detailed errors to the flash message if needed, but keep it concise
					// session()->flash('import_errors', $errors);
				}

				return redirect()->route('lessons.list')->with('success', $message);

			} catch (\Exception $e) {
				DB::rollBack();
				Log::error('JSON Import Error: Failed during database insertion.', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
				return redirect()->route('lesson.import.form')
					->with('error', 'An error occurred during import: ' . $e->getMessage())
					->withInput();
			}
		}


	}
