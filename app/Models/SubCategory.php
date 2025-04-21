<?php

	namespace App\Models;

	use Illuminate\Database\Eloquent\Factories\HasFactory;
	use Illuminate\Database\Eloquent\Model;
	use Illuminate\Validation\Rule;

	// Import Rule


	class SubCategory extends Model
	{
		use HasFactory;

		protected $fillable = ['name', 'main_category_id', 'user_id'];

		protected $casts = [
			'user_id' => 'integer',
			'main_category_id' => 'integer',
		];


		public function user()
		{
			return $this->belongsTo(User::class);
		}


		/**
		 * Get the main category that owns the sub-category.
		 */
		public function mainCategory()
		{
			return $this->belongsTo(MainCategory::class)->where('user_id', $this->user_id);
		}

		/**
		 * Get the lessons associated with the sub-category.
		 */
		public function lessons()
		{
			return $this->hasMany(Lesson::class);
		}

		/**
		 * Basic validation rules.
		 */
		public static function validationRules(Request $request): array
		{
			$userId = Auth::id();
			$mainCategoryId = $request->input('main_category_id');

			return [
				'name' => [
					'required',
					'string',
					'max:255',
					Rule::unique('sub_categories')->where(function ($query) use ($userId, $mainCategoryId) {
						return $query->where('main_category_id', $mainCategoryId)
							->where('user_id', $userId); // Scope by user
					}),
				],
				'main_category_id' => [ // Validate that the selected main category belongs to the user
					'required',
					'integer',
					Rule::exists('main_categories', 'id')->where('user_id', $userId)
				],
				// user_id will be set automatically
			];
		}

		/**
		 * Validation rules for update (ignore self, check uniqueness within main category).
		 */
		public static function updateValidationRules(Request $request, $subCategoryId): array
		{
			$userId = Auth::id();
			$targetMainCategoryId = $request->input('main_category_id');

			return [
				'name' => [
					'required',
					'string',
					'max:255',
					Rule::unique('sub_categories', 'name')
						->where('main_category_id', $targetMainCategoryId)
						->where('user_id', $userId) // Scope by user
						->ignore($subCategoryId), // Ignore the current sub-category ID
				],
				'main_category_id' => [ // Validate that the selected main category belongs to the user
					'required',
					'integer',
					Rule::exists('main_categories', 'id')->where('user_id', $userId)
				],
				// user_id should not be changed
			];
		}
	}
