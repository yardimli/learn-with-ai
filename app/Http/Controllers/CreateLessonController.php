<?php

	namespace App\Http\Controllers;

	use App\Helpers\MyHelper;
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
			// Eager load question count for potential display (optional)
			$lessons = Lesson::withCount('questions')->orderBy('created_at', 'desc')->get();
			return view('create_lesson', compact('llms', 'lessons'));
		}

		public function listLessons()
		{
			// Eager load question count for potential display (optional)
			$lessons = Lesson::withCount('questions')->orderBy('created_at', 'desc')->get();
			return view('lessons_list', compact('lessons')); // Return the new view
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

		public static function generateLessonStructure(string $llm, string $userLesson, int $maxRetries = 1): array
		{
			$chatHistoryLessonStructGen = [['role' => 'user', 'content' => $userLesson]];
			Log::info("Requesting lesson structure generation for lesson: '{$userLesson}' using LLM: {$llm}");
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
				'lesson' => 'required|string|max:1024',
				'llm' => 'required|string|max:100',
			]);

			if ($validator->fails()) {
				return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
			}

			$userLesson = $request->input('lesson');
			$llm = $request->input('llm');

			$maxRetries = 1;
			Log::info("AJAX request received for plan preview. Lesson: '{$userLesson}', LLM: {$llm}");

			Log::info("Generating lesson structure...");
			$planStructureResult = self::generateLessonStructure($llm, $userLesson, $maxRetries);

			if (isset($planStructureResult['error'])) {
				$errorMsg = $planStructureResult['error'];
				Log::error("LLM Structure Gen Error: " . $errorMsg, ['lesson' => $userLesson, 'llm' => $llm]);
				return response()->json(['success' => false, 'message' => 'Failed to generate lesson structure: ' . $errorMsg]);
			}

			if (!self::isValidLessonStructureResponse($planStructureResult)) {
				$errorMsg = 'LLM returned an invalid lesson structure.';
				Log::error($errorMsg, ['lesson' => $userLesson, 'llm' => $llm, 'response' => $planStructureResult]);
				return response()->json(['success' => false, 'message' => $errorMsg . ' Please try refining your lesson or using a different model.']);
			}

			Log::info("Lesson structure generated successfully for preview (no questions).");
			return response()->json(['success' => true, 'plan' => $planStructureResult]);
		}


		public function createLesson(Request $request)
		{
			// Validation rules simplified - only structure needed
			$validator = Validator::make($request->all(), [
				'lesson_name' => 'required|string|max:512',
				'preferred_llm' => 'required|string|max:100',
				'tts_engine' => 'required|string|in:google,openai',
				'tts_voice' => 'required|string|max:100',
				'tts_language_code' => 'required|string|max:10',
				'plan' => 'required|array',
				'plan.main_title' => 'required|string',
				'plan.image_prompt_idea' => 'required|string',
				'plan.lesson_parts' => 'required|array|size:3',
				'plan.lesson_parts.*.title' => 'required|string',
				'plan.lesson_parts.*.text' => 'required|string',
				'plan.lesson_parts.*.image_prompt_idea' => 'required|string',
			]);

			if ($validator->fails()) {
				Log::error('Invalid data received for lesson creation.', ['errors' => $validator->errors()->toArray(), 'data' => $request->input('plan', [])]);
				return response()->json(['success' => false, 'message' => 'Invalid data received for lesson creation. ' . $validator->errors()->first()], 422);
			}

			$userLesson = $request->input('lesson_name');
			$preferredLlm = $request->input('preferred_llm');
			$ttsEngine = $request->input('tts_engine');
			$ttsVoice = $request->input('tts_voice');
			$ttsLanguageCode = $request->input('tts_language_code');
			$plan = $request->input('plan');

			// Double-check plan validity using the structure validator
			if (!self::isValidLessonStructureResponse($plan)) { // Use the simpler validator
				Log::error('Invalid final plan structure received on createLesson endpoint.', ['plan' => $plan]);
				return response()->json(['success' => false, 'message' => 'Invalid lesson plan structure received during final check.'], 400);
			}

			$sessionId = Str::uuid()->toString(); // Generate a unique ID for this lesson session
			Log::info("Confirmed creation request received. Saving structure ONLY. Session ID: {$sessionId}, Lesson: '{$userLesson}'");

			// --- 1. Create Lesson Record (Store structured plan, NO QUIZZES) ---
			$lesson = Lesson::create([
				'name' => $userLesson,
				'title' => $plan['main_title'],
				'image_prompt_idea' => $plan['image_prompt_idea'],
				'lesson_parts' => $plan['lesson_parts'], // Store parts array (no questions inside yet)
				'session_id' => $sessionId,
				'preferredLlm' => $preferredLlm,
				'ttsEngine' => $ttsEngine,
				'ttsVoice' => $ttsVoice,
				'ttsLanguageCode' => $ttsLanguageCode,
			]);

			Log::info("Lesson record created with ID: {$lesson->id}, SessionID: {$sessionId}. No questions created at this stage.");

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
