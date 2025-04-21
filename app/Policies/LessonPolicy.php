<?php

	namespace App\Policies;

	use App\Models\Lesson;
	use App\Models\User;
	use Illuminate\Auth\Access\Response;

	class LessonPolicy
	{
		/**
		 * Determine whether the user can view any models.
		 * User can view their own list.
		 */
		public function viewAny(User $user): bool
		{
			return true; // Any logged-in user can view their own list page
		}

		/**
		 * Determine whether the user can view the model.
		 * Check if the lesson belongs to the user.
		 */
		public function view(User $user, Lesson $lesson): bool
		{
			return $user->id === $lesson->user_id;
		}

		/**
		 * Determine whether the user can create models.
		 */
		public function create(User $user): bool
		{
			return true; // Any logged-in user can create a lesson
		}

		/**
		 * Determine whether the user can update the model.
		 */
		public function update(User $user, Lesson $lesson): bool
		{
			return $user->id === $lesson->user_id;
		}

		/**
		 * Determine whether the user can delete the model.
		 */
		public function delete(User $user, Lesson $lesson): bool
		{
			return $user->id === $lesson->user_id;
		}

		/**
		 * Determine whether the user can archive the model's progress.
		 */
		public function archive(User $user, Lesson $lesson): bool
		{
			return $user->id === $lesson->user_id;
		}

		/**
		 * Determine whether the user can generate assets for the model.
		 * (Covers generating audio, images, questions etc.)
		 */
		public function generateAssets(User $user, Lesson $lesson): bool
		{
			return $user->id === $lesson->user_id;
		}

		/**
		 * Determine whether the user can view the progress report.
		 */
		public function viewProgress(User $user, Lesson $lesson): bool
		{
			return $user->id === $lesson->user_id;
		}

		/**
		 * Determine whether the user can take the lesson (view interface, submit answers).
		 */
		public function takeLesson(User $user, Lesson $lesson): bool
		{
			// For now, allow any logged-in user to take any lesson?
			// Or restrict to owner only? Let's restrict to owner for consistency.
			return $user->id === $lesson->user_id;
			// If you want anyone to take it: return true;
		}


		// Add other specific actions if needed, e.g., restore, forceDelete

		// You might not need restore and forceDelete unless you use soft deletes
		// /**
		//  * Determine whether the user can restore the model.
		//  */
		// public function restore(User $user, Lesson $lesson): bool
		// {
		//     return $user->id === $lesson->user_id;
		// }

		// /**
		//  * Determine whether the user can permanently delete the model.
		//  */
		// public function forceDelete(User $user, Lesson $lesson): bool
		// {
		//     return $user->id === $lesson->user_id;
		// }
	}
