<?php

	namespace App\Models;

	use Illuminate\Database\Eloquent\Factories\HasFactory;
	use Illuminate\Database\Eloquent\Model;
	use Illuminate\Validation\Rule; // Import Rule

	class MainCategory extends Model
	{
		use HasFactory;

		protected $fillable = ['name'];

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
			return [
				'name' => 'required|string|max:255|unique:main_categories,name',
			];
		}

		/**
		 * Validation rules for update (ignore self).
		 */
		public static function updateValidationRules($mainCategoryId): array
		{
			return [
				'name' => [
					'required',
					'string',
					'max:255',
					Rule::unique('main_categories', 'name')->ignore($mainCategoryId),
				]
			];
		}
	}
