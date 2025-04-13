@extends('layouts.app')

@section('title', 'Manage Categories - Learn with AI')

@section('content')
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h1>Manage Categories</h1>
		<div>
			<a href="{{ route('categories.create') }}" class="btn btn-primary">
				<i class="fas fa-plus"></i> Create New Category
			</a>
			<a href="{{ route('home') }}" class="btn btn-outline-secondary ms-2">
				<i class="fas fa-arrow-left"></i> Back to Create Lesson
			</a>
		</div>
	</div>
	
	{{-- Session Messages --}}
	@include('partials.session_messages')
	
	@if($categories->isEmpty())
		<div class="alert alert-info" role="alert">
			No categories found. <a href="{{ route('categories.create') }}">Create the first one!</a>
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
						@foreach($categories as $category)
							<tr>
								<td>{{ $category->name }}</td>
								<td class="text-end">
									<a href="{{ route('categories.edit', $category->id) }}" class="btn btn-sm btn-outline-primary me-1" title="Edit">
										<i class="fas fa-edit"></i> Edit
									</a>
									<form action="{{ route('categories.destroy', $category->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete the category \'{{ $category->name }}\'? Lessons using this category will have their category unassigned.');">
										@csrf
										@method('DELETE')
										<button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
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
			@if ($categories->hasPages())
				<div class="card-footer">
					{{ $categories->links() }}
				</div>
			@endif
		</div>
	@endif
@endsection
