@extends('layouts.app')

@section('title', 'Manage Main Categories - Learn with AI')

@section('content')
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h1>Manage Main Categories</h1>
		<div>
			<a href="{{ route('category_management.sub.index') }}" class="btn btn-outline-secondary me-2">
				<i class="fas fa-sitemap"></i> View Sub Categories
			</a>
			<a href="{{ route('category_management.main.create') }}" class="btn btn-primary">
				<i class="fas fa-plus"></i> Create Main Category
			</a>
			<a href="{{ route('lessons.list') }}" class="btn btn-outline-secondary ms-2">
				<i class="fas fa-arrow-left"></i> Back to Lessons List
			</a>
		</div>
	</div>
	
	@include('partials.session_messages')
	
	@if($mainCategories->isEmpty())
		<div class="alert alert-info" role="alert">
			No main categories found. <a href="{{ route('category_management.main.create') }}">Create the first one!</a>
		</div>
	@else
		<div class="card shadow-sm">
			<div class="card-body">
				<div class="table-responsive">
					<table class="table table-hover align-middle">
						<thead>
						<tr>
							<th>Name</th>
							<th class="text-end">Actions</th>
						</tr>
						</thead>
						<tbody>
						@foreach($mainCategories as $category)
							<tr>
								<td>{{ $category->name }}</td>
								<td class="text-end">
									<a href="{{ route('category_management.sub.create', $category->id) }}" class="btn btn-sm btn-outline-success me-1" title="Add Sub Category">
										<i class="fas fa-plus-circle"></i> Add Sub
									</a>
									<a href="{{ route('category_management.main.edit', $category->id) }}" class="btn btn-sm btn-outline-primary me-1" title="Edit Main Category">
										<i class="fas fa-edit"></i> Edit
									</a>
									<form action="{{ route('category_management.main.destroy', $category->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete the main category \'{{ $category->name }}\'? This will also delete ALL its sub-categories and unlink associated lessons.');">
										@csrf
										@method('DELETE')
										<button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Main Category">
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
			@if ($mainCategories->hasPages())
				<div class="card-footer">
					{{ $mainCategories->links() }}
				</div>
			@endif
		</div>
	@endif
@endsection
