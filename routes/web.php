<?php

	use App\Http\Controllers\CategoryManagementController;
	use App\Http\Controllers\Controller;
	use App\Http\Controllers\EditLessonController;
	use App\Http\Controllers\FreePikController;
	use App\Http\Controllers\GenerateAssetController;
	use App\Http\Controllers\ProgressController;
	use App\Http\Controllers\UserController;
	use App\Http\Controllers\ViewLessonsController;
	use App\Http\Controllers\WeeklyPlanController;
	use Illuminate\Support\Facades\Route;
	use App\Http\Controllers\CreateLessonController;
	use App\Http\Controllers\LessonController;
	use Illuminate\Support\Facades\Auth;

	Auth::routes();

	Route::middleware(['auth'])->group(function () {
// --- Lesson Creation & Setup ---
		Route::get('/', [CreateLessonController::class, 'index'])->name('create-lesson');
		Route::post('/lesson/save-basic', [CreateLessonController::class, 'createBasicLesson'])->name('lesson.save.basic');
		Route::get('/lesson/import', [CreateLessonController::class, 'showImportForm'])->name('lesson.import.form');
		Route::post('/lesson/import/process', [CreateLessonController::class, 'processImport'])->name('lesson.import.process');

		Route::get('/progress/{lesson:id}', [ProgressController::class, 'show'])->name('progress.show');
		Route::get('/lessons', [ViewLessonsController::class, 'listLessons'])->name('lessons.list');
		Route::get('/weekly-plan', [WeeklyPlanController::class, 'index'])->name('weekly.plan.configure');
		Route::post('/weekly-plan/load', [WeeklyPlanController::class, 'loadPlan'])->name('weekly.plan.load');

		Route::delete('/lesson/{lesson:id}', [ViewLessonsController::class, 'deleteLesson'])->name('lesson.delete');
		Route::post('/lesson/{lesson}/archive', [ViewLessonsController::class, 'archiveProgress'])->name('lesson.archive');

		Route::post('/lesson/{lesson}/generate-preview', [CreateLessonController::class, 'generatePlanPreview'])->name('lesson.generate.preview');
		Route::post('/lesson/{lesson}/apply-plan', [CreateLessonController::class, 'applyGeneratedPlan'])->name('lesson.apply.plan');

// --- Lesson Editing & Asset Management ---
		Route::get('/lesson/{lesson}/edit', [EditLessonController::class, 'edit'])->name('lesson.edit');
		Route::post('/lesson/{lesson}/update-settings', [EditLessonController::class, 'updateSettingsAjax'])->name('lesson.update.settings');

		Route::post('/lesson/{lesson}/update-content', [EditLessonController::class, 'updateLessonContentAjax'])
			->name('lesson.content.update.text'); // Kept similar name for now, JS might use it

		Route::post('/lesson/{lesson}/add-youtube', [EditLessonController::class, 'addYoutubeVideoAjax'])->name('lesson.add.youtube');

// --- Category Management ---
		Route::prefix('manage')->name('category_management.')->group(function () {
			// Main Categories
			Route::get('main-category_management', [CategoryManagementController::class, 'mainIndex'])->name('main.index');
			Route::get('main-category_management/create', [CategoryManagementController::class, 'mainCreate'])->name('main.create');
			Route::post('main-category_management', [CategoryManagementController::class, 'mainStore'])->name('main.store');
			Route::get('main-category_management/{mainCategory}/edit', [CategoryManagementController::class, 'mainEdit'])->name('main.edit');
			Route::put('main-category_management/{mainCategory}', [CategoryManagementController::class, 'mainUpdate'])->name('main.update');
			Route::delete('main-category_management/{mainCategory}', [CategoryManagementController::class, 'mainDestroy'])->name('main.destroy');
			// Sub Categories
			Route::get('sub-category_management', [CategoryManagementController::class, 'subIndex'])->name('sub.index');
			Route::get('main-category_management/{mainCategory}/sub-category_management/create', [CategoryManagementController::class, 'subCreate'])->name('sub.create');
			Route::post('sub-category_management', [CategoryManagementController::class, 'subStore'])->name('sub.store');
			Route::get('sub-category_management/{subCategory}/edit', [CategoryManagementController::class, 'subEdit'])->name('sub.edit');
			Route::put('sub-category_management/{subCategory}', [CategoryManagementController::class, 'subUpdate'])->name('sub.update');
			Route::delete('sub-category_management/{subCategory}', [CategoryManagementController::class, 'subDestroy'])->name('sub.destroy');
		});
// --- End Category Management ---

// MODIFIED: Generate Question Batch (no partIndex)
		Route::post('/lesson/{lesson}/generate-questions/{difficulty}', [EditLessonController::class, 'generateQuestionBatchAjax'])
			->where(['difficulty' => 'easy|medium|hard'])
			->name('question.generate.batch');

// Question Text/Answer Update
		Route::post('/question/{question}/update-texts', [EditLessonController::class, 'updateQuestionTextsAjax'])
			->name('question.update.texts');

// Delete Question
		Route::delete('/question/{question}', [EditLessonController::class, 'deleteQuestionAjax'])
			->name('question.delete');

// Generate Assets
// MODIFIED: Route for lesson content assets (was part audio)
		Route::post('/lesson/{lesson}/generate-content-assets', [GenerateAssetController::class, 'generateLessonContentAssetsAjax'])
			->name('lesson.content.generate.assets'); // New name

// MODIFIED: Sentence asset routes (no partIndex)
		Route::post('/lesson/{lesson}/sentence/{sentenceIndex}/generate-image', [GenerateAssetController::class, 'generateSentenceImageAjax'])
			->where(['sentenceIndex' => '[0-9]+'])
			->name('sentence.generate.image');
		Route::post('/lesson/{lesson}/sentence/{sentenceIndex}/upload-image', [GenerateAssetController::class, 'uploadSentenceImageAjax'])
			->where(['sentenceIndex' => '[0-9]+'])
			->name('sentence.image.upload');
		Route::post('/lesson/{lesson}/sentence/{sentenceIndex}/search-freepik', [FreePikController::class, 'searchFreepikSentenceAjax'])
			->where(['sentenceIndex' => '[0-9]+'])
			->name('sentence.image.search_freepik');
		Route::post('/lesson/{lesson}/sentence/{sentenceIndex}/select-freepik', [FreePikController::class, 'selectFreepikSentenceImageAjax'])
			->where(['sentenceIndex' => '[0-9]+'])
			->name('sentence.image.select_freepik');

		Route::post('/question/{question}/generate-audio/question', [GenerateAssetController::class, 'generateQuestionAudioAjax'])->name('question.generate.audio.question');
		Route::post('/question/{question}/generate-audio/answers', [GenerateAssetController::class, 'generateAnswerAudioAjax'])->name('question.generate.audio.answers');
		Route::post('/question/{question}/generate-image', [GenerateAssetController::class, 'generateQuestionImageAjax'])->name('question.generate.image');

// --- Question Image Upload & Freepik ---
		Route::post('/question/{question}/upload-image', [GenerateAssetController::class, 'uploadQuestionImageAjax'])->name('question.image.upload');
		Route::post('/question/{question}/search-freepik', [FreePikController::class, 'searchFreepikAjax'])->name('question.image.search_freepik');
		Route::post('/question/{question}/select-freepik', [FreePikController::class, 'selectFreepikImageAjax'])->name('question.image.select_freepik');

// --- Lesson Display / Taking Question ---
		Route::get('/lesson/{lesson:id}/question', [LessonController::class, 'showQuestionInterface'])
			->name('question.interface');
// MODIFIED: Route for lesson questions (was part questions)
		Route::post('/lesson/{lesson:id}/questions', [LessonController::class, 'getLessonQuestionsAjax'])
			->name('lesson.questions'); // New name

		Route::post('/question/{question}/submit', [LessonController::class, 'submitAnswer'])
			->name('question.submit_answer');

		Route::get('/user/llm-instructions', [UserController::class, 'getLlmInstructions'])->name('user.llm.instructions');
	});

	Route::get('/api/llms-list', function () {
		return response()->json(['llms' => App\Helpers\LlmHelper::checkLLMsJson()]);
	})->name('api.llms.list');

	Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
