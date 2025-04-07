<?php

	use App\Http\Controllers\Controller;
	use App\Http\Controllers\EditController;
	use App\Http\Controllers\FreePikController;
	use App\Http\Controllers\GenerateAssetController;
	use App\Http\Controllers\ProgressController;
	use Illuminate\Support\Facades\Route;
	use App\Http\Controllers\SubjectController;
	use App\Http\Controllers\LessonController;

// --- Lesson Creation & Setup ---
	Route::get('/', [SubjectController::class, 'index'])->name('home');
	Route::post('/lesson/generate-structure', [SubjectController::class, 'generatePlanPreview'])->name('lesson.generate.structure');
	Route::post('/lesson/save-structure', [SubjectController::class, 'createLesson'])->name('lesson.save.structure');
	Route::post('/lesson/{subject}/archive', [SubjectController::class, 'archiveProgress'])->name('lesson.archive');
	Route::get('/progress/{subject:session_id}', [ProgressController::class, 'show'])->name('progress.show');


	Route::get('/api/llms-list', function () {
		return response()->json(['llms' => App\Helpers\MyHelper::checkLLMsJson()]);
	})->name('api.llms.list');

	Route::post('/settings/llm-preference', function (Illuminate\Http\Request $request) {
		$validated = $request->validate([
			'llm' => 'required|string',
			'subject_id' => 'required|string'
		]);

		// Store in session
		session(['preferred_llm' => $validated['llm']]);

		// Optionally update the subject record too
		if ($subject = App\Models\Subject::where('session_id', $validated['subject_id'])->first()) {
			$subject->llm_used = $validated['llm'];
			$subject->save();
		}

		return response()->json(['success' => true]);
	})->name('settings.llm.preference');

	Route::post('/settings/voice-preference', function (Illuminate\Http\Request $request) {
		$validated = $request->validate([
			'voice' => 'required|string',
			'engine' => 'required|string|in:google,openai'
		]);

		// Store in session
		session(['preferred_voice' => $validated['voice']]);
		session(['preferred_tts_engine' => $validated['engine']]);

		return response()->json(['success' => true]);
	})->name('settings.voice.preference');

	Route::get('/settings/get-preferences', function () {
		return response()->json([
			'preferred_llm' => session('preferred_llm'),
			'preferred_voice' => session('preferred_voice'),
			'preferred_tts_engine' => session('preferred_tts_engine', 'google'), // Default to 'google' if not set
		]);
	})->name('settings.get.preferences');

// --- Lesson Editing & Asset Management ---
	Route::get('/lesson/{subject}/edit', [EditController::class, 'edit'])->name('lesson.edit');

// Generate Question Batch
	Route::post('/lesson/{subject}/part/{partIndex}/generate-questions/{difficulty}', [EditController::class, 'generateQuestionBatchAjax'])
		->where(['partIndex' => '[0-9]+', 'difficulty' => 'easy|medium|hard'])
		->name('question.generate.batch');

	Route::post('/question/{question}/update-texts', [EditController::class, 'updateQuestionTextsAjax'])
		->name('question.update.texts');
	Route::delete('/question/{question}', [EditController::class, 'deleteQuestionAjax'])
		->name('question.delete');

// Generate Assets
	Route::post('/lesson/{subject}/part/{partIndex}/generate-video', [GenerateAssetController::class, 'generatePartVideoAjax'])
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
	Route::get('/lesson/{subject:session_id}/question', [LessonController::class, 'showQuestionInterface'])
		->name('question.interface');
	Route::post('/lesson/{subject:session_id}/part-questions', [LessonController::class, 'getPartQuestionsAjax'])
		->name('question.part_questions');
	Route::post('/question/{question}/submit', [LessonController::class, 'submitAnswer'])
		->name('question.submit_answer');
