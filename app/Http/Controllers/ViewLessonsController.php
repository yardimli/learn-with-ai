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

	class ViewLessonsController extends Controller
	{
		public function listLessons()
		{
			$lessons = Lesson::with(['subCategory.mainCategory']) // Eager load relationships
			->withCount('questions')
				->orderBy('created_at', 'desc')
				->get();

			// Calculate current progress for each lesson
			foreach ($lessons as $lesson) {
				$lesson->currentProgress = ProgressController::calculateFirstAttemptScore($lesson->id);
			}

			// Group lessons: Prioritize selected_main_category_id, then subCategory's mainCategory, then null
			$groupedLessons = $lessons->groupBy(function ($lesson) {
				// Priority 1: Use selected_main_category_id if it's set
				if ($lesson->selected_main_category_id) {
					return $lesson->selected_main_category_id;
				}
				// Priority 2: Use the main category ID from the sub-category if it exists
				// Make sure subCategory and its mainCategory are loaded and not null
				if ($lesson->subCategory && $lesson->subCategory->mainCategory) {
					return $lesson->subCategory->mainCategory->id;
				}
				// Default: Uncategorized (null key)
				return null;
			});


			$mainCategoryNames = MainCategory::orderBy('name')->pluck('name', 'id')->all();
			$orderedMainCategoryIds = array_keys($mainCategoryNames);

			// Get LLMs for the generation modal
			$llms = LlmHelper::checkLLMsJson();

			Log::info("Fetched and grouped lessons for listing. Main Categories found: " . count($mainCategoryNames));

			return view('lessons_list', compact('groupedLessons', 'mainCategoryNames', 'orderedMainCategoryIds', 'llms'));
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

		public function deleteLesson(Lesson $lesson)
		{
			Log::info("Delete request received for Lesson Session: {$lesson->session_id} (ID: {$lesson->id})");

			try {
				// Delete all questions associated with the lesson
				$lesson->questions()->delete();
				Log::info("All questions associated with Lesson ID: {$lesson->id} deleted successfully.");

				// Delete the lesson
				$lesson->delete();
				Log::info("Lesson ID: {$lesson->id} deleted successfully.");

				return response()->json(['success' => true, 'message' => 'Lesson deleted successfully.'], 200);
			} catch (\Exception $e) {
				Log::error("Error deleting lesson ID: {$lesson->id} - " . $e->getMessage());
				return response()->json(['success' => false, 'message' => 'Failed to delete lesson.'], 500);
			}
		}

	}
