<?php

	namespace App\Models;

	use Illuminate\Database\Eloquent\Factories\HasFactory;
	use Illuminate\Database\Eloquent\Model;
	use Illuminate\Support\Facades\Auth;
	use Illuminate\Validation\Rule;

	// Import Rule

	class MainCategory extends Model
	{
		use HasFactory;

		protected $fillable = ['name', 'user_id'];

		protected $casts = [
			'user_id' => 'integer',
		];

		public function user()
		{
			return $this->belongsTo(User::class);
		}

		/**
		 * Get the sub-category_management for the main category.
		 */
		public function subCategories()
		{
			return $this->hasMany(SubCategory::class);
		}

		/**
		 * Get the lessons associated with this main category through its sub-category_management.
		 */
		public function lessons()
		{
			return $this->hasManyThrough(Lesson::class, SubCategory::class);
		}

		/**
		 * Basic validation rules.
		 */
		public static function validationRules(): array
		{
			$userId = Auth::id(); // Get current user ID
			return [
				'name' => [
					'required',
					'string',
					'max:255',
					// Unique name per user
					Rule::unique('main_categories', 'name')->where(function ($query) use ($userId) {
						return $query->where('user_id', $userId);
					}),
				],
				// user_id will be set automatically in the controller
			];
		}


		/**
		 * Validation rules for update (ignore self).
		 */
		public static function updateValidationRules($mainCategoryId): array
		{
			$userId = Auth::id(); // Get current user ID
			return [
				'name' => [
					'required',
					'string',
					'max:255',
					Rule::unique('main_categories', 'name')
						->ignore($mainCategoryId) // Ignore the current category ID
						->where(function ($query) use ($userId) { // Scope by user
							return $query->where('user_id', $userId);
						}),
				]
				// user_id should not be changed here
			];
		}
	}
