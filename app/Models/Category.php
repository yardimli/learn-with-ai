<?php

	namespace App\Models;

	use Illuminate\Database\Eloquent\Factories\HasFactory;
	use Illuminate\Database\Eloquent\Model;

	class Category extends Model
	{
		use HasFactory;

		protected $fillable = ['name'];

		/**
		 * Get the lessons associated with the category.
		 */
		public function lessons()
		{
			return $this->hasMany(Lesson::class);
		}

		/**
		 * Basic validation rules (can be used in controllers).
		 */
		public static function validationRules(): array
		{
			return [
				'name' => 'required|string|max:255|unique:categories,name',
			];
		}

		/**
		 * Validation rules for update (ignore self).
		 */
		public static function updateValidationRules($categoryId): array
		{
			return [
				'name' => 'required|string|max:255|unique:categories,name,' . $categoryId,
			];
		}
	}
