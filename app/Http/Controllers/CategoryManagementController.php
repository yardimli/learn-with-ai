<?php

	namespace App\Http\Controllers;

	use App\Models\MainCategory;
	use App\Models\SubCategory;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\DB;
	use Illuminate\Support\Facades\Log;
	use Illuminate\Support\Facades\Validator;
	use Illuminate\Validation\Rule;
	use Exception;

	class CategoryManagementController extends Controller
	{
		// ==============================
		// Main Category Methods
		// ==============================

		/**
		 * Display a listing of the main category_management.
		 */
		public function mainIndex()
		{
			$mainCategories = MainCategory::orderBy('name')->paginate(20);
			return view('category_management.index', compact('mainCategories'));
		}

		/**
		 * Show the form for creating a new main category.
		 */
		public function mainCreate()
		{
			return view('category_management.create');
		}

		/**
		 * Store a newly created main category in storage.
		 */
		public function mainStore(Request $request)
		{
			$validator = Validator::make($request->all(), MainCategory::validationRules());

			if ($validator->fails()) {
				return redirect()->route('category_management.main.create')
					->withErrors($validator)
					->withInput();
			}

			try {
				$category = MainCategory::create($validator->validated());
				Log::info("Main Category created: ID {$category->id}, Name: {$category->name}");
				return redirect()->route('category_management.main.index')
					->with('success', "Main Category '{$category->name}' created successfully.");
			} catch (Exception $e) {
				Log::error("Error creating main category: " . $e->getMessage());
				return redirect()->route('category_management.main.create')
					->with('error', 'Failed to create main category. Please try again.')
					->withInput();
			}
		}

		/**
		 * Show the form for editing the specified main category.
		 */
		public function mainEdit(MainCategory $mainCategory)
		{
			return view('category_management.edit', compact('mainCategory'));
		}

		/**
		 * Update the specified main category in storage.
		 */
		public function mainUpdate(Request $request, MainCategory $mainCategory)
		{
			$validator = Validator::make($request->all(), MainCategory::updateValidationRules($mainCategory->id));

			if ($validator->fails()) {
				return redirect()->route('category_management.main.edit', $mainCategory->id)
					->withErrors($validator)
					->withInput();
			}

			try {
				$oldName = $mainCategory->name;
				$mainCategory->update($validator->validated());
				Log::info("Main Category updated: ID {$mainCategory->id}, Old: {$oldName}, New: {$mainCategory->name}");
				return redirect()->route('category_management.main.index')
					->with('success', "Main Category '{$oldName}' updated to '{$mainCategory->name}' successfully.");
			} catch (Exception $e) {
				Log::error("Error updating main category ID {$mainCategory->id}: " . $e->getMessage());
				return redirect()->route('category_management.main.edit', $mainCategory->id)
					->with('error', 'Failed to update main category. Please try again.')
					->withInput();
			}
		}

		/**
		 * Remove the specified main category from storage.
		 * Note: Sub-category_management and Lesson links will be handled by DB constraints (cascade/set null).
		 */
		public function mainDestroy(MainCategory $mainCategory)
		{
			// Optional: Check if it's safe to delete (e.g., warn if many lessons use it)
			// $subCategoryCount = $mainCategory->subCategories()->count();
			// $lessonCount = $mainCategory->lessons()->count();
			// if ($subCategoryCount > 0 || $lessonCount > 0) {
			//     // Potentially add a warning, but deletion should still work due to constraints
			// }

			try {
				DB::beginTransaction();
				$categoryName = $mainCategory->name;
				$categoryId = $mainCategory->id;

				// Deleting the main category will cascade delete sub-category_management
				// and set lesson sub_category_id to null due to migration setup.
				$mainCategory->delete();

				DB::commit();
				Log::info("Main Category deleted: ID {$categoryId}, Name: {$categoryName}");
				return redirect()->route('category_management.main.index')
					->with('success', "Main Category '{$categoryName}' and its sub-category_management deleted successfully.");
			} catch (Exception $e) {
				DB::rollBack();
				Log::error("Error deleting main category ID {$mainCategory->id}: " . $e->getMessage());
				return redirect()->route('category_management.main.index')
					->with('error', 'Failed to delete main category. Please try again.');
			}
		}


		// ==============================
		// Sub Category Methods
		// ==============================

		/**
		 * Display a listing of the sub category_management.
		 */
		public function subIndex(Request $request)
		{
			$query = SubCategory::with('mainCategory')->orderBy('name');

			// Optional: Filter by main category
			if ($request->has('main_category_id') && $request->main_category_id != '') {
				$query->where('main_category_id', $request->main_category_id);
			}

			$subCategories = $query->paginate(25);
			$mainCategories = MainCategory::orderBy('name')->pluck('name', 'id'); // For filter dropdown

			//get first main category id as default if no filter is applied
			$main_category_id = $request->has('main_category_id') ? $request->main_category_id : $mainCategories->keys()->first();

			return view('category_management.sub.index', compact('subCategories', 'mainCategories', 'main_category_id'));
		}

		/**
		 * Show the form for creating a new sub category.
		 * Optionally accepts a main category to pre-select.
		 */
		public function subCreate(Request $request, MainCategory $mainCategory = null)
		{
			$mainCategories = MainCategory::orderBy('name')->pluck('name', 'id');
			if ($mainCategories->isEmpty()) {
				return redirect()->route('category_management.main.create')
					->with('warning', 'You must create a Main Category before adding Sub Categories.');
			}
			$selectedMainCategoryId = $mainCategory->id ?? $request->input('main_category_id', old('main_category_id')); // Pre-select if provided

			return view('category_management.sub.create', compact('mainCategories', 'selectedMainCategoryId'));
		}


		/**
		 * Store a newly created sub category in storage.
		 */
		public function subStore(Request $request)
		{
			// Custom validation rule for uniqueness within main category
			$rules = [
				'name' => [
					'required',
					'string',
					'max:255',
					Rule::unique('sub_categories')->where(function ($query) use ($request) {
						return $query->where('main_category_id', $request->main_category_id);
					}),
				],
				'main_category_id' => 'required|integer|exists:main_categories,id',
			];

			$validator = Validator::make($request->all(), $rules, [
				'name.unique' => 'The sub-category name must be unique within the selected main category.'
			]);


			if ($validator->fails()) {
				return redirect()->route('category_management.sub.create', ['main_category_id' => $request->main_category_id])
					->withErrors($validator)
					->withInput();
			}

			try {
				$category = SubCategory::create($validator->validated());
				Log::info("Sub Category created: ID {$category->id}, Name: {$category->name}, Main ID: {$category->main_category_id}");
				return redirect()->route('category_management.sub.index')
					->with('success', "Sub Category '{$category->name}' created successfully.");
			} catch (Exception $e) {
				Log::error("Error creating sub category: " . $e->getMessage());
				return redirect()->route('category_management.sub.create')
					->with('error', 'Failed to create sub category. Please try again.')
					->withInput();
			}
		}

		/**
		 * Show the form for editing the specified sub category.
		 */
		public function subEdit(SubCategory $subCategory)
		{
			$mainCategories = MainCategory::orderBy('name')->pluck('name', 'id');
			return view('category_management.sub.edit', compact('subCategory', 'mainCategories'));
		}

		/**
		 * Update the specified sub category in storage.
		 */
		public function subUpdate(Request $request, SubCategory $subCategory)
		{
			// Get target main category ID from the request
			$targetMainCategoryId = $request->input('main_category_id');

			// Custom validation rule for uniqueness within the target main category, ignoring self
			$rules = [
				'name' => [
					'required',
					'string',
					'max:255',
					Rule::unique('sub_categories', 'name')
						->where('main_category_id', $targetMainCategoryId)
						->ignore($subCategory->id), // Ignore the current sub-category ID
				],
				'main_category_id' => 'required|integer|exists:main_categories,id',
			];

			$validator = Validator::make($request->all(), $rules, [
				'name.unique' => 'The sub-category name must be unique within the selected main category.'
			]);


			if ($validator->fails()) {
				return redirect()->route('category_management.sub.edit', $subCategory->id)
					->withErrors($validator)
					->withInput();
			}

			try {
				$oldName = $subCategory->name;
				$subCategory->update($validator->validated());
				Log::info("Sub Category updated: ID {$subCategory->id}, Old: {$oldName}, New: {$subCategory->name}, Main ID: {$subCategory->main_category_id}");
				return redirect()->route('category_management.sub.index')
					->with('success', "Sub Category '{$oldName}' updated to '{$subCategory->name}' successfully.");
			} catch (Exception $e) {
				Log::error("Error updating sub category ID {$subCategory->id}: " . $e->getMessage());
				return redirect()->route('category_management.sub.edit', $subCategory->id)
					->with('error', 'Failed to update sub category. Please try again.')
					->withInput();
			}
		}

		/**
		 * Remove the specified sub category from storage.
		 * Note: Lesson links will be handled by DB constraints (set null).
		 */
		public function subDestroy(SubCategory $subCategory)
		{
			// Optional: Check if lessons are attached and warn
			// if ($subCategory->lessons()->exists()) {
			//     // Could redirect with a specific warning
			// }

			try {
				DB::beginTransaction();
				$categoryName = $subCategory->name;
				$categoryId = $subCategory->id;

				// Lessons using this sub-category will have their sub_category_id set to null
				$subCategory->delete();

				DB::commit();
				Log::info("Sub Category deleted: ID {$categoryId}, Name: {$categoryName}");
				return redirect()->route('category_management.sub.index')
					->with('success', "Sub Category '{$categoryName}' deleted successfully.");
			} catch (Exception $e) {
				DB::rollBack();
				Log::error("Error deleting sub category ID {$subCategory->id}: " . $e->getMessage());
				return redirect()->route('category_management.sub.index')
					->with('error', 'Failed to delete sub category. Please try again.');
			}
		}
	}
