@extends('layouts.app')

@section('title', 'Create Sub Category - Learn with AI')

@section('content')
	<h1>Create New Sub Category</h1>
	
	<div class="card shadow-sm mt-4">
		<div class="card-body">
			<form action="{{ route('category_management.sub.store') }}" method="POST">
				@csrf
				
				@include('partials.session_messages')
				
				<div class="mb-3">
					<label for="main_category_id" class="form-label">Main Category <span class="text-danger">*</span></label>
					<select class="form-select @error('main_category_id') is-invalid @enderror"
					        id="main_category_id"
					        name="main_category_id"
					        required>
						<option value="" disabled {{ is_null($selectedMainCategoryId) ? 'selected' : '' }}>-- Select Main Category --</option>
						@foreach ($mainCategories as $id => $name)
							<option value="{{ $id }}" {{ $selectedMainCategoryId == $id ? 'selected' : '' }}>
								{{ $name }}
							</option>
						@endforeach
					</select>
					@error('main_category_id')
					<div class="invalid-feedback">
						{{ $message }}
					</div>
					@enderror
					<div class="form-text">The broader category this sub-category belongs to.</div>
				</div>
				
				
				<div class="mb-3">
					<label for="name" class="form-label">Sub Category Name <span class="text-danger">*</span></label>
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
					<div id="nameHelp" class="form-text">Specific topic name within the main category (e.g., Photosynthesis, World War II). Must be unique within the chosen Main Category.</div>
				</div>
				
				<div class="d-flex justify-content-end">
					<a href="{{ route('category_management.sub.index') }}" class="btn btn-outline-secondary me-2">Cancel</a>
					<button type="submit" class="btn btn-primary">
						<i class="fas fa-save"></i> Save Sub Category
					</button>
				</div>
			</form>
		</div>
	</div>
@endsection
