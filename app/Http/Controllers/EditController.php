<?php namespace App\Http\Controllers;

use App\Helpers\LlmHelper;
use App\Helpers\AudioImageHelper;
use App\Models\GeneratedImage;
use App\Models\MainCategory;
use App\Models\SubCategory;
use App\Models\Question;

// Keep Question model import
use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Http;
use Intervention\Image\Laravel\Facades\Image as InterventionImage;

// For image resizing
use Illuminate\Http\UploadedFile;

// For type hinting
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

// For transactions
use Exception;

// Add Exception import

class EditController extends Controller
{
	// MODIFIED: System prompt for quiz generation based on single lesson content
	private const SYSTEM_PROMPT_QUIZ_GENERATION = <<<PROMPT
You are an AI assistant specialized in creating integrated questions for educational lessons.
The user will provide:
1. The title and text content for THE ENTIRE LESSON.
2. The target difficulty level ('easy', 'medium', or 'hard').
3. A list of question questions already generated for OTHER difficulties of the lesson.

You MUST generate a JSON array containing exactly 3 question questions ONLY for the CURRENT lesson's content and the SPECIFIED difficulty.
The JSON output MUST be ONLY an array of 3 objects, like this:
{
    "questions" : [
        { // Question 1 (matching target difficulty)
            "question": "Question 1 text based ONLY on the provided lesson content and targeted difficulty.",
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
- All questions, answers, and feedback MUST be directly based on the provided "Current Lesson Text" and "Current Lesson Title". Do NOT use external knowledge beyond interpreting the provided text.
- Generate questions appropriate for the requested 'Target Difficulty'.
- **CRITICAL**: Review the "Previously Generated Questions" list provided by the user. Do NOT generate questions that are identical or substantially similar in meaning to any question in that list.
- `image_prompt_idea` short, and descriptive.
- `image_search_keywords` short, and relevant to the question without hinting the answer.
PROMPT;

	private const SYSTEM_PROMPT_SENTENCE_IMAGE_IDEA = <<<PROMPT
You are an AI assistant. Given a single sentence from an educational text, provide a concise visual idea for an image representing that sentence, and 2-3 relevant search keywords.
Your output MUST be ONLY a valid JSON object with the following structure:
{
    "image_prompt_idea": "A short phrase describing a visual for the sentence (max 10 words).",
    "image_search_keywords": "2-3 relevant keywords for image search (max 5 words total)."
}
No explanations or introductory text.
PROMPT;

	/**
	 * Generates image prompt idea and keywords for a single sentence using an LLM.
	 *
	 * @param string $llm The LLM model ID.
	 * @param string $sentenceText The text of the sentence.
	 * @param int $maxRetries Maximum number of retries.
	 * @return array Result with 'image_prompt_idea', 'image_search_keywords', or 'error'.
	 */
	public static function generateSentenceImageIdeas(string $llm, string $sentenceText, int $maxRetries = 1): array
	{
		$userMessageContent = "Generate image ideas for this sentence:\n\"" . $sentenceText . "\"";
		$chatHistory = [['role' => 'user', 'content' => $userMessageContent]];

		Log::info("Requesting image ideas for sentence: '" . Str::limit($sentenceText, 50) . "...' using LLM: {$llm}");
		$result = LlmHelper::llm_no_tool_call($llm, self::SYSTEM_PROMPT_SENTENCE_IMAGE_IDEA, $chatHistory, true, $maxRetries);

		// Basic validation of the result structure
		if (isset($result['error'])) {
			Log::error("LLM error generating sentence image ideas: " . $result['error']);
			return ['error' => $result['error']];
		}

		if (!isset($result['image_prompt_idea']) || !isset($result['image_search_keywords'])) {
			Log::error("LLM returned invalid structure for sentence image ideas.", ['response' => $result]);
			// Attempt to extract if nested (some models wrap output)
			if (isset($result['response']['image_prompt_idea']) && isset($result['response']['image_search_keywords'])) {
				return [
					'image_prompt_idea' => $result['response']['image_prompt_idea'],
					'image_search_keywords' => $result['response']['image_search_keywords'],
				];
			}
			return ['error' => 'Invalid structure received from LLM for sentence image ideas.'];
		}

		return [
			'image_prompt_idea' => $result['image_prompt_idea'],
			'image_search_keywords' => $result['image_search_keywords'],
		];
	}


	/**
	 * MODIFIED: Generates 3 questions for the lesson content and difficulty using an LLM.
	 *
	 * @param string $llm The LLM model ID.
	 * @param string $lessonPrompt Initial lesson subject/prompt.
	 * @param string $lessonTitle Title of the lesson content.
	 * @param string $lessonContentText Text of the lesson content.
	 * @param string $difficulty The target difficulty ('easy', 'medium', 'hard').
	 * @param array $existingQuestionTexts Array of question texts already generated for the whole lesson.
	 * @param int $maxRetries Maximum number of retries for the LLM call.
	 * @return array Result from llm_no_tool_call (JSON decoded array or error array).
	 */
	public static function generateQuestionsForLessonDifficulty(string $llm, string $lessonPrompt, string $lessonTitle, string $lessonContentText, string $difficulty, array $existingQuestionTexts, int $maxRetries = 1): array
	{
		$userContent = "Initial Lesson Prompt: " . $lessonPrompt . "\n\n";
		$userContent .= "Lesson Title: " . $lessonTitle . "\n\n";
		$userContent .= "Lesson Text: " . $lessonContentText . "\n\n";
		$userContent .= "Target Difficulty: " . $difficulty . "\n\n";
		$userContent .= "Previously Generated Questions (Avoid Duplicates):\n";
		if (empty($existingQuestionTexts)) {
			$userContent .= "- None yet";
		} else {
			foreach ($existingQuestionTexts as $qText) {
				$userContent .= "- " . $qText . "\n";
			}
		}

		$chatHistoryQuestionGen = [['role' => 'user', 'content' => $userContent]];
		Log::info("Requesting {$difficulty} question generation for lesson '{$lessonTitle}' using LLM: {$llm}");
		return LlmHelper::llm_no_tool_call($llm, self::SYSTEM_PROMPT_QUIZ_GENERATION, $chatHistoryQuestionGen, true, $maxRetries);
	}


	public static function isValidQuestionListResponse(?array $questionListData): bool
	{
		if (empty($questionListData) || !is_array($questionListData)) {
			Log::warning('Invalid Question List: Data is empty or not an array.', ['data' => $questionListData]);
			return false;
		}
		// Check if the expected "questions" key exists and is an array
		if (!isset($questionListData['questions']) || !is_array($questionListData['questions'])) {
			Log::warning('Invalid Question List: Missing "questions" key or not an array.', ['data' => $questionListData]);
			return false;
		}

		foreach ($questionListData['questions'] as $index => $question) {
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


	// ==============================================
	// EDITING AND ON-DEMAND GENERATION METHODS
	// ==============================================

	public function updateSettingsAjax(Request $request, Lesson $lesson)
	{
		$this->authorize('update', $lesson);
		$userId = Auth::id(); // Get current user ID

		$validator = Validator::make($request->all(), [
			'preferred_llm' => 'required|string|max:100',
			'tts_engine' => 'required|string|in:google,openai',
			'tts_voice' => 'required|string|max:100',
			'tts_language_code' => 'required|string|max:10',
			'language' => 'required|string|max:30',
			// --- Category Validation ---
			'main_category_id' => [
				'required', // Main category is now always required on edit
				'integer',
				Rule::exists('main_categories', 'id')->where('user_id', $userId)
			],
			'sub_category_id' => [
				'nullable', // Sub-category can be null
				'integer',
				Rule::exists('sub_categories', 'id')
					->where('user_id', $userId)
					->where('main_category_id', $request->input('main_category_id')) // Ensure sub belongs to selected main
			],
			// --- End Category Validation ---
			'user_title' => 'nullable|string|max:255',
			'subject' => 'nullable|string|max:2048',
			'notes' => 'nullable|string|max:5000',
			'month' => 'nullable|integer|between:1,12',
			'year' => 'nullable|integer|digits:4',
			'week' => 'nullable|integer|between:1,53',
		]);


		if ($request->filled('sub_category_id') && !is_numeric($request->input('sub_category_id'))) {
			return response()->json([
				'success' => false,
				'message' => 'Validation failed: Invalid Sub-Category selected.'
			], 422);
		}


		if ($validator->fails()) {
			Log::warning("Lesson settings update validation failed for Lesson ID: {$lesson->id}", ['errors' => $validator->errors()]);
			return response()->json([
				'success' => false,
				'message' => 'Validation failed: ' . $validator->errors()->first(),
				'errors' => $validator->errors()
			], 422);
		}

		try {
			$lesson->preferredLlm = $request->input('preferred_llm');
			$lesson->ttsEngine = $request->input('tts_engine');
			$lesson->ttsVoice = $request->input('tts_voice');
			$lesson->ttsLanguageCode = $request->input('tts_language_code');
			$lesson->language = $request->input('language');

			// Category handling:
			// selected_main_category_id is always set from main_category_id
			$lesson->selected_main_category_id = $request->input('main_category_id');
			// sub_category_id is set if provided, otherwise null
			$subCategoryId = $request->input('sub_category_id');
			$lesson->sub_category_id = ($subCategoryId && is_numeric($subCategoryId) && $subCategoryId > 0) ? (int)$subCategoryId : null;
			if ($lesson->sub_category_id) {
				$lesson->category_selection_mode = 'both';
			} else {
				$lesson->category_selection_mode = 'main_only';
			}


			$lesson->user_title = $request->input('user_title');
			$lesson->subject = $request->input('subject');
			$lesson->notes = $request->input('notes');
			$lesson->month = $request->input('month') ?: null;
			$lesson->year = $request->input('year') ?: null;
			$lesson->week = $request->input('week') ?: null;

			$lesson->save();
			Log::info("Updated lesson settings for Lesson ID: {$lesson->id}");
			return response()->json(['success' => true, 'message' => 'Lesson settings updated successfully.']);
		} catch (Exception $e) {
			Log::error("Error updating lesson settings for Lesson ID {$lesson->id}: " . $e->getMessage());
			return response()->json([
				'success' => false,
				'message' => 'Failed to update settings: ' . $e->getMessage()
			], 500);
		}
	}

	/**
	 * Show the lesson edit page.
	 */
	public function edit(Lesson $lesson)
	{
		$this->authorize('update', $lesson);
		$userId = Auth::id();

		// Eager load necessary relationships
		$lesson->load([
			'questions' => function ($query) {
				$query->orderByRaw("FIELD(difficulty_level, 'easy', 'medium', 'hard')")
					->orderBy('order', 'asc')
					->orderBy('id', 'asc');
			},
			'questions.generatedImage',
			'subCategory.mainCategory', // Eager load mainCategory via subCategory
			'mainCategory' // Eager load the directly selected mainCategory
		]);

		Log::info("Showing edit page for Lesson ID: {$lesson->id}");

		$groupedQuestions = ['easy' => [], 'medium' => [], 'hard' => []];
		foreach ($lesson->questions as $question) {
			$difficulty = $question->difficulty_level;
			$groupedQuestions[$difficulty][] = $question;
		}

		// MODIFIED: Decode lesson_content (it's a single object now)
		$lessonContent = $lesson->lesson_content; // Already an array (decoded by model cast)
		if (is_string($lessonContent)) { // Should not happen if cast is working
			$lessonContent = json_decode($lessonContent, true);
		}
		// Ensure it's an array, default if not
		$lesson->lesson_content = is_array($lessonContent) ? $lessonContent : ['text' => null, 'sentences' => []];


		$llms = LlmHelper::checkLLMsJson();
		$llm = $lesson->preferredLlm ?: env('DEFAULT_LLM');
		if (empty($llm)) {
			Log::error("No LLM configured for lesson {$lesson->id} or as default.");
			// return back()->withErrors('AI model configuration error.'); // This is an AJAX context, handle differently if needed
		}


		$allMainCategories = Auth::user()->mainCategories()
			->with(['subCategories' => function ($query) use ($userId) {
				$query->where('user_id', $userId)->orderBy('name');
			}])
			->orderBy('name')->get();

		$categoriesData = $allMainCategories->mapWithKeys(function ($mainCat) {
			return [$mainCat->id => [
				'id' => $mainCat->id,
				'name' => $mainCat->name,
				'subCategories' => $mainCat->subCategories->mapWithKeys(function ($subCat) {
					return [$subCat->id => ['id' => $subCat->id, 'name' => $subCat->name]];
				})->all()
			]];
		})->toJson();


		return view('edit_lesson', [
			'lesson' => $lesson,
			'groupedQuestions' => $groupedQuestions, // Pass as is, view will adapt
			'llm' => $llm,
			'llms' => $llms, // Pass all available LLMs for the modal
			'allMainCategories' => $allMainCategories, // For settings dropdown
			'categoriesData' => $categoriesData, // For JS subcategory population
		]);
	}

	public function updateQuestionTextsAjax(Request $request, Question $question)
	{
		$this->authorize('update', $question->lesson);
		$questionId = $question->id;
		$lessonId = $question->lesson_id;
		Log::info("AJAX request to update texts for Question ID: {$questionId} from Lesson ID: {$lessonId}");

		$validator = Validator::make($request->all(), [
			'question_text' => 'required|string|min:5',
			'answers' => 'required|array|size:4',
			'answers.*.text' => 'required|string|min:1',
			'answers.*.is_correct' => 'required|boolean',
			'answers.*.feedback' => 'required|string|min:1',
		]);

		if ($validator->fails()) {
			return response()->json([
				'success' => false,
				'message' => 'Validation failed',
				'errors' => $validator->errors()
			], 422);
		}

		try {
			DB::beginTransaction();
			$question->question_text = $request->question_text;
			$newAnswers = [];
			$oldAnswers = $question->answers; // This is already an array

			$correctCount = 0;
			foreach ($request->answers as $answer) {
				if ($answer['is_correct']) {
					$correctCount++;
				}
			}

			if ($correctCount !== 1) {
				DB::rollBack();
				return response()->json([
					'success' => false,
					'message' => 'There must be exactly one correct answer.'
				], 422);
			}


			foreach ($request->answers as $index => $newAnswer) {
				$answerData = [
					'text' => $newAnswer['text'],
					'is_correct' => (bool)$newAnswer['is_correct'],
					'feedback' => $newAnswer['feedback'],
				];
				// Preserve existing audio paths if they exist in oldAnswers
				if (isset($oldAnswers[$index])) {
					if (isset($oldAnswers[$index]['answer_audio_path'])) {
						$answerData['answer_audio_path'] = $oldAnswers[$index]['answer_audio_path'];
					}
					if (isset($oldAnswers[$index]['answer_audio_url'])) {
						$answerData['answer_audio_url'] = $oldAnswers[$index]['answer_audio_url'];
					}
					if (isset($oldAnswers[$index]['feedback_audio_path'])) {
						$answerData['feedback_audio_path'] = $oldAnswers[$index]['feedback_audio_path'];
					}
					if (isset($oldAnswers[$index]['feedback_audio_url'])) {
						$answerData['feedback_audio_url'] = $oldAnswers[$index]['feedback_audio_url'];
					}
				}
				$newAnswers[] = $answerData;
			}
			$question->answers = $newAnswers;
			$question->save();
			DB::commit();

			Log::info("Successfully updated texts for Question ID: {$questionId}");
			return response()->json([
				'success' => true,
				'message' => 'Question texts updated successfully',
				'question' => [ // Send back the updated question data for JS
					'id' => $question->id,
					'question_text' => $question->question_text,
					'answers' => $question->answers // Send the processed answers
				]
			]);
		} catch (Exception $e) {
			DB::rollBack();
			Log::error("Error updating texts for Question ID {$questionId}: " . $e->getMessage());
			return response()->json([
				'success' => false,
				'message' => 'Failed to update question texts: ' . $e->getMessage()
			], 500);
		}
	}

	/**
	 * AJAX endpoint to generate a batch of 3 questions for the lesson content and difficulty.
	 *
	 * @param Request $request
	 * @param Lesson $lesson
	 * @param string $difficulty ('easy', 'medium', 'hard')
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function generateQuestionBatchAjax(Request $request, Lesson $lesson, string $difficulty)
	{
		$this->authorize('generateAssets', $lesson);
		Log::info("AJAX request to generate '{$difficulty}' question batch for Lesson ID: {$lesson->id}");

		if (!in_array($difficulty, ['easy', 'medium', 'hard'])) {
			Log::error("Invalid difficulty '{$difficulty}' requested.");
			return response()->json(['success' => false, 'message' => 'Invalid difficulty level provided.'], 400);
		}

		$lessonPrompt = $lesson->subject;
		// MODIFIED: Retrieve single lesson content
		$lessonContent = is_array($lesson->lesson_content) ? $lesson->lesson_content : json_decode($lesson->lesson_content, true);

		if (!is_array($lessonContent) || !isset($lessonContent['text'])) {
			Log::error("Invalid lesson content data for Lesson ID: {$lesson->id}.");
			return response()->json(['success' => false, 'message' => 'Invalid lesson content.'], 400);
		}
		$lessonTitle = $leson->title ?? $lesson->user_title ?? 'Lesson Content';
		$contentText = $lessonContent['text'] ?? '';

		if (empty($contentText)) {
			Log::error("Cannot generate questions for lesson {$lesson->id}: Text is empty.");
			return response()->json(['success' => false, 'message' => 'Lesson content text is empty.'], 400);
		}

		$llm = $lesson->preferredLlm;
		if (empty($llm)) {
			$llm = env('DEFAULT_LLM');
			Log::warning("Lesson {$lesson->id} preferredLlm is empty, falling back to default.");
			if (empty($llm)) {
				Log::error("No LLM configured for lesson {$lesson->id} or as default.");
				return response()->json(['success' => false, 'message' => 'AI model configuration error.'], 500);
			}
		}

		$existingQuestionTexts = $lesson->questions()->pluck('question_text')->toArray();
		Log::debug("Found " . count($existingQuestionTexts) . " existing questions for lesson {$lesson->id}");
		$maxRetries = 1;

		// MODIFIED: Call generateQuestionsForLessonDifficulty
		$questionResult = self::generateQuestionsForLessonDifficulty(
			$llm,
			$lessonPrompt,
			$lessonTitle,
			$contentText,
			$difficulty,
			$existingQuestionTexts,
			$maxRetries
		);
		Log::info("LLM Question Gen Result for Lesson {$lesson->id}, Difficulty '{$difficulty}': ", $questionResult);


		if (isset($questionResult['error'])) {
			$errorMsg = $questionResult['error'];
			$logMsg = "LLM Question Gen Error for Lesson {$lesson->id}, Difficulty '{$difficulty}': " . $errorMsg;
			Log::error($logMsg, ['lesson' => $lesson->id, 'llm' => $llm, 'lesson_title' => $lessonTitle]);
			return response()->json(['success' => false, 'message' => "Failed to generate {$difficulty} questions: " . $errorMsg], 500);
		}

		if (!self::isValidQuestionListResponse($questionResult)) { // Pass the whole $questionResult
			$errorMsg = "LLM returned an invalid {$difficulty} question structure for lesson '{$lessonTitle}'.";
			Log::error($errorMsg, ['lesson' => $lesson->id, 'llm' => $llm, 'lesson_title' => $lessonTitle, 'response' => $questionResult]);
			return response()->json(['success' => false, 'message' => $errorMsg . ' Please try again.'], 500);
		}

		$createdQuestionsData = [];
		$maxOrder = Question::where('lesson_id', $lesson->id)->max('order') ?? -1;
		$nextOrder = $maxOrder + 1;

		try {
			foreach ($questionResult['questions'] as $questionQuestionData) {
				$answersToStore = [];
				foreach ($questionQuestionData['answers'] as $answer) {
					$answersToStore[] = [
						'text' => $answer['text'],
						'is_correct' => $answer['is_correct'],
						'feedback' => $answer['feedback'],
						// Audio paths will be null initially
						'answer_audio_path' => null,
						'feedback_audio_path' => null,
					];
				}

				$newQuestion = Question::create([
					'lesson_id' => $lesson->id,
					'image_prompt_idea' => $questionQuestionData['image_prompt_idea'] ?? null,
					'image_search_keywords' => $questionQuestionData['image_search_keywords'] ?? null,
					'question_text' => $questionQuestionData['question'],
					'answers' => $answersToStore,
					'difficulty_level' => $difficulty,
					'order' => $nextOrder++,
					// Audio paths will be null initially
					'question_audio_path' => null,
				]);
				$createdQuestionsData[] = $newQuestion->toArray() + ['question_audio_url' => null];
			}
			Log::info("Created " . count($createdQuestionsData) . " new '{$difficulty}' question records for Lesson ID: {$lesson->id}");

			return response()->json([
				'success' => true,
				'message' => "Successfully generated " . count($createdQuestionsData) . " {$difficulty} questions!", // MODIFIED: Message
				'questions' => $createdQuestionsData
			]);
		} catch (Exception $e) {
			Log::error("Database error saving new questions for Lesson ID {$lesson->id}: " . $e->getMessage());
			return response()->json(['success' => false, 'message' => 'Failed to save generated questions.'], 500);
		}
	}

	/**
	 * AJAX endpoint to delete a specific question.
	 *
	 * @param Question $question Route model binding
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function deleteQuestionAjax(Question $question)
	{
		$this->authorize('update', $question->lesson);
		$questionId = $question->id;
		$lessonId = $question->lesson_id;
		Log::info("AJAX request to delete Question ID: {$questionId} from Lesson ID: {$lessonId}");

		DB::beginTransaction();
		try {
			// Delete question audio
			if ($question->question_audio_path && Storage::disk('public')->exists($question->question_audio_path)) {
				Storage::disk('public')->delete($question->question_audio_path);
				Log::info("Deleted question audio file: {$question->question_audio_path}");
			}

			// Delete answer and feedback audio files
			if (is_array($question->answers)) {
				foreach ($question->answers as $answer) {
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

			// Delete associated generated image and its files
			if ($question->generated_image_id) {
				$image = GeneratedImage::find($question->generated_image_id);
				if ($image) {
					Log::info("Deleting storage files for GeneratedImage ID: {$image->id} linked to Question ID: {$questionId}");
					$image->deleteStorageFiles(); // Method to delete files from storage
					$image->delete(); // Delete the image record itself
				}
			}

			$question->delete(); // Delete the question record
			DB::commit();
			Log::info("Successfully deleted Question ID: {$questionId}");
			return response()->json(['success' => true, 'message' => 'Question deleted successfully.']);
		} catch (Exception $e) {
			DB::rollBack();
			Log::error("Error deleting Question ID {$questionId}: " . $e->getMessage());
			return response()->json(['success' => false, 'message' => 'Failed to delete question.'], 500);
		}
	}

	/**
	 * AJAX endpoint to update the title and text of the lesson content.
	 *
	 * @param Request $request
	 * @param Lesson $lesson
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function updateLessonContentAjax(Request $request, Lesson $lesson)
	{
		$this->authorize('update', $lesson);
		Log::info("AJAX request to update content for Lesson ID: {$lesson->id}");

		$validator = Validator::make($request->all(), [
			'lesson_title' => 'required|string|max:255',
			'lesson_text' => 'required|string|min:10|max:2000', // Max length for lesson text
		]);

		if ($validator->fails()) {
			Log::warning("Lesson content update validation failed for Lesson ID: {$lesson->id}", ['errors' => $validator->errors()]);
			return response()->json([
				'success' => false,
				'message' => 'Validation failed: ' . $validator->errors()->first()
			], 422);
		}

		DB::beginTransaction();
		try {
			$lessonContent = $lesson->lesson_content; // Already an array due to cast
			if (!is_array($lessonContent)) { // Should not happen
				$lessonContent = ['title' => null, 'text' => null, 'image_prompt_idea' => null, 'sentences' => []];
			}

			$oldText = $lessonContent['text'] ?? '';
			$newText = $request->input('lesson_text');

			if ($oldText !== $newText) {
				Log::info("Lesson text changed for {$lesson->id}. Clearing sentence assets.");
				$oldSentences = $lessonContent['sentences'] ?? [];
				foreach ($oldSentences as $oldSentence) {
					if (!empty($oldSentence['audio_path']) && Storage::disk('public')->exists($oldSentence['audio_path'])) {
						try {
							Storage::disk('public')->delete($oldSentence['audio_path']);
						} catch (Exception $e) {
							Log::warning("Could not delete old sentence audio: " . $e->getMessage());
						}
					}
					if (!empty($oldSentence['generated_image_id'])) {
						$oldImage = GeneratedImage::find($oldSentence['generated_image_id']);
						if ($oldImage && in_array($oldImage->source, ['upload', 'freepik', 'ai'])) { // Include 'ai' source for deletion
							try {
								$oldImage->deleteStorageFiles();
								$oldImage->delete();
							} catch (Exception $e) {
								Log::warning("Could not delete old sentence image: " . $e->getMessage());
							}
						}
					}
				}
				$lessonContent['sentences'] = []; // Clear sentences array
				$lessonContent['audio_generated_at'] = null; // Reset timestamp for the whole content block
			}

			$lessonContent['title'] = $request->input('lesson_title');
			$lessonContent['text'] = $newText;
			// Image prompt idea for the whole lesson content is not edited here, but could be added if needed.

			$lesson->lesson_content = $lessonContent; // Save the modified object back
			$lesson->save();
			DB::commit();

			Log::info("Successfully updated content for Lesson ID: {$lesson->id}");
			return response()->json([
				'success' => true,
				'message' => 'Lesson content updated successfully.',
				'updated_content' => [ // Send back updated data for JS
					'title' => $lessonContent['title'],
					'text' => $lessonContent['text'],
					'sentences_cleared' => ($oldText !== $newText) // Flag if sentences were cleared
				]
			]);
		} catch (Exception $e) {
			DB::rollBack();
			Log::error("Error updating lesson content for Lesson ID {$lesson->id}: " . $e->getMessage());
			return response()->json([
				'success' => false,
				'message' => 'Failed to update lesson content: ' . $e->getMessage()
			], 500);
		}
	}

	public static function processAndStoreYouTubeVideo(Lesson $lesson, string $youtubeVideoIdToProcess): array
	{
		$rapidApiKey = env('RAPID_API_KEY');
		$rapidApiHost = env('RAPID_API_YOUTUBE_HOST', 'youtube-media-downloader.p.rapidapi.com');
		$videoStoragePath = null; // Initialize

		if (!$rapidApiKey) {
			Log::error("RapidAPI Key is not configured for YouTube processing for lesson {$lesson->id}.");
			return ['success' => false, 'message' => 'Server configuration error (API Key missing).', 'video_title' => null];
		}

		Log::info("Attempting to process YouTube video '{$youtubeVideoIdToProcess}' for Lesson ID: {$lesson->id}");

		try {
			$response = Http::withHeaders([
				'x-rapidapi-host' => $rapidApiHost,
				'x-rapidapi-key' => $rapidApiKey,
			])->timeout(30) // 30 seconds for API details
			->get("https://{$rapidApiHost}/v2/video/details", ['videoId' => $youtubeVideoIdToProcess]);

			if (!$response->successful()) {
				Log::error("RapidAPI request failed for video {$youtubeVideoIdToProcess}, Lesson ID {$lesson->id}.", ['status' => $response->status(), 'body' => $response->body()]);
				throw new Exception("Failed to fetch video details from API (Status: {$response->status()}).");
			}

			$videoData = $response->json();
			if (!$videoData || !($videoData['status'] ?? false)) {
				Log::error("RapidAPI returned error or invalid data for video {$youtubeVideoIdToProcess}, Lesson ID {$lesson->id}.", ['response' => $videoData]);
				throw new Exception("API returned an error: " . ($videoData['errorId'] ?? 'Unknown error'));
			}

			Log::info("Successfully fetched details for video: " . ($videoData['title'] ?? 'N/A') . " for Lesson ID: {$lesson->id}");

			$bestVideoUrl = null;
			$highestQuality = 0;
			if (isset($videoData['videos']['items']) && is_array($videoData['videos']['items'])) {
				foreach ($videoData['videos']['items'] as $video) {
					if (isset($video['url'], $video['height'], $video['hasAudio']) &&
						$video['hasAudio'] === true &&
						str_contains($video['mimeType'] ?? '', 'mp4') &&
						$video['height'] > $highestQuality) {
						$bestVideoUrl = $video['url'];
						$highestQuality = $video['height'];
					}
				}
			}

			if (!$bestVideoUrl) {
				if (isset($videoData['audios']['items']) && is_array($videoData['audios']['items']) && count($videoData['audios']['items']) > 0) {
					Log::warning("No suitable video stream found for {$youtubeVideoIdToProcess}, Lesson ID {$lesson->id}. Falling back to audio stream.");
					$bestVideoUrl = $videoData['audios']['items'][0]['url'] ?? null;
				}
				if (!$bestVideoUrl) {
					Log::error("No suitable video or audio stream found for {$youtubeVideoIdToProcess}, Lesson ID {$lesson->id}.");
					throw new Exception("Could not find a downloadable video/audio stream.");
				}
			}

			$videoFileName = 'video_' . Str::random(10) . '.mp4'; // Ensure .mp4 extension
			$videoStoragePath = "lessons/{$lesson->id}/{$videoFileName}";

			Log::info("Attempting to download video from {$bestVideoUrl} to {$videoStoragePath} for Lesson ID: {$lesson->id}");
			$downloadResponse = Http::timeout(300)->withOptions(['stream' => true])->get($bestVideoUrl); // 5 minutes timeout for download

			if (!$downloadResponse->successful()) {
				Log::error("Failed to download video stream for {$youtubeVideoIdToProcess}, Lesson ID {$lesson->id}. Status: " . $downloadResponse->status());
				throw new Exception("Failed to download video file (Status: {$downloadResponse->status()}).");
			}

			$storageSuccess = Storage::disk('public')->put($videoStoragePath, $downloadResponse->getBody());
			if (!$storageSuccess) {
				Log::error("Failed to save downloaded video to storage at {$videoStoragePath} for Lesson ID {$lesson->id}. Check permissions.");
				throw new Exception("Failed to save video file to storage.");
			}
			Log::info("Successfully downloaded and saved video to {$videoStoragePath} for Lesson ID: {$lesson->id}");

			$plaintextSubtitles = "";
			$rawSubtitleContent = '';
			if (isset($videoData['subtitles']['items']) && is_array($videoData['subtitles']['items'])) {
				Log::info("Found " . count($videoData['subtitles']['items']) . " subtitle tracks for {$youtubeVideoIdToProcess}, Lesson ID {$lesson->id}.");
				$subtitleUrl = '';
				// Prioritize English 'en'
				foreach ($videoData['subtitles']['items'] as $subtitle) {
					if (isset($subtitle['url'], $subtitle['code']) && $subtitle['code'] == 'en') {
						if (isset($subtitle['text']) && $subtitle['text'] === 'English') { // Prefer explicitly named "English"
							$subtitleUrl = $subtitle['url'];
							break;
						}
						if (empty($subtitleUrl)) { // Fallback to any 'en' code
							$subtitleUrl = $subtitle['url'];
						}
					}
				}
				// If no 'en' found, try 'en-US' or 'en-GB' as common alternatives
				if (empty($subtitleUrl)) {
					foreach ($videoData['subtitles']['items'] as $subtitle) {
						if (isset($subtitle['url'], $subtitle['code']) && in_array($subtitle['code'], ['en-US', 'en-GB'])) {
							$subtitleUrl = $subtitle['url'];
							break;
						}
					}
				}


				if ($subtitleUrl !== '') {
					try {
						$subtitleResponse = Http::timeout(20)->get($subtitleUrl);
						if ($subtitleResponse->successful()) {
							$rawSubtitleContent = $subtitleResponse->body();
							$tempSubtitleText = '';
							// Basic VTT/SRT parsing to get plaintext
							if (str_contains($rawSubtitleContent, 'WEBVTT') || preg_match('/^\d+\s*\R\d{2}:\d{2}:\d{2},\d{3} --> \d{2}:\d{2}:\d{2},\d{3}/m', $rawSubtitleContent)) {
								$lines = preg_split('/\r\n|\r|\n/', $rawSubtitleContent);
								$isTextLine = false;
								foreach ($lines as $line) {
									if (empty(trim($line))) {
										$isTextLine = false;
										continue;
									}
									if (str_contains($line, '-->') || preg_match('/^\d+$/', trim($line)) || str_contains($line, 'WEBVTT')) {
										$isTextLine = true; // Next non-empty line(s) are text
										continue;
									}
									if ($isTextLine) {
										$tempSubtitleText .= strip_tags($line) . ' ';
									}
								}
							} else { // Assume XML-like (ttml) if not VTT/SRT
								$xml = @simplexml_load_string($rawSubtitleContent); // Suppress errors for invalid XML
								if ($xml !== false) {
									foreach ($xml->xpath('//p | //text') as $textNode) { // Common subtitle text nodes
										$tempSubtitleText .= (string)$textNode . ' ';
									}
								} else {
									Log::info("Failed to parse subtitle XML for track for video {$youtubeVideoIdToProcess}, Lesson ID {$lesson->id}. Content might not be XML.");
									// Fallback: try to strip all tags if not parsed as XML
									$tempSubtitleText = strip_tags($rawSubtitleContent);
								}
							}
							$plaintextSubtitles = html_entity_decode($tempSubtitleText, ENT_QUOTES | ENT_HTML5);
							$plaintextSubtitles = preg_replace('/\s+/', ' ', $plaintextSubtitles);
							$plaintextSubtitles = trim($plaintextSubtitles);
							Log::info("Processed subtitles for video {$youtubeVideoIdToProcess}, Lesson ID {$lesson->id}. Length: " . strlen($plaintextSubtitles));

						} else {
							Log::warning("Failed to download subtitle track for {$youtubeVideoIdToProcess}, Lesson ID {$lesson->id}. Status: " . $subtitleResponse->status());
						}
					} catch (Exception $subE) {
						Log::warning("Error processing subtitle track for {$youtubeVideoIdToProcess}, Lesson ID {$lesson->id}: " . $subE->getMessage());
					}
				} else {
					Log::warning("No suitable English subtitle track found for {$youtubeVideoIdToProcess}, Lesson ID {$lesson->id}.");
				}
			} else {
				Log::info("No subtitle tracks found for {$youtubeVideoIdToProcess}, Lesson ID {$lesson->id}.");
			}

			$lesson->video_api_host = $rapidApiHost;
			$lesson->video_api_response = $videoData;
			$lesson->video_path = $videoStoragePath;
			$lesson->video_subtitles = !empty($rawSubtitleContent) ? trim($rawSubtitleContent) : null;
			$lesson->video_subtitles_text = !empty($plaintextSubtitles) ? trim($plaintextSubtitles) : null;
			$lesson->save();

			Log::info("Successfully updated Lesson ID {$lesson->id} with video data for '{$youtubeVideoIdToProcess}'.");
			return [
				'success' => true,
				'message' => 'YouTube video processed and saved successfully!',
				'video_title' => $videoData['title'] ?? 'N/A'
			];

		} catch (Exception $e) {
			Log::error("Error processing YouTube video {$youtubeVideoIdToProcess} for Lesson {$lesson->id}: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
			if (isset($videoStoragePath) && Storage::disk('public')->exists($videoStoragePath)) {
				Storage::disk('public')->delete($videoStoragePath);
				Log::info("Cleaned up partially downloaded video file: {$videoStoragePath} for lesson {$lesson->id}");
			}
			// Ensure lesson fields are not partially set if error occurs before save
			if ($lesson->isDirty(['video_api_host', 'video_api_response', 'video_path', 'video_subtitles', 'video_subtitles_text'])) {
				$lesson->video_api_host = null;
				$lesson->video_api_response = null;
				$lesson->video_path = null;
				$lesson->video_subtitles = null;
				$lesson->video_subtitles_text = null;
				// $lesson->youtube_video_id = null; // Keep the ID if it was set initially, so user knows it was attempted
				$lesson->saveQuietly(); // Save without triggering events if needed
			}
			return ['success' => false, 'message' => 'Error processing video: ' . $e->getMessage(), 'video_title' => null];
		}
	}


	public function addYoutubeVideoAjax(Request $request, Lesson $lesson)
	{
		// Authorization check
		$this->authorize('update', $lesson);

		$validator = Validator::make($request->all(), [
			'youtube_video_id' => 'required|string|max:50', // JS should send the extracted ID
		]);

		if ($validator->fails()) {
			return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
		}

		$youtubeVideoId = $request->input('youtube_video_id');

		// Call the refactored static method
		$result = self::processAndStoreYouTubeVideo($lesson, $youtubeVideoId);

		if (!$result['success']) {
			// The processAndStoreYouTubeVideo method already logs errors and cleans up.
			return response()->json(['success' => false, 'message' => $result['message']], 500);
		}

		// Refresh lesson to get updated attributes like video_url
		$lesson->refresh();

		return response()->json([
			'success' => true,
			'message' => 'YouTube video added successfully!',
			'video_title' => $result['video_title'] ?? 'N/A',
			'video_url' => $lesson->video_url // Accessor for the URL
		]);
	}
}
