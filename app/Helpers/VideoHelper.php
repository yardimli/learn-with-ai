<?php
	namespace App\Helpers;

	use App\Models\Lesson;
	use GuzzleHttp\Client;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Http;
	use Illuminate\Support\Facades\Log;
	use Illuminate\Support\Facades\Storage;
	use Illuminate\Support\Str;

	class VideoHelper
	{

		public static function text2video($text, $faceUrl, $voice = 'en-US-Studio-O', float $speakingRate = 1.0, float $pitch = 0.0)
		{
			$gooeyApiKey = env('GOOEY_API_KEY');
			if (empty($gooeyApiKey)) {
				Log::error('GOOEY_API_KEY environment variable is not set');
				return ['success' => false, 'message' => 'Video generation API key not configured.'];
			}
			if (empty($text)) {
				return ['success' => false, 'message' => 'Text cannot be empty for video generation.'];
			}
			if (!filter_var($faceUrl, FILTER_VALIDATE_URL)) {
				Log::warning("Invalid face URL provided for text2video: {$faceUrl}. Using default.");
				$faceUrl = env('DEFAULT_FACE_URL', 'https://elooi.com/video/video1.mp4'); // Fallback to default
			}


			// Data payload for Gooey API (check their current API docs for exact fields)
			$data = [
				"input_face" => $faceUrl,
				// "input_audio" => "", // Use TTS provider instead
				"face_padding_top" => 3,
				"face_padding_bottom" => 16,
				"face_padding_left" => 12,
				"face_padding_right" => 6,
				"text_prompt" => $text,
				"tts_provider" => "GOOGLE_TTS", // Or other supported provider
				"uberduck_voice_name" => "", // Only if using Uberduck
				"uberduck_speaking_rate" => 1,
				"google_voice_name" => $voice,
				"google_speaking_rate" => $speakingRate,
				"google_pitch" => $pitch,
				// Add webhook URL if you want to be notified on completion
				// "webhook_url": route('gooey.webhook'), // Example if using webhooks
			];

			Log::info("Sending request to Gooey AI LipsyncTTS for text: " . Str::limit($text, 50));
			Log::debug("Gooey Payload: ", $data);

			try {
				$client = new Client(['timeout' => 60.0]); // Timeout for initiating the job
				$response = $client->post('https://api.gooey.ai/v2/LipsyncTTS/', [
					'headers' => [
						'Authorization' => 'Bearer ' . $gooeyApiKey,
						'Content-Type' => 'application/json',
					],
					'json' => $data,
				]);


				$statusCode = $response->getStatusCode();
				$body = $response->getBody()->getContents();
				$responseData = json_decode($body, true);


				Log::info("Gooey AI Response Status: {$statusCode}");
				Log::debug("Gooey Raw Response Body: {$body}");

				if ($statusCode === 200) {
					$video_url = $responseData['output']['output_video'] ?? null;
					if ($video_url) {
						//download the video
						$video_content = @file_get_contents($video_url); // Use @ to suppress warnings on failure
						if ($video_content === false) {
							Log::error("Failed to download video from URL: {$video_url}");
							return ['success' => false, 'message' => __('Failed to download generated video.')];
						}
						// Save the video
						$video_guid = Str::uuid()->toString();
						$video_path = "public/videos/{$video_guid}.mp4"; // Save path
						Storage::put($video_path, $video_content); // Store using Storage facade
						$video_url = Storage::url($video_path); // Get public URL for the video
						Log::info("Video successfully generated and saved: {$video_url}");
						return [
							'success' => true,
							'message' => __('Video generated successfully'),
							'video_guid' => $video_guid,
							'video_url' => $video_url,
							'video_path' => $video_path, // Relative path
						];
					}
				} else {
					// Generic error if structure is unexpected
					Log::error("Gooey AI request failed or returned unexpected response. Status: {$statusCode}");
					return ['success' => false, 'message' => 'Failed to start video generation job.', 'status_code' => $statusCode, 'response_body' => $body];
				}

			} catch (\GuzzleHttp\Exception\RequestException $e) {
				$statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 'N/A';
				$errorBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
				Log::error("Guzzle HTTP Request Exception during Gooey call: Status {$statusCode} - " . $errorBody);
				return ['success' => false, 'message' => __('Network error communicating with video service.'), 'status_code' => $statusCode];
			} catch (\Exception $e) {
				Log::error("General Exception during text2video call: " . $e->getMessage());
				return ['success' => false, 'message' => __('An unexpected error occurred initiating video generation.')];
			}

			return ['success' => false, 'message' => __('Failed to start video generation job.')];
		}


		/**
		 * NEW: Generates a talking head video using OpenAI TTS and Gooey Lipsync API.
		 *
		 * @param string $text The text content for the speech.
		 * @param string $faceVideoUrl URL of the input face video (e.g., stored on GCS).
		 * @return array Result array: ['success' => bool, 'message' => string, 'video_url' => string|null, 'gooey_run_id' => string|null, 'api_response' => array|null]
		 */
		public static function text2videov2(
			string $text,
			string $faceVideoUrl,
			string $ttsEngine,
			string $ttsVoice,
			string $ttsLanguageCode
		): array
		{
			Log::info("Starting text2videov2 process for text: " . Str::limit($text, 50) . "...");

			if (empty(trim($text))) {
				Log::warning('text2videov2 called with empty text.');
				return ['success' => false, 'message' => 'Input text cannot be empty.'];
			}
			if (empty(trim($faceVideoUrl))) {
				Log::warning('text2videov2 called with empty face video URL.');
				return ['success' => false, 'message' => 'Input face video URL cannot be empty.'];
			}

			// --- Step 1: Generate Audio using OpenAI TTS ---
			$filenameBase = 'video_audio_' . Str::slug(Str::limit($text, 30));

			Log::info("Generating audio using OpenAI TTS (Voice: {$ttsVoice})...");
			$ttsResult = self::text2speech( // Call updated text2speech
				$text,
				$ttsVoice,
				$ttsLanguageCode,
				$filenameBase,
				$ttsEngine
			);

			if (!$ttsResult['success'] || empty($ttsResult['fileUrl'])) {
				Log::error("Failed to generate audio for video: " . ($ttsResult['message'] ?? 'Unknown TTS error'));
				return [
					'success' => false,
					'message' => 'Failed to generate prerequisite audio: ' . ($ttsResult['message'] ?? 'Unknown TTS error'),
					'video_url' => null,
					'gooey_run_id' => null,
					'api_response' => null,
				];
			}

			$audioFileUrl = $ttsResult['fileUrl']; // Public URL of the generated audio
			Log::info("Audio generated successfully: {$audioFileUrl}");

			// --- Step 2: Call Gooey Lipsync API ---
			$gooeyApiKey = env('GOOEY_API_KEY');
			$gooeyApiUrl = 'https://api.gooey.ai/v2/Lipsync';

			if (!$gooeyApiKey) {
				Log::error('Gooey API Key (GOOEY_API_KEY) is not configured in .env');
				return [
					'success' => false,
					'message' => 'Gooey API Key is not configured.',
					'video_url' => null,
					'gooey_run_id' => null,
					'api_response' => null,
				];
			}

			// Define payload based on the Gooey API documentation/example
			$payload = [
				'input_face' => $faceVideoUrl,
				'face_padding_top' => 3,        // From example
				'face_padding_bottom' => 16,    // From example
				'face_padding_left' => 12,     // From example
				'face_padding_right' => 6,      // From example
				'selected_model' => 'Wav2Lip',  // From example
				'input_audio' => $audioFileUrl, // Use the URL from our TTS step
				// Optional: Add 'run_settings' if you need async callbacks later
				// 'run_settings' => [
				//     'callback_url' => route('gooey.webhook') // Example if you set up a webhook route
				// ]
			];

			Log::info("Calling Gooey Lipsync API at: {$gooeyApiUrl} with input face video URL: {$faceVideoUrl} and audio URL: {$audioFileUrl}");
			// Log::debug("Gooey Payload: ", $payload); // Optional: Log payload for debugging (sensitive data!)

			try {
				$response = Http::withToken($gooeyApiKey)
					->timeout(120) // Set a reasonable timeout (Gooey can take time)
					->withHeaders([
						'Content-Type' => 'application/json',
						'Accept' => 'application/json', // Be explicit
					])
					->post($gooeyApiUrl, $payload);

				$statusCode = $response->status();
				$responseData = $response->json(); // Get response body as array

				Log::info("Gooey API Response Status: {$statusCode}");
				// Log::debug("Gooey Response Body: ", $responseData); // Optional: Log full response

				if (!$response->successful()) {
					// Check for specific Gooey errors if documented, otherwise generic message
					$errorMessage = $responseData['detail'] ?? ($responseData['error'] ?? 'Unknown Gooey API error');
					if (is_array($errorMessage)) { // Sometimes detail is an array
						$errorMessage = json_encode($errorMessage);
					}
					Log::error("Gooey Lipsync API Error (Status: {$statusCode}): " . $errorMessage);
					return [
						'success' => false,
						'message' => "Gooey Lipsync API failed (Status: {$statusCode}): " . $errorMessage,
						'video_url' => null,
						'gooey_run_id' => $responseData['id'] ?? null, // Might still get an ID on failure
						'api_response' => $responseData,
					];
				}

				// --- Step 3: Process Successful Gooey Response ---
				$runId = $responseData['id'] ?? null;
				$outputVideoUrl = $responseData['output']['output_video'] ?? null;

				if ($runId && $outputVideoUrl) {
					Log::info("Gooey Lipsync successful. Run ID: {$runId}, Video URL: {$outputVideoUrl}");

					//download the video
					$video_content = @file_get_contents($outputVideoUrl); // Use @ to suppress warnings on failure
					if ($video_content === false) {
						Log::error("Failed to download video from URL: {$outputVideoUrl}");
						return ['success' => false, 'message' => __('Failed to download generated video.')];
					}
					// Save the video
					$video_guid = Str::uuid()->toString();
					$video_path = "public/videos/{$video_guid}.mp4"; // Save path
					Storage::put($video_path, $video_content); // Store using Storage facade
					$video_url = Storage::url($video_path); // Get public URL for the video
					Log::info("Video successfully generated and saved: {$outputVideoUrl}");
					return [
						'success' => true,
						'message' => __('Video generated successfully'),
						'video_guid' => $video_guid,
						'video_url' => $outputVideoUrl,
						'video_path' => $video_path, // Relative path
					];
				} else {
					Log::error("Gooey Lipsync response structure invalid or missing output_video. Run ID: {$runId}");
					return [
						'success' => false,
						'message' => 'Gooey API returned success status but output video URL was not found.',
						'video_url' => null,
						'gooey_run_id' => $runId,
						'api_response' => $responseData,
					];
				}

			} catch (Exception $e) {
				Log::error("Exception during Gooey Lipsync API call: " . $e->getMessage());
				return ['success' => false,
					'message' => 'An exception occurred while contacting the video generation service: ' . $e->getMessage(),
					'video_url' => null,
					'gooey_run_id' => null,
					'api_response' => null,];
			}
		}

		public function generatePartVideoAjax(Request $request, Lesson $lesson, int $partIndex)
		{
			// ... (Keep existing implementation) ...
			Log::info("AJAX request to generate video for Lesson ID: {$lesson->id}, Part Index: {$partIndex}");
			// Retrieve and decode lesson parts
			$lessonParts = is_array($lesson->lesson_parts) ? $lesson->lesson_parts : json_decode($lesson->lesson_parts, true);
			// Validate partIndex
			if (!is_array($lessonParts) || !isset($lessonParts[$partIndex])) {
				Log::error("Invalid part index ({$partIndex}) or lesson parts data for Lesson ID: {$lesson->id}.");
				return response()->json(['success' => false, 'message' => 'Invalid lesson part index.'], 400);
			}

			// Get text for video generation
			$partData = $lessonParts[$partIndex];
			$videoText = ($partData['title'] ?? 'Lesson Part') . ". \n" . ($partData['text'] ?? 'No content.');
			$defaultFaceUrl = env('DEFAULT_FACE_URL', 'https://elooi.com/video/video1.mp4');
			$ttsEngine = $lesson->ttsEngine ?? env('DEFAULT_TTS_ENGINE', 'google');
			$ttsVoice = $lesson->ttsVoice; // Required from lesson
			$ttsLanguageCode = $lesson->ttsLanguageCode; // Required from lesson

			$videoResult = null;
			try {
				$useV2 = (stripos(env('APP_URL', ''), 'localhost') === false); // Prefer v2 unless on localhost
				Log::info("Attempting video generation. Using " . ($useV2 ? "text2videov2 (OpenAI TTS + Gooey Lipsync)" : "text2video (Gooey Lipsync+Google TTS)"));

				if ($useV2) {
					$videoResult = LlmHelper::text2videov2($videoText, $defaultFaceUrl, $ttsEngine, $ttsVoice, $ttsLanguageCode);
				} else {
					// Note: text2video might need specific Google voice/config from env
					$googleVoice = env('GOOGLE_TTS_VOICE', 'en-US-Studio-O'); // Example Google Voice
					$videoResult = LlmHelper::text2video($videoText, $defaultFaceUrl, $googleVoice);
				}

				if ($videoResult && $videoResult['success'] && isset($videoResult['video_path'])) {
					// Ensure relative path for storage URL
					$relativePath = $videoResult['video_path'];
					if (strpos($relativePath, 'public/') === 0) {
						$relativePath = substr($relativePath, strlen('public/'));
					}
					$videoUrl = Storage::disk('public')->url($relativePath); // Generate public URL

					// Update the specific lesson part in the array
					$lessonParts[$partIndex]['video_path'] = $relativePath; // Store relative path
					$lessonParts[$partIndex]['video_url'] = $videoUrl; // Store generated URL

					// Save the modified array back to the model
					$lesson->lesson_parts = $lessonParts;
					$lesson->save();

					Log::info("Part video generated and saved. Lesson ID: {$lesson->id}, Part Index: {$partIndex}. Path: {$relativePath}, URL: {$videoUrl}");
					return response()->json([
						'success' => true,
						'message' => 'Video generated successfully!',
						'video_url' => $videoUrl,
						'video_path' => $relativePath
					]);
				} else {
					$errorMsg = $videoResult['message'] ?? 'Unknown video generation error';
					Log::error("Part video generation failed for Lesson ID {$lesson->id}, Part {$partIndex}: " . $errorMsg, ['result' => $videoResult]);
					return response()->json([
						'success' => false,
						'message' => 'Failed to generate video: ' . $errorMsg
					], 500);
				}
			} catch (\Exception $e) {
				Log::error("Exception during AJAX part video generation for Lesson ID {$lesson->id}, Part {$partIndex}: " . $e->getMessage());
				return response()->json(['success' => false, 'message' => 'Server error during video generation.'], 500);
			}
		}

		/**
		 * Get the video URL for a specific lesson part.
		 */
		private function getPartVideoUrl(Lesson $lesson, int $partIndex): ?string
		{
			$lessonParts = is_array($lesson->lesson_parts) ? $lesson->lesson_parts : json_decode($lesson->lesson_parts, true);
			if (isset($lessonParts[$partIndex]['video_path']) && $lessonParts[$partIndex]['video_path']) {
				// Check if path starts with 'public/', remove if so for Storage::url
				$path = $lessonParts[$partIndex]['video_path'];
				if (Str::startsWith($path, 'public/')) {
					$path = Str::substr($path, 7);
				}
				return Storage::disk('public')->url($path);
			}
			return null;
		}


	}
