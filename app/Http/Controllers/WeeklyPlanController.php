<?php

	namespace App\Http\Controllers;

	use App\Models\Lesson;
	use App\Models\MainCategory;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Auth;
	use Illuminate\Support\Facades\Log;
	use Illuminate\Support\Carbon;
	use Illuminate\Support\Str;
	use Illuminate\Support\Facades\App;
	use Illuminate\Support\Facades\Cache;
	use Illuminate\View\View;
	use Illuminate\Http\JsonResponse;

	class WeeklyPlanController extends Controller
	{
		// Define internal keys consistently
		private $internalDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
		private $internalTimeSlots = ['Morning', 'Noon', 'Afternoon'];

		/**
		 * Display the weekly plan configuration interface.
		 *
		 * @param Request $request
		 * @return \Illuminate\View\View
		 */
		public function index(Request $request): View
		{
			// --- Language Handling ---
			$supportedLocales = ['en', 'zh-TW', 'tr'];
			$defaultLocale = config('app.fallback_locale', 'en');
			$currentLocale = $request->query('lang', session('locale', $defaultLocale));
			if (!in_array($currentLocale, $supportedLocales)) {
				$currentLocale = $defaultLocale;
			}
			App::setLocale($currentLocale);
			session(['locale' => $currentLocale]);

			// --- Get User's Main Categories ---
			$userId = Auth::id();
			$mainCategories = MainCategory::where('user_id', $userId)->orderBy('name')->get();

			// --- Generate Localized Display Names ---
			$displayDays = collect($this->internalDays)->mapWithKeys(function ($day) {
				return [$day => trans('weekly_plan.' . strtolower($day))];
			})->all();
			$displayTimeSlots = collect($this->internalTimeSlots)->mapWithKeys(function ($slot) {
				return [$slot => trans('weekly_plan.' . strtolower($slot))];
			})->all();

			// --- Prepare Data for View ---
			$currentYear = Carbon::now()->year;
			$years = range($currentYear - 5, $currentYear + 5);
			$months = [];
			for ($m = 1; $m <= 12; $m++) {
				$months[$m] = Carbon::create()->month($m)->locale($currentLocale)->isoFormat('MMMM');
			}

			$internalDays = $this->internalDays;
			$internalTimeSlots = $this->internalTimeSlots;

			return view('weekly_plan', compact(
				'mainCategories',
				'internalDays',
				'internalTimeSlots',
				'displayDays',
				'displayTimeSlots',
				'currentLocale',
				'years',
				'months'
			));
		}

		/**
		 * Load and populate the weekly plan based on user configuration (AJAX).
		 * Uses a fixed 4-week structure per month based on Lesson's year/month/week(1-4).
		 * Implements fallback logic for missing lessons within a month.
		 *
		 * @param Request $request
		 * @return \Illuminate\Http\JsonResponse|\Illuminate\View\View
		 */
		public function loadPlan(Request $request): JsonResponse|View
		{
			Log::info("Load Plan request received (4-week structure with fallback).");

			// --- Validate Input ---
			$validated = $request->validate([
				'template' => 'required|array',
				'template.*' => 'required|array',
				'template.*.*' => 'required|string',
				'start_month' => 'required|integer|between:1,12',
				'start_year' => 'required|integer',
				'end_month' => 'required|integer|between:1,12',
				'end_year' => 'required|integer',
			]);

			$template = $validated['template'];
			$startMonth = $validated['start_month'];
			$startYear = $validated['start_year'];
			$endMonth = $validated['end_month'];
			$endYear = $validated['end_year'];

			// --- Language Handling ---
			$currentLocale = session('locale', config('app.fallback_locale', 'en'));
			App::setLocale($currentLocale);

			// --- Date Range Logic ---
			try {
				// Use start of month for comparison logic
				$startDate = Carbon::create($startYear, $startMonth, 1)->startOfMonth();
				$endDate = Carbon::create($endYear, $endMonth, 1)->startOfMonth();

				// Compare month starts
				if ($startDate->greaterThan($endDate)) {
					return response()->json(['error' => 'Start date cannot be after end date.'], 422);
				}
			} catch (\Exception $e) {
				Log::error("Error creating dates: " . $e->getMessage());
				return response()->json(['error' => 'Invalid date range provided.'], 422);
			}

			Log::info("Fetching lessons for User ID: " . Auth::id() . " between " . $startDate->format('Y-m') . " and " . $endDate->format('Y-m'));

			// --- Fetch Lessons within the Date Range (Months) ---
			$userId = Auth::id();
			$lessons = Lesson::where('user_id', $userId)
				->whereNotNull('selected_main_category_id') // Only lessons with a category
				->whereBetween('week', [1, 4]) // Ensure week is 1, 2, 3, or 4
				->where(function ($query) use ($startDate, $endDate) {
					// Logic to select lessons within the start and end month/year range
					$query->where(function ($q) use ($startDate, $endDate) {
						// Years strictly between start and end year
						$q->where('year', '>', $startDate->year)
							->where('year', '<', $endDate->year);
					})->orWhere(function ($q) use ($startDate, $endDate) {
						// Same year as start, month >= start month
						$q->where('year', $startDate->year)
							->where('month', '>=', $startDate->month);
					})->orWhere(function ($q) use ($startDate, $endDate) {
						// Same year as end, month <= end month
						$q->where('year', $endDate->year)
							->where('month', '<=', $endDate->month);
					});
					// Handle case where start and end year are the same
					if ($startDate->year === $endDate->year) {
						$query->where('year', $startDate->year)
							->whereBetween('month', [$startDate->month, $endDate->month]);
					}
				})
				->with('mainCategory')
				->orderBy('year', 'asc')
				->orderBy('month', 'asc')
				->orderBy('week', 'asc') // Order by the 1-4 week number
				->orderBy('created_at', 'asc') // Fallback sort
				->get();

			Log::info("Found " . $lessons->count() . " lessons within date range and week 1-4.");

			// --- Group Lessons by Main Category ID ---
			$lessonsByCatId = $lessons->groupBy('selected_main_category_id');

			// --- Initialize Plan Structure (4 weeks per month) ---
			$populatedPlan = [];
			$lessonUsageCount = []; // Track how many times a lesson is used
			$currentLoopDate = $startDate->copy();

			while ($currentLoopDate->lte($endDate)) {
				$year = $currentLoopDate->year;
				$month = $currentLoopDate->month;
				if (!isset($populatedPlan[$year])) $populatedPlan[$year] = [];
				if (!isset($populatedPlan[$year][$month])) $populatedPlan[$year][$month] = [];

				// Create structure for 4 weeks
				for ($week = 1; $week <= 4; $week++) {
					if (!isset($populatedPlan[$year][$month][$week])) {
						$populatedPlan[$year][$month][$week] = ['lessons' => []];
						foreach ($this->internalDays as $dayKey) {
							$populatedPlan[$year][$month][$week]['lessons'][$dayKey] = array_fill_keys($this->internalTimeSlots, null); // Initialize with null
						}
						// No 'week_start_date' needed as weeks are abstract (1-4)
					}
				}
				// Move to the next month
				$currentLoopDate->addMonthNoOverflow();
			}

			// --- Generate Localized Display Names (Needed for the partial view) ---
			$displayDays = collect($this->internalDays)->mapWithKeys(function ($day) {
				return [$day => trans('weekly_plan.' . strtolower($day))];
			})->all();
			$displayTimeSlots = collect($this->internalTimeSlots)->mapWithKeys(function ($slot) {
				return [$slot => trans('weekly_plan.' . strtolower($slot))];
			})->all();
			$monthNames = [];
			for ($m = 1; $m <= 12; $m++) {
				$monthNames[$m] = Carbon::create()->month($m)->locale($currentLocale)->isoFormat('MMMM');
			}

			// --- Populate Plan based on Template and Fetched Lessons ---
			foreach ($populatedPlan as $year => &$months) {
				foreach ($months as $month => &$weeks) {
					foreach ($weeks as $week => &$weekData) { // Week is 1, 2, 3, or 4
						foreach ($this->internalDays as $dayKey) {
							foreach ($this->internalTimeSlots as $slotKey) {
								$templateValue = $template[$dayKey][$slotKey] ?? 'empty';
								$slotContent = null;

								if ($templateValue === 'pe') {
									$slotContent = ['type' => 'pe', 'title' => trans('weekly_plan.physical_education')];
								} elseif ($templateValue === 'review') {
									$slotContent = ['type' => 'review', 'title' => trans('weekly_plan.review')];
								} elseif ($templateValue !== 'empty' && Str::startsWith($templateValue, 'cat_')) {
									$catId = (int)Str::after($templateValue, 'cat_');
									$lessonToPlace = null;
									$availableLessons = $lessonsByCatId->get($catId); // Lessons for this category in the whole range

									// --- START: Lesson Finding Logic with Fallback ---
									if ($availableLessons) {
										// 1. Try to find lesson for the EXACT year, month, and week
										$potentialLessons = $availableLessons->filter(function ($lesson) use ($year, $month, $week) {
											return $lesson->year == $year && $lesson->month == $month && $lesson->week == $week;
										})->sortBy(function ($lesson) use ($lessonUsageCount) {
											return $lessonUsageCount[$lesson->id] ?? 0; // Sort by least used
										})->values(); // Re-index

										if ($potentialLessons->isNotEmpty()) {
											$lessonToPlace = $potentialLessons->first();
											Log::debug("Exact match found for Cat ID {$catId} in {$year}-{$month}-W{$week}. Lesson ID: {$lessonToPlace->id}");
										} else {
											// 2. Exact match failed, try FALLBACK logic if week > 1
											if ($week > 1) {
												Log::debug("Exact match failed for Cat ID {$catId} in {$year}-{$month}-W{$week}. Trying fallback...");

												// Determine the order of fallback weeks to check based on the current week
												$fallbackWeeksToCheck = [];
												if ($week == 2) {
													$fallbackWeeksToCheck = [1];
												} elseif ($week == 3) {
													$fallbackWeeksToCheck = [2, 1]; // Check week 2 first, then week 1
												} elseif ($week == 4) {
													$fallbackWeeksToCheck = [3, 2, 1]; // Check week 3, then 2, then 1
												}

												foreach ($fallbackWeeksToCheck as $fallbackWeek) {
													$fallbackLessons = $availableLessons->filter(function ($lesson) use ($year, $month, $fallbackWeek) {
														return $lesson->year == $year && $lesson->month == $month && $lesson->week == $fallbackWeek;
													})->sortBy(function ($lesson) use ($lessonUsageCount) {
														return $lessonUsageCount[$lesson->id] ?? 0; // Sort by least used
													})->values(); // Re-index

													if ($fallbackLessons->isNotEmpty()) {
														$lessonToPlace = $fallbackLessons->first();
														Log::debug("Fallback successful for Cat ID {$catId} in {$year}-{$month}-W{$week}. Using lesson from W{$fallbackWeek} (ID: {$lessonToPlace->id})");
														break; // Found a fallback lesson, stop checking earlier weeks
													}
												}
												if (!$lessonToPlace) {
													Log::debug("Fallback failed for Cat ID {$catId} in {$year}-{$month}-W{$week}. No lessons found in weeks " . implode(', ', $fallbackWeeksToCheck));
												}
											} else {
												Log::debug("Exact match failed for Cat ID {$catId} in {$year}-{$month}-W{$week}. No fallback possible (Week 1).");
											}
										}
									} else {
										Log::debug("No lessons found at all for Cat ID {$catId} in the selected date range.");
									}
									// --- END: Lesson Finding Logic with Fallback ---


									// 3. Assign content based on whether a lesson was found (either exact or fallback)
									if ($lessonToPlace) {
										// Increment usage count only when a lesson is actually placed
										$lessonUsageCount[$lessonToPlace->id] = ($lessonUsageCount[$lessonToPlace->id] ?? 0) + 1;
										Log::debug("Placing Lesson ID {$lessonToPlace->id} (Cat ID: {$catId}) in {$year}-{$month}-W{$week}. Usage: {$lessonUsageCount[$lessonToPlace->id]}");
										$slotContent = $lessonToPlace; // Assign the actual Lesson object
									} else {
										// No lesson found even after checking fallbacks
										Log::warning("No lesson found (including fallbacks) for Cat ID {$catId} for period {$year}-{$month}-W{$week}");
										$mainCat = Cache::remember("main_cat_{$catId}", 600, function () use ($catId) {
											return MainCategory::find($catId);
										});
										$catName = $mainCat ? $mainCat->name : "ID {$catId}";
										$slotContent = ['type' => 'missing', 'title' => trans('weekly_plan.no_lesson_for_category_period', ['category' => $catName, 'period' => "{$year}-{$month}-W{$week}"])];
									}

								} else {
									// Slot is 'empty'
									$slotContent = ['type' => 'empty', 'title' => trans('weekly_plan.empty_slot')];
								}

								// Assign the content to the plan structure
								$weekData['lessons'][$dayKey][$slotKey] = $slotContent;
							}
						}
					}
					unset($weekData); // Unset reference
				}
				unset($weeks); // Unset reference
			}
			unset($months); // Unset reference

			Log::info("Plan population complete (4-week structure with fallback). Rendering partial view.");

			// --- Return Rendered Partial View ---
			$internalDays = $this->internalDays;
			$internalTimeSlots = $this->internalTimeSlots;

			$html = view('partials._weekly_plan_display', compact(
				'populatedPlan',
				'internalDays',
				'internalTimeSlots',
				'displayDays',
				'displayTimeSlots',
				'monthNames'
			))->render();

			return response()->json(['html' => $html]);
		}
	}
