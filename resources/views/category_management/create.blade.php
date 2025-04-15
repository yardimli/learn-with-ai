@extends('layouts.app')

@section('title', 'Create Main Category - Learn with AI')

@section('content')
	<h1>Create New Main Category</h1>
	
	<div class="card shadow-sm mt-4">
		<div class="card-body">
			<form action="{{ route('category_management.main.store') }}" method="POST">
				@csrf
				
				@include('partials.session_messages')
				
				<div class="mb-3">
					<label for="name" class="form-label">Main Category Name <span class="text-danger">*</span></label>
					<input type="text"
					       class="form-control @error('name') is-invalid @enderror"
					       id="name"
					       name="name"
					       value="{{ old('name') }}"
					       required
					       aria-describedby="nameHelp">
					@error('name')
					<div class="invalid-feedback">
						{{ $message }}
					</div>
					@enderror
					<div id="nameHelp" class="form-text">The name for the broad category (e.g., Science, History).</div>
				</div>
				
				<div class="d-flex justify-content-end">
					<a href="{{ route('category_management.main.index') }}" class="btn btn-outline-secondary me-2">Cancel</a>
					<button type="submit" class="btn btn-primary">
						<i class="fas fa-save"></i> Save Main Category
					</button>
				</div>
			</form>
		</div>
	</div>
@endsection
