<?php

	use Illuminate\Support\Facades\Route;
	use App\Http\Controllers\ContentController; // Add this

// Route::get('/', function () { // Remove default
//     return view('welcome');
// });
// Auth::routes(); // Remove or comment out if you used laravel/ui scaffolding and don't want auth routes
// Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home'); // Remove or comment out


// Main route for the application UI
	Route::get('/', [ContentController::class, 'index'])->name('home');

// API-like routes for AJAX calls
	Route::post('/generate-content', [ContentController::class, 'generateInitialContent'])->name('content.generate');
	Route::post('/generate-quiz', [ContentController::class, 'generateQuiz'])->name('quiz.generate');
	Route::post('/submit-answer', [ContentController::class, 'submitAnswer'])->name('answer.submit');
