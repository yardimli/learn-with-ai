<?php

	namespace App\Providers;

	// use Illuminate\Support\Facades\Gate;
	use App\Models\Lesson;
	use App\Models\MainCategory;
	use App\Models\SubCategory;
	use App\Policies\LessonPolicy;
	use App\Policies\MainCategoryPolicy;
	use App\Policies\SubCategoryPolicy;
	use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

	class AuthServiceProvider extends ServiceProvider
	{
		/**
		 * The model to policy mappings for the application.
		 *
		 * @var array<class-string, class-string>
		 */
		protected $policies = [
			Lesson::class => LessonPolicy::class,
			MainCategory::class => MainCategoryPolicy::class,
			SubCategory::class => SubCategoryPolicy::class,
		];

		/**
		 * Register any authentication / authorization services.
		 */
		public function boot(): void
		{
			$this->registerPolicies();
			//
		}
	}
