<?php

	namespace App\Http\Controllers;

	use App\Helpers\MyHelper;
	use App\Models\GeneratedImage;
	use App\Models\Quiz;
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

		/**
		 * NEW: Handle AJAX request to search Freepik.
		 * Note: Requires Freepik API key in .env (e.g., FREEPIK_API_KEY)
		 * and attribution requirements met if using free tier.
		 */
		public function searchFreepikAjax(Request $request, Quiz $quiz)
		{
			$validator = Validator::make($request->all(), [
				'query' => 'required|string|max:100',
				'page' => 'nullable|integer|min:1',
			]);

			if ($validator->fails()) {
				return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
			}

			$apiKey = env('FREEPIK_API_KEY');
			if (!$apiKey) {
				Log::error("Freepik API Key not configured.");
				return response()->json(['success' => false, 'message' => 'Image search service is not configured.'], 503); // Service Unavailable
			}

			$query = $request->input('query');
			$page = $request->input('page', 1);
			$perPage = 12; // Number of results per page

			Log::info("AJAX request to search Freepik for Quiz ID: {$quiz->id}. Query: '{$query}', Page: {$page}");

			try {
				// Check Freepik API docs for current endpoint and parameters
				// Example using v2 API (adjust if necessary)
				$response = Http::withHeaders([
					'Accept' => 'application/json',
					'x-freepik-api-key' => $apiKey
				])->get('https://api.freepik.com/v1/resources', [
					'page' => $page,
					'limit' => $perPage,
					'order' => 'relevance', // or 'popular'
					'term' => $query,
					// Add filters if needed, e.g., 'filters[orientation][values][0]' => 'landscape'
					// 'filters[type][values][0]' => 'photo'
				]);

				if (!$response->successful()) {
					Log::error('Freepik API Error:', ['status' => $response->status(), 'body' => $response->body()]);
					throw new Exception('Failed to fetch images from search provider. Status: ' . $response->status());
				}

				$data = $response->json();

				// --- Process results (extract relevant data) ---
				$results = [];
				if (isset($data['data']) && is_array($data['data'])) {
					foreach ($data['data'] as $item) {
						$previewUrl = $item['image']['source']['url'] ?? null; // Adjust based on Freepik's response structure
						if ($previewUrl && isset($item['id'])) {
							$results[] = [
								'id' => $item['id'], // Freepik's resource ID
								'preview_url' => $previewUrl,
								'description' => $item['title'] ?? 'Freepik Image',
							];
						}
					}
				}

				// --- Pagination Info (Optional but good UX) ---
				$pagination = [
					'current_page' => $data['meta']['current_page'] ?? $page,
					'total_pages' => $data['meta']['last_page'] ?? 0, // Might need calculation based on total results
					'total_results' => $data['meta']['total'] ?? 0,
				];


				return response()->json([
					'success' => true,
					'results' => $results,
					'data' => $data, // Full data for debugging
					'pagination' => $pagination, // Add pagination info
				]);

			} catch (Exception $e) {
				Log::error("Exception during Freepik search for Quiz ID {$quiz->id}: " . $e->getMessage(), ['exception' => $e]);
				return response()->json(['success' => false, 'message' => 'Server error during image search: ' . $e->getMessage()], 500);
			}
		}

		/**
		 * NEW: Handle AJAX request to select and download a Freepik image.
		 */
		public function selectFreepikImageAjax(Request $request, Quiz $quiz)
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
			$apiKey = env('FREEPIK_API_KEY'); // Needed again potentially

			Log::info("AJAX request to select Freepik image ID {$freepikId} for Quiz ID: {$quiz->id}");

			if (!$apiKey) {
				Log::error("Freepik API Key not configured for download.");
				return response()->json(['success' => false, 'message' => 'Image selection service is not configured.'], 503);
			}

			DB::beginTransaction();
			try {
				$use_freepik_api_to_download = false;
				if ($use_freepik_api_to_download) {
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
				} else {
					// Fallback to using the search result URL directly
					$actualImageUrl = $request->input('download_token_or_url');
				}

				// --- Step 2: Download the image content ---
				$imageContentResponse = Http::timeout(30)->get($actualImageUrl); // 30-second timeout
				if (!$imageContentResponse->successful()) {
					throw new Exception('Failed to download the image content from provider. Status: ' . $imageContentResponse->status());
				}
				$imageContent = $imageContentResponse->body();
				$originalFilename = pathinfo($actualImageUrl, PATHINFO_FILENAME);
				$baseDir = 'uploads/quiz_images/' . $quiz->subject_id; // Organize by subject
				$baseName = Str::slug($originalFilename) . '_freepik_source_' . time(); // Unique base name
				$imageExtension = pathinfo($actualImageUrl, PATHINFO_EXTENSION);
				$imageExtension = $imageExtension ?: 'jpg'; // Default to jpg if no extension found
				$imagePath = $baseDir . '/' . $baseName . '.' . $imageExtension;
				$tempPath = storage_path('app/public/' . $imagePath); // Full path for storage
				Log::info("Downloading Freepik image to: {$tempPath}");
				file_put_contents($tempPath, $imageContent);


				// --- Step 3: Process and Save Locally ---
				$baseName = 'freepik_' . $freepikId . '_' . time(); // Unique name
				$imagePaths = MyHelper::handleImageProcessing($tempPath, $baseDir, $baseName);

				if (!$imagePaths) {
					throw new Exception('Failed to process and save the downloaded image.');
				}

				// --- Step 4: Create GeneratedImage Record ---
				$newImage = GeneratedImage::create([
					'image_type' => 'quiz',
					'image_guid' => Str::uuid(), // Unique GUID for image set
					'source' => 'freepik',
					'prompt' => $description,
					'image_model' => 'freepik-' . $freepikId, // Store Freepik ID
					'image_size_setting' => strlen($imageContent), // Store original size
					'image_original_path' => $imagePaths['original_path'],
					'image_large_path' => $imagePaths['large_path'],
					'image_medium_path' => $imagePaths['medium_path'],
					'image_small_path' => $imagePaths['small_path'],
					'api_response_data' => ['freepik_id' => $freepikId, 'original_url' => $actualImageUrl],
				]);

				// --- Step 5: Clean up old image files if replaced ---
				if ($quiz->generated_image_id) {
					$oldImage = GeneratedImage::find($quiz->generated_image_id);
					if ($oldImage && in_array($oldImage->source, ['upload', 'freepik'])) {
						Log::info("Deleting old image files (ID: {$oldImage->id}) replaced by Freepik selection for Quiz ID: {$quiz->id}");
						$oldImage->deleteStorageFiles();
						// $oldImage->delete(); // Optional: Delete record
					}
				}

				// --- Step 6: Link to Quiz ---
				$quiz->generated_image_id = $newImage->id;
				$quiz->image_prompt_idea = $description; // Update prompt to description
				$quiz->save();

				DB::commit();

				// --- Step 7: Return Success Response ---
				$newImage->refresh();
				$imageUrls = [
					'small' => $newImage->small_url,
					'medium' => $newImage->medium_url,
					'large' => $newImage->large_url,
					'original' => $newImage->original_url,
				];

				Log::info("Freepik image {$freepikId} selected, downloaded, and linked for Quiz ID: {$quiz->id}. New Image ID: {$newImage->id}");
				return response()->json([
					'success' => true,
					'message' => 'Image selected successfully!',
					'image_id' => $newImage->id,
					'image_urls' => $imageUrls,
					'prompt' => $quiz->image_prompt_idea // Return updated prompt
				]);

			} catch (Exception $e) {
				DB::rollBack();
				// Clean up temp file if it exists on error
				if (isset($tempPath) && file_exists($tempPath)) {
					unlink($tempPath);
				}
				Log::error("Exception during Freepik image selection for Quiz ID {$quiz->id}: " . $e->getMessage(), ['exception' => $e]);
				return response()->json(['success' => false, 'message' => 'Server error during image selection: ' . $e->getMessage()], 500);
			}
		}
	}
