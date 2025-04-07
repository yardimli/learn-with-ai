<?php

	namespace App\Http\Controllers;

	use App\Helpers\MyHelper;
	use App\Models\Subject;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Log;
	use Illuminate\Support\Facades\Validator;
	use Illuminate\Support\Str;

	use Illuminate\Support\Facades\Http;
	use Intervention\Image\Laravel\Facades\Image as InterventionImage; // For image resizing

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

		public static function generateLessonStructure(string $llm, string $userSubject, int $maxRetries = 1): array
		{
			$chatHistoryLessonStructGen = [['role' => 'user', 'content' => $userSubject]];
			Log::info("Requesting lesson structure generation for subject: '{$userSubject}' using LLM: {$llm}");
			return MyHelper::llm_no_tool_call($llm, self::SYSTEM_PROMPT_LESSON_STRUCTURE, $chatHistoryLessonStructGen, true, $maxRetries);
		}

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

	} // End of SubjectController
