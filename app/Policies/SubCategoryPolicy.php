<?php

	namespace App\Policies;

	use App\Models\SubCategory;
	use App\Models\User;
	use Illuminate\Auth\Access\Response;

	class SubCategoryPolicy
	{
		/**
		 * Determine whether the user can view any models.
		 */
		public function viewAny(User $user): bool
		{
			return true; // Any authenticated user can see the list page (filtered later)
		}

		/**
		 * Determine whether the user can view the model.
		 */
		public function view(User $user, SubCategory $subCategory): bool
		{
			return $user->id === $subCategory->user_id;
		}

		/**
		 * Determine whether the user can create models.
		 */
		public function create(User $user): bool
		{
			return true; // Any authenticated user can attempt to create
		}

		/**
		 * Determine whether the user can update the model.
		 */
		public function update(User $user, SubCategory $subCategory): bool
		{
			return $user->id === $subCategory->user_id;
		}

		/**
		 * Determine whether the user can delete the model.
		 */
		public function delete(User $user, SubCategory $subCategory): bool
		{
			return $user->id === $subCategory->user_id;
		}

		// Add restore and forceDelete if using soft deletes
	}
