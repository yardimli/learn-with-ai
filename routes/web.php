<?php

	use App\Http\Controllers\Controller;
	use App\Http\Controllers\FreePikController;
	use Illuminate\Support\Facades\Route;
	use App\Http\Controllers\SubjectController;
	use App\Http\Controllers\QuestionController;

// --- Lesson Creation & Setup ---
	Route::get('/', [SubjectController::class, 'index'])->name('home');
	Route::post('/lesson/generate-structure', [SubjectController::class, 'generatePlanPreview'])->name('lesson.generate.structure');
	Route::post('/lesson/save-structure', [SubjectController::class, 'createLesson'])->name('lesson.save.structure');

// --- Lesson Editing & Asset Management ---
	Route::get('/lesson/{subject}/edit', [SubjectController::class, 'edit'])->name('lesson.edit');

// Generate Part Video
	Route::post('/lesson/{subject}/part/{partIndex}/generate-video', [SubjectController::class, 'generatePartVideoAjax'])
		->where('partIndex', '[0-9]+')
		->name('lesson.part.generate.video');

// Generate Question Batch
	Route::post('/lesson/{subject}/part/{partIndex}/generate-questions/{difficulty}', [SubjectController::class, 'generateQuestionBatchAjax'])
		->where(['partIndex' => '[0-9]+', 'difficulty' => 'easy|medium|hard'])
		->name('question.generate.batch');

// Delete Question
	Route::delete('/question/{question}', [SubjectController::class, 'deleteQuestionAjax'])
		->name('question.delete');

// Generate Individual Question Assets
	Route::post('/question/{question}/generate-audio/question', [SubjectController::class, 'generateQuestionAudioAjax'])->name('question.generate.audio.question');
	Route::post('/question/{question}/generate-audio/answers', [SubjectController::class, 'generateAnswerAudioAjax'])->name('question.generate.audio.answers');
	Route::post('/question/{question}/generate-image', [SubjectController::class, 'generateQuestionImageAjax'])->name('question.generate.image'); // For LLM generation/regeneration

// --- Question Image Upload & Freepik ---
	Route::post('/question/{question}/upload-image', [SubjectController::class, 'uploadQuestionImageAjax'])->name('question.image.upload');
	Route::post('/question/{question}/search-freepik', [FreePikController::class, 'searchFreepikAjax'])->name('question.image.search_freepik');
	Route::post('/question/{question}/select-freepik', [FreePikController::class, 'selectFreepikImageAjax'])->name('question.image.select_freepik');

// --- Lesson Display / Taking Question ---
	Route::get('/lesson/{subject:session_id}/question', [QuestionController::class, 'showQuestionInterface'])
		->name('question.interface');
	Route::post('/lesson/{subject:session_id}/part-questions', [QuestionController::class, 'getPartQuestionsAjax'])
		->name('question.part_questions');
	Route::post('/question/{question}/submit', [QuestionController::class, 'submitAnswer'])
		->name('question.submit_answer');
