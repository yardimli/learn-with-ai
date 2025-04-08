<?php

	namespace App\Http\Controllers;

	use App\Helpers\MyHelper;
	use App\Models\GeneratedImage;
	use App\Models\Question;

	// Keep Question model import
	use App\Models\Lesson;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Log;
	use Illuminate\Support\Facades\Storage;
	use Illuminate\Support\Facades\Validator;
	use Illuminate\Support\Str;

	use Illuminate\Support\Facades\Http;
	use Intervention\Image\Laravel\Facades\Image as InterventionImage;

	// For image resizing
	use Illuminate\Http\UploadedFile;

	// For type hinting
	use Illuminate\Support\Facades\DB;

	// For transactions

	use Exception;

	// Add Exception import

	class EditController extends Controller
	{
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
		 * MODIFIED: Generates 3 questions for a single lesson part and difficulty using an LLM.
		 *
		 * @param string $llm The LLM model ID.
		 * @param string $lessonPartTitle Title of the current lesson part.
		 * @param string $lessonPartText Text content of the current lesson part.
		 * @param string $difficulty The target difficulty ('easy', 'medium', 'hard').
		 * @param array $existingQuestionTexts Array of question texts already generated for the whole lesson.
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





		// ==============================================
		// EDITING AND ON-DEMAND GENERATION METHODS
		// ==============================================

		/**
		 * Show the lesson edit page.
		 */
		public function edit(Lesson $lesson)
		{
			// Eager load questions and their associated images
			// Order them by part, then difficulty, then their own order/id
			$lesson->load(['questions' => function ($query) {
				$query->orderBy('lesson_part_index', 'asc')
					->orderByRaw("FIELD(difficulty_level, 'easy', 'medium', 'hard')")
					->orderBy('order', 'asc') // Use the order field
					->orderBy('id', 'asc');
			}, 'questions.generatedImage']);

			Log::info("Showing edit page for Lesson ID: {$lesson->id} (Session: {$lesson->session_id})");

			// Group questions by part and difficulty for easier display in the view
			$groupedQuestions = [];
			foreach ($lesson->questions as $question) {
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
			$lessonParts = $lesson->lesson_parts;
			if (is_string($lessonParts)) {
				$lessonParts = json_decode($lessonParts, true);
			}
			// Ensure it's an array for the view
			$lesson->lesson_parts = is_array($lessonParts) ? $lessonParts : [];

			// Get the default LLM (needed if generating questions on this page)
			$llm = $lesson->llm_used ?: env('DEFAULT_LLM');

			// Get available LLMs
			$llms = MyHelper::checkLLMsJson();

			return view('edit_lesson', [
				'lesson' => $lesson,
				'groupedQuestions' => $groupedQuestions,
				'llm' => $llm,
				'llms' => $llms // Pass LLMs to view
			]);
		}

		public function updateQuestionTextsAjax(Request $request, Question $question)
		{
			$questionId = $question->id;
			$lessonId = $question->lesson_id;

			Log::info("AJAX request to update texts for Question ID: {$questionId} from Lesson ID: {$lessonId}");

			// Validate the request
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

				// Update question text
				$question->question_text = $request->question_text;

				// Update answers array
				$newAnswers = [];
				$oldAnswers = $question->answers;

				// Ensure exactly one correct answer
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

				// Process each answer
				foreach ($request->answers as $index => $newAnswer) {
					// Start with the updated data
					$answerData = [
						'text' => $newAnswer['text'],
						'is_correct' => (bool)$newAnswer['is_correct'],
						'feedback' => $newAnswer['feedback'],
					];

					// Preserve audio paths/urls if they exist in the old data
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
					'question' => [
						'id' => $question->id,
						'question_text' => $question->question_text,
						'answers' => $question->answers
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
		 * NEW: AJAX endpoint to generate a batch of 3 questions for a specific part and difficulty.
		 *
		 * @param Request $request
		 * @param Lesson $lesson
		 * @param int $partIndex
		 * @param string $difficulty ('easy', 'medium', 'hard')
		 * @return \Illuminate\Http\JsonResponse
		 */
		public function generateQuestionBatchAjax(Request $request, Lesson $lesson, int $partIndex, string $difficulty)
		{
			Log::info("AJAX request to generate '{$difficulty}' question batch for Lesson ID: {$lesson->id}, Part Index: {$partIndex}");

			// Validate difficulty
			if (!in_array($difficulty, ['easy', 'medium', 'hard'])) {
				Log::error("Invalid difficulty '{$difficulty}' requested.");
				return response()->json(['success' => false, 'message' => 'Invalid difficulty level provided.'], 400);
			}

			// Retrieve and decode lesson parts
			$lessonParts = is_array($lesson->lesson_parts) ? $lesson->lesson_parts : json_decode($lesson->lesson_parts, true);

			// Validate partIndex and get part data
			if (!is_array($lessonParts) || !isset($lessonParts[$partIndex])) {
				Log::error("Invalid part index ({$partIndex}) or lesson parts data for Lesson ID: {$lesson->id}.");
				return response()->json(['success' => false, 'message' => 'Invalid lesson part index.'], 400);
			}
			$partData = $lessonParts[$partIndex];
			$partTitle = $partData['title'] ?? 'Lesson Part ' . ($partIndex + 1);
			$partText = $partData['text'] ?? '';

			if (empty($partText)) {
				Log::error("Cannot generate questions for part {$partIndex}: Text is empty.");
				return response()->json(['success' => false, 'message' => 'Lesson part text is empty.'], 400);
			}

			// Get LLM
			$llm = session('preferred_llm', $lesson->llm_used) ?: env('DEFAULT_LLM');
			if (empty($llm)) {
				Log::error("No LLM configured for lesson {$lesson->id} or as default.");
				return response()->json(['success' => false, 'message' => 'AI model configuration error.'], 500);
			}

			// Fetch ALL existing question texts for THIS lesson to prevent duplicates
			$existingQuestionTexts = $lesson->questions()->pluck('question_text')->toArray();
			Log::debug("Found " . count($existingQuestionTexts) . " existing questions for lesson {$lesson->id}");

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
				Log::error($logMsg, ['lesson' => $lesson->id, 'llm' => $llm, 'part_title' => $partTitle]);
				return response()->json(['success' => false, 'message' => "Failed to generate {$difficulty} questions: " . $errorMsg], 500);
			}


			// Validate the generated list of questions
			if (!self::isValidQuestionListResponse($questionResult['questions'])) {
				$errorMsg = "LLM returned an invalid {$difficulty} question structure for lesson part '{$partTitle}'.";
				Log::error($errorMsg, ['lesson' => $lesson->id, 'llm' => $llm, 'part_title' => $partTitle, 'response' => $questionResult]);
				return response()->json(['success' => false, 'message' => $errorMsg . ' Please try again.'], 500);
			}

			// Save the new questions
			$createdQuestionsData = [];
			// Determine the next order number
			$maxOrder = Question::where('lesson_id', $lesson->id)->max('order') ?? -1;
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
						'lesson_id' => $lesson->id,
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
				Log::info("Created " . count($createdQuestionsData) . " new '{$difficulty}' question records for Lesson ID: {$lesson->id}, Part: {$partIndex}");

				// Return the data for the newly created questions so the frontend can render them
				return response()->json([
					'success' => true,
					'message' => "Successfully generated 5 {$difficulty} questions!",
					'questions' => $createdQuestionsData // Send back data for JS rendering
				]);

			} catch (Exception $e) {
				Log::error("Database error saving new questions for Lesson ID {$lesson->id}: " . $e->getMessage());
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
			$lessonId = $question->lesson_id;
			Log::info("AJAX request to delete Question ID: {$questionId} from Lesson ID: {$lessonId}");

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


	}
