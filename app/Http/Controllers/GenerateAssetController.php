<?php namespace App\Http\Controllers;

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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Intervention\Image\Laravel\Facades\Image as InterventionImage; // For image resizing
use Illuminate\Http\UploadedFile; // For type hinting
use Illuminate\Support\Facades\DB; // For transactions
use Exception; // Add Exception import

class GenerateAssetController extends Controller
{
	/**
	 * Generates audio and image prompts for sentences in the lesson content.
	 *
	 * @param Request $request
	 * @param Lesson $lesson
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function generateLessonContentAssetsAjax(Request $request, Lesson $lesson)
	{
		$this->authorize('generateAssets', $lesson);
		Log::info("AJAX request to generate sentence assets for Lesson ID: {$lesson->id}");

		$ttsEngine = $lesson->ttsEngine ?? env('DEFAULT_TTS_ENGINE', 'openai');
		$ttsVoice = $lesson->ttsVoice ?? ($ttsEngine === 'openai' ? 'alloy' : 'en-US-Studio-O');
		$ttsLanguageCode = $lesson->ttsLanguageCode ?? 'en-US';
		$llm = $lesson->preferredLlm ?? env('DEFAULT_LLM');

		if (empty($llm)) {
			Log::error("No LLM configured for generating sentence image ideas for Lesson ID {$lesson->id}.");
			return response()->json(['success' => false, 'message' => 'AI model for image ideas is not configured.'], 500);
		}

		// MODIFIED: Get single lesson content
		$lessonContent = is_array($lesson->lesson_content) ? $lesson->lesson_content : json_decode($lesson->lesson_content, true);

		if (!is_array($lessonContent)) {
			Log::error("Invalid lesson content data for Lesson ID: {$lesson->id}.");
			// Ensure lesson_content is an array for saving later
			$lessonContent = ['title' => null, 'text' => null, 'image_prompt_idea' => null, 'sentences' => []];
			// return response()->json(['success' => false, 'message' => 'Invalid lesson content structure.'], 400);
		}

		$lessonText = $lessonContent['text'] ?? '';

		if (empty($lessonText)) {
			Log::warning("Lesson text is empty for Lesson ID: {$lesson->id}. Cannot generate assets.");
			$lessonContent['sentences'] = [];
			$lessonContent['audio_generated_at'] = Carbon::now()->toIso8601String();
			$lesson->lesson_content = $lessonContent;
			$lesson->save();
			return response()->json(['success' => true, 'message' => 'Lesson text was empty. No assets generated.', 'sentences' => []]);
		}

		$sentencesText = LlmHelper::splitIntoSentences($lessonText);
		if (empty($sentencesText)) {
			Log::warning("Could not split lesson text into sentences for Lesson ID: {$lesson->id}.");
			$lessonContent['sentences'] = [];
			$lessonContent['audio_generated_at'] = Carbon::now()->toIso8601String();
			$lesson->lesson_content = $lessonContent;
			$lesson->save();
			return response()->json(['success' => true, 'message' => 'Could not split text into sentences.', 'sentences' => []]);
		}

		$processedSentences = [];
		$overallSuccess = true;

		// --- Cleanup Old Audio Files for this Lesson Content ---
		$oldSentences = $lessonContent['sentences'] ?? [];
		foreach ($oldSentences as $oldSentence) {
			if (!empty($oldSentence['audio_path']) && Storage::disk('public')->exists($oldSentence['audio_path'])) {
				try {
					Storage::disk('public')->delete($oldSentence['audio_path']);
					Log::info("Deleted old sentence audio: " . $oldSentence['audio_path']);
				} catch (Exception $e) {
					Log::warning("Could not delete old sentence audio file: " . $oldSentence['audio_path'] . " - " . $e->getMessage());
				}
			}
		}
		// --- End Cleanup ---

		foreach ($sentencesText as $sentenceIndex => $sentenceText) {
			$sentenceData = [
				'text' => $sentenceText,
				'audio_path' => null,
				'audio_url' => null,
				'image_prompt_idea' => null,
				'image_search_keywords' => null,
				'generated_image_id' => null,
			];

			$filenameBase = "lessons/{$lesson->id}/sent{$sentenceIndex}_" . Str::slug(Str::limit($sentenceText, 20));
			$audioResult = AudioImageHelper::text2speech($sentenceText, $ttsVoice, $ttsLanguageCode, $filenameBase, $ttsEngine);
			if ($audioResult['success']) {
				$sentenceData['audio_path'] = $audioResult['storage_path'];
				$sentenceData['audio_url'] = $audioResult['fileUrl'];
			} else {
				Log::warning("Failed to generate audio for sentence {$sentenceIndex}, Lesson {$lesson->id}. Error: " . ($audioResult['message'] ?? 'Unknown TTS error'));
				$overallSuccess = false;
			}

			$imageIdeaResult = EditLessonController::generateSentenceImageIdeas($llm, $sentenceText);
			if (!isset($imageIdeaResult['error'])) {
				$sentenceData['image_prompt_idea'] = $imageIdeaResult['image_prompt_idea'];
				$sentenceData['image_search_keywords'] = $imageIdeaResult['image_search_keywords'];
			} else {
				Log::warning("Failed to generate image ideas for sentence {$sentenceIndex}, Lesson {$lesson->id}. Error: " . ($imageIdeaResult['error'] ?? 'Unknown LLM error'));
			}
			$processedSentences[] = $sentenceData;
		}

		try {
			// MODIFIED: Update the single lesson_content object
			$lessonContent['sentences'] = $processedSentences;
			$lessonContent['audio_generated_at'] = Carbon::now()->toIso8601String();
			$lesson->lesson_content = $lessonContent; // Assign the modified object back
			$lesson->save();

			Log::info("Successfully generated sentence assets for Lesson ID: {$lesson->id}. Sentences processed: " . count($processedSentences));

			return response()->json([
				'success' => true,
				'message' => 'Sentence audio and image prompts generated.',
				'sentences' => $processedSentences,
			]);
		} catch (Exception $e) {
			Log::error("Error saving updated lesson content for Lesson ID {$lesson->id}: " . $e->getMessage());
			return response()->json(['success' => false, 'message' => 'Failed to save sentence assets to lesson.'], 500);
		}
	}

	public function generateQuestionAudioAjax(Question $question)
	{
		$this->authorize('generateAssets', $question->lesson);
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

			$questionIdentifier = "s{$question->lesson_id}_q{$question->id}";
			$outputFilenameBase = 'audio/question_q_' . $questionIdentifier;

			$audioResult = AudioImageHelper::text2speech(
				$question->question_text,
				$ttsVoice,
				$ttsLanguageCode,
				$outputFilenameBase,
				$ttsEngine
			);

			if ($audioResult['success'] && isset($audioResult['storage_path'])) {
				$question->question_audio_path = $audioResult['storage_path'];
				$question->save();
				$generatedUrl = $question->fresh()->question_audio_url;
				Log::info("Question audio generated for Question ID: {$question->id}. Path: {$question->question_audio_path}, URL: {$generatedUrl}");
				return response()->json([
					'success' => true,
					'message' => 'Question audio generated successfully!',
					'audio_url' => $generatedUrl,
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
		$this->authorize('generateAssets', $question->lesson);
		Log::info("AJAX request to generate answer/feedback audio for Question ID: {$question->id}");
		$currentAnswers = $question->answers ?? [];
		if (empty($currentAnswers) || !is_array($currentAnswers)) {
			Log::error("Cannot generate answer audio for Question ID {$question->id}: Answers data is missing or invalid.");
			return response()->json(['success' => false, 'message' => 'Question answers data is missing or invalid.'], 400);
		}
		try {
			$lesson = $question->lesson;
			$ttsEngine = $lesson->ttsEngine ?? env('DEFAULT_TTS_ENGINE', 'google');
			$ttsVoice = $lesson->ttsVoice;
			$ttsLanguageCode = $lesson->ttsLanguageCode;
			if (empty($ttsVoice) || empty($ttsLanguageCode)) {
				Log::error("Missing TTS Voice or Language Code in Lesson {$lesson->id} for QID {$question->id} answers");
				return response()->json(['success' => false, 'message' => 'Lesson TTS settings are incomplete.'], 400);
			}

			$questionIdentifier = "s{$question->lesson_id}_q{$question->id}";
			$filenamePrefix = 'audio/question_' . $questionIdentifier;

			$processedAnswers = Question::processAnswersWithTTS(
				$currentAnswers,
				$question->id,
				$filenamePrefix,
				$ttsEngine,
				$ttsVoice,
				$ttsLanguageCode
			);
			$question->answers = $processedAnswers;
			$question->save();
			Log::info("Answer/feedback audio generation complete for Question ID: {$question->id}");
			return response()->json([
				'success' => true,
				'message' => 'Answer and feedback audio generated successfully!',
				'answers' => $processedAnswers
			]);
		} catch (\Exception $e) {
			Log::error("Exception during answer/feedback audio generation for Question ID {$question->id}: " . $e->getMessage());
			return response()->json(['success' => false, 'message' => 'Server error during audio generation: ' . $e->getMessage()], 500);
		}
	}

	public function generateQuestionImageAjax(Request $request, Question $question)
	{
		$this->authorize('generateAssets', $question->lesson);
		$newPrompt = $request->input('new_prompt');
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

		try {
			$promptToUse = $question->image_prompt_idea;
			if (empty($promptToUse)) {
				throw new \Exception('Image prompt is unexpectedly empty.');
			}
			$imageModel = env('DEFAULT_IMAGE_MODEL', 'fal-ai/flux/schnell');
			$imageSize = 'square_hd';
			Log::info("Calling makeImage for Question {$question->id}. Model: {$imageModel}, Size: {$imageSize}, Prompt: '{$promptToUse}'");
			$imageResult = AudioImageHelper::makeImage(
				$promptToUse,
				$imageModel,
				$imageSize
			);

			if ($imageResult['success'] && isset($imageResult['image_id'], $imageResult['image_urls'])) {
				$question->generated_image_id = $imageResult['image_id'];
				$question->save();
				Log::info("Image " . ($newPrompt ? "regenerated" : "generated") . " and linked for Question ID: {$question->id}. Image ID: {$imageResult['image_id']}");
				return response()->json([
					'success' => true,
					'message' => 'Image ' . ($newPrompt ? "regenerated" : "generated") . ' successfully!',
					'image_id' => $imageResult['image_id'],
					'image_guid' => $imageResult['image_guid'] ?? null,
					'image_urls' => $imageResult['image_urls'],
					'prompt' => $promptToUse
				]);
			} else {
				if ($imageResult['success'] && isset($imageResult['image_guid'])) {
					$imageModelRecord = GeneratedImage::where('image_guid', $imageResult['image_guid'])->first(); // Renamed variable
					if ($imageModelRecord) {
						$question->generated_image_id = $imageModelRecord->id;
						$question->save();
						Log::info("Image " . ($newPrompt ? "regenerated" : "generated") . " and linked (fallback lookup by GUID) for Question ID: {$question->id}. Image ID: {$imageModelRecord->id}");
						$imageUrls = [
							'small' => $imageModelRecord->small_url,
							'medium' => $imageModelRecord->medium_url,
							'large' => $imageModelRecord->large_url,
							'original' => $imageModelRecord->original_url,
						];
						return response()->json([
							'success' => true,
							'message' => 'Image ' . ($newPrompt ? "regenerated" : "generated") . ' successfully!',
							'image_id' => $imageModelRecord->id,
							'image_guid' => $imageResult['image_guid'],
							'image_urls' => $imageUrls,
							'prompt' => $promptToUse
						]);
					} else {
						Log::error("Image generation reported success with GUID {$imageResult['image_guid']} but GeneratedImage record not found. Question ID {$question->id}");
						throw new \Exception('Image generation succeeded but failed to find/link the image record.');
					}
				}
				throw new \Exception($imageResult['message'] ?? 'Image generation failed');
			}
		} catch (\Exception $e) {
			Log::error("Exception during image generation/regeneration for Question ID {$question->id}: " . $e->getMessage(), ['exception' => $e]);
			if ($newPrompt) {
				Log::warning("Image regeneration failed, but the new prompt '{$newPrompt}' remains saved for Question ID {$question->id}.");
			}
			return response()->json(['success' => false, 'message' => 'Server error during image generation: ' . $e->getMessage()], 500);
		}
	}

	public function uploadQuestionImageAjax(Request $request, Question $question)
	{
		$this->authorize('generateAssets', $question->lesson);
		$validator = Validator::make($request->all(), [
			'question_image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
		]);
		if ($validator->fails()) {
			return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
		}
		Log::info("AJAX request to upload image for Question ID: {$question->id}");
		/** @var UploadedFile $uploadedFile */
		$uploadedFile = $request->file('question_image');
		$originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
		$baseDir = 'uploads/question_images/' . $question->lesson_id;
		$baseName = Str::slug($originalFilename) . '_' . time();
		DB::beginTransaction();
		try {
			$imagePaths = AudioImageHelper::handleImageProcessing($uploadedFile, $baseDir, $baseName);
			if (!$imagePaths) {
				throw new Exception('Failed to process and save the uploaded image.');
			}
			$newImage = GeneratedImage::create([
				'image_type' => 'question',
				'image_guid' => Str::uuid(),
				'source' => 'upload',
				'prompt' => 'User Upload: ' . $uploadedFile->getClientOriginalName(),
				'image_model' => 'upload',
				'image_size_setting' => $uploadedFile->getSize(),
				'image_original_path' => $imagePaths['original_path'],
				'image_large_path' => $imagePaths['large_path'],
				'image_medium_path' => $imagePaths['medium_path'],
				'image_small_path' => $imagePaths['small_path'],
				'api_response_data' => ['original_filename' => $uploadedFile->getClientOriginalName()],
			]);
			if ($question->generated_image_id) {
				$oldImage = GeneratedImage::find($question->generated_image_id);
				if ($oldImage && in_array($oldImage->source, ['upload', 'freepik'])) {
					Log::info("Deleting old image files (ID: {$oldImage->id}) replaced by upload for Question ID: {$question->id}");
					$oldImage->deleteStorageFiles();
				}
			}
			$question->generated_image_id = $newImage->id;
			$question->save();
			DB::commit();
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

	public function generateSentenceImageAjax(Request $request, Lesson $lesson, int $sentenceIndex)
	{
		$this->authorize('generateAssets', $lesson);
		$validator = Validator::make($request->all(), [
			'prompt' => 'required|string|max:1000',
			'image_model' => 'nullable|string|max:100',
			'size' => 'nullable|string|max:50',
		]);
		if ($validator->fails()) {
			return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
		}
		Log::info("AJAX request to generate AI image for Lesson ID: {$lesson->id}, Sentence: {$sentenceIndex}");
		$prompt = $request->input('prompt');
		$imageModel = $request->input('image_model', env('DEFAULT_IMAGE_GEN_MODEL', 'fal-ai/flux/schnell'));
		$size = $request->input('size', 'square_hd');

		// MODIFIED: Access sentence data from single lesson content
		$lessonContent = is_array($lesson->lesson_content) ? $lesson->lesson_content : json_decode($lesson->lesson_content, true);
		if (!isset($lessonContent['sentences'][$sentenceIndex])) {
			Log::error("Sentence index {$sentenceIndex} not found for lesson {$lesson->id}.");
			return response()->json(['success' => false, 'message' => 'Sentence not found.'], 404);
		}

		$result = AudioImageHelper::makeImage($prompt, $imageModel, $size);
		if ($result['success']) {
			try {
				// MODIFIED: Update sentence in the single lesson content object
				$lessonContent['sentences'][$sentenceIndex]['generated_image_id'] = $result['image_id'];
				$lessonContent['sentences'][$sentenceIndex]['image_prompt_idea'] = $prompt;
				$lesson->lesson_content = $lessonContent;
				$lesson->save();
				Log::info("AI Image generated and linked for Lesson ID: {$lesson->id}, Sentence: {$sentenceIndex}. Image ID: {$result['image_id']}");
				return response()->json([
					'success' => true,
					'message' => 'AI Image generated successfully!',
					'image_urls' => $result['image_urls'],
					'prompt' => $prompt,
					'image_id' => $result['image_id'],
					'sentenceIndex' => $sentenceIndex,
				]);
			} catch (Exception $e) {
				Log::error("Error saving generated_image_id for sentence: " . $e->getMessage());
				return response()->json(['success' => false, 'message' => 'Image generated but failed to link to sentence.'], 500);
			}
		} else {
			Log::error("Failed to generate AI Image for sentence. Error: " . ($result['message'] ?? 'Unknown image generation error'));
			return response()->json([
				'success' => false,
				'message' => $result['message'] ?? 'Failed to generate image.',
				'details' => $result['details'] ?? null
			], isset($result['status_code']) && $result['status_code'] >= 500 ? 502 : 400);
		}
	}

	public function uploadSentenceImageAjax(Request $request, Lesson $lesson, int $sentenceIndex)
	{
		$this->authorize('generateAssets', $lesson);
		$validator = Validator::make($request->all(), [
			'sentence_image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
		]);
		if ($validator->fails()) {
			return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
		}
		Log::info("AJAX request to upload image for Lesson ID: {$lesson->id}, Sentence: {$sentenceIndex}");

		// MODIFIED: Access sentence data from single lesson content
		$lessonContent = is_array($lesson->lesson_content) ? $lesson->lesson_content : json_decode($lesson->lesson_content, true);
		if (!isset($lessonContent['sentences'][$sentenceIndex])) {
			Log::error("Sentence index {$sentenceIndex} not found for lesson {$lesson->id}.");
			return response()->json(['success' => false, 'message' => 'Sentence not found.'], 404);
		}
		$sentenceData = $lessonContent['sentences'][$sentenceIndex];

		if ($request->hasFile('sentence_image')) {
			$file = $request->file('sentence_image');
			$baseDir = 'uploads/lesson_sentence_images';
			$baseName = "lesson_{$lesson->id}_sent_{$sentenceIndex}_" . time();
			$imagePaths = AudioImageHelper::handleImageProcessing($file, $baseDir, $baseName);

			if ($imagePaths) {
				if (!empty($sentenceData['generated_image_id'])) {
					$oldImage = GeneratedImage::find($sentenceData['generated_image_id']);
					if ($oldImage && in_array($oldImage->source, ['upload', 'freepik'])) {
						Log::info("Deleting old uploaded/freepik image files for Sentence {$sentenceIndex}, Image ID: {$oldImage->id}");
						$oldImage->deleteStorageFiles();
						$oldImage->delete();
					}
				}
				try {
					$imageRecord = GeneratedImage::create([
						'image_type' => 'sentence',
						'source' => 'upload',
						'image_guid' => Str::uuid()->toString(),
						'image_alt' => "Uploaded image for sentence: " . Str::limit($sentenceData['text'] ?? '', 50),
						'prompt' => null,
						'image_model' => null,
						'image_size_setting' => null,
						'image_original_path' => $imagePaths['original_path'],
						'image_large_path' => $imagePaths['large_path'],
						'image_medium_path' => $imagePaths['medium_path'],
						'image_small_path' => $imagePaths['small_path'],
						'api_response_data' => null,
					]);

					// MODIFIED: Update sentence in the single lesson content object
					$lessonContent['sentences'][$sentenceIndex]['generated_image_id'] = $imageRecord->id;
					$lesson->lesson_content = $lessonContent;
					$lesson->save();

					Log::info("Image uploaded and linked for Sentence {$sentenceIndex}. New Image ID: {$imageRecord->id}");
					return response()->json([
						'success' => true,
						'message' => 'Image uploaded successfully!',
						'image_urls' => [
							'original' => $imageRecord->original_url,
							'large' => $imageRecord->large_url,
							'medium' => $imageRecord->medium_url,
							'small' => $imageRecord->small_url,
						],
						'prompt' => null,
						'image_id' => $imageRecord->id,
						'sentenceIndex' => $sentenceIndex,
					]);
				} catch (Exception $e) {
					Log::error("Error creating GeneratedImage record or saving lesson after upload: " . $e->getMessage());
					Storage::disk('public')->delete(array_values($imagePaths));
					return response()->json(['success' => false, 'message' => 'Failed to process uploaded image.'], 500);
				}
			} else {
				return response()->json(['success' => false, 'message' => 'Failed to process image file after upload.'], 500);
			}
		}
		return response()->json(['success' => false, 'message' => 'No image file provided.'], 400);
	}
}
