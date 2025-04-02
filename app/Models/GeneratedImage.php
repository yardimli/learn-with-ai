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

		// Accessors for URLs
		public function getOriginalUrlAttribute() {
			return $this->image_original_path ? Storage::disk('public')->url($this->image_original_path) : null;
		}
		public function getLargeUrlAttribute() {
			return $this->image_large_path ? Storage::disk('public')->url($this->image_large_path) : null;
		}
		public function getMediumUrlAttribute() {
			return $this->image_medium_path ? Storage::disk('public')->url($this->image_medium_path) : null;
		}
		public function getSmallUrlAttribute() {
			return $this->image_small_path ? Storage::disk('public')->url($this->image_small_path) : null;
		}
	}
