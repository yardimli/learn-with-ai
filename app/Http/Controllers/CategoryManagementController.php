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
	use Illuminate\Support\Facades\Auth; // Import Auth

	class CategoryManagementController extends Controller
	{
		// ==============================
		// Main Category Methods
		// ==============================
		public function mainIndex()
		{
			// Filter by logged-in user
			$mainCategories = Auth::user()->mainCategories()->orderBy('name')->paginate(20);
			return view('category_management.index', compact('mainCategories'));
		}

		public function mainCreate()
		{
			$this->authorize('create', MainCategory::class); // Authorize creation
			return view('category_management.create');
		}

		public function mainStore(Request $request)
		{
			$this->authorize('create', MainCategory::class); // Authorize creation

			// Use static method from model for validation rules
			$validator = Validator::make($request->all(), MainCategory::validationRules());

			if ($validator->fails()) {
				return redirect()->route('category_management.main.create')
					->withErrors($validator)
					->withInput();
			}

			try {
				$validatedData = $validator->validated();
				$validatedData['user_id'] = Auth::id(); // Set user_id

				$category = MainCategory::create($validatedData);
				Log::info("Main Category created: ID {$category->id}, Name: {$category->name}, UserID: {$category->user_id}");
				return redirect()->route('category_management.main.index')
					->with('success', "Main Category '{$category->name}' created successfully.");
			} catch (Exception $e) {
				Log::error("Error creating main category: " . $e->getMessage());
				return redirect()->route('category_management.main.create')
					->with('error', 'Failed to create main category. Please try again.')
					->withInput();
			}
		}

		public function mainEdit(MainCategory $mainCategory)
		{
			$this->authorize('update', $mainCategory); // Authorize editing this specific category
			return view('category_management.edit', compact('mainCategory'));
		}

		public function mainUpdate(Request $request, MainCategory $mainCategory)
		{
			$this->authorize('update', $mainCategory); // Authorize update

			// Use static method from model for validation rules
			$validator = Validator::make($request->all(), MainCategory::updateValidationRules($mainCategory->id));

			if ($validator->fails()) {
				return redirect()->route('category_management.main.edit', $mainCategory->id)
					->withErrors($validator)
					->withInput();
			}

			try {
				$oldName = $mainCategory->name;
				// user_id should not be updated here
				$mainCategory->update($validator->validated());
				Log::info("Main Category updated: ID {$mainCategory->id}, Old: {$oldName}, New: {$mainCategory->name}, UserID: {$mainCategory->user_id}");
				return redirect()->route('category_management.main.index')
					->with('success', "Main Category '{$oldName}' updated to '{$mainCategory->name}' successfully.");
			} catch (Exception $e) {
				Log::error("Error updating main category ID {$mainCategory->id}: " . $e->getMessage());
				return redirect()->route('category_management.main.edit', $mainCategory->id)
					->with('error', 'Failed to update main category. Please try again.')
					->withInput();
			}
		}

		public function mainDestroy(MainCategory $mainCategory)
		{
			$this->authorize('delete', $mainCategory); // Authorize deletion

			try {
				DB::beginTransaction();
				$categoryName = $mainCategory->name;
				$categoryId = $mainCategory->id;
				$userId = $mainCategory->user_id;

				// Deleting the main category will cascade delete sub-categories (due to DB constraint)
				// and set lesson sub_category_id to null (due to migration setup).
				// Ensure cascade is set correctly in sub_categories migration for main_category_id
				$mainCategory->delete();
				DB::commit();
				Log::info("Main Category deleted: ID {$categoryId}, Name: {$categoryName}, UserID: {$userId}");
				return redirect()->route('category_management.main.index')
					->with('success', "Main Category '{$categoryName}' and its sub-categories deleted successfully.");
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
		public function subIndex(Request $request)
		{
			// Start query for the logged-in user's subcategories
			$query = Auth::user()->subCategories()->with('mainCategory')->orderBy('name');

			if ($request->has('main_category_id') && $request->main_category_id != '') {
				$query->where('main_category_id', $request->main_category_id);
			}

			$subCategories = $query->paginate(25);
			$mainCategories = Auth::user()->mainCategories()->orderBy('name')->pluck('name', 'id');

			// Get first main category id as default if no filter is applied
			$main_category_id = $request->input('main_category_id', $mainCategories->keys()->first());

			if ($request->has('main_category_id') && !$mainCategories->has($main_category_id)) {
				$main_category_id = $mainCategories->keys()->first(); // Fallback if invalid ID passed
			}


			return view('category_management.sub.index', compact('subCategories', 'mainCategories', 'main_category_id'));
		}

		public function subCreate(Request $request, MainCategory $mainCategory = null)
		{
			$this->authorize('create', SubCategory::class);

			// If a mainCategory is passed via route model binding, authorize viewing it
			if ($mainCategory) {
				$this->authorize('view', $mainCategory);
			}

			// Get only the user's main categories
			$mainCategories = Auth::user()->mainCategories()->orderBy('name')->pluck('name', 'id');

			if ($mainCategories->isEmpty()) {
				return redirect()->route('category_management.main.create')
					->with('warning', 'You must create a Main Category before adding Sub Categories.');
			}

			// Pre-select if provided via route or request, ensuring it belongs to the user
			$selectedMainCategoryId = null;
			if ($mainCategory) {
				$selectedMainCategoryId = $mainCategory->id;
			} else {
				$requestedId = $request->input('main_category_id', old('main_category_id'));
				if ($requestedId && $mainCategories->has($requestedId)) { // Check if ID exists in user's categories
					$selectedMainCategoryId = $requestedId;
				}
			}

			return view('category_management.sub.create', compact('mainCategories', 'selectedMainCategoryId'));
		}

		public function subStore(Request $request)
		{
			$this->authorize('create', SubCategory::class);

			// Use static method from model for validation rules, passing the request
			$validator = Validator::make($request->all(), SubCategory::validationRules($request), [
				'name.unique' => 'The sub-category name must be unique within the selected main category for your account.'
			]);

			if ($validator->fails()) {
				// Pass back the intended main_category_id for the redirect
				return redirect()->route('category_management.sub.create', ['main_category_id' => $request->main_category_id])
					->withErrors($validator)
					->withInput();
			}

			try {
				$validatedData = $validator->validated();
				$validatedData['user_id'] = Auth::id(); // Set user_id

				$category = SubCategory::create($validatedData);
				Log::info("Sub Category created: ID {$category->id}, Name: {$category->name}, Main ID: {$category->main_category_id}, UserID: {$category->user_id}");
				return redirect()->route('category_management.sub.index')
					->with('success', "Sub Category '{$category->name}' created successfully.");
			} catch (Exception $e) {
				Log::error("Error creating sub category: " . $e->getMessage());
				return redirect()->route('category_management.sub.create')
					->with('error', 'Failed to create sub category. Please try again.')
					->withInput();
			}
		}

		public function subEdit(SubCategory $subCategory)
		{
			$this->authorize('update', $subCategory); // Authorize editing this specific sub-category

			// Get only the user's main categories for the dropdown
			$mainCategories = Auth::user()->mainCategories()->orderBy('name')->pluck('name', 'id');

			return view('category_management.sub.edit', compact('subCategory', 'mainCategories'));
		}

		public function subUpdate(Request $request, SubCategory $subCategory)
		{
			$this->authorize('update', $subCategory); // Authorize update

			// Use static method from model for validation rules, passing request and ID
			$validator = Validator::make($request->all(), SubCategory::updateValidationRules($request, $subCategory->id), [
				'name.unique' => 'The sub-category name must be unique within the selected main category for your account.'
			]);

			if ($validator->fails()) {
				return redirect()->route('category_management.sub.edit', $subCategory->id)
					->withErrors($validator)
					->withInput();
			}

			try {
				$oldName = $subCategory->name;
				// user_id should not be updated
				$subCategory->update($validator->validated());
				Log::info("Sub Category updated: ID {$subCategory->id}, Old: {$oldName}, New: {$subCategory->name}, Main ID: {$subCategory->main_category_id}, UserID: {$subCategory->user_id}");
				return redirect()->route('category_management.sub.index')
					->with('success', "Sub Category '{$oldName}' updated to '{$subCategory->name}' successfully.");
			} catch (Exception $e) {
				Log::error("Error updating sub category ID {$subCategory->id}: " . $e->getMessage());
				return redirect()->route('category_management.sub.edit', $subCategory->id)
					->with('error', 'Failed to update sub category. Please try again.')
					->withInput();
			}
		}

		public function subDestroy(SubCategory $subCategory)
		{
			$this->authorize('delete', $subCategory); // Authorize deletion

			try {
				DB::beginTransaction();
				$categoryName = $subCategory->name;
				$categoryId = $subCategory->id;
				$userId = $subCategory->user_id;

				// Lessons using this sub-category will have their sub_category_id set to null (due to DB constraint)
				$subCategory->delete();
				DB::commit();
				Log::info("Sub Category deleted: ID {$categoryId}, Name: {$categoryName}, UserID: {$userId}");
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
