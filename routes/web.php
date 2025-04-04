<?php

	use Illuminate\Support\Facades\Route;
	use App\Http\Controllers\SubjectController;
	use App\Http\Controllers\QuizController;

// Route for the initial subject input page (Home)
	Route::get('/', [SubjectController::class, 'index'])->name('home');

	Route::post('/plan-preview', [SubjectController::class, 'generatePlanPreview'])->name('plan.preview');
	Route::post('/create-lesson', [SubjectController::class, 'createLesson'])->name('lesson.create');

	Route::get('/lesson/{subject}/edit', [SubjectController::class, 'edit'])->name('lesson.edit');
	Route::post('/lesson/{subject}/part/{partIndex}/generate-video', [SubjectController::class, 'generatePartVideoAjax'])->name('lesson.part.generate.video');
	Route::post('/quiz/{quiz}/generate-audio/question', [SubjectController::class, 'generateQuestionAudioAjax'])->name('quiz.generate.audio.question');
	Route::post('/quiz/{quiz}/generate-audio/answers', [SubjectController::class, 'generateAnswerAudioAjax'])->name('quiz.generate.audio.answers');
	Route::post('/quiz/{quiz}/generate-image', [SubjectController::class, 'generateQuizImageAjax'])->name('quiz.generate.image');


// --- Lesson Display ---
	// Main route to load the quiz interface for a lesson session
	Route::get('/lesson/{subject:session_id}/quiz', [QuizController::class, 'showQuizInterface'])
		->name('quiz.interface'); // Use session_id for route model binding

// AJAX endpoint to get the next quiz question based on progress
	Route::post('/lesson/{subject:session_id}/next-question', [QuizController::class, 'getNextQuestionAjax'])
		->name('quiz.next_question'); // Use session_id

// AJAX endpoint to submit an answer (Keep Existing - uses Quiz ID)
	Route::post('/quiz/{quiz}/submit', [QuizController::class, 'submitAnswer'])
		->name('quiz.submit_answer');

