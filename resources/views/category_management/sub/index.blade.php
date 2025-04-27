@extends('layouts.app')

@section('title', 'Manage Sub Categories - Learn with AI')

@section('content')
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h1>Manage Sub Categories</h1>
		<div>
			<a href="{{ route('category_management.main.index') }}" class="btn btn-outline-secondary me-2">
				<i class="fas fa-folder-open"></i> View Main Categories
			</a>
			<a href="{{ route('category_management.sub.create', $main_category_id) }}" class="btn btn-primary">
				<i class="fas fa-plus"></i> Create Sub Category
			</a>
			<a href="{{ route('lessons.list') }}" class="btn btn-outline-secondary ms-2">
				<i class="fas fa-arrow-left"></i> Back to Lessons List
			</a>
		</div>
	</div>
	
	@include('partials.session_messages')
	
	{{-- Optional Filter Form --}}
	<div class="card shadow-sm mb-4">
		<div class="card-body">
			<form method="GET" action="{{ route('category_management.sub.index') }}" class="row g-3 align-items-end">
				<div class="col-md-4">
					<label for="main_category_id_filter" class="form-label">Filter by Main Category:</label>
					<select name="main_category_id" id="main_category_id_filter" class="form-select">
						<option value="">-- All Main Categories --</option>
						@foreach ($mainCategories as $id => $name)
							<option value="{{ $id }}" {{ request('main_category_id') == $id ? 'selected' : '' }}>
								{{ $name }}
							</option>
						@endforeach
					</select>
				</div>
				<div class="col-md-auto">
					<button type="submit" class="btn btn-info">Filter</button>
					<a href="{{ route('category_management.sub.index') }}" class="btn btn-light ms-2">Reset</a>
				</div>
			</form>
		</div>
	</div>
	
	
	@if($subCategories->isEmpty())
		<div class="alert alert-info" role="alert">
			No sub categories found.
			@if(request()->has('main_category_id') && request('main_category_id') != '')
				Perhaps clear the filter or <a href="{{ route('category_management.sub.create', ['mainCategory' => request('main_category_id')]) }}">create one for this main category?</a>
			@else
				<a href="{{ route('category_management.sub.create') }}">Create the first one!</a> (Make sure you have Main Categories first).
			@endif
		</div>
	@else
		<div class="card shadow-sm">
			<div class="card-body">
				<div class="table-responsive">
					<table class="table table-hover align-middle">
						<thead>
						<tr>
							<th>Sub Category Name</th>
							<th>Main Category</th>
							<th class="text-end">Actions</th>
						</tr>
						</thead>
						<tbody>
						@foreach($subCategories as $category)
							<tr>
								<td>{{ $category->name }}</td>
								<td>
									@if($category->mainCategory)
										<a href="{{ route('category_management.main.edit', $category->mainCategory->id) }}" title="Edit Main Category">
											{{ $category->mainCategory->name }}
										</a>
									@else
										<span class="text-muted fst-italic">N/A</span>
									@endif
								</td>
								<td class="text-end">
									<a href="{{ route('category_management.sub.edit', $category->id) }}" class="btn btn-sm btn-outline-primary me-1" title="Edit Sub Category">
										<i class="fas fa-edit"></i> Edit
									</a>
									<form action="{{ route('category_management.sub.destroy', $category->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete the sub category \'{{ $category->name }}\'? Associated lessons will be unlinked.');">
										@csrf
										@method('DELETE')
										<button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Sub Category">
											<i class="fas fa-trash"></i> Delete
										</button>
									</form>
								</td>
							</tr>
						@endforeach
						</tbody>
					</table>
				</div>
			</div>
			{{-- Pagination Links --}}
			@if ($subCategories->hasPages())
				<div class="card-footer">
					{{ $subCategories->appends(request()->query())->links() }} {{-- Preserve filter on pagination --}}
				</div>
			@endif
		</div>
	@endif
@endsection
