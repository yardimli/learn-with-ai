<?php

	namespace App\Http\Controllers;

	use App\Models\Question;
	use App\Models\Lesson;
	use App\Models\UserAnswer;
	use App\Models\UserAnswerArchive;
	use Illuminate\Http\Request;
	use Illuminate\Support\Carbon;
	use Illuminate\Support\Facades\DB;
	use Illuminate\Support\Facades\Log;

	class ProgressController extends Controller
	{
		/**
		 * Calculate the score based on first correct attempts without errors.
		 *
		 * @param int $lessonId
		 * @param bool $useArchive If true, calculates score from UserAnswerArchive table.
		 * @param mixed $archiveBatchId Optional: Filter archive by a specific batch ID.
		 * @return array ['score', 'total_questions']
		 */
		private static function calculateFirstAttemptScore(int $lessonId, bool $useArchive = false, ?string $archiveBatchId = null): array
		{
			// Get all questions relevant FOR THIS LESSON at the time of calculation
			// It's better to count questions from the Questions table directly for consistency
			$relevantQuestionIds = Question::where('lesson_id', $lessonId)->pluck('id');
			$totalQuestions = $relevantQuestionIds->count();

			if ($totalQuestions === 0) {
				return ['score' => 0, 'total_questions' => 0];
			}

			$score = 0;
			$answerModel = $useArchive ? UserAnswerArchive::class : UserAnswer::class;

			foreach ($relevantQuestionIds as $questionId) {
				$queryBase = $answerModel::where('lesson_id', $lessonId)
					->where('question_id', $questionId)
					->where('attempt_number', 1);

				// Apply batch ID filter if applicable
				if ($useArchive && $archiveBatchId) {
					$queryBase->where('archive_batch_id', $archiveBatchId);
				}

				// Clone the query before adding specific conditions
				$correctQuery = clone $queryBase;
				$incorrectQuery = clone $queryBase;

				$wasCorrectFirst = $correctQuery->where('was_correct', true)->exists();
				$hadIncorrectFirst = $incorrectQuery->where('was_correct', false)->exists();


				if ($wasCorrectFirst && !$hadIncorrectFirst) {
					$score++;
				}
			}

			return ['score' => $score, 'total_questions' => $totalQuestions];
		}

		/**
		 * Display the progress page for a lesson.
		 *
		 * @param Lesson $lesson
		 * @return \Illuminate\View\View
		 */
		public function show(Lesson $lesson)
		{
			Log::info("Showing progress page for Lesson Session: {$lesson->session_id} (ID: {$lesson->id})");

			// Calculate Current Progress Score
			$currentProgress = self::calculateFirstAttemptScore($lesson->id, false);

			// --- Calculate Archived Progress Scores (per batch) ---
			$archivedProgressSets = [];

			// Get distinct batch IDs and their corresponding archive date (using max is safe as they are archived together)
			$archiveBatches = UserAnswerArchive::where('lesson_id', $lesson->id)
				->select('archive_batch_id', DB::raw('MAX(archived_at) as archive_date'))
				->whereNotNull('archive_batch_id') // Only consider batches with an ID
				->groupBy('archive_batch_id')
				->orderBy('archive_date', 'desc') // Show most recent first
				->get();

			if ($archiveBatches->isNotEmpty()) {
				foreach ($archiveBatches as $batch) {
					if (empty($batch->archive_batch_id)) continue; // Skip if somehow null

					$batchScoreData = self::calculateFirstAttemptScore($lesson->id, true, $batch->archive_batch_id);

					// Only add if there were questions associated with this score calculation
					// (Handles cases where questions might have been deleted later)
					if ($batchScoreData['total_questions'] > 0) {
						$archivedProgressSets[] = [
							'batch_id' => $batch->archive_batch_id,
							'date' => Carbon::parse($batch->archive_date), // Parse to Carbon instance
							'score' => $batchScoreData['score'],
							'total_questions' => $batchScoreData['total_questions'],
							'percentage' => round(($batchScoreData['score'] / $batchScoreData['total_questions']) * 100),
						];
					} else {
						Log::warning("Skipping archive batch {$batch->archive_batch_id} for lesson {$lesson->id} as total_questions was 0 during score calculation.");
					}
				}
			}

			return view('progress_reports', compact('lesson', 'currentProgress', 'archivedProgressSets'));
		}
	}
