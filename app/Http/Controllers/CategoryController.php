<?php

	namespace App\Http\Controllers;

	use App\Models\Category;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Log;
	use Illuminate\Support\Facades\Validator;
	use Illuminate\Validation\Rule; // Needed for unique rule on update

	class CategoryController extends Controller
	{
		/**
		 * Display a listing of the categories.
		 *
		 * @return \Illuminate\View\View
		 */
		public function index()
		{
			$categories = Category::orderBy('name')->paginate(20); // Paginate for potentially many categories
			return view('categories.index', compact('categories'));
		}

		/**
		 * Show the form for creating a new category.
		 *
		 * @return \Illuminate\View\View
		 */
		public function create()
		{
			return view('categories.create');
		}

		/**
		 * Store a newly created category in storage.
		 *
		 * @param  \Illuminate\Http\Request  $request
		 * @return \Illuminate\Http\RedirectResponse
		 */
		public function store(Request $request)
		{
			// Use validation rules from the model
			$validator = Validator::make($request->all(), Category::validationRules());

			if ($validator->fails()) {
				return redirect()->route('categories.create')
					->withErrors($validator)
					->withInput();
			}

			try {
				$category = Category::create($validator->validated());
				Log::info("Category created successfully: ID {$category->id}, Name: {$category->name}");
				return redirect()->route('categories.index')
					->with('success', "Category '{$category->name}' created successfully.");
			} catch (\Exception $e) {
				Log::error("Error creating category: " . $e->getMessage());
				return redirect()->route('categories.create')
					->with('error', 'Failed to create category. Please try again.')
					->withInput();
			}
		}

		/**
		 * Display the specified resource.
		 * NOTE: We excluded 'show' in routes, so this won't be hit by default.
		 * Keep it empty or remove if you are sure you won't need it.
		 */
		// public function show(Category $category)
		// {
		//     // Typically not needed for simple management list
		// }

		/**
		 * Show the form for editing the specified category.
		 *
		 * @param  \App\Models\Category  $category Route model binding injects the category
		 * @return \Illuminate\View\View
		 */
		public function edit(Category $category)
		{
			return view('categories.edit', compact('category'));
		}

		/**
		 * Update the specified category in storage.
		 *
		 * @param  \Illuminate\Http\Request  $request
		 * @param  \App\Models\Category  $category
		 * @return \Illuminate\Http\RedirectResponse
		 */
		public function update(Request $request, Category $category)
		{
			// Use update validation rules from the model, ignoring the current category's name
			$validator = Validator::make($request->all(), Category::updateValidationRules($category->id));

			if ($validator->fails()) {
				return redirect()->route('categories.edit', $category->id)
					->withErrors($validator)
					->withInput();
			}

			try {
				$oldName = $category->name;
				$category->update($validator->validated());
				Log::info("Category updated successfully: ID {$category->id}, Old Name: {$oldName}, New Name: {$category->name}");
				return redirect()->route('categories.index')
					->with('success', "Category '{$oldName}' updated to '{$category->name}' successfully.");
			} catch (\Exception $e) {
				Log::error("Error updating category ID {$category->id}: " . $e->getMessage());
				return redirect()->route('categories.edit', $category->id)
					->with('error', 'Failed to update category. Please try again.')
					->withInput();
			}
		}

		/**
		 * Remove the specified category from storage.
		 *
		 * @param  \App\Models\Category  $category
		 * @return \Illuminate\Http\RedirectResponse
		 */
		public function destroy(Category $category)
		{
			try {
				$categoryName = $category->name;

				// Optional: Check if category is in use and prevent deletion if needed.
				// The current migration sets lesson.category_id to NULL on delete,
				// so deletion is allowed by default.
				// if ($category->lessons()->exists()) {
				//     return redirect()->route('categories.index')
				//                      ->with('error', "Cannot delete category '{$categoryName}' because it is assigned to existing lessons.");
				// }

				$category->delete();
				Log::info("Category deleted successfully: ID {$category->id}, Name: {$categoryName}");
				return redirect()->route('categories.index')
					->with('success', "Category '{$categoryName}' deleted successfully.");
			} catch (\Exception $e) {
				Log::error("Error deleting category ID {$category->id}: " . $e->getMessage());
				return redirect()->route('categories.index')
					->with('error', 'Failed to delete category. Please try again.');
			}
		}
	}
