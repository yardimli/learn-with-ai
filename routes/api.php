<?php

	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Route;
	use App\Models\GeneratedImage;

	/*
	|--------------------------------------------------------------------------
	| API Routes
	|--------------------------------------------------------------------------
	|
	| Here is where you can register API routes for your application. These
	| routes are loaded by the RouteServiceProvider and all of them will
	| be assigned to the "api" middleware group. Make something great!
	|
	*/

	Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
		return $request->user();
	});


	Route::get('/image-details/{generatedImage}', function (GeneratedImage $generatedImage) {
		// Basic authorization could be added here if needed
		return response()->json([
			'success' => true,
			'image_urls' => [
				'original' => $generatedImage->original_url,
				'large' => $generatedImage->large_url,
				'medium' => $generatedImage->medium_url,
				'small' => $generatedImage->small_url,
			],
			'alt' => $generatedImage->image_alt ?? 'Generated Image',
		]);
	})->name('api.image.details');
