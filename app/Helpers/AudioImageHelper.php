<?php

	namespace App\Helpers;

	use App\Models\Lesson;
	use App\Models\Question;
	use App\Models\GeneratedImage;
	use App\Models\UserAnswer;
	use Carbon\Carbon;
	use GuzzleHttp\Client;
	use Illuminate\Http\Request;
	use Illuminate\Http\UploadedFile;
	use Illuminate\Support\Facades\Auth;

	use Illuminate\Support\Facades\DB;
	use Illuminate\Support\Facades\File;
	use Illuminate\Support\Facades\Http;
	use Illuminate\Support\Facades\Log;
	use Illuminate\Support\Facades\Session;
	use Illuminate\Support\Facades\Storage;
	use Illuminate\Support\Facades\Validator;

	use Ahc\Json\Fixer;
	use Illuminate\Support\Str;

	use Google\Cloud\TextToSpeech\V1\AudioConfig;
	use Google\Cloud\TextToSpeech\V1\AudioEncoding;
	use Google\Cloud\TextToSpeech\V1\SsmlVoiceGender;
	use Google\Cloud\TextToSpeech\V1\SynthesisInput;
	use Google\Cloud\TextToSpeech\V1\TextToSpeechClient;
	use Google\Cloud\TextToSpeech\V1\VoiceSelectionParams;
	use Exception;
	use Intervention\Image\ImageManager;
	use Normalizer;


	class AudioImageHelper
	{
		public static function resizeImage($sourcePath, $destinationPath, $maxWidth)
		{
			list($originalWidth, $originalHeight, $type) = getimagesize($sourcePath);

			// Calculate new dimensions
			$ratio = $originalWidth / $originalHeight;
			$newWidth = min($maxWidth, $originalWidth);
			$newHeight = $newWidth / $ratio;

			// Create new image
			$newImage = imagecreatetruecolor($newWidth, $newHeight);

			// Handle transparency for PNG images
			if ($type == IMAGETYPE_PNG) {
				imagealphablending($newImage, false);
				imagesavealpha($newImage, true);
				$transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
				imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
			}

			// Load source image
			switch ($type) {
				case IMAGETYPE_JPEG:
					$source = imagecreatefromjpeg($sourcePath);
					break;
				case IMAGETYPE_PNG:
					$source = imagecreatefrompng($sourcePath);
					break;
				case IMAGETYPE_GIF:
					$source = imagecreatefromgif($sourcePath);
					break;
				default:
					return false;
			}

			// Resize
			imagecopyresampled(
				$newImage,
				$source,
				0, 0, 0, 0,
				$newWidth,
				$newHeight,
				$originalWidth,
				$originalHeight
			);

			// Save resized image
			switch ($type) {
				case IMAGETYPE_JPEG:
					imagejpeg($newImage, $destinationPath, 90);
					break;
				case IMAGETYPE_PNG:
					imagepng($newImage, $destinationPath, 9);
					break;
				case IMAGETYPE_GIF:
					imagegif($newImage, $destinationPath);
					break;
			}

			// Free up memory
			imagedestroy($newImage);
			imagedestroy($source);

			return true;
		}

		public static function makeImage($prompt, $image_model = 'fal-ai/flux/schnell', $size = 'square_hd')
		{
			Log::info("Starting image generation process.");
			Log::info("User Prompt: {$prompt}, Image Model: {$image_model}, Size: {$size}");

			$image_model_url = filter_var($image_model, FILTER_VALIDATE_URL) ? $image_model : 'https://queue.fal.run/' . $image_model; // Construct URL if needed

			$falApiKey = env('FAL_API_KEY');
			if (empty($falApiKey)) {
				Log::error('FAL_API_KEY environment variable is not set');
				return ['success' => false, 'message' => 'Image generation API key not configured.'];
			}

			//-----------------------------------------
			try {
				$client = new Client(['timeout' => 120.0]); // 2 min timeout for image gen API

				$response = $client->post($image_model_url, [
					'headers' => [
						'Authorization' => 'Key ' . $falApiKey,
						'Content-Type' => 'application/json',
					],
					'json' => [
						'prompt' => $prompt,
						'image_size' => $size,
						'safety_tolerance' => '5',
					]
				]);
				Log::info('FLUX image response');
				Log::info($response->getBody());

				$statusCode = $response->getStatusCode();
				$body = $response->getBody();
				$data = json_decode($body, true);
				Log::info("Fal.ai Response Status: {$statusCode}");
				Log::debug("Fal.ai Raw Response: {$body}");

				if ($statusCode == 200) {

					$status_url = $data['status_url'];
					$check_count = 0;
					$check_limit = 20;
					$response_url = '';
					while ($check_count < $check_limit) {
						$response = $client->get($status_url, [
							'headers' => [
								'Authorization' => 'Key ' . $falApiKey,
								'Content-Type' => 'application/json',
							]
						]);
						Log::debug('FLUX image status response');
						Log::debug($response->getBody());

						$body = $response->getBody();
						$data = json_decode($body, true);
						if ($data['status'] == 'COMPLETED') {
							$response_url = $data['response_url'];
							break;
						}
						sleep(3);
						$check_count++;
					}

					if ($response_url !== '') {
						$response = $client->get($response_url, [
							'headers' => [
								'Authorization' => 'Key ' . $falApiKey,
								'Content-Type' => 'application/json',
							]
						]);
						Log::debug('FLUX image status response');
						Log::debug($response->getBody());
						$body = $response->getBody();
						$data = json_decode($body, true);
					}

					if (isset($data['images'][0]['url'])) {
						$image_url = $data['images'][0]['url'];
						Log::info("Image successfully generated: {$image_url}");

						// Download the image
						$image_content = @file_get_contents($image_url); // Use @ to suppress warnings on failure
						if ($image_content === false) {
							Log::error("Failed to download image from URL: {$image_url}");
							return ['success' => false, 'message' => __('Failed to download generated image.')];
						}


						// --- Save and Resize ---
						$baseDir = 'ai-images';
						Storage::disk('public')->makeDirectory($baseDir); // Ensure base directory exists
						$guid = Str::uuid();
						$extension = 'jpg'; // Assuming JPG output, adjust if needed

						// Define paths using Storage facade for portability
						$originalPath = "{$baseDir}/original/{$guid}.{$extension}";
						$largePath = "{$baseDir}/large/{$guid}_large.{$extension}";
						$mediumPath = "{$baseDir}/medium/{$guid}_medium.{$extension}";
						$smallPath = "{$baseDir}/small/{$guid}_small.{$extension}";

						// Ensure directories exist (Storage::put handles this for the file, but good practice)
						Storage::disk('public')->makeDirectory("{$baseDir}/original");
						Storage::disk('public')->makeDirectory("{$baseDir}/large");
						Storage::disk('public')->makeDirectory("{$baseDir}/medium");
						Storage::disk('public')->makeDirectory("{$baseDir}/small");


						// Save original image
						Storage::disk('public')->put($originalPath, $image_content);
						$sourcePath = Storage::disk('public')->path($originalPath);

						self::resizeImage($sourcePath, Storage::disk('public')->path($largePath), 1024);
						self::resizeImage($sourcePath, Storage::disk('public')->path($mediumPath), 600);
						self::resizeImage($sourcePath, Storage::disk('public')->path($smallPath), 300);
						Log::info("Image resizing completed successfully for GUID: {$guid}");


						// --- Save metadata to database ---
						// Use the GeneratedImage model alias
						$imageModel = GeneratedImage::create([
							'image_type' => 'generated',
							'image_guid' => $guid,
							'image_alt' => Str::limit($prompt, 150),
							'prompt' => $prompt,
							'image_model' => $image_model, // Store which image model was used
							'image_size_setting' => $size, // Store requested size
							// Store relative paths (without 'public/') for easier use with Storage::url()
							'image_original_path' => $originalPath,
							'image_large_path' => $largePath,
							'image_medium_path' => $mediumPath,
							'image_small_path' => $smallPath,
							'api_response_data' => json_encode($data), // Store raw API response if needed
						]);
						Log::info("Image metadata saved to database with ID: {$imageModel->id}");

						// Return success data
						return [
							'success' => true,
							'message' => __('Image generated successfully'),
							'image_guid' => $guid,
							'image_id' => $imageModel->id,
							'image_urls' => [ // Provide URLs for frontend
								'large' => Storage::disk('public')->url($largePath),
								'medium' => Storage::disk('public')->url($mediumPath),
								'small' => Storage::disk('public')->url($smallPath),
								'original' => Storage::disk('public')->url($originalPath),
							],
							'image_paths' => [ // Relative paths
								'large' => $largePath,
								'medium' => $mediumPath,
								'small' => $smallPath,
								'original' => $originalPath,
							],
							'seed' => $data['seed'] ?? null, // Include seed if available
							'prompt' => $prompt,
						];
					}

				} else {
					Log::error("Error generating image via Fal.ai (Status: {$statusCode}). Details: " . $body);
					return ['success' => false, 'message' => __('Error response from image generation service.'), 'status_code' => $statusCode, 'details' => $body];
				}

			} catch (\GuzzleHttp\Exception\RequestException $e) {
				$statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 'N/A';
				$errorBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
				Log::error("Guzzle HTTP Request Exception during Image call: Status {$statusCode} - " . $errorBody);
				return ['success' => false, 'message' => __('Network error communicating with image service.'), 'status_code' => $statusCode];
			} catch (\Exception $e) {
				Log::error("General Exception during Image generation: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
				return ['success' => false, 'message' => __('An unexpected error occurred during image generation.')];
			}
		}

		public static function amplifyMp3Volume($inputFile, $outputFile, $volumeLevel = 2.0)
		{
			// Check if input file exists
			if (!file_exists($inputFile)) {
				Log::error("amplify: Input file does not exist: {$inputFile}");
				return false;
			}

			// Validate volume level (prevent negative values)
			$volumeLevel = max(0, (float)$volumeLevel);
			$bitrate = '128k';

			// Create a temporary file for the intermediate step
			$tempFile = str_replace('.mp3', '_temp.mp3', $inputFile);

			if (file_exists($tempFile)) {
				unlink($tempFile); // Remove temp file if it exists
			}

			// First pass: Amplify volume
			$amplifyCommand = sprintf(
				'ffmpeg -i %s -filter:a "volume=%.2f" -c:a libmp3lame -b:a %s %s',
				escapeshellarg($inputFile),
				$volumeLevel,
				$bitrate,
				escapeshellarg($tempFile)
			);

			// Execute amplify command
			exec($amplifyCommand, $output, $returnCode);

			if ($returnCode !== 0) {
				// Clean up if the first pass failed
				Log::error("amplify: Failed to amplify volume. Command: {$amplifyCommand}, Return Code: {$returnCode}");
				if (file_exists($tempFile)) {
					unlink($tempFile);
				}
				return false;
			}

			// Second pass: Remove silence from beginning and end
			$silenceRemoveCommand = sprintf(
				'ffmpeg -i %s -af "silenceremove=start_periods=1:start_duration=0:start_threshold=-60dB:detection=peak,areverse,silenceremove=start_periods=1:start_duration=0:start_threshold=-60dB:detection=peak,areverse" -b:a %s %s',
				escapeshellarg($tempFile),
				$bitrate,
				escapeshellarg($outputFile)
			);
		//	 ffmpeg -i '/var/www/elooi/storage/app/public/tts/audioquestion-s2-p0-q14-fb-hen-ga-3_temp.mp3' -af "silenceremove=start_periods=1:start_duration=0:start_threshold=-60dB:detection=peak,areverse,silenceremove=start_periods=1:start_duration=0:start_threshold=-60dB:detection=peak,areverse -b:a 128k '/var/www/elooi/storage/app/public/tts/audioquestion-s2-p0-q14-fb-hen-ga-3_loud.mp3'
			Log::info("Executing silence removal command: {$silenceRemoveCommand}");

			// Execute silence removal command
			exec($silenceRemoveCommand, $output, $returnCode);

			Log::info("Silence removal command executed. Return Code: {$returnCode}");

			// Clean up the temporary file
//			if (file_exists($tempFile)) {
//				unlink($tempFile);
//			}

			return $returnCode === 0;
		}

		/**
		 * Converts text to speech using the specified engine.
		 *
		 * @param string $text The text to synthesize.
		 * @param string $voiceName The voice name (engine-specific). Google: 'en-US-Wavenet-A', OpenAI: 'alloy'.
		 * @param string $languageCode The language code (primarily for Google, e.g., 'en-US').
		 * @param string $outputFilenameBase Base name for the output file (without extension).
		 * @param string|null $engine The TTS engine ('google' or 'openai'). Defaults to env('DEFAULT_TTS_ENGINE').
		 * @return array Associative array with 'success' (bool), 'storage_path' (string|null), 'fileUrl' (string|null), 'message' (string|null).
		 */
		public static function text2speech(
			string  $text,
			string  $voiceName,
			string  $languageCode = 'en-US', // Keep for Google compatibility
			string  $outputFilenameBase = 'tts_output',
			?string $engine = null // Add engine parameter
		): array
		{
			// Determine engine, defaulting from .env
			$selectedEngine = $engine ?? env('DEFAULT_TTS_ENGINE', 'google');
			$filename = Str::slug($outputFilenameBase, '_') . '.mp3'; // Use mp3 for both now
			$directory = 'tts'; // Store in storage/app/public/tts
			$storagePath = $directory . '/' . $filename;

			if (!Storage::exists($directory)) {
				Storage::makeDirectory($directory); // This is relative to the disk's root (storage/app)
			}

			Log::info("text2speech called. Engine: {$selectedEngine}, Voice: {$voiceName}, Text: '" . Str::limit($text, 50) . "...'");

			try {
				// Ensure the directory exists
				Storage::disk('public')->makeDirectory($directory);

				if ($selectedEngine === 'openai') {
					// --- OpenAI TTS Implementation ---
					$apiKey = env('OPENAI_API_KEY');
					$openAiVoice = $voiceName; // Directly use the voice name provided
					$openAiModel = env('OPENAI_TTS_MODEL', 'tts-1');

					if (!$apiKey) {
						throw new \Exception('OpenAI API key is not configured in .env');
					}

					//check if $text contain non english characters
					$prefix_str = '';
					$suffix_str = '';
					if (preg_match('/[^\x20-\x7E]/', $text)) {

					} else {

						//get word count of $text
						$wordCount = str_word_count($text);
						if ($wordCount < 2) {
							$prefix_str = '... ';
						}
						//check if $text ends with a period
						if (!str_ends_with($text, '.')) {
							$suffix_str = '.';
						}
					}
					Log::info("OpenAI TTS: Prefix: {$prefix_str}, Suffix: {$suffix_str}");

					$response = Http::withToken($apiKey)
						->timeout(60) // Increased timeout for audio generation
						->post('https://api.openai.com/v1/audio/speech', [
							'model' => $openAiModel,
							'input' => $prefix_str.$text.$suffix_str,
							'voice' => $openAiVoice,
							'instructions' => 'Speak in a cheerful and positive tone.',
							'response_format' => 'mp3', // Request MP3 format
						]);

					if ($response->successful()) {
						// Save the raw audio content directly
						// Check if the $storagePath exists and delete it if it does
						if (Storage::disk('public')->exists($storagePath)) {
							Storage::disk('public')->delete($storagePath);
						}

						//check if $storagePath exists and delete it if it does
						if (file_exists($storagePath)) {
							unlink($storagePath);
						}

						$saved = Storage::disk('public')->put($storagePath, $response->body());
						if (!$saved) {
							throw new \Exception("Failed to save OpenAI TTS audio to disk at {$storagePath}. Check permissions.");
						}

						$loudness = 4.0; // Adjust volume level as needed
						$newFilePath = Storage::disk('public')->path($storagePath);
						$newFilePath = str_replace('.mp3', '_loud.mp3', $newFilePath);
						//delete $newFilePath if it exists
						if (file_exists($newFilePath)) {
							unlink($newFilePath);
						}
						$amplified = self::amplifyMp3Volume(Storage::disk('public')->path($storagePath), $newFilePath, $loudness);

						if ($amplified) {
							$fileUrl = Storage::disk('public')->url(str_replace('.mp3', '_loud.mp3', $storagePath));
							$storagePath = str_replace('.mp3', '_loud.mp3', $storagePath);
						} else {
							$fileUrl = Storage::disk('public')->url($storagePath);
						}
						Log::info("OpenAI TTS successful. File saved: {$storagePath}, URL: {$fileUrl}");
						return [
							'success' => true,
							'storage_path' => $storagePath,
							'fileUrl' => $fileUrl,
							'message' => 'OpenAI TTS generated successfully.',
						];
					} else {
						$errorMessage = "OpenAI TTS API request failed. Status: " . $response->status();
						$errorBody = $response->body();
						Log::error($errorMessage . " Body: " . $errorBody);
						// Attempt to decode JSON error if possible
						$decodedError = json_decode($errorBody, true);
						if (isset($decodedError['error']['message'])) {
							$errorMessage .= " Message: " . $decodedError['error']['message'];
						}
						throw new \Exception($errorMessage);
					}

				} elseif ($selectedEngine === 'google') {
					$credentialsPath = base_path(env('GOOGLE_TTS_CREDENTIALS'));
					if (empty($credentialsPath) || !File::exists($credentialsPath)) {
						Log::error('Google TTS credentials path not set or file not found: ' . $credentialsPath);
						return null;
					}

					// Check if credentials file is readable
					if (!is_readable($credentialsPath)) {
						Log::error('Google TTS credentials file is not readable: ' . $credentialsPath);
						return null;
					}

					// Instantiates a client
					$client = new TextToSpeechClient(['credentials' => $credentialsPath]);

					// Sets the text input to be synthesized
					$synthesisInput = (new SynthesisInput())->setText($text);

					// Builds the voice request; languageCode and name are required
					$voice = (new VoiceSelectionParams())
						->setLanguageCode($languageCode) // Use the provided language code
						->setName($voiceName);           // Use the provided Google voice name

					// Selects the type of audio file to return
					$audioConfig = (new AudioConfig())
						->setAudioEncoding(AudioEncoding::MP3); // Use MP3

					// Performs the text-to-speech request
					$response = $client->synthesizeSpeech($synthesisInput, $voice, $audioConfig);
					$audioContent = $response->getAudioContent();

					// check if file exists and delete it if it does
					if (Storage::disk('public')->exists($storagePath)) {
						Storage::disk('public')->delete($storagePath);
					}

					// Save the MP3 audio content to the public disk
					$saved = Storage::disk('public')->put($storagePath, $audioContent);
					if (!$saved) {
						throw new \Exception("Failed to save Google TTS audio to disk at {$storagePath}. Check permissions.");
					}

					$fileUrl = Storage::disk('public')->url($storagePath);
					Log::info("Google TTS successful. File saved: {$storagePath}, URL: {$fileUrl}");

					// Close the client connection
					$client->close();

					return [
						'success' => true,
						'storage_path' => $storagePath,
						'fileUrl' => $fileUrl,
						'message' => 'Google TTS generated successfully.',
					];
				} else {
					throw new \Exception("Unsupported TTS engine: {$selectedEngine}");
				}

			} catch (\Throwable $e) {
				Log::error("text2speech Error ({$selectedEngine}): " . $e->getMessage(), [
					'exception' => $e,
					'text' => Str::limit($text, 100) . '...',
					'voice' => $voiceName,
					'engine' => $selectedEngine
				]);
				return [
					'success' => false,
					'storage_path' => null,
					'fileUrl' => null,
					'message' => "TTS generation failed ({$selectedEngine}): " . $e->getMessage(),
				];
			}
		}

		/**
		 * Helper to process an uploaded or downloaded image file.
		 * Saves original and resized versions, returns relative paths.
		 *
		 * @param UploadedFile|string $file Input file (UploadedFile or path to temp downloaded file)
		 * @param string $baseDir Base directory within 'public' disk (e.g., 'uploads/question_images')
		 * @param string $baseName Base filename without extension
		 * @return array|null Array of paths [original_path, large_path, medium_path, small_path] or null on failure
		 */
		public static function handleImageProcessing($file, string $baseDir, string $baseName): ?array
		{
			$disk = Storage::disk('public');
			$paths = [];

			try {
				// Ensure base directory exists
				if (!$disk->exists($baseDir)) {
					$disk->makeDirectory($baseDir);
				}

				// Determine extension (handle both UploadedFile and path string)
				$extension = '';
				if ($file instanceof UploadedFile) {
					$extension = $file->getClientOriginalExtension();
				} elseif (is_string($file)) {
					$extension = pathinfo($file, PATHINFO_EXTENSION);
				}
				$extension = strtolower($extension ?: 'jpg'); // Default to jpg if unknown

				// 1. Store Original
				$originalFilename = $baseName . '_original.' . $extension;
				$originalPath = $baseDir . '/' . $originalFilename;
				if ($file instanceof UploadedFile) {
					$disk->putFileAs($baseDir, $file, $originalFilename);
				} elseif (is_string($file)) {
					// If it's a path, we need to read and write it
					$disk->put($originalPath, file_get_contents($file));
				} else {
					throw new Exception('Invalid file type for processing.');
				}
				$paths['original_path'] = $originalPath;

				// Get full path for Intervention Image
				$fullOriginalPath = $disk->path($originalPath);

				$manager = new ImageManager(
					new \Intervention\Image\Drivers\Gd\Driver()
				);

// open an image file
				$image = $manager->read($fullOriginalPath);

				// Large (e.g., 1024px wide)
				$largeFilename = $baseName . '_large.' . $extension;
				$largePath = $baseDir . '/' . $largeFilename;
				$image->scale(1024)->save($disk->path($largePath), 80); // Save with quality 80
				$paths['large_path'] = $largePath;

				// Medium (e.g., 512px wide)
				$mediumFilename = $baseName . '_medium.' . $extension;
				$mediumPath = $baseDir . '/' . $mediumFilename;
				$image->scale(512)->save($disk->path($mediumPath), 80);
				$paths['medium_path'] = $mediumPath;

				// Small (e.g., 256px wide)
				$smallFilename = $baseName . '_small.' . $extension;
				$smallPath = $baseDir . '/' . $smallFilename;
				$image->scale(256)->save($disk->path($smallPath), 75);
				$paths['small_path'] = $smallPath;

				return $paths;

			} catch (Exception $e) {
				Log::error("Image processing failed: " . $e->getMessage(), ['baseDir' => $baseDir, 'baseName' => $baseName]);
				// Clean up any partially created files? Maybe not essential here.
				return null;
			}
		}

	}
