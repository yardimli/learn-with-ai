<?php namespace App\Http\Controllers;

use App\Helpers\LlmHelper;
use App\Helpers\AudioImageHelper;
use App\Models\GeneratedImage;
use App\Models\Question;
use App\Models\Lesson;
use App\Models\UserAnswer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class LessonController extends Controller
{
	public function showQuestionInterface(Lesson $lesson)
	{
		$this->authorize('takeLesson', $lesson);
		Log::info("Loading question interface for Lesson ID: {$lesson->id})");
		$state = $this->calculateCurrentState($lesson->id);
		$lessonIntro = $this->getLessonIntro($lesson);
		$question = null;
		Log::info("Initial State for Lesson ID {$lesson->id}: ", $state);

		return view('lesson_interface', compact('lesson', 'question', 'state', 'lessonIntro'));
	}

	/**
	 * Get the text content for the lesson.
	 */
	private function getLessonText(Lesson $lesson): ?string
	{
		$lessonContent = is_array($lesson->lesson_content) ? $lesson->lesson_content : json_decode($lesson->lesson_content, true);
		return $lessonContent['text'] ?? null;
	}

	/**
	 * Get intro data for the single lesson content.
	 */
	private function getLessonIntro(Lesson $lesson): array
	{
		$lessonContent = is_array($lesson->lesson_content) ? $lesson->lesson_content : json_decode($lesson->lesson_content, true);
		if (!is_array($lessonContent)) {
			// Provide a default structure if lesson_content is not as expected
			$lessonContent = ['title' => $lesson->title ?? 'Lesson Content', 'text' => '', 'sentences' => []];
		}

		$allSentenceImageIds = [];
		if (isset($lessonContent['sentences']) && is_array($lessonContent['sentences'])) {
			foreach ($lessonContent['sentences'] as $sentence) {
				if (!empty($sentence['generated_image_id'])) {
					$allSentenceImageIds[] = $sentence['generated_image_id'];
				}
			}
		}

		$imagesById = GeneratedImage::whereIn('id', array_unique($allSentenceImageIds))->get()->keyBy('id');

		$processedSentences = [];
		$sentencesData = $lessonContent['sentences'] ?? [];
		foreach ($sentencesData as $sentence) {
			// Ensure sentence has text and audio_url to be considered valid for intro playback
			if (!empty($sentence['text']) && !empty($sentence['audio_url'])) {
				$imageId = $sentence['generated_image_id'] ?? null;
				$imageUrl = null;
				if ($imageId && isset($imagesById[$imageId])) {
					$imageUrl = $imagesById[$imageId]->medium_url;
				}
				$sentence['image_url'] = $imageUrl;
				$processedSentences[] = $sentence;
			}
		}

		$videoUrl = $lesson->video_url; // Accessor from Lesson model

		return [
			'title' => $lesson->title ?? "Lesson Content",
			'full_text' => $this->getLessonText($lesson),
			'sentences' => $processedSentences,
			'has_audio' => !empty($processedSentences),
			'has_video' => !empty($videoUrl),
			'video_url' => $videoUrl,
		];
	}


	private function calculateCurrentState(int $lessonId): array
	{
		$lesson = Lesson::find($lessonId);
		if (!$lesson) {
			Log::error("Lesson not found for ID: {$lessonId} in calculateCurrentState");
			return [
				'status' => 'error',
				'overallTotalQuestions' => 0,
				'overallCorrectCount' => 0,
				'currentTotalQuestions' => 0, // Renamed to currentLessonTotalQuestions
				'currentCorrectCount' => 0,   // Renamed to currentLessonCorrectCount
			];
		}

		$totalQuestionsInLesson = Question::where('lesson_id', $lessonId)
			->count();

		$correctlyAnsweredInLesson = UserAnswer::where('lesson_id', $lessonId)
			->select('question_id')
			->groupBy('attempt_number', 'question_id')
			->havingRaw('COUNT(*) = 1') // Only one attempt total
			->havingRaw('SUM(CASE WHEN was_correct = 1 THEN 1 ELSE 0 END) = 1') // That attempt was correct
			->count();

		$status = 'inprogress';
		if ($totalQuestionsInLesson === 0) {
			$status = 'empty'; // Or 'completed' if no questions means complete
		} elseif ($correctlyAnsweredInLesson >= $totalQuestionsInLesson) {
			$status = 'completed';
		}

		Log::info("Calculated State for Lesson {$lessonId}: ", [
			'status' => $status,
			'overallTotalQuestions' => $totalQuestionsInLesson, // Same as currentLessonTotalQuestions
			'overallCorrectCount' => $correctlyAnsweredInLesson, // Same as currentLessonCorrectCount
			'currentTotalQuestions' => $totalQuestionsInLesson,
			'currentCorrectCount' => $correctlyAnsweredInLesson,
		]);

		return [
			'status' => $status,
			'overallTotalQuestions' => $totalQuestionsInLesson,
			'overallCorrectCount' => $correctlyAnsweredInLesson,
			'currentTotalQuestions' => $totalQuestionsInLesson,
			'currentCorrectCount' => $correctlyAnsweredInLesson,
		];
	}

	public function getLessonQuestionsAjax(Request $request, Lesson $lesson)
	{
		Log::info("AJAX request for ALL questions for Lesson ID: {$lesson->id}");
		try {
			$questions = Question::where('lesson_id', $lesson->id)
				->with('generatedImage')
				->get();

			if ($questions->isEmpty()) {
				Log::warning("No questions found for Lesson {$lesson->id}.");
				return response()->json([
					'success' => true,
					'questions' => [],
					'message' => 'No questions found for this lesson.'
				]);
			}

			$processedQuestions = $questions->map(function ($question) use ($lesson) {
				$lastAttempt = UserAnswer::where('question_id', $question->id)
					->where('lesson_id', $lesson->id)
					->orderBy('attempt_number', 'desc')
					->first();
				$nextAttemptNumber = $lastAttempt ? $lastAttempt->attempt_number + 1 : 1;
				$wasCorrectLastAttempt = false;
				$hadNoWrongAnswers = true;
				if ($lastAttempt) {
					$wasCorrectLastAttempt = $lastAttempt->was_correct;
					$wrongAnswersInLastAttempt = UserAnswer::where('question_id', $question->id)
						->where('lesson_id', $lesson->id)
						->where('attempt_number', $lastAttempt->attempt_number)
						->where('was_correct', false)
						->exists();
					$hadNoWrongAnswers = !$wrongAnswersInLastAttempt;
				}
				$shouldSkip = $wasCorrectLastAttempt && $hadNoWrongAnswers;
				$processedAnswers = $question->answers;
				if ($processedAnswers && is_array($processedAnswers)) {
					foreach ($processedAnswers as $index => &$answer) {
						$answer['index'] = $index;
						$answer['answer_audio_url'] = $question->getAnswerAudioUrl($index);
						$answer['feedback_audio_url'] = $question->getFeedbackAudioUrl($index);
					}
					unset($answer);
				}
				shuffle($processedAnswers);
				return [
					'id' => $question->id,
					'question_text' => $question->question_text,
					'question_audio_url' => $question->question_audio_url,
					'image_url' => $question->generatedImage?->mediumUrl,
					'answers' => $processedAnswers,
					'difficulty_level' => $question->difficulty_level,
					'next_attempt_number' => $nextAttemptNumber,
					'should_skip' => $shouldSkip,
				];
			});
			Log::info("Found {$processedQuestions->count()} questions for Lesson {$lesson->id}.");
			return response()->json([
				'success' => true,
				'questions' => $processedQuestions,
			]);
		} catch (\Exception $e) {
			Log::error("Error fetching lesson questions for Lesson ID {$lesson->id}: " . $e->getMessage());
			return response()->json([
				'success' => false,
				'message' => 'An error occurred while loading questions. Please try again.'
			], 500);
		}
	}


	/**
	 * Handles submitting a user's answer to a question.
	 * Modified to return state change info.
	 *
	 * @param Request $request
	 * @param Question $question Route model binding
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function submitAnswer(Request $request, Question $question)
	{
		$this->authorize('takeLesson', $question->lesson);
		$validator = Validator::make($request->all(), [
			'selected_index' => 'required|integer|min:0|max:3',
			'attempt_number' => 'required|integer|min:1',
		]);

		if ($validator->fails()) {
			return response()->json(['success' => false, 'message' => 'Invalid answer selection.', 'errors' => $validator->errors()], 422);
		}

		$lesson_id = $question->lesson_id;
		$selectedIndex = $request->input('selected_index');
		$attemptNumber = $request->input('attempt_number');
		Log::info("Submitting answer for Question ID: {$question->id}, Index: {$selectedIndex}, Lesson ID: {$lesson_id}, Attempt: {$attemptNumber}");

		$answers = $question->answers;
		if (!isset($answers[$selectedIndex])) {
			Log::error("Invalid answer index {$selectedIndex} submitted for Question ID {$question->id}. Answers:", $answers);
			return response()->json(['success' => false, 'message' => 'Invalid answer index provided.'], 400);
		}

		$processedAnswers = $question->answers;
		$correctIndex = -1;
		if ($processedAnswers && is_array($processedAnswers)) {
			foreach ($processedAnswers as $index => &$answer) {
				$answer['answer_audio_url'] = $question->getAnswerAudioUrl($index);
				$answer['feedback_audio_url'] = $question->getFeedbackAudioUrl($index);
				if ($answer['is_correct'] === true) {
					$correctIndex = $index;
				}
			}
			unset($answer);
		} else {
			Log::error("Answers data is missing or invalid when processing submit for Question ID: {$question->id}");
			return response()->json(['success' => false, 'message' => 'Error processing question answers.'], 500);
		}

		$selectedAnswer = $processedAnswers[$selectedIndex];
		$wasCorrect = $selectedAnswer['is_correct'] === true;
		$feedbackText = $selectedAnswer['feedback'];
		$feedbackAudioUrl = $selectedAnswer['feedback_audio_url'] ?? null;

		UserAnswer::create([
			'question_id' => $question->id,
			'lesson_id' => $lesson_id,
			'selected_answer_index' => $selectedIndex,
			'was_correct' => $wasCorrect,
			'attempt_number' => $attemptNumber,
		]);
		Log::info("User answer saved for Question ID {$question->id}. Attempt: {$attemptNumber}. Correct: " . ($wasCorrect ? 'Yes' : 'No'));

		$newState = $this->calculateCurrentState($lesson_id);
		return response()->json([
			'success' => true,
			'was_correct' => $wasCorrect,
			'correct_index' => $correctIndex,
			'feedback_text' => $feedbackText,
			'feedback_audio_url' => $feedbackAudioUrl,
			'newState' => $newState,
			'lesson_completed' => $newState['status'] === 'completed',
		]);
	}

	private function isLessonCompleted(int $lessonId): bool
	{
		$totalQuestions = Question::where('lesson_id', $lessonId)
			->count();

		if ($totalQuestions === 0) {
			return true; // No questions means lesson is "complete"
		}

		$correctAnsweredCount = UserAnswer::where('lesson_id', $lessonId)
			->select('question_id')
			->groupBy('attempt_number', 'question_id')
			->havingRaw('COUNT(*) = 1')
			->havingRaw('SUM(CASE WHEN was_correct = 1 THEN 1 ELSE 0 END) = 1')
			->count();
		return $correctAnsweredCount >= $totalQuestions;
	}
}
