<?php

	namespace App\Policies;

	use App\Models\MainCategory;
	use App\Models\User;
	use Illuminate\Auth\Access\Response;

	class MainCategoryPolicy
	{
		/**
		 * Determine whether the user can view any models.
		 * Filtering is done in controller, this just allows access to the index page.
		 */
		public function viewAny(User $user): bool
		{
			return true; // Any authenticated user can see the list page (filtered later)
		}

		/**
		 * Determine whether the user can view the model.
		 */
		public function view(User $user, MainCategory $mainCategory): bool
		{
			return $user->id === $mainCategory->user_id;
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
		public function update(User $user, MainCategory $mainCategory): bool
		{
			return $user->id === $mainCategory->user_id;
		}

		/**
		 * Determine whether the user can delete the model.
		 */
		public function delete(User $user, MainCategory $mainCategory): bool
		{
			return $user->id === $mainCategory->user_id;
		}

		// Add restore and forceDelete if using soft deletes
	}
