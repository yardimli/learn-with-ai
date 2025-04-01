<?php

	namespace App\Http\Controllers;

	use App\Helpers\MyHelper;
	use App\Models\GeneratedImage;
	use App\Models\Subject;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Log;
	use Illuminate\Support\Facades\Session;
	use Illuminate\Support\Facades\Storage;
	use Illuminate\Support\Facades\Validator;
	use Illuminate\Support\Str;

	class SubjectController extends Controller
	{
		/**
		 * Display the subject input form (Home Page).
		 *
		 * @return \Illuminate\View\View
		 */
		public function index()
		{
			$llms = MyHelper::checkLLMsJson();
			return view('subject_input', compact('llms'));
		}

		/**
		 * Handle subject submission, generate initial content, create Subject record,
		 * and redirect to the content display page.
		 *
		 * @param Request $request
		 * @return \Illuminate\Http\RedirectResponse
		 */
		public function startLearning(Request $request)
		{
			$validator = Validator::make($request->all(), [
				'subject' => 'required|string|max:150',
				'llm' => 'nullable|string|max:100',
			]);

			if ($validator->fails()) {
				return redirect()->back()
					->withErrors($validator)
					->withInput();
			}

			$userSubject = $request->input('subject');
			$llm = $request->input('llm', env('DEFAULT_LLM'));
			$sessionId = Session::getId(); // Important for tracking session

			Log::info("Starting content generation for subject: '{$userSubject}' by session: {$sessionId}");

			// --- Generate Text ---
			$systemPromptTextGen = <<<PROMPT
You are an AI assistant creating educational micro-content. Generate a short introductory text about the subject provided by the user.
The output MUST be a JSON object containing:
1. "title": A concise and engaging title (max 10 words).
2. "main_text": A brief introductory paragraph (3-5 sentences, approx 50-100 words) explaining the core concept of the subject. Keep it simple and clear.
3. "image_prompt_idea": A short phrase or idea (max 15 words) that captures the essence of the subject visually, suitable for an AI image generator.

Example Input: Photosynthesis
Example Output:
{
  "title": "Photosynthesis: Plants Making Food",
  "main_text": "Photosynthesis is the amazing process plants use to turn sunlight, water, and carbon dioxide into their food (sugar) and oxygen. It happens in special parts called chloroplasts, using a green pigment called chlorophyll. This process is vital for life on Earth, providing food and the air we breathe.",
  "image_prompt_idea": "Sunlight hitting green leaves, energy conversion"
}

Ensure the output is ONLY the valid JSON object and nothing else.
PROMPT;
			$chatHistoryTextGen = [['role' => 'user', 'content' => $userSubject]];
			$textResult = MyHelper::llm_no_tool_call($llm, $systemPromptTextGen, $chatHistoryTextGen, true);

			if (isset($textResult['error']) || !isset($textResult['title'], $textResult['main_text'], $textResult['image_prompt_idea'])) {
				$errorMsg = $textResult['error'] ?? 'LLM did not return the expected JSON structure for text generation.';
				Log::error("LLM Text Gen Error: " . $errorMsg);
				// Redirect back with error
				return redirect()->route('home') // Redirect to home (subject input)
				->with('error', 'Failed to generate initial text content: ' . $errorMsg);
			}

			Log::info("Initial text content generated successfully.");
			$title = $textResult['title'];
			$mainText = $textResult['main_text'];
			$imagePromptIdea = $textResult['image_prompt_idea'];

			// --- Generate Image ---
			$imageResult = MyHelper::makeImage($imagePromptIdea, env('DEFAULT_IMAGE_MODEL', 'fal-ai/flux/schnell'), 'landscape_16_9');
			$generatedImageModel = null;
			if ($imageResult['success'] && isset($imageResult['image_guid'])) {
				Log::info("Image generated successfully. GUID: " . $imageResult['image_guid']);
				// Fetch the model to get its ID
				$generatedImageModel = GeneratedImage::where('image_guid', $imageResult['image_guid'])->first();
			} else {
				Log::error("Image generation failed: " . ($imageResult['message'] ?? 'Unknown error'));
				// Optionally inform user image failed? For now, just log it.
			}

			// --- Save Subject ---
			$subject = Subject::create([
				'name' => $userSubject,
				'title' => $title,
				'main_text' => $mainText,
				'image_prompt_idea' => $imagePromptIdea,
				'session_id' => $sessionId, // Associate with session
				'generated_image_id' => $generatedImageModel?->id, // Link image if created
				'initial_video_path' => null, // Will be filled by video generation
				'initial_video_url' => null,
			]);

			// If image model was created, link it back to the subject
			if ($generatedImageModel && !$generatedImageModel->subject_id) {
				$generatedImageModel->subject_id = $subject->id;
				$generatedImageModel->save();
			}
			Log::info("Subject record created with ID: {$subject->id}");

			// --- Initiate Video Generation (Async - no waiting here) ---
			// Use job queue later for robustness, for now direct call
			$videoResult = MyHelper::text2video(
				$mainText,
				env('DEFAULT_FACE_URL'),
				env('DEFAULT_TTS_VOICE', 'en-US-Studio-O')
			);
			if ($videoResult['success'] && isset($videoResult['video_path'], $videoResult['video_url'])) {
				// Update subject immediately if path/URL available (sync generation assumed here)
				$subject->initial_video_path = $videoResult['video_path'];
				$subject->initial_video_url = $videoResult['video_url'];
				$subject->save();
				Log::info("Initial video path/URL saved for Subject ID: {$subject->id}");
			} else {
				// Log failure, but don't block the user
				Log::error("Failed to start/get initial text video generation: " . ($videoResult['message'] ?? 'Unknown error'));
			}

			// Redirect to the content display page
			return redirect()->route('content.show', $subject->id);
		}
	}
