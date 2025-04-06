<?php

	namespace App\Models;

	use Illuminate\Database\Eloquent\Factories\HasFactory;
	use Illuminate\Database\Eloquent\Model;
	use Illuminate\Support\Facades\Storage; // For URL accessors

	class GeneratedImage extends Model
	{
		use HasFactory;

		// Table name explicit if class name differs significantly or for clarity
		protected $table = 'generated_images';

		protected $fillable = [
			'image_type',
			'image_guid',
			'image_alt',
			'prompt',
			'image_model',
			'image_size_setting',
			'source',
			'image_original_path',
			'image_large_path',
			'image_medium_path',
			'image_small_path',
			'api_response_data',
		];

		protected $casts = [
			'api_response_data' => 'array', // Cast JSON column to array
			'image_guid' => 'string', // Cast UUID if needed, though string is usually fine
		];

		public function subject()
		{
			return $this->belongsTo(Subject::class);
		}

		public function quiz()
		{
			return $this->belongsTo(Quiz::class);
		}

		// --- Accessors for URLs ---
		public function getOriginalUrlAttribute(): ?string {
			return $this->generateUrl($this->image_original_path);
		}
		public function getLargeUrlAttribute(): ?string {
			return $this->generateUrl($this->image_large_path);
		}
		public function getMediumUrlAttribute(): ?string {
			return $this->generateUrl($this->image_medium_path);
		}
		public function getSmallUrlAttribute(): ?string {
			return $this->generateUrl($this->image_small_path);
		}

		// --- Helper for URL generation ---
		private function generateUrl(?string $path): ?string {
			// Ensure the path exists before generating URL
			if ($path && Storage::disk('public')->exists($path)) {
				return Storage::disk('public')->url($path);
			}
			return null;
		}

		// --- Accessors for Paths (Useful for deletion) ---
		public function getOriginalStoragePathAttribute(): ?string {
			return $this->image_original_path;
		}
		public function getLargeStoragePathAttribute(): ?string {
			return $this->image_large_path;
		}
		public function getMediumStoragePathAttribute(): ?string {
			return $this->image_medium_path;
		}
		public function getSmallStoragePathAttribute(): ?string {
			return $this->image_small_path;
		}

		// Helper to delete associated storage files
		public function deleteStorageFiles(): void {
			$disk = Storage::disk('public');
			$pathsToDelete = [
				$this->original_storage_path,
				$this->large_storage_path,
				$this->medium_storage_path,
				$this->small_storage_path,
			];
			foreach ($pathsToDelete as $path) {
				if ($path && $disk->exists($path)) {
					$disk->delete($path);
				}
			}
		}
	}
