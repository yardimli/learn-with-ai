@extends('layouts.app')

@section('title', 'Edit Sub Category: ' . $subCategory->name . ' - Learn with AI')

@section('content')
	<h1>Edit Sub Category: <span class="text-primary">{{ $subCategory->name }}</span></h1>
	
	<div class="card shadow-sm mt-4">
		<div class="card-body">
			<form action="{{ route('category_management.sub.update', $subCategory->id) }}" method="POST">
				@csrf
				@method('PUT') {{-- Important for updates --}}
				
				@include('partials.session_messages')
				
				<div class="mb-3">
					<label for="main_category_id" class="form-label">Main Category <span class="text-danger">*</span></label>
					<select class="form-select @error('main_category_id') is-invalid @enderror"
					        id="main_category_id"
					        name="main_category_id"
					        required>
						<option value="" disabled >-- Select Main Category --</option>
						@foreach ($mainCategories as $id => $name)
							<option value="{{ $id }}" {{ old('main_category_id', $subCategory->main_category_id) == $id ? 'selected' : '' }}>
								{{ $name }}
							</option>
						@endforeach
					</select>
					@error('main_category_id')
					<div class="invalid-feedback">
						{{ $message }}
					</div>
					@enderror
				</div>
				
				<div class="mb-3">
					<label for="name" class="form-label">Sub Category Name <span class="text-danger">*</span></label>
					<input type="text"
					       class="form-control @error('name') is-invalid @enderror"
					       id="name"
					       name="name"
					       value="{{ old('name', $subCategory->name) }}"
					       required
					       aria-describedby="nameHelp">
					@error('name')
					<div class="invalid-feedback">
						{{ $message }}
					</div>
					@enderror
					<div id="nameHelp" class="form-text">Must be unique within the chosen Main Category.</div>
				</div>
				
				
				<div class="d-flex justify-content-end">
					<a href="{{ route('category_management.sub.index') }}" class="btn btn-outline-secondary me-2">Cancel</a>
					<button type="submit" class="btn btn-primary">
						<i class="fas fa-save"></i> Update Sub Category
					</button>
				</div>
			</form>
		</div>
	</div>
@endsection
