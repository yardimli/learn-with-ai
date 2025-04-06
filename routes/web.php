<?php

	use App\Http\Controllers\Controller;
	use App\Http\Controllers\FreePikController;
	use Illuminate\Support\Facades\Route;
	use App\Http\Controllers\SubjectController;
	use App\Http\Controllers\QuizController;

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

// Generate Quiz Batch
	Route::post('/lesson/{subject}/part/{partIndex}/generate-quizzes/{difficulty}', [SubjectController::class, 'generateQuizBatchAjax'])
		->where(['partIndex' => '[0-9]+', 'difficulty' => 'easy|medium|hard'])
		->name('quiz.generate.batch');

// Delete Quiz
	Route::delete('/quiz/{quiz}', [SubjectController::class, 'deleteQuizAjax'])
		->name('quiz.delete');

// Generate Individual Quiz Assets
	Route::post('/quiz/{quiz}/generate-audio/question', [SubjectController::class, 'generateQuestionAudioAjax'])->name('quiz.generate.audio.question');
	Route::post('/quiz/{quiz}/generate-audio/answers', [SubjectController::class, 'generateAnswerAudioAjax'])->name('quiz.generate.audio.answers');
	Route::post('/quiz/{quiz}/generate-image', [SubjectController::class, 'generateQuizImageAjax'])->name('quiz.generate.image'); // For LLM generation/regeneration

// --- Quiz Image Upload & Freepik ---
	Route::post('/quiz/{quiz}/upload-image', [SubjectController::class, 'uploadQuizImageAjax'])->name('quiz.image.upload');
	Route::post('/quiz/{quiz}/search-freepik', [FreePikController::class, 'searchFreepikAjax'])->name('quiz.image.search_freepik');
	Route::post('/quiz/{quiz}/select-freepik', [FreePikController::class, 'selectFreepikImageAjax'])->name('quiz.image.select_freepik');

// --- Lesson Display / Taking Quiz ---
	Route::get('/lesson/{subject:session_id}/quiz', [QuizController::class, 'showQuizInterface'])
		->name('quiz.interface');
	Route::post('/lesson/{subject:session_id}/part-questions', [QuizController::class, 'getPartQuestionsAjax'])
		->name('quiz.part_questions');
	Route::post('/quiz/{quiz}/submit', [QuizController::class, 'submitAnswer'])
		->name('quiz.submit_answer');
