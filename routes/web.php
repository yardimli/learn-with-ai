<?php

	use Illuminate\Support\Facades\Route;
	use App\Http\Controllers\SubjectController; // New
	use App\Http\Controllers\ContentController; // Revised
	use App\Http\Controllers\QuizController;    // New

// Route for the initial subject input page (Home)
	Route::get('/', [SubjectController::class, 'index'])->name('home');

// Route to handle the subject submission and start content generation
	Route::post('/start-learning', [SubjectController::class, 'startLearning'])->name('content.start_learning');

// --- Content Display ---
// Route to display the generated content (title, text, image/video)
	Route::get('/content/{subject}', [ContentController::class, 'show'])->name('content.show');
// Route to generate the *first* quiz and redirect to the quiz page
	Route::post('/content/{subject}/generate-quiz', [ContentController::class, 'generateFirstQuiz'])->name('quiz.start');


// --- Quiz Display and Interaction ---
// Route to display the quiz interface for a subject
	Route::get('/quiz/{subject}', [QuizController::class, 'show'])->name('quiz.show');

// Route to submit an answer for a specific quiz (AJAX)
	Route::post('/quiz/{quiz}/submit', [QuizController::class, 'submitAnswer'])->name('quiz.submit_answer');

// Route to generate the *next* quiz question for a subject (AJAX)
	Route::post('/quiz/{subject}/next', [QuizController::class, 'generateNextQuiz'])->name('quiz.generate_next');


	// Potential route for Gooey webhook if used later
	// Route::post('/webhook/gooey', [WebhookController::class, 'handleGooeyWebhook'])->name('gooey.webhook'); // Maybe a separate controller?
