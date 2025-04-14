<?php

	namespace App\Http\Controllers;

	use App\Helpers\MyHelper;
	use App\Models\GeneratedImage;
	use App\Models\Question; // Keep Question model import
	use App\Models\Lesson;
	use Carbon\Carbon;
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

		public function generatePartAudioAjax(Request $request, Lesson $lesson, int $partIndex)
		{
			Log::info("AJAX request to generate sentence audio for Lesson ID: {$lesson->id}, Part Index: {$partIndex}");

			// Retrieve and decode lesson parts
			$lessonParts = is_array($lesson->lesson_parts) ? $lesson->lesson_parts : json_decode($lesson->lesson_parts, true);

			// Validate partIndex
			if (!is_array($lessonParts) || !isset($lessonParts[$partIndex])) {
				Log::error("Invalid part index ({$partIndex}) or lesson parts data for Lesson ID: {$lesson->id}.");
				return response()->json(['success' => false, 'message' => 'Invalid lesson part index.'], 400);
			}

			$partData = $lessonParts[$partIndex];
			$fullText = $partData['text'] ?? '';

			if (empty($fullText)) {
				Log::warning("Lesson Part {$partIndex} text is empty for Lesson ID: {$lesson->id}. Skipping audio generation.");
				return response()->json(['success' => true, 'message' => 'Part text is empty, no audio generated.', 'sentences' => []], 200);
			}

			// Get TTS settings from the lesson
			$ttsEngine = $lesson->ttsEngine ?? env('DEFAULT_TTS_ENGINE', 'google');
			$ttsVoice = $lesson->ttsVoice;
			$ttsLanguageCode = $lesson->ttsLanguageCode;

			if (empty($ttsVoice) || empty($ttsLanguageCode)) {
				Log::error("Missing TTS Voice or Language Code in Lesson {$lesson->id} for Part {$partIndex} audio");
				return response()->json(['success' => false, 'message' => 'Lesson TTS settings are incomplete.'], 400);
			}

			// --- Start Sentence Processing ---
			try {
				$sentencesText = MyHelper::splitIntoSentences($fullText);
				if (empty($sentencesText)) {
					Log::warning("Could not split text into sentences for Lesson ID: {$lesson->id}, Part Index: {$partIndex}.");
					return response()->json(['success' => false, 'message' => 'Could not split text into sentences.'], 400);
				}

				$generatedSentencesData = [];
				$hasFailures = false;

				foreach ($sentencesText as $sentenceIndex => $sentence) {
					$sentenceIdentifier = "s{$lesson->id}_p{$partIndex}_s{$sentenceIndex}";
					$outputFilenameBase = 'audio/intro_' . $sentenceIdentifier; // Include path segment

					Log::info("Generating audio for sentence {$sentenceIndex}: '{$sentence}'");

					$audioResult = MyHelper::text2speech(
						$sentence,
						$ttsVoice,
						$ttsLanguageCode,
						$outputFilenameBase, // Pass the base path + filename prefix
						$ttsEngine
					);

					if ($audioResult['success'] && !empty($audioResult['storage_path']) && !empty($audioResult['fileUrl'])) {
						$generatedSentencesData[] = [
							'text' => $sentence,
							'audio_path' => $audioResult['storage_path'], // Relative path within disk
							'audio_url' => $audioResult['fileUrl'],
						];
						Log::info("Success generating audio for sentence {$sentenceIndex}. Path: {$audioResult['storage_path']}");
					} else {
						Log::error("Failed to generate audio for sentence {$sentenceIndex} (Lesson {$lesson->id}, Part {$partIndex}): " . ($audioResult['message'] ?? 'Unknown TTS error'));
						$generatedSentencesData[] = [
							'text' => $sentence,
							'audio_path' => null,
							'audio_url' => null,
							'error' => $audioResult['message'] ?? 'Unknown TTS error',
						];
						$hasFailures = true;
					}
				} // End foreach sentence

				// Update the specific lesson part in the array
				$lessonParts[$partIndex]['sentences'] = $generatedSentencesData;
				$lessonParts[$partIndex]['audio_generated_at'] = Carbon::now()->toDateTimeString();

				// Save the modified array back to the model
				$lesson->lesson_parts = $lessonParts;
				$lesson->save();

				Log::info("Part sentence audio generation process completed for Lesson ID: {$lesson->id}, Part Index: {$partIndex}. Failures: " . ($hasFailures ? 'Yes' : 'No'));

				return response()->json([
					'success' => !$hasFailures, // Overall success is true only if NO failures occurred
					'message' => $hasFailures ? 'Audio generated for some sentences, but failures occurred.' : 'Sentence audio generated successfully!',
					'sentences' => $generatedSentencesData // Return the detailed data
				]);

			} catch (\Exception $e) {
				Log::error("Exception during AJAX part sentence audio generation for Lesson ID {$lesson->id}, Part {$partIndex}: " . $e->getMessage());
				return response()->json(['success' => false, 'message' => 'Server error during sentence audio generation.'], 500);
			}
		}

		public function generateQuestionAudioAjax(Question $question)
		{
			// ... (Keep existing implementation) ...
			Log::info("AJAX request to generate question audio for Question ID: {$question->id}");

			if (empty($question->question_text)) {
				Log::error("Cannot generate question audio for Question ID {$question->id}: Question text is empty.");
				return response()->json(['success' => false, 'message' => 'Question text is empty.'], 400);
			}

			try {
				$lesson = $question->lesson;
				$ttsEngine = $lesson->ttsEngine ?? env('DEFAULT_TTS_ENGINE', 'google');
				$ttsVoice = $lesson->ttsVoice;
				$ttsLanguageCode = $lesson->ttsLanguageCode;

				if (empty($ttsVoice) || empty($ttsLanguageCode)) {
					Log::error("Missing TTS Voice or Language Code in Lesson {$lesson->id} for QID {$question->id}");
					return response()->json(['success' => false, 'message' => 'Lesson TTS settings are incomplete.'], 400);
				}

				// More robust unique identifier
				$questionIdentifier = "s{$question->lesson_id}_p{$question->lesson_part_index}_q{$question->id}";
				$outputFilenameBase = 'audio/question_q_' . $questionIdentifier; // Include path segment

				$audioResult = MyHelper::text2speech(
					$question->question_text,
					$ttsVoice,
					$ttsLanguageCode,
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

			try {
				// Get preferences from session or default
				$lesson = $question->lesson;
				$ttsEngine = $lesson->ttsEngine ?? env('DEFAULT_TTS_ENGINE', 'google');
				$ttsVoice = $lesson->ttsVoice;
				$ttsLanguageCode = $lesson->ttsLanguageCode;

				if (empty($ttsVoice) || empty($ttsLanguageCode)) {
					Log::error("Missing TTS Voice or Language Code in Lesson {$lesson->id} for QID {$question->id} answers");
					return response()->json(['success' => false, 'message' => 'Lesson TTS settings are incomplete.'], 400);
				}

				$questionIdentifier = "s{$question->lesson_id}_p{$question->lesson_part_index}_q{$question->id}";
				$filenamePrefix = 'audio/question_' . $questionIdentifier; // Include path segment

				// Process answers using the static method in Question model
				$processedAnswers = Question::processAnswersWithTTS(
					$currentAnswers,
					$question->id, // Pass Question ID for potential use inside, although identifier is main now
					$filenamePrefix, // Identifier for filenames
					$ttsEngine,
					$ttsVoice,
					$ttsLanguageCode
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
			$baseDir = 'uploads/question_images/' . $question->lesson_id; // Organize by lesson
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


	} // End of CreateLessonController
