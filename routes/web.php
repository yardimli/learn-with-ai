<?php

	use App\Http\Controllers\CategoryController;
	use App\Http\Controllers\Controller;
	use App\Http\Controllers\EditController;
	use App\Http\Controllers\FreePikController;
	use App\Http\Controllers\GenerateAssetController;
	use App\Http\Controllers\ProgressController;
	use Illuminate\Support\Facades\Route;
	use App\Http\Controllers\CreateLessonController;
	use App\Http\Controllers\LessonController;

// --- Lesson Creation & Setup ---
	Route::get('/', [CreateLessonController::class, 'index'])->name('home');
	Route::post('/lesson/generate-structure', [CreateLessonController::class, 'generatePlanPreview'])->name('lesson.generate.structure');
	Route::post('/lesson/save-structure', [CreateLessonController::class, 'createLesson'])->name('lesson.save.structure');
	Route::post('/lesson/{lesson}/archive', [CreateLessonController::class, 'archiveProgress'])->name('lesson.archive');
	Route::get('/progress/{lesson:session_id}', [ProgressController::class, 'show'])->name('progress.show');
	Route::get('/lessons', [CreateLessonController::class, 'listLessons'])->name('lessons.list');
	//delete lesson
	Route::delete('/lesson/{lesson:session_id}', [CreateLessonController::class, 'deleteLesson'])->name('lesson.delete');


	Route::get('/api/llms-list', function () {
		return response()->json(['llms' => App\Helpers\MyHelper::checkLLMsJson()]);
	})->name('api.llms.list');


// --- Lesson Editing & Asset Management ---
	Route::get('/lesson/{lesson}/edit', [EditController::class, 'edit'])->name('lesson.edit');
	Route::post('/lesson/{lesson}/update-settings', [EditController::class, 'updateSettingsAjax'])->name('lesson.update.settings');
	Route::post('/lesson/{lesson}/part/{partIndex}/update-text', [EditController::class, 'updatePartTextAjax'])
		->where('partIndex', '[0-9]+')
		->name('lesson.part.update.text');

	Route::resource('categories', CategoryController::class)->except(['show']);


// Generate Question Batch
	Route::post('/lesson/{lesson}/part/{partIndex}/generate-questions/{difficulty}', [EditController::class, 'generateQuestionBatchAjax'])
		->where(['partIndex' => '[0-9]+', 'difficulty' => 'easy|medium|hard'])
		->name('question.generate.batch');

// Question Text/Answer Update
	Route::post('/question/{question}/update-texts', [EditController::class, 'updateQuestionTextsAjax'])
		->name('question.update.texts');

// Delete Question
	Route::delete('/question/{question}', [EditController::class, 'deleteQuestionAjax'])
		->name('question.delete');

// Generate Assets
	Route::post('/lesson/{lesson}/part/{partIndex}/generate-video', [GenerateAssetController::class, 'generatePartVideoAjax'])
		->where('partIndex', '[0-9]+')
		->name('lesson.part.generate.video');
	Route::post('/question/{question}/generate-audio/question', [GenerateAssetController::class, 'generateQuestionAudioAjax'])->name('question.generate.audio.question');
	Route::post('/question/{question}/generate-audio/answers', [GenerateAssetController::class, 'generateAnswerAudioAjax'])->name('question.generate.audio.answers');
	Route::post('/question/{question}/generate-image', [GenerateAssetController::class, 'generateQuestionImageAjax'])->name('question.generate.image'); // For LLM generation/regeneration

// --- Question Image Upload & Freepik ---
	Route::post('/question/{question}/upload-image', [GenerateAssetController::class, 'uploadQuestionImageAjax'])->name('question.image.upload');
	Route::post('/question/{question}/search-freepik', [FreePikController::class, 'searchFreepikAjax'])->name('question.image.search_freepik');
	Route::post('/question/{question}/select-freepik', [FreePikController::class, 'selectFreepikImageAjax'])->name('question.image.select_freepik');

// --- Lesson Display / Taking Question ---
	Route::get('/lesson/{lesson:session_id}/question', [LessonController::class, 'showQuestionInterface'])
		->name('question.interface');
	Route::post('/lesson/{lesson:session_id}/part-questions', [LessonController::class, 'getPartQuestionsAjax'])
		->name('question.part_questions');
	Route::post('/question/{question}/submit', [LessonController::class, 'submitAnswer'])
		->name('question.submit_answer');
