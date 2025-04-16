<?php

	namespace App\Http\Controllers;

	use App\Helpers\LlmHelper;
	use App\Helpers\AudioImageHelper;

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
			Log::info("AJAX request to generate sentence assets for Lesson ID: {$lesson->id}, Part Index: {$partIndex}");

			// 1. Get Lesson Settings and Part Data
			$ttsEngine = $lesson->ttsEngine ?? env('DEFAULT_TTS_ENGINE', 'openai');
			$ttsVoice = $lesson->ttsVoice ?? ($ttsEngine === 'openai' ? 'alloy' : 'en-US-Studio-O'); // Default based on engine
			$ttsLanguageCode = $lesson->ttsLanguageCode ?? 'en-US';
			$llm = $lesson->preferredLlm ?? env('DEFAULT_LLM');

			if (empty($llm)) {
				Log::error("No LLM configured for generating sentence image ideas for Lesson ID {$lesson->id}.");
				return response()->json(['success' => false, 'message' => 'AI model for image ideas is not configured.'], 500);
			}

			$lessonParts = is_array($lesson->lesson_parts) ? $lesson->lesson_parts : json_decode($lesson->lesson_parts, true);

			if (!is_array($lessonParts) || !isset($lessonParts[$partIndex])) {
				Log::error("Invalid part index ({$partIndex}) or lesson parts data for Lesson ID: {$lesson->id}.");
				return response()->json(['success' => false, 'message' => 'Invalid lesson part index.'], 400);
			}

			$partData = $lessonParts[$partIndex];
			$partText = $partData['text'] ?? '';
			$partTitleSlug = Str::slug($partData['title'] ?? 'part-' . $partIndex); // For filenames

			if (empty($partText)) {
				Log::warning("Part {$partIndex} text is empty for Lesson ID: {$lesson->id}. Cannot generate assets.");
				// Return success but indicate nothing was done? Or failure? Let's return success with a note.
				$lessonParts[$partIndex]['sentences'] = []; // Ensure sentences array is empty
				$lessonParts[$partIndex]['audio_generated_at'] = Carbon::now()->toIso8601String();
				$lesson->lesson_parts = $lessonParts;
				$lesson->save();
				return response()->json(['success' => true, 'message' => 'Part text was empty. No assets generated.', 'sentences' => []]);
			}

			// 2. Split into Sentences
			$sentencesText = LlmHelper::splitIntoSentences($partText);
			if (empty($sentencesText)) {
				Log::warning("Could not split part {$partIndex} text into sentences for Lesson ID: {$lesson->id}.");
				$lessonParts[$partIndex]['sentences'] = [];
				$lessonParts[$partIndex]['audio_generated_at'] = Carbon::now()->toIso8601String();
				$lesson->lesson_parts = $lessonParts;
				$lesson->save();
				return response()->json(['success' => true, 'message' => 'Could not split text into sentences.', 'sentences' => []]);
			}

			// 3. Process Each Sentence
			$processedSentences = [];
			$overallSuccess = true; // Track if all steps succeed for all sentences

			// --- Cleanup Old Audio Files for this Part ---
			$oldSentences = $lessonParts[$partIndex]['sentences'] ?? [];
			foreach($oldSentences as $oldSentence) {
				if (!empty($oldSentence['audio_path']) && Storage::disk('public')->exists($oldSentence['audio_path'])) {
					try {
						Storage::disk('public')->delete($oldSentence['audio_path']);
						Log::info("Deleted old sentence audio: " . $oldSentence['audio_path']);
					} catch (Exception $e) {
						Log::warning("Could not delete old sentence audio file: " . $oldSentence['audio_path'] . " - " . $e->getMessage());
					}
				}
				// Note: We don't delete associated GeneratedImage records here, only the audio.
				// Deleting the sentence entry breaks the link. Image cleanup needs separate logic if required.
			}
			// --- End Cleanup ---


			foreach ($sentencesText as $sentenceIndex => $sentenceText) {
				$sentenceData = [
					'text' => $sentenceText,
					'audio_path' => null,
					'audio_url' => null,
					'image_prompt_idea' => null,
					'image_search_keywords' => null,
					'generated_image_id' => null, // Keep existing ID if regenerating? No, reset on full regen.
				];

				// a) Generate Audio
				$filenameBase = "lessons/{$lesson->session_id}/part{$partIndex}_sent{$sentenceIndex}_" . Str::slug(Str::limit($sentenceText, 20));
				$audioResult = AudioImageHelper::text2speech($sentenceText, $ttsVoice, $ttsLanguageCode, $filenameBase, $ttsEngine);

				if ($audioResult['success']) {
					$sentenceData['audio_path'] = $audioResult['storage_path'];
					$sentenceData['audio_url'] = $audioResult['fileUrl'];
				} else {
					Log::warning("Failed to generate audio for sentence {$sentenceIndex}, Part {$partIndex}, Lesson {$lesson->id}. Error: " . ($audioResult['message'] ?? 'Unknown TTS error'));
					$overallSuccess = false; // Mark failure if any audio gen fails
				}

				// b) Generate Image Prompt/Keywords
				$imageIdeaResult = EditController::generateSentenceImageIdeas($llm, $sentenceText);
				if (!isset($imageIdeaResult['error'])) {
					$sentenceData['image_prompt_idea'] = $imageIdeaResult['image_prompt_idea'];
					$sentenceData['image_search_keywords'] = $imageIdeaResult['image_search_keywords'];
				} else {
					Log::warning("Failed to generate image ideas for sentence {$sentenceIndex}, Part {$partIndex}, Lesson {$lesson->id}. Error: " . ($imageIdeaResult['error'] ?? 'Unknown LLM error'));
					// Don't mark overallSuccess as false just for image ideas failing? Maybe. Let's allow it.
					// $overallSuccess = false;
				}

				$processedSentences[] = $sentenceData;
			}

			// 4. Update Lesson Data and Save
			try {
				// Update the lesson_parts array directly
				$lessonParts[$partIndex]['sentences'] = $processedSentences;
				$lessonParts[$partIndex]['audio_generated_at'] = Carbon::now()->toIso8601String(); // Timestamp generation

				// This ensures the whole array is saved back correctly
				$lesson->lesson_parts = $lessonParts;
				$lesson->save();

				Log::info("Successfully generated sentence assets for Lesson ID: {$lesson->id}, Part Index: {$partIndex}. Sentences processed: " . count($processedSentences));

				// Eager load generated images for the response if IDs were set (unlikely on initial gen)
				// This part is complex as IDs are inside JSON. We'll pass IDs back and let JS handle image loading.
				$sentencesWithImageIds = array_map(function($sentence) {
					// We need the generated image URL if ID exists
					// This requires fetching image data based on sentence['generated_image_id']
					// Let's skip this complex lookup here and handle image display client-side based on the ID
					return $sentence;
				}, $processedSentences);


				return response()->json([
					'success' => true, // Report overall success even if some minor things failed
					'message' => 'Sentence audio and image prompts generated.',
					'sentences' => $sentencesWithImageIds, // Return the processed data
					'partIndex' => $partIndex
				]);

			} catch (Exception $e) {
				Log::error("Error saving updated lesson parts for Lesson ID {$lesson->id}, Part {$partIndex}: " . $e->getMessage());
				return response()->json(['success' => false, 'message' => 'Failed to save sentence assets to lesson.'], 500);
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

				$audioResult = AudioImageHelper::text2speech(
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

				$imageResult = AudioImageHelper::makeImage(
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
				$imagePaths = AudioImageHelper::handleImageProcessing($uploadedFile, $baseDir, $baseName);

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

		public function generateSentenceImageAjax(Request $request, Lesson $lesson, int $partIndex, int $sentenceIndex)
		{
			$validator = Validator::make($request->all(), [
				'prompt' => 'required|string|max:1000',
				'image_model' => 'nullable|string|max:100', // Optional: allow specifying model per sentence
				'size' => 'nullable|string|max:50', // Optional: allow specifying size per sentence
			]);

			if ($validator->fails()) {
				return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
			}

			Log::info("AJAX request to generate AI image for Lesson ID: {$lesson->id}, Part: {$partIndex}, Sentence: {$sentenceIndex}");

			$prompt = $request->input('prompt');
			$imageModel = $request->input('image_model', env('DEFAULT_IMAGE_GEN_MODEL', 'fal-ai/flux/schnell')); // Use default if not provided
			$size = $request->input('size', 'square_hd');

			// --- Access Sentence Data ---
			$lessonParts = is_array($lesson->lesson_parts) ? $lesson->lesson_parts : json_decode($lesson->lesson_parts, true);
			if (!isset($lessonParts[$partIndex]['sentences'][$sentenceIndex])) {
				Log::error("Sentence index {$sentenceIndex} not found for part {$partIndex}, lesson {$lesson->id}.");
				return response()->json(['success' => false, 'message' => 'Sentence not found.'], 404);
			}
			// --- End Access Sentence Data ---

			$result = AudioImageHelper::makeImage($prompt, $imageModel, $size);

			if ($result['success']) {
				// Update the specific sentence with the new generated_image_id
				try {
					$lessonParts[$partIndex]['sentences'][$sentenceIndex]['generated_image_id'] = $result['image_id'];
					$lessonParts[$partIndex]['sentences'][$sentenceIndex]['image_prompt_idea'] = $prompt; // Update prompt used

					$lesson->lesson_parts = $lessonParts; // Assign the modified array back
					$lesson->save();

					Log::info("AI Image generated and linked for Lesson ID: {$lesson->id}, Part: {$partIndex}, Sentence: {$sentenceIndex}. Image ID: {$result['image_id']}");

					return response()->json([
						'success' => true,
						'message' => 'AI Image generated successfully!',
						'image_urls' => $result['image_urls'],
						'prompt' => $prompt, // Return the prompt used
						'image_id' => $result['image_id'],
						'partIndex' => $partIndex,
						'sentenceIndex' => $sentenceIndex,
					]);

				} catch (Exception $e) {
					Log::error("Error saving generated_image_id for sentence: " . $e->getMessage());
					// Should we delete the generated image record/files if DB save fails? Complex cleanup.
					return response()->json(['success' => false, 'message' => 'Image generated but failed to link to sentence.'], 500);
				}

			} else {
				Log::error("Failed to generate AI Image for sentence. Error: " . ($result['message'] ?? 'Unknown image generation error'));
				return response()->json([
					'success' => false,
					'message' => $result['message'] ?? 'Failed to generate image.',
					'details' => $result['details'] ?? null
				], isset($result['status_code']) && $result['status_code'] >= 500 ? 502 : 400); // 502 Bad Gateway if upstream failed
			}
		}

		/**
		 * AJAX: Handles uploading an image for a specific sentence.
		 */
		public function uploadSentenceImageAjax(Request $request, Lesson $lesson, int $partIndex, int $sentenceIndex)
		{
			$validator = Validator::make($request->all(), [
				'sentence_image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB Max
			]);

			if ($validator->fails()) {
				return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
			}

			Log::info("AJAX request to upload image for Lesson ID: {$lesson->id}, Part: {$partIndex}, Sentence: {$sentenceIndex}");

			// --- Access Sentence Data ---
			$lessonParts = is_array($lesson->lesson_parts) ? $lesson->lesson_parts : json_decode($lesson->lesson_parts, true);
			if (!isset($lessonParts[$partIndex]['sentences'][$sentenceIndex])) {
				Log::error("Sentence index {$sentenceIndex} not found for part {$partIndex}, lesson {$lesson->id}.");
				return response()->json(['success' => false, 'message' => 'Sentence not found.'], 404);
			}
			$sentenceData = $lessonParts[$partIndex]['sentences'][$sentenceIndex];
			// --- End Access Sentence Data ---


			if ($request->hasFile('sentence_image')) {
				$file = $request->file('sentence_image');
				$baseDir = 'uploads/lesson_sentence_images'; // Specific directory
				$baseName = "lesson_{$lesson->id}_part_{$partIndex}_sent_{$sentenceIndex}_" . time(); // Unique name

				// Use Helper function to handle resizing and saving
				$imagePaths = AudioImageHelper::handleImageProcessing($file, $baseDir, $baseName);

				if ($imagePaths) {
					// --- Delete old image if it exists and was 'upload' or 'freepik' source ---
					if (!empty($sentenceData['generated_image_id'])) {
						$oldImage = GeneratedImage::find($sentenceData['generated_image_id']);
						if ($oldImage && in_array($oldImage->source, ['upload', 'freepik'])) {
							Log::info("Deleting old uploaded/freepik image files for Sentence {$sentenceIndex}, Image ID: {$oldImage->id}");
							$oldImage->deleteStorageFiles();
							$oldImage->delete(); // Delete the record too
						}
					}
					// --- End Delete old image ---

					try {
						// Create GeneratedImage record for the upload
						$imageRecord = GeneratedImage::create([
							'image_type' => 'sentence', // Or keep 'generated'? Let's use 'sentence' for clarity
							'source' => 'upload',
							'image_guid' => Str::uuid()->toString(), // Generate a GUID for uploads too? Optional but can be useful.
							'image_alt' => "Uploaded image for sentence: " . Str::limit($sentenceData['text'] ?? '', 50),
							'prompt' => null, // No AI prompt
							'image_model' => null,
							'image_size_setting' => null,
							'image_original_path' => $imagePaths['original_path'],
							'image_large_path' => $imagePaths['large_path'],
							'image_medium_path' => $imagePaths['medium_path'],
							'image_small_path' => $imagePaths['small_path'],
							'api_response_data' => null,
						]);

						// Update the sentence with the new image ID
						$lessonParts[$partIndex]['sentences'][$sentenceIndex]['generated_image_id'] = $imageRecord->id;
						$lesson->lesson_parts = $lessonParts;
						$lesson->save();

						Log::info("Image uploaded and linked for Sentence {$sentenceIndex}. New Image ID: {$imageRecord->id}");

						return response()->json([
							'success' => true,
							'message' => 'Image uploaded successfully!',
							'image_urls' => [ // Use accessors from the model
								'original' => $imageRecord->original_url,
								'large' => $imageRecord->large_url,
								'medium' => $imageRecord->medium_url,
								'small' => $imageRecord->small_url,
							],
							'prompt' => null, // No prompt for uploads
							'image_id' => $imageRecord->id,
							'partIndex' => $partIndex,
							'sentenceIndex' => $sentenceIndex,
						]);

					} catch (Exception $e) {
						Log::error("Error creating GeneratedImage record or saving lesson after upload: " . $e->getMessage());
						// Clean up newly uploaded files if DB save fails?
						Storage::disk('public')->delete(array_values($imagePaths));
						return response()->json(['success' => false, 'message' => 'Failed to process uploaded image.'], 500);
					}
				} else {
					return response()->json(['success' => false, 'message' => 'Failed to process image file after upload.'], 500);
				}
			}

			return response()->json(['success' => false, 'message' => 'No image file provided.'], 400);
		}


	} // End of CreateLessonController
