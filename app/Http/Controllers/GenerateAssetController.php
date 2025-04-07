<?php

	namespace App\Http\Controllers;

	use App\Helpers\MyHelper;
	use App\Models\GeneratedImage;
	use App\Models\Question; // Keep Question model import
	use App\Models\Subject;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Log;
	use Illuminate\Support\Facades\Storage;
	use Illuminate\Support\Facades\Validator;
	use Illuminate\Support\Str;

	use Illuminate\Support\Facades\Http;
	use Intervention\Image\Laravel\Facades\Image as InterventionImage; // For image resizing
	use Illuminate\Http\UploadedFile; // For type hinting
	use Illuminate\Support\Facades\DB; // For transactions

	use Exception; // Add Exception import

	class GenerateAssetController extends Controller
	{

		public function generatePartVideoAjax(Request $request, Subject $subject, int $partIndex)
		{
			// ... (Keep existing implementation) ...
			Log::info("AJAX request to generate video for Subject ID: {$subject->id}, Part Index: {$partIndex}");
			// Retrieve and decode lesson parts
			$lessonParts = is_array($subject->lesson_parts) ? $subject->lesson_parts : json_decode($subject->lesson_parts, true);
			// Validate partIndex
			if (!is_array($lessonParts) || !isset($lessonParts[$partIndex])) {
				Log::error("Invalid part index ({$partIndex}) or lesson parts data for Subject ID: {$subject->id}.");
				return response()->json(['success' => false, 'message' => 'Invalid lesson part index.'], 400);
			}
			// Check if video already exists for this part
			if (!empty($lessonParts[$partIndex]['video_path']) && !empty($lessonParts[$partIndex]['video_url'])) {
				$relativePath = $lessonParts[$partIndex]['video_path'];
				$videoUrl = $lessonParts[$partIndex]['video_url'];
				// Ensure file actually exists before claiming success
				if (Storage::disk('public')->exists($relativePath)) {
					Log::warning("Video already exists for Subject ID: {$subject->id}, Part Index: {$partIndex}. Path: {$relativePath}");
					return response()->json([
						'success' => true, // Indicate it exists
						'message' => 'Video already exists for this part.',
						'video_url' => $videoUrl,
						'video_path' => $relativePath
					], 200); // 200 OK is fine here
				} else {
					Log::warning("Video path/URL recorded but file missing for Subject ID: {$subject->id}, Part Index: {$partIndex}. Path: {$relativePath}. Will attempt regeneration.");
					// Allow generation to proceed
				}
			}

			// Get text for video generation
			$partData = $lessonParts[$partIndex];
			$videoText = ($partData['title'] ?? 'Lesson Part') . ". \n" . ($partData['text'] ?? 'No content.');
			$defaultFaceUrl = env('DEFAULT_FACE_URL', 'https://elooi.com/video/video1.mp4');
			$videoResult = null;
			try {
				$useV2 = (stripos(env('APP_URL', ''), 'localhost') === false); // Prefer v2 unless on localhost
				Log::info("Attempting video generation. Using " . ($useV2 ? "text2videov2 (OpenAI TTS + Gooey Lipsync)" : "text2video (Gooey Lipsync+Google TTS)"));

				if ($useV2) {
					$videoResult = MyHelper::text2videov2($videoText, $defaultFaceUrl);
				} else {
					// Note: text2video might need specific Google voice/config from env
					$googleVoice = env('GOOGLE_TTS_VOICE', 'en-US-Studio-O'); // Example Google Voice
					$videoResult = MyHelper::text2video($videoText, $defaultFaceUrl, $googleVoice);
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
					$subject->lesson_parts = $lessonParts;
					$subject->save();

					Log::info("Part video generated and saved. Subject ID: {$subject->id}, Part Index: {$partIndex}. Path: {$relativePath}, URL: {$videoUrl}");
					return response()->json([
						'success' => true,
						'message' => 'Video generated successfully!',
						'video_url' => $videoUrl,
						'video_path' => $relativePath
					]);
				} else {
					$errorMsg = $videoResult['message'] ?? 'Unknown video generation error';
					Log::error("Part video generation failed for Subject ID {$subject->id}, Part {$partIndex}: " . $errorMsg, ['result' => $videoResult]);
					return response()->json([
						'success' => false,
						'message' => 'Failed to generate video: ' . $errorMsg
					], 500);
				}
			} catch (\Exception $e) {
				Log::error("Exception during AJAX part video generation for Subject ID {$subject->id}, Part {$partIndex}: " . $e->getMessage());
				return response()->json(['success' => false, 'message' => 'Server error during video generation.'], 500);
			}
		}

		public function generateQuestionAudioAjax(Question $question)
		{
			// ... (Keep existing implementation) ...
			Log::info("AJAX request to generate question audio for Question ID: {$question->id}");

			if (!empty($question->question_audio_path) && !empty($question->question_audio_url)) {
				// Verify file existence before claiming success
				if (Storage::disk('public')->exists($question->question_audio_path)) {
					Log::warning("Question audio already exists for Question ID: {$question->id}. Path: {$question->question_audio_path}");
					return response()->json([
						'success' => true, // Indicate it exists
						'message' => 'Question audio already exists.',
						'audio_url' => $question->question_audio_url,
						'audio_path' => $question->question_audio_path
					], 200); // 200 OK
				} else {
					Log::warning("Question audio path/URL recorded but file missing for Question ID: {$question->id}. Path: {$question->question_audio_path}. Will attempt regeneration.");
					// Allow generation to proceed
				}
			}

			if (empty($question->question_text)) {
				Log::error("Cannot generate question audio for Question ID {$question->id}: Question text is empty.");
				return response()->json(['success' => false, 'message' => 'Question text is empty.'], 400);
			}

			try {
				$ttsEngine = env('DEFAULT_TTS_ENGINE', 'google');
				$ttsVoice = ($ttsEngine === 'openai') ? env('OPENAI_TTS_VOICE', 'alloy') : env('GOOGLE_TTS_VOICE', 'en-US-Studio-O');
				$languageCode = 'en-US';
				// More robust unique identifier
				$questionIdentifier = "s{$question->subject_id}_p{$question->lesson_part_index}_q{$question->id}";
				$outputFilenameBase = 'audio/question_q_' . $questionIdentifier; // Include path segment

				$audioResult = MyHelper::text2speech(
					$question->question_text,
					$ttsVoice,
					$languageCode,
					$outputFilenameBase,
					$ttsEngine
				);

				if ($audioResult['success'] && isset($audioResult['storage_path'])) {
					$question->question_audio_path = $audioResult['storage_path'];
					$question->save(); // Save the path, accessor will generate URL

					// We need to get the URL generated by the accessor to return it
					$generatedUrl = $question->fresh()->question_audio_url; // Refresh model and get URL

					Log::info("Question audio generated for Question ID: {$question->id}. Path: {$question->question_audio_path}, URL: {$generatedUrl}");
					return response()->json([
						'success' => true,
						'message' => 'Question audio generated successfully!',
						'audio_url' => $generatedUrl, // Return the generated URL
						'audio_path' => $question->question_audio_path,
					]);
				} else {
					throw new \Exception($audioResult['message'] ?? 'TTS generation failed');
				}
			} catch (\Exception $e) {
				Log::error("Exception during question audio generation for Question ID {$question->id}: " . $e->getMessage());
				return response()->json(['success' => false, 'message' => 'Server error during audio generation: ' . $e->getMessage()], 500);
			}
		}

		public function generateAnswerAudioAjax(Question $question)
		{
			Log::info("AJAX request to generate answer/feedback audio for Question ID: {$question->id}");
			$currentAnswers = $question->answers ?? [];

			if (empty($currentAnswers) || !is_array($currentAnswers)) {
				Log::error("Cannot generate answer audio for Question ID {$question->id}: Answers data is missing or invalid.");
				return response()->json(['success' => false, 'message' => 'Question answers data is missing or invalid.'], 400);
			}

			// Check if audio seems to exist already (e.g., first answer has BOTH paths/URLs and files exist)
			$audioExists = false;
			if(isset($currentAnswers[0]['answer_audio_path'], $currentAnswers[0]['feedback_audio_path'])) {
				if(Storage::disk('public')->exists($currentAnswers[0]['answer_audio_path']) &&
					Storage::disk('public')->exists($currentAnswers[0]['feedback_audio_path'])) {
					$audioExists = true;
				} else {
					Log::warning("Answer/Feedback audio paths recorded but files missing for Question ID: {$question->id}. Will attempt regeneration.");
				}
			}

			if ($audioExists) {
				Log::warning("Answer/feedback audio seems to already exist and files are present for Question ID: {$question->id}.");
				// Return the existing data so JS can potentially update button states if needed
				return response()->json([
					'success' => true, // Indicate it exists
					'message' => 'Answer/feedback audio appears to already exist.',
					'answers' => $question->answers, // Return current answer data
				], 200);
			}

			try {
				$ttsEngine = env('DEFAULT_TTS_ENGINE', 'google');
				$ttsVoice = ($ttsEngine === 'openai') ? env('OPENAI_TTS_VOICE', 'alloy') : env('GOOGLE_TTS_VOICE', 'en-US-Studio-O');
				$languageCode = 'en-US';
				$questionIdentifier = "s{$question->subject_id}_p{$question->lesson_part_index}_q{$question->id}";
				$filenamePrefix = 'audio/question_' . $questionIdentifier; // Include path segment

				// Process answers using the static method in Question model
				$processedAnswers = Question::processAnswersWithTTS(
					$currentAnswers,
					$question->id, // Pass Question ID for potential use inside, although identifier is main now
					$filenamePrefix, // Identifier for filenames
					$ttsEngine,
					$ttsVoice,
					$languageCode
				);

				// Update the question's answers column
				$question->answers = $processedAnswers;
				$question->save();

				Log::info("Answer/feedback audio generation complete for Question ID: {$question->id}");
				return response()->json([
					'success' => true,
					'message' => 'Answer and feedback audio generated successfully!',
					'answers' => $processedAnswers // Return updated answers array
				]);
			} catch (\Exception $e) {
				Log::error("Exception during answer/feedback audio generation for Question ID {$question->id}: " . $e->getMessage());
				return response()->json(['success' => false, 'message' => 'Server error during audio generation: ' . $e->getMessage()], 500);
			}
		}

		public function generateQuestionImageAjax(Request $request, Question $question)
		{
			$newPrompt = $request->input('new_prompt'); // Get potential new prompt

			if ($newPrompt) {
				Log::info("AJAX request to *regenerate* image for Question ID: {$question->id} with new prompt.");
				$validator = Validator::make(['new_prompt' => $newPrompt], [
					'new_prompt' => 'required|string|max:500'
				]);
				if ($validator->fails()) {
					Log::error("Invalid prompt provided for image regeneration. Question ID: {$question->id}", ['errors' => $validator->errors()]);
					return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
				}
				$question->image_prompt_idea = $newPrompt;
				$question->save();
				Log::info("Updated image prompt for Question ID: {$question->id}. Proceeding with regeneration.");

			} else {
				Log::info("AJAX request to generate image for Question ID: {$question->id}.");
				// --- Standard Generation - Check Existence ---
				if (!empty($question->generated_image_id)) {
					$existingImage = GeneratedImage::find($question->generated_image_id);
					if ($existingImage && $existingImage->original_url && Storage::disk('public')->exists($existingImage->image_original_path)) {
						Log::warning("Image already exists and file found for Question ID: {$question->id}. Image ID: {$question->generated_image_id}");
						return response()->json([
							'success' => true, // Indicate it exists
							'message' => 'Image already exists for this question.',
							'image_id' => $question->generated_image_id,
							'image_urls' => [
								'small' => $existingImage->small_url,
								'medium' => $existingImage->medium_url,
								'large' => $existingImage->large_url,
								'original' => $existingImage->original_url,
							],
							'prompt' => $question->image_prompt_idea // Return current prompt
						], 200); // 200 OK
					} else {
						Log::warning("Image ID {$question->generated_image_id} linked to Question {$question->id}, but image record or file missing. Will attempt regeneration.");
						// Reset link and allow generation to proceed
						$question->generated_image_id = null;
						$question->save();
					}
				}
				if (empty($question->image_prompt_idea)) {
					Log::error("Cannot generate image for Question ID {$question->id}: Image prompt is empty.");
					return response()->json(['success' => false, 'message' => 'Image prompt is empty.'], 400);
				}
			}

			// --- Common Generation Call ---
			try {
				$promptToUse = $question->image_prompt_idea;
				if (empty($promptToUse)) {
					throw new \Exception('Image prompt is unexpectedly empty.');
				}

				$imageModel = env('DEFAULT_IMAGE_MODEL', 'fal-ai/flux/schnell');
				$imageSize = 'square_hd'; // Or 'square' etc.

				Log::info("Calling makeImage for Question {$question->id}. Model: {$imageModel}, Size: {$imageSize}, Prompt: '{$promptToUse}'");

				$imageResult = MyHelper::makeImage(
					$promptToUse,
					$imageModel,
					$imageSize
				);

				// Check for success and *image_id* which is now preferred over guid lookup
				if ($imageResult['success'] && isset($imageResult['image_id'], $imageResult['image_urls'])) {
					// Link the generated image to the question
					$question->generated_image_id = $imageResult['image_id'];
					$question->save(); // Save the question with the new image ID

					Log::info("Image " . ($newPrompt ? "regenerated" : "generated") . " and linked for Question ID: {$question->id}. Image ID: {$imageResult['image_id']}");
					return response()->json([
						'success' => true,
						'message' => 'Image ' . ($newPrompt ? "regenerated" : "generated") . ' successfully!',
						'image_id' => $imageResult['image_id'],
						'image_guid' => $imageResult['image_guid'] ?? null, // Include GUID if available
						'image_urls' => $imageResult['image_urls'],
						'prompt' => $promptToUse // Return the prompt used
					]);
				} else {
					// Handle potential case where GUID is returned but not ID (older helper version?)
					if ($imageResult['success'] && isset($imageResult['image_guid'])) {
						$imageModel = GeneratedImage::where('image_guid', $imageResult['image_guid'])->first();
						if ($imageModel) {
							$question->generated_image_id = $imageModel->id;
							$question->save();
							Log::info("Image " . ($newPrompt ? "regenerated" : "generated") . " and linked (fallback lookup by GUID) for Question ID: {$question->id}. Image ID: {$imageModel->id}");
							$imageUrls = [
								'small' => $imageModel->small_url,
								'medium' => $imageModel->medium_url,
								'large' => $imageModel->large_url,
								'original' => $imageModel->original_url,
							];
							return response()->json([
								'success' => true,
								'message' => 'Image ' . ($newPrompt ? "regenerated" : "generated") . ' successfully!',
								'image_id' => $imageModel->id,
								'image_guid' => $imageResult['image_guid'],
								'image_urls' => $imageUrls,
								'prompt' => $promptToUse
							]);
						} else {
							Log::error("Image generation reported success with GUID {$imageResult['image_guid']} but GeneratedImage record not found. Question ID {$question->id}");
							throw new \Exception('Image generation succeeded but failed to find/link the image record.');
						}
					}
					// If it truly failed or returned unexpected structure
					throw new \Exception($imageResult['message'] ?? 'Image generation failed');
				}
			} catch (\Exception $e) {
				Log::error("Exception during image generation/regeneration for Question ID {$question->id}: " . $e->getMessage(), ['exception' => $e]);
				// Revert prompt change if regeneration failed? Maybe not, user might want to try again.
				if ($newPrompt) {
					Log::warning("Image regeneration failed, but the new prompt '{$newPrompt}' remains saved for Question ID {$question->id}.");
				}
				return response()->json(['success' => false, 'message' => 'Server error during image generation: ' . $e->getMessage()], 500);
			}
		}

		public function uploadQuestionImageAjax(Request $request, Question $question)
		{
			$validator = Validator::make($request->all(), [
				'question_image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB Max
			]);

			if ($validator->fails()) {
				return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
			}

			Log::info("AJAX request to upload image for Question ID: {$question->id}");

			/** @var UploadedFile $uploadedFile */
			$uploadedFile = $request->file('question_image');
			$originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
			$baseDir = 'uploads/question_images/' . $question->subject_id; // Organize by subject
			$baseName = Str::slug($originalFilename) . '_' . time(); // Unique base name

			DB::beginTransaction();
			try {
				// --- Process and Save Image ---
				$imagePaths = MyHelper::handleImageProcessing($uploadedFile, $baseDir, $baseName);

				if (!$imagePaths) {
					throw new Exception('Failed to process and save the uploaded image.');
				}

				// --- Create GeneratedImage Record ---
				$newImage = GeneratedImage::create([
					'image_type' => 'question',
					'image_guid' => Str::uuid(), // Unique GUID for image set
					'source' => 'upload',
					'prompt' => 'User Upload: ' . $uploadedFile->getClientOriginalName(),
					'image_model' => 'upload', // Or null
					'image_size_setting' => $uploadedFile->getSize(), // Store original size
					'image_original_path' => $imagePaths['original_path'],
					'image_large_path' => $imagePaths['large_path'],
					'image_medium_path' => $imagePaths['medium_path'],
					'image_small_path' => $imagePaths['small_path'],
					'api_response_data' => ['original_filename' => $uploadedFile->getClientOriginalName()],
				]);

				// --- Clean up old image files if replaced ---
				if ($question->generated_image_id) {
					$oldImage = GeneratedImage::find($question->generated_image_id);
					if ($oldImage && in_array($oldImage->source, ['upload', 'freepik'])) {
						Log::info("Deleting old image files (ID: {$oldImage->id}) replaced by upload for Question ID: {$question->id}");
						$oldImage->deleteStorageFiles();
						// Optionally delete the old GeneratedImage record itself
						// $oldImage->delete();
					}
				}

				// --- Link to Question ---
				$question->generated_image_id = $newImage->id;
				$question->save();

				DB::commit();

				// --- Return Success Response ---
				// Refresh image model to get URLs from accessors
				$newImage->refresh();
				$imageUrls = [
					'small' => $newImage->small_url,
					'medium' => $newImage->medium_url,
					'large' => $newImage->large_url,
					'original' => $newImage->original_url,
				];

				Log::info("Image uploaded and linked for Question ID: {$question->id}. New Image ID: {$newImage->id}");
				return response()->json([
					'success' => true,
					'message' => 'Image uploaded successfully!',
					'image_id' => $newImage->id,
					'image_urls' => $imageUrls,
					'prompt' => $question->image_prompt_idea
				]);

			} catch (Exception $e) {
				DB::rollBack();
				Log::error("Exception during image upload for Question ID {$question->id}: " . $e->getMessage(), ['exception' => $e]);
				return response()->json(['success' => false, 'message' => 'Server error during image upload: ' . $e->getMessage()], 500);
			}
		}


	} // End of SubjectController
