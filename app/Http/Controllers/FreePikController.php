<?php

	namespace App\Http\Controllers;

	use App\Helpers\MyHelper;
	use App\Models\GeneratedImage;
	use App\Models\Lesson;
	use App\Models\Question;
	use Exception;
	use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
	use Illuminate\Foundation\Validation\ValidatesRequests;
	use Illuminate\Http\UploadedFile;
	use Illuminate\Routing\Controller as BaseController;
	use Illuminate\Support\Facades\DB;
	use Illuminate\Support\Facades\Http;
	use Illuminate\Support\Facades\Log;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Storage;
	use Illuminate\Support\Facades\Validator;
	use Illuminate\Support\Str;
	use Intervention\Image\ImageManager;
	use Intervention\Image\Drivers\Gd\Driver;


	class FreePikController extends BaseController
	{
		use AuthorizesRequests, ValidatesRequests;

		public function searchFreepikAjax(Request $request, Question $question)
		{
			$validator = Validator::make($request->all(), [
				'query' => 'required|string|max:255',
				'page' => 'integer|min:1',
			]);

			if ($validator->fails()) {
				return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
			}

			$query = $request->input('query');
			$page = $request->input('page', 1);

			Log::info("Freepik search for Question {$question->id}. Query: '{$query}', Page: {$page}");

			// Use the existing helper function or directly call the API
			$result = $this->performFreepikApiSearch($query, $page);

			return response()->json($result); // Return the raw result (success, message, results, pagination)
		}

		/**
		 * NEW: Handle AJAX request to select and download a Freepik image.
		 */
		public function selectFreepikImageAjax(Request $request, Question $question)
		{
			$validator = Validator::make($request->all(), [
				'freepik_id' => 'required|numeric', // Freepik resource ID
				'description' => 'nullable|string|max:255',
				// May need download URL/token depending on Freepik API flow
				'download_token_or_url' => 'nullable|string', // Adjust as needed based on search response
			]);

			if ($validator->fails()) {
				return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
			}

			$freepikId = $request->input('freepik_id');
			$description = $request->input('description', 'Image from Freepik');
			$downloadUrl = $request->input('download_token_or_url');
			$baseDir = 'uploads/question_images/' . $question->lesson_id; // Organize by lesson

			Log::info("AJAX request to select Freepik image ID {$freepikId} for Question ID: {$question->id}");

			DB::beginTransaction();
			try {
				// Download and save the image, create a record
				$result = $this->downloadAndCreateImageRecord(
					$freepikId,
					$description,
					$downloadUrl,
					$baseDir,
					'question',
					false // Don't use Freepik API for download
				);

				if (!$result['success']) {
					throw new Exception($result['message']);
				}

				$newImage = $result['image_record'];

				// --- Step 5: Clean up old image files if replaced ---
				if ($question->generated_image_id) {
					$oldImage = GeneratedImage::find($question->generated_image_id);
					if ($oldImage && in_array($oldImage->source, ['upload', 'freepik'])) {
						Log::info("Deleting old image files (ID: {$oldImage->id}) replaced by Freepik selection for Question ID: {$question->id}");
						$oldImage->deleteStorageFiles();
						// $oldImage->delete(); // Optional: Delete record
					}
				}

				// --- Step 6: Link to Question ---
				$question->generated_image_id = $newImage->id;
				$question->save();

				DB::commit();

				Log::info("Freepik image {$freepikId} selected, downloaded, and linked for Question ID: {$question->id}. New Image ID: {$newImage->id}");
				return response()->json([
					'success' => true,
					'message' => 'Image selected successfully!',
					'image_id' => $newImage->id,
					'image_urls' => $result['image_urls'],
					'prompt' => $question->image_search_keywords
				]);

			} catch (Exception $e) {
				DB::rollBack();
				Log::error("Exception during Freepik image selection for Question ID {$question->id}: " . $e->getMessage(), ['exception' => $e]);
				return response()->json(['success' => false, 'message' => 'Server error during image selection: ' . $e->getMessage()], 500);
			}
		}


		public function searchFreepikSentenceAjax(Request $request, Lesson $lesson, int $partIndex, int $sentenceIndex)
		{
			$validator = Validator::make($request->all(), [
				'query' => 'required|string|max:255',
				'page' => 'integer|min:1',
			]);

			if ($validator->fails()) {
				return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
			}

			$query = $request->input('query');
			$page = $request->input('page', 1);

			Log::info("Freepik search for sentence {$partIndex}-{$sentenceIndex}, Lesson {$lesson->id}. Query: '{$query}', Page: {$page}");

			$result = $this->performFreepikApiSearch($query, $page);

			return response()->json($result); // Return the raw result (success, message, results, pagination)
		}

		/**
		 * AJAX: Select a Freepik image and link it to a specific sentence.
		 */
		public function selectFreepikSentenceImageAjax(Request $request, Lesson $lesson, int $partIndex, int $sentenceIndex)
		{
			$validator = Validator::make($request->all(), [
				'freepik_id' => 'required|string', // Freepik's image ID
				'description' => 'required|string|max:1000',
				'download_token_or_url' => 'required|string', // The preview URL from search results
			]);

			if ($validator->fails()) {
				return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
			}

			$freepikId = $request->input('freepik_id');
			$description = $request->input('description');
			$previewUrl = $request->input('download_token_or_url'); // Use the preview as the 'original' for now
			$baseDir = 'freepik/lesson_sentence_images';

			Log::info("Selecting Freepik image ID {$freepikId} for Lesson {$lesson->id}, Sentence {$partIndex}-{$sentenceIndex}");

			// --- Access Sentence Data ---
			$lessonParts = is_array($lesson->lesson_parts) ? $lesson->lesson_parts : json_decode($lesson->lesson_parts, true);
			if (!isset($lessonParts[$partIndex]['sentences'][$sentenceIndex])) {
				Log::error("Sentence index {$sentenceIndex} not found for part {$partIndex}, lesson {$lesson->id}.");
				return response()->json(['success' => false, 'message' => 'Sentence not found.'], 404);
			}
			$sentenceData = $lessonParts[$partIndex]['sentences'][$sentenceIndex];
			// --- End Access Sentence Data ---

			// --- Delete old image if it exists and was 'upload' or 'freepik' ---
			if (!empty($sentenceData['generated_image_id'])) {
				$oldImage = GeneratedImage::find($sentenceData['generated_image_id']);
				if ($oldImage && in_array($oldImage->source, ['upload', 'freepik'])) {
					Log::info("Deleting old uploaded/freepik image files for Sentence {$sentenceIndex}, Image ID: {$oldImage->id}");
					$oldImage->deleteStorageFiles();
					$oldImage->delete();
				}
			}
			// --- End Delete old image ---

			try {
				// Download and save the image, create a record
				$result = $this->downloadAndCreateImageRecord(
					$freepikId,
					$description,
					$previewUrl,
					$baseDir,
					'sentence',
					false // Don't use Freepik API for download
				);

				if (!$result['success']) {
					throw new Exception($result['message']);
				}

				$imageRecord = $result['image_record'];

				// Update the sentence with the new image ID
				$lessonParts[$partIndex]['sentences'][$sentenceIndex]['generated_image_id'] = $imageRecord->id;
				$lesson->lesson_parts = $lessonParts;
				$lesson->save();

				Log::info("Freepik Image {$freepikId} selected and linked for Sentence {$sentenceIndex}. New Image ID: {$imageRecord->id}");

				return response()->json([
					'success' => true,
					'message' => 'Freepik image selected successfully!',
					'image_urls' => $result['image_urls'],
					'prompt' => $description, // Return description as prompt/alt text
					'image_id' => $imageRecord->id,
					'partIndex' => $partIndex,
					'sentenceIndex' => $sentenceIndex,
				]);

			} catch (Exception $e) {
				Log::error("Error creating GeneratedImage record or saving lesson after Freepik selection: " . $e->getMessage());
				return response()->json(['success' => false, 'message' => 'Failed to save selected Freepik image link.'], 500);
			}
		}

		/**
		 * Download and create an image record for Freepik images
		 *
		 * @param string $freepikId The Freepik resource ID
		 * @param string $description Image description
		 * @param string $downloadUrl The URL to download from
		 * @param string $baseDir The base directory to save the image
		 * @param string $imageType The type of image (question or sentence)
		 * @param bool $useFreepikApiDownload Whether to use Freepik API for download
		 * @return array The result with image record and URLs
		 */
		private function downloadAndCreateImageRecord(
			string $freepikId,
			string $description,
			string $downloadUrl,
			string $baseDir,
			string $imageType = 'question',
			bool $useFreepikApiDownload = false
		) {
			$apiKey = env('FREEPIK_API_KEY');
			$actualImageUrl = $downloadUrl;
			$tempPath = null;

			try {
				if ($useFreepikApiDownload && $apiKey) {
					$downloadLinkResponse = Http::withHeaders([
						'Accept' => 'application/json',
						'X-Freepik-API-Key' => $apiKey
					])->get("https://api.freepik.com/v2/resources/{$freepikId}/download-link"); // Fictional endpoint - check Freepik docs!

					if (!$downloadLinkResponse->successful()) {
						Log::error('Freepik Download Link API Error:', ['status' => $downloadLinkResponse->status(), 'body' => $downloadLinkResponse->body()]);
						throw new Exception('Failed to get download link from provider. Status: ' . $downloadLinkResponse->status());
					}
					$downloadData = $downloadLinkResponse->json();
					$actualImageUrl = $downloadData['data']['link'] ?? null; // Fictional response structure

					if (!$actualImageUrl) {
						throw new Exception('Could not resolve the image download URL from provider.');
					}
				}

				// --- Step 2: Download the image content ---
				$imageContentResponse = Http::timeout(30)->get($actualImageUrl); // 30-second timeout
				if (!$imageContentResponse->successful()) {
					throw new Exception('Failed to download the image content from provider. Status: ' . $imageContentResponse->status());
				}
				$imageContent = $imageContentResponse->body();
				$originalFilename = pathinfo($actualImageUrl, PATHINFO_FILENAME);
				$baseName = Str::slug($originalFilename) . '_freepik_' . $freepikId . '_' . time(); // Unique base name
				$imageExtension = pathinfo($actualImageUrl, PATHINFO_EXTENSION);
				$imageExtension = $imageExtension ?: 'jpg'; // Default to jpg if no extension found
				$imagePath = $baseDir . '/' . $baseName . '.' . $imageExtension;
				$tempPath = storage_path('app/public/' . $imagePath); // Full path for storage

				// Ensure the directory exists
				Storage::makeDirectory('public/' . $baseDir, 0755, true, true);

				Log::info("Downloading Freepik image to: {$tempPath}");
				file_put_contents($tempPath, $imageContent);

				// --- Step 3: Process and Save Locally ---
				$imagePaths = MyHelper::handleImageProcessing($tempPath, $baseDir, $baseName);

				if (!$imagePaths) {
					throw new Exception('Failed to process and save the downloaded image.');
				}

				// --- Step 4: Create GeneratedImage Record ---
				$newImage = GeneratedImage::create([
					'image_type' => $imageType,
					'image_guid' => Str::uuid(), // Unique GUID for image set
					'source' => 'freepik',
					'prompt' => $description,
					'image_alt' => $description,
					'image_model' => 'freepik-' . $freepikId, // Store Freepik ID
					'image_size_setting' => strlen($imageContent), // Store original size
					'image_original_path' => $imagePaths['original_path'],
					'image_large_path' => $imagePaths['large_path'],
					'image_medium_path' => $imagePaths['medium_path'],
					'image_small_path' => $imagePaths['small_path'],
					'api_response_data' => ['freepik_id' => $freepikId, 'original_url' => $actualImageUrl],
				]);

				// Return the newly created image record and URLs
				$newImage->refresh();
				$imageUrls = [
					'small' => $newImage->small_url,
					'medium' => $newImage->medium_url,
					'large' => $newImage->large_url,
					'original' => $newImage->original_url,
				];

				return [
					'success' => true,
					'image_record' => $newImage,
					'image_urls' => $imageUrls,
				];

			} catch (Exception $e) {
				// Clean up temp file if it exists on error
				if (isset($tempPath) && file_exists($tempPath)) {
					unlink($tempPath);
				}
				Log::error("Exception during Freepik image processing: " . $e->getMessage(), ['exception' => $e]);
				return ['success' => false, 'message' => $e->getMessage()];
			}
		}

		// Helper function (keep or adapt from existing)
		private function performFreepikApiSearch(string $query, int $page = 1, int $limit = 12)
		{
			$apiKey = env('FREEPIK_API_KEY');
			if (!$apiKey) {
				Log::error('Freepik API key is not configured.');
				return ['success' => false, 'message' => 'Image search service not configured.'];
			}

			$url = 'https://api.freepik.com/v1/resources';

			try {
				$response = Http::withHeaders([
					'Accept-Language' => 'en-US', // Or configure based on lesson language?
					'Accept' => 'application/json',
					'X-Freepik-API-Key' => $apiKey,
				])->timeout(30)->get($url, [
					'term' => $query,
					'page' => $page,
					'limit' => $limit,
					'order' => 'latest', // Or 'popular'
					'filters[content_type][photo]' => 1, // Only photos? or 'vector', 'illustration', 'psd'
					'filters[orientation][square]' => 1, // Prefer square
				]);

				if ($response->failed()) {
					Log::error('Freepik API error. Status: ' . $response->status() . ' Body: ' . $response->body());
					return ['success' => false, 'message' => 'Error communicating with image search service (Status: ' . $response->status() . ')'];
				}

				$data = $response->json();
				//Log::info('Freepik API response: ', ['data' => $data]);

				if (!isset($data['data']) || !is_array($data['data'])) {
					Log::error('Unexpected Freepik API response structure.', ['response' => $data]);
					return ['success' => false, 'message' => 'Unexpected response from image search service.'];
				}

				$results = [];
				foreach ($data['data'] as $item) {
					// Find a suitable preview URL (e.g., medium size)
					$previewUrl = $item['image']['source']['url'] ?? null;
					$previewUrl = $item['image']['source']['url'] ?? null;
					if ($previewUrl) {
						$results[] = [
							'id' => $item['id'],
							'description' => $item['title'] ?? 'Freepik Image',
							'preview_url' => $previewUrl,
							// Add other relevant fields if needed (e.g., author, source URL)
						];
					}
				}

				// Basic pagination info (Freepik response structure might vary)
				$pagination = [
					'current_page' => $data['meta']['current_page'] ?? $page,
					'per_page' => $data['pagination']['limit'] ?? $limit,
					'total_pages' => $data['meta']['last_page'] ?? 0, // Might need calculation based on total results
					'total_results' => $data['meta']['total'] ?? 0,
				];

				Log::info("Freepik search results: " . count($results) . " items found. Page: {$page}, Limit: {$limit}");
				return [
					'success' => true,
					'results' => $results,
					'pagination' => $pagination,
				];

			} catch (Exception $e) {
				Log::error('Exception during Freepik search: ' . $e->getMessage());
				return ['success' => false, 'message' => 'An error occurred during image search.'];
			}
		}
	}
