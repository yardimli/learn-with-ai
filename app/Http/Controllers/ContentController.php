<?php

	namespace App\Http\Controllers;

	use App\Helpers\MyHelper;
	use App\Models\Quiz;
	use App\Models\Subject;
	use App\Models\UserAnswer; // Needed for context in first quiz prompt potentially
	use Illuminate\Http\Request; // Needed if you add Request param later
	use Illuminate\Support\Facades\Log;
	use Illuminate\Support\Facades\Session;
	use Illuminate\Support\Facades\Storage; // Potentially needed if handling files directly
	use Illuminate\Support\Str;

	class ContentController extends Controller
	{
		/**
		 * Display the generated content (title, text, image/video).
		 *
		 * @param Subject $subject Route model binding
		 * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
		 */
		public function show(Subject $subject)
		{
			// Ensure the subject belongs to the current session for security
			if ($subject->session_id !== Session::getId()) {
				Log::warning("Attempt to access subject ID {$subject->id} from different session.");
				// Decide action: show error, redirect home?
				return redirect()->route('home')->with('error', 'Content not found or session expired.');
			}

			// Eager load relations if needed often (optional optimization)
			$subject->load('generatedImage');
			// dd($subject);
			return view('content_display', compact('subject'));
		}

		/**
		 * Generate the FIRST quiz for a subject and redirect to quiz view.
		 * Triggered by the "Start Quiz" button on the content page.
		 *
		 * @param Subject $subject Route model binding
		 * @return \Illuminate\Http\RedirectResponse
		 */
		public function generateFirstQuiz(Subject $subject)
		{
			$sessionId = Session::getId();
			// Ensure the subject belongs to the current session
			if ($subject->session_id !== $sessionId) {
				Log::warning("Attempt to start quiz for subject ID {$subject->id} from different session.");
				return redirect()->route('content.show', $subject->id)->with('error', 'Cannot start quiz: Session mismatch.');
			}

			Log::info("Generating first quiz for Subject ID: {$subject->id}, Session: {$sessionId}");

			// Simplified context for the first question
			$difficultyInstruction = "Generate a new multiple-choice question about the subject: '{$subject->name}'. This is the first question (level 1 difficulty).";
			$llm = env('DEFAULT_LLM'); // Use default LLM for first quiz

			// --- LLM Prompt for Quiz Generation ---
			$systemPromptQuizGen = <<<PROMPT
You are an AI quiz master creating educational multiple-choice questions based on a given subject and context.
The user is learning about: '{$subject->name}'.
The introductory text provided was: '{$subject->main_text}'

{$difficultyInstruction} // Inject the dynamic instructions here

Your output MUST be a valid JSON object with the following structure:
{
  "question": "The text of the multiple-choice question?",
  "answers": [
    { "text": "Answer option 1", "is_correct": false, "feedback": "Brief explanation why this answer is wrong." },
    { "text": "Answer option 2", "is_correct": true, "feedback": "Brief explanation why this answer is correct." },
    { "text": "Answer option 3", "is_correct": false, "feedback": "Brief explanation why this answer is wrong." },
    { "text": "Answer option 4", "is_correct": false, "feedback": "Brief explanation why this answer is wrong." }
  ]
}

RULES:
- There must be exactly 4 answer options.
- Exactly ONE answer must have "is_correct": true.
- Keep question and answer text concise.
- Feedback should be helpful and educational (1-2 sentences).
- Ensure the entire output is ONLY the JSON object, nothing before or after.
PROMPT;
			$chatHistoryQuizGen = []; // No user message needed
			$quizResult = MyHelper::llm_no_tool_call($llm, $systemPromptQuizGen, $chatHistoryQuizGen, true);

			// --- Validate LLM Response using the helper ---
			if (!MyHelper::isValidQuizResponse($quizResult)) { // Use helper method
				$errorMsg = $quizResult['error'] ?? 'LLM did not return a valid quiz structure.';
				Log::error("LLM First Quiz Gen Error: " . $errorMsg);
				return redirect()->route('content.show', $subject->id)->with('error', 'Failed to generate the first quiz question. ' . $errorMsg);
			}

			Log::info("First quiz question generated successfully.");
			$quizData = $quizResult;
			$currentDifficultyLevel = 1; // First question is level 1

			// --- Generate TTS for Question Text ---
			$questionAudioPath = null; // Store the public URL now
			$questionTtsResult = MyHelper::text2speech(
				$quizData['question'],
				env('DEFAULT_TTS_VOICE', 'en-US-Wavenet-A'), // Use appropriate voice
				'en-US',
				'question_' . $subject->id . '_initial_' . Str::slug(Str::limit($quizData['question'], 20))
			);
			if ($questionTtsResult && isset($questionTtsResult['fileUrl'])) {
				$questionAudioPath = $questionTtsResult['fileUrl']; // Store the URL
				Log::info("Generated TTS for initial question: {$questionAudioPath}");
			} else {
				Log::warning("Failed to generate TTS for initial question");
			}

			// --- Process Answers (Generate TTS for feedback & answers) ---
			$processedAnswers = Quiz::processAnswersWithTTS($quizData['answers'], $subject->id, 'initial');

			// --- Save Quiz to Database ---
			$quiz = Quiz::create([
				'subject_id' => $subject->id,
				'question_text' => $quizData['question'],
				'question_audio_path' => $questionAudioPath, // Store the URL
				'answers' => $processedAnswers, // Store full processed data including TTS URLs
				'difficulty_level' => $currentDifficultyLevel,
				'session_id' => $sessionId, // Link quiz to session
			]);

			Log::info("First Quiz record created with ID: {$quiz->id}");

			// Redirect to the quiz display page (handled by QuizController::show)
			return redirect()->route('quiz.show', $subject->id);
		}
	}
