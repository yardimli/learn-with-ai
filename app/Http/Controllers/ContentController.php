<?php

	namespace App\Http\Controllers;

	use App\Helpers\MyHelper;
	use App\Models\Quiz;
	use App\Models\Subject;
	use App\Models\UserAnswer;

	// Needed for context in first quiz prompt potentially
	use Illuminate\Http\Request;

	// Needed if you add Request param later
	use Illuminate\Support\Facades\Log;
	use Illuminate\Support\Facades\Session;
	use Illuminate\Support\Facades\Storage;

	// Potentially needed if handling files directly
	use Illuminate\Support\Str;
	use App\Models\GeneratedImage;

	class ContentController extends Controller
	{
		public function show($sessionId)
		{
			$subject = Subject::where('session_id', $sessionId)->first();

			// Check if subject exists
			if (!$subject) {
				Log::warning("Subject not found for session ID: {$sessionId}");
				return redirect()->route('home')->with('error', 'Content not found or session expired.');
			}

			$subject->load('generatedImage');
			$mediumImageUrl = $subject->generatedImage ? $subject->generatedImage->medium_url : null;
			$subject->medium_image_url = $mediumImageUrl;

			return view('content_display', compact('subject'));
		}

		public function generateFirstQuiz($sessionId)
		{
			$subject = Subject::where('session_id', $sessionId)->first();

			// Check if subject exists
			if (!$subject) {
				Log::warning("Subject not found for session ID: {$sessionId}");
				return redirect()->route('home')->with('error', 'Content not found or session expired.');
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
  "image_prompt_idea": "Short visual description related ONLY to the question itself (max 15 words).",
  "answers": [
    { "text": "Answer option 1", "is_correct": false, "feedback": "Brief explanation why this answer is wrong." },
    { "text": "Answer option 2", "is_correct": true,  "feedback": "Brief explanation why this answer is correct." },
    { "text": "Answer option 3", "is_correct": false, "feedback": "Brief explanation why this answer is wrong." },
    { "text": "Answer option 4", "is_correct": false, "feedback": "Brief explanation why this answer is wrong." }
  ]
}

RULES:
- The Question must be something explained in the introductory text.
- There must be exactly 4 answer options.
- Exactly ONE answer must have "is_correct": true.
- Keep question and answer text concise.
- Provide a relevant image_prompt_idea for the question (max 15 words).
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

			// --- Generate Image for the Question ---
			$imagePromptIdea = $quizData['image_prompt_idea'];
			$questionImageId = 0;
			if (!empty($imagePromptIdea)) {
				Log::info("Generating image for first quiz question with prompt: '{$imagePromptIdea}'");
				$imageResult = MyHelper::makeImage($imagePromptIdea, env('DEFAULT_IMAGE_MODEL', 'fal-ai/flux/schnell'),
					'landscape_16_9');
				Log::info("Image generation result: " . json_encode($imageResult));

				if ($imageResult['success']) {
					$questionImageId = $imageResult['image_id'];
				} else {
					Log::error("Quiz question image generation failed: " . ($imageResult['message'] ?? 'Unknown error'));
				}
			} else {
				Log::warning("LLM did not provide an image_prompt_idea for the first quiz question.");
			}


			$ttsEngine = env('DEFAULT_TTS_ENGINE', 'google');
			$ttsVoice = ($ttsEngine === 'openai')
				? env('OPENAI_TTS_VOICE', 'alloy') // Default OpenAI voice
				: env('DEFAULT_TTS_VOICE', 'en-US-Wavenet-A'); // Default Google voice
			$ttsLanguageCode = 'en-US'; // Primarily for Google

			// --- Generate TTS for Question Text ---
			$questionAudioPath = null; // Store the public URL now
			$questionTtsResult = MyHelper::text2speech(
				$quizData['question'],
				$ttsVoice,            // Use determined voice
				$ttsLanguageCode,     // Pass language code
				'question_' . $subject->id . '_initial_' . Str::slug(Str::limit($quizData['question'], 20)),
				$ttsEngine            // Pass determined engine
			);

			if ($questionTtsResult && isset($questionTtsResult['fileUrl'])) {
				$questionAudioPath = $questionTtsResult['fileUrl']; // Store the URL
				Log::info("Generated TTS for initial question: {$questionAudioPath}");
			} else {
				Log::warning("Failed to generate TTS for initial question");
			}

			// --- Process Answers (Generate TTS for feedback & answers) ---
			$processedAnswers = Quiz::processAnswersWithTTS(
				$quizData['answers'],
				$subject->id,
				'initial',
				$ttsEngine,         // Pass engine
				$ttsVoice,          // Pass voice
				$ttsLanguageCode    // Pass lang code
			);

			// --- Save Quiz to Database ---
			$quiz = Quiz::create([
				'subject_id' => $subject->id,
				'generated_image_id' => $questionImageId,
				'question_text' => $quizData['question'],
				'question_audio_path' => $questionAudioPath, // Store the URL
				'answers' => $processedAnswers, // Store full processed data including TTS URLs
				'difficulty_level' => $currentDifficultyLevel,
			]);

			Log::info("First Quiz record created with ID: {$quiz->id}, Image ID: {$questionImageId}");

			// Redirect to the quiz display page (handled by QuizController::show)
			return redirect()->route('quiz.show', $sessionId);
		}
	}
