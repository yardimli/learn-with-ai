@extends('layouts.app')

@section('title', 'Edit Category: ' . $category->name . ' - Learn with AI')

@section('content')
	<h1>Edit Category: <span class="text-primary">{{ $category->name }}</span></h1>
	
	<div class="card shadow-sm mt-4">
		<div class="card-body">
			<form action="{{ route('categories.update', $category->id) }}" method="POST">
				@csrf
				@method('PUT') {{-- Important for updates --}}
				
				{{-- Session Messages specific to this form (optional, handled globally too) --}}
				@include('partials.session_messages')
				
				<div class="mb-3">
					<label for="name" class="form-label">Category Name <span class="text-danger">*</span></label>
					<input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $category->name) }}" required>
					@error('name')
					<div class="invalid-feedback">
						{{ $message }}
					</div>
					@enderror
				</div>
				
				<div class="d-flex justify-content-end">
					<a href="{{ route('categories.index') }}" class="btn btn-outline-secondary me-2">Cancel</a>
					<button type="submit" class="btn btn-primary">
						<i class="fas fa-save"></i> Update Category
					</button>
				</div>
			</form>
		</div>
	</div>
@endsection
