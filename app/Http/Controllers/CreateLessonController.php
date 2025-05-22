<?php namespace App\Http\Controllers;

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
use App\Http\Controllers\EditLessonController;

// For YouTube processing

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

	public static function extractYouTubeId(string $youtubeUrlOrId): ?string
	{
		$patterns = [
			'/(?:https?:\/\/)?(?:www\.)?youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/',
			'/(?:https?:\/\/)?(?:www\.)?youtu\.be\/([a-zA-Z0-9_-]{11})/',
			'/(?:https?:\/\/)?(?:www\.)?youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/',
			'/(?:https?:\/\/)?(?:www\.)?youtube\.com\/v\/([a-zA-Z0-9_-]{11})/'
		];
		foreach ($patterns as $pattern) {
			if (preg_match($pattern, $youtubeUrlOrId, $matches)) {
				return $matches[1];
			}
		}
		// If no match from URL patterns, assume the input *is* the ID (basic check)
		if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $youtubeUrlOrId)) {
			return $youtubeUrlOrId;
		}
		return null;
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
			'youtube_video_id' => 'nullable|string|max:100', // Added for YouTube video ID/URL
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
		$youtubeVideoIdInput = $request->input('youtube_video_id');

		Log::info("Creating basic lesson. Lesson: '{$lessonSubject}', Lang: {$language}, CategoryMode: {$categorySelectionMode}");

		$extractedYouTubeId = null;
		if ($youtubeVideoIdInput) {
			$extractedYouTubeId = self::extractYouTubeId($youtubeVideoIdInput);
			if (!$extractedYouTubeId) {
				Log::warning("Invalid YouTube URL/ID provided during lesson creation, ignoring: " . $youtubeVideoIdInput);
				// Optionally, you could return an error here if strict validation is needed:
				// return response()->json(['success' => false, 'message' => 'Invalid YouTube Video ID or URL provided.'], 422);
			}
		}

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

		// --- Create Basic Lesson Record (without lesson_content) ---
		$lesson = Lesson::create([
			'user_id' => $userId,
			'user_title' => $userTitle,
			'subject' => $lessonSubject,
			'notes' => $notes,
			'title' => null, // Will be populated by AI later
			'image_prompt_idea' => null, // Will be populated by AI later
			'lesson_content' => json_encode(['text' => null, 'sentences' => []]),
			'preferredLlm' => $preferredLlm,
			'ttsEngine' => $ttsEngine,
			'ttsVoice' => $ttsVoice,
			'ttsLanguageCode' => $ttsLanguageCode,
			'language' => $language,
			'sub_category_id' => $finalSubCategoryId,
			'ai_generated' => false,
			'category_selection_mode' => $categorySelectionMode,
			'selected_main_category_id' => ($categorySelectionMode !== 'ai_decide') ? $mainCategoryId : null,
			'youtube_video_id' => $extractedYouTubeId, // Store extracted YouTube ID
		]);

		Log::info("Basic lesson record created with ID: {$lesson->id}, SubCategoryID: {$finalSubCategoryId}, Mode: {$categorySelectionMode}, YouTubeID: {$extractedYouTubeId}");

		if ($extractedYouTubeId && $lesson) {
			// Process YouTube video details (this happens synchronously)
			$videoResult = EditLessonController::processAndStoreYouTubeVideo($lesson, $extractedYouTubeId);
			if (!$videoResult['success']) {
				Log::warning("Failed to process YouTube video {$extractedYouTubeId} details for new lesson ID {$lesson->id}: " . $videoResult['message']);
				// Lesson is created, but video details might be missing. User can add/retry from edit page.
			} else {
				Log::info("Successfully processed YouTube video {$extractedYouTubeId} for new lesson ID {$lesson->id}.");
			}
		}

		return response()->json([
			'success' => true,
			'message' => 'Basic lesson created! Generate content with AI from the lessons list.',
			'redirectUrl' => route('lessons.list')
		]);
	}


	public static function generateLessonStructure(
		string  $llm,
		string  $lessonSubject,
		bool    $autoDetectMain = true,
		        $lessonLanguage = 'English',
		int     $maxRetries = 1,
		string  $userTitle = '',
		string  $notes = '',
		?string $selectedMainCategoryName = null,
		bool    $autoDetectSub = true,
		?string $additionalInstructions = null,
		?string $videoTranscript = null
	): array
	{
		// --- System Prompt ---
		// MODIFIED: System prompt for a single lesson content object
		$systemPrompt = <<<PROMPT
You are an AI assistant specialized in creating the structure for educational lessons.
The user will provide input (either a subject/notes OR a video transcript) and potentially a list of existing MAIN categories.
Create the lesson structure in the specified language. The lesson structure should include all points addressed in the input.

You MUST generate the basic lesson plan structure as a single JSON object.
The JSON object MUST have the following structure:
{
    "title": "A concise and engaging title for the entire lesson (max 15 words). Use the user's provided title if relevant, otherwise create one based on the content.",
    "image_prompt_idea": "A short phrase or idea (max 15 words) for a single, representative image for the lesson.",
    "lesson_content": "Content for the lesson (e.g., based on the input provided). The content should be complete and self-contained so questions can be generated from it.",
    "suggested_main_category": "Based on the content, suggest a concise MAIN category (e.g., 'Science', 'History', max 5 words). If existing main categories are provided, try to match one.",
    "suggested_sub_category": "Suggest a concise SUB-category name (e.g., 'Photosynthesis', 'World War II', max 5 words) that fits within the suggested main category."
}

Constraints:
- The output MUST be ONLY the valid JSON object described above. No introductory text, explanations, or markdown formatting outside the JSON structure. Only use the key value pairs JSON structure provided, don't add any other key value pairs.
- All text content (titles, lesson text) should be clear, concise, and factually accurate, derived from the user's input (subject/notes or transcript).
- Generate content suitable for a general audience.
- The `suggested_main_category` and `suggested_sub_category` fields MUST always be included.
- If the user provided a video transcript, base the lesson content primarily on the transcript content.
- If the user provided subject/notes, base the lesson content on that information.
- Try to reuse an existing `suggested_main_category` and `suggested_sub_category` if appropriate based on provided category list, otherwise suggest a new one.
PROMPT;

		if (!$autoDetectMain && $selectedMainCategoryName) {
			$systemPrompt .= "\n\nIMPORTANT: The user has already selected the main category as '{$selectedMainCategoryName}'. You MUST use this exact main category name in the 'suggested_main_category' field. Only suggest a new sub-category that fits within this main category. If the provided sub categories don't fit.";
		}

		// --- User Message Content ---
		$userMessageContent = "Language: " . $lessonLanguage . "\n";
		if (!empty($userTitle)) {
			$userMessageContent .= "User's Preferred Title (use if relevant): " . $userTitle . "\n";
		}

		// Determine input source
		if (!empty($videoTranscript)) {
			Log::info("Generating lesson structure from video transcript.");
			$userMessageContent .= "\n--- Video Transcript ---\n";
			$userMessageContent .= trim($videoTranscript);
			$userMessageContent .= "\n--- End Transcript ---\n";
		} else {
			Log::info("Generating lesson structure from subject/notes.");
			$userMessageContent .= "Subject: " . $lessonSubject . "\n";
			if (!empty($notes)) {
				$userMessageContent .= "Additional Notes: " . $notes . "\n";
			}
		}


		if (!$autoDetectMain && $selectedMainCategoryName) {
			$userMessageContent .= "\nSelected Main Category: " . $selectedMainCategoryName . "\n";
		}

		if (!empty($additionalInstructions)) {
			$userMessageContent .= "\nUser's Additional Instructions:\n" . trim($additionalInstructions) . "\n";
			Log::info("Appending additional user instructions to LLM prompt.");
		}


		if ($autoDetectMain || $autoDetectSub) {
			// Provide existing categories for context if we're auto-detecting either category level
			$mainCategories = Auth::user()->mainCategories()
				->with('subCategories')
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
		$logSubject = $videoTranscript ? "Video Transcript based lesson" : ($lessonSubject ?? 'Unknown Subject');

		Log::info("Requesting lesson structure generation for lesson: '{$logSubject}' using LLM: {$llm}. " .
			"Source: " . ($videoTranscript ? 'Video' : 'Subject/Notes') . ", " .
			"Auto-detect main: " . ($autoDetectMain ? 'Yes' : 'No') .
			", Auto-detect sub: " . ($autoDetectSub ? 'Yes' : 'No') .
			($selectedMainCategoryName ? ", Selected main: {$selectedMainCategoryName}" : ""));

		return LlmHelper::llm_no_tool_call($llm, $systemPrompt, $chatHistoryLessonStructGen, true, $maxRetries);
	}

	// MODIFIED: Validation for single lesson content
	public static function isValidLessonStructureResponse(?array $planData): bool
	{
		if (empty($planData) || !is_array($planData)) return false;
		if (!isset($planData['title']) || !is_string($planData['title'])) return false;
		if (!isset($planData['image_prompt_idea']) || !is_string($planData['image_prompt_idea'])) return false;
		if (!isset($planData['lesson_content']) || !is_string($planData['lesson_content'])) return false;

		// Add check for the suggested category name
		if (!isset($planData['suggested_main_category']) || !is_string($planData['suggested_main_category'])) return false;
		if (!isset($planData['suggested_sub_category']) || !is_string($planData['suggested_sub_category'])) return false;

		return true; // All checks passed
	}

	public function generatePlanPreview(Request $request, Lesson $lesson)
	{
		$validator = Validator::make($request->all(), [
			'llm' => 'required|string|max:100',
			'user_title' => 'required|string|max:255',
			'subject' => 'required_if:generation_source,subject|nullable|string|max:1024',
			'notes' => 'nullable|string|max:5000',
			'auto_detect_category' => 'required|boolean',
			'additional_instructions' => 'nullable|string|max:5000',
			'generation_source' => 'required|string|in:subject,video',
			'video_subtitles' => 'required_if:generation_source,video|nullable|string',
		]);

		if ($validator->fails()) {
			return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
		}

		$llm = $request->input('llm');
		$userTitle = $request->input('user_title');
		$notes = $request->input('notes') ?? '';
		$additionalInstructions = $request->input('additional_instructions');
		$autoDetect = $request->input('auto_detect_category');
		$generationSource = $request->input('generation_source');
		$userSubject = $request->input('subject');
		$videoSubtitles = $request->input('video_subtitles');

		// If source is video, double-check the lesson model has subtitles just in case
		if ($generationSource === 'video' && empty($videoSubtitles) && !empty($lesson->video_subtitles_text)) {
			Log::warning("Subtitles provided in request were empty, but found in lesson model. Using model data.", ['lesson_id' => $lesson->id]);
			$videoSubtitles = $lesson->video_subtitles_text;
		} elseif ($generationSource === 'video' && empty($videoSubtitles)) {
			Log::error("Generation source is video, but no subtitles were provided or found.", ['lesson_id' => $lesson->id]);
			return response()->json(['success' => false, 'message' => 'Cannot generate from video: Subtitles are missing.'], 400);
		}


		try {
			$user = Auth::user();
			if ($user->llm_generation_instructions !== $additionalInstructions) {
				$user->update(['llm_generation_instructions' => $additionalInstructions]);
				Log::info("Updated user's LLM generation instructions.", ['user_id' => $user->id]);
			}
		} catch (\Exception $e) {
			Log::error("Failed to save user's LLM generation instructions.", ['user_id' => Auth::id(), 'error' => $e->getMessage()]);
		}

		$lesson->preferredLlm = $llm; // Update the preferred LLM in the lesson record
		$lesson->user_title = $userTitle; // Update the user title
		if ($generationSource === 'subject') {
			$lesson->subject = $userSubject;
			$lesson->notes = $notes ?? '';
		}
		$lesson->save(); // Save the lesson record

		$categorySelectionMode = $lesson->category_selection_mode ?? 'ai_decide';
		$autoDetectMain = $autoDetect;
		$autoDetectSub = $autoDetect;

		// If main category is pre-selected but sub is AI-decided, provide that context
		$selectedMainCategoryName = null;
		if ($categorySelectionMode === 'main_only') {
			$autoDetectMain = false; // Don't ask AI to detect main
			$autoDetectSub = true;  // Always ask AI to detect sub in this mode
			if ($lesson->selected_main_category_id) {
				$mainCategory = MainCategory::find($lesson->selected_main_category_id);
				$selectedMainCategoryName = $mainCategory ? $mainCategory->name : null;
			}
		} else if ($categorySelectionMode === 'both') {
			// If mode is 'both', neither should be auto-detected for the prompt context.
			$autoDetectMain = false;
			$autoDetectSub = false;
			// We don't need to pass category names here as the AI isn't suggesting them.
		}

		$language = $lesson->language ?? 'English';
		$maxRetries = 1;

		Log::info("Generating lesson structure preview... Mode: {$categorySelectionMode}, Source: {$generationSource}");

		$planStructureResult = self::generateLessonStructure(
			$llm,
			$userSubject, // Subject is still passed, even if source is video, for context if needed
			$autoDetectMain,
			$language,
			$maxRetries,
			$userTitle,
			$notes, // Notes are still passed
			$selectedMainCategoryName,
			$autoDetectSub,
			$additionalInstructions,
			$videoSubtitles // Pass subtitles if source is video
		);


		if (isset($planStructureResult['error'])) {
			$errorMsg = $planStructureResult['error'];
			Log::error("LLM Structure Gen Error: " . $errorMsg, ['lesson' => $userTitle, 'llm' => $llm, 'source' => $generationSource]);
			return response()->json(['success' => false, 'message' => 'Failed to generate lesson structure: ' . $errorMsg]);
		}

		if (!self::isValidLessonStructureResponse($planStructureResult)) {
			$errorMsg = 'LLM returned an invalid lesson structure (check includes category).';
			Log::error($errorMsg, ['lesson' => $userTitle, 'llm' => $llm, 'source' => $generationSource, 'response' => $planStructureResult]);
			return response()->json(['success' => false, 'message' => $errorMsg . ' Please try refining your lesson or using a different model.']);
		}
		Log::info("Lesson structure generated successfully for preview. Suggested Main: " . ($planStructureResult['suggested_main_category'] ?? 'N/A') . ", Sub: " . ($planStructureResult['suggested_sub_category'] ?? 'N/A'));


		// Prepare response data
		$responseData = [
			'success' => true,
			'plan' => $planStructureResult,
			'lesson_id' => $lesson->id,
			'language_selected' => $language,
			'current_sub_category_id' => $lesson->sub_category_id,
			'category_selection_mode' => $categorySelectionMode,
			'selected_main_category_id' => $lesson->selected_main_category_id,
			'suggested_main_category' => $planStructureResult['suggested_main_category'] ?? null,
			'suggested_sub_category' => $planStructureResult['suggested_sub_category'] ?? null,
			'current_instructions' => $additionalInstructions,
			'auto_detect_was_on' => $autoDetect
		];
		return response()->json($responseData);
	}

	public function applyGeneratedPlan(Request $request, Lesson $lesson)
	{
		$this->authorize('update', $lesson); // Ensure user owns the lesson
		$userId = Auth::id();

		// MODIFIED: Validator for single lesson content
		$validator = Validator::make($request->all(), [
			'plan' => 'required|array',
			'plan.title' => 'required|string',
			'plan.image_prompt_idea' => 'required|string',
			'plan.lesson_content' => 'required|string',
			// No need to validate suggested_main_category and suggested_sub_category here,
			// they are optional in the request and handled by logic below.
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

		// Get the suggested categories from the plan (these might be in the 'plan' array itself or passed separately)
		$suggestedMainCategoryName = $plan['suggested_main_category'] ?? null;
		$suggestedSubCategoryName = $plan['suggested_sub_category'] ?? null;


		// Determine category handling based on lesson's saved selection mode
		$categorySelectionMode = $lesson->category_selection_mode ?? 'ai_decide';
		$selectedMainCategoryId = $lesson->selected_main_category_id; // The main category ID if user selected 'main_only' or 'both'

		// The final sub-category ID will be determined based on the selection mode
		$finalSubCategoryId = $lesson->sub_category_id; // Default to current, especially for 'both' mode

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
		} else if ($categorySelectionMode === 'main_only' && $selectedMainCategoryId && !empty($suggestedSubCategoryName)) {
			// User selected main, AI suggesting sub
			DB::beginTransaction();
			try {
				// Find the user's main category
				$mainCategory = MainCategory::where('id', $selectedMainCategoryId)
					->where('user_id', $userId)
					->first();

				if (!$mainCategory) {
					throw new Exception("Selected main category not found for user.");
				}

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
		// For 'both' mode, the sub-category was already selected and saved in the lesson record,
		// so $finalSubCategoryId (which defaults to $lesson->sub_category_id) is already correct.

		// Update the lesson with the generated plan
		try {
			$lesson->update([
				'title' => $plan['title'],
				'image_prompt_idea' => $plan['image_prompt_idea'],
				'lesson_content' => json_encode(['text' => $plan['lesson_content'], 'sentences' => []]),
				'sub_category_id' => $finalSubCategoryId,
				'ai_generated' => true, // Mark as AI-generated
			]);
			Log::info("Successfully applied AI-generated plan to lesson ID: {$lesson->id}");

			return response()->json([
				'success' => true,
				'message' => 'Lesson content generated successfully!',
				'redirectUrl' => route('lesson.edit', ['lesson' => $lesson->id])
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
					'lesson_content' => json_encode(['text' => null, 'sentences' => []]),
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
				Log::info("JSON Import: Created lesson '{$lessonData['title']}'");
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
