@extends('layouts.app')

@section('title', 'Edit Main Category: ' . $mainCategory->name . ' - Learn with AI')

@section('content')
	<h1>Edit Main Category: <span class="text-primary">{{ $mainCategory->name }}</span></h1>
	
	<div class="card shadow-sm mt-4">
		<div class="card-body">
			<form action="{{ route('category_management.main.update', $mainCategory->id) }}" method="POST">
				@csrf
				@method('PUT') {{-- Important for updates --}}
				
				@include('partials.session_messages')
				
				<div class="mb-3">
					<label for="name" class="form-label">Main Category Name <span class="text-danger">*</span></label>
					<input type="text"
					       class="form-control @error('name') is-invalid @enderror"
					       id="name"
					       name="name"
					       value="{{ old('name', $mainCategory->name) }}"
					       required>
					@error('name')
					<div class="invalid-feedback">
						{{ $message }}
					</div>
					@enderror
				</div>
				
				<div class="d-flex justify-content-end">
					<a href="{{ route('category_management.main.index') }}" class="btn btn-outline-secondary me-2">Cancel</a>
					<button type="submit" class="btn btn-primary">
						<i class="fas fa-save"></i> Update Main Category
					</button>
				</div>
			</form>
		</div>
	</div>
@endsection
