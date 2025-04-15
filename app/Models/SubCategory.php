<?php

	namespace App\Models;

	use Illuminate\Database\Eloquent\Factories\HasFactory;
	use Illuminate\Database\Eloquent\Model;
	use Illuminate\Validation\Rule; // Import Rule


	class SubCategory extends Model
	{
		use HasFactory;

		protected $fillable = ['name', 'main_category_id'];

		/**
		 * Get the main category that owns the sub-category.
		 */
		public function mainCategory()
		{
			return $this->belongsTo(MainCategory::class);
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
		public static function validationRules(): array
		{
			return [
				'name' => [
					'required',
					'string',
					'max:255',
					// Rule::unique('sub_categories')->where(function ($query) use ($mainCategoryId) { // This requires passing main_category_id
					//     return $query->where('main_category_id', $mainCategoryId);
					// })
					// Simpler way for create (needs main_category_id context):
					// In the controller: 'name' => ['required', 'string', 'max:255', Rule::unique('sub_categories')->where('main_category_id', $request->main_category_id)],
					'unique:sub_categories,name,NULL,id,main_category_id,' . request('main_category_id') // Make sure main_category_id is in request
				],
				'main_category_id' => 'required|integer|exists:main_categories,id',

			];
		}

		/**
		 * Validation rules for update (ignore self, check uniqueness within main category).
		 */
		public static function updateValidationRules($subCategoryId, $mainCategoryId): array
		{
			// Check if main_category_id is changing
			$subCategory = self::find($subCategoryId);
			$currentMainCategoryId = $subCategory ? $subCategory->main_category_id : null;
			$targetMainCategoryId = request('main_category_id', $currentMainCategoryId); // Get target main category

			return [
				'name' => [
					'required',
					'string',
					'max:255',
					Rule::unique('sub_categories', 'name')
						->where('main_category_id', $targetMainCategoryId)
						->ignore($subCategoryId), // Ignore the current sub-category ID
				],
				'main_category_id' => 'required|integer|exists:main_categories,id',
			];
		}
	}
