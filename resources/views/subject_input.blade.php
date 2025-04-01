@extends('layouts.app')

@section('title', 'Enter Subject - Learn with AI')

@section('content')
	<h1 class="text-center mb-4">Learn Something New with AI</h1>
	
	<div class="content-card"> {{-- Use consistent card class --}}
		<form id="subjectForm" action="{{ route('content.start_learning') }}" method="POST">
			@csrf
			<div class="mb-3">
				<label for="subjectInput" class="form-label fs-5">Enter a Subject:</label>
				<input type="text" class="form-control form-control-lg" id="subjectInput" name="subject" placeholder="e.g., Quantum Physics, Photosynthesis" required>
			</div>
			<div class="mb-3">
				<label for="llmSelect" class="form-label">Choose AI Model (Optional):</label>
				<select class="form-select" id="llmSelect" name="llm">
					<option value="">Use Default ({{ env('DEFAULT_LLM', 'Not Set') }})</option>
					@foreach ($llms ?? [] as $llm)
						<option value="{{ $llm['id'] }}">{{ $llm['name'] }}</option>
					@endforeach
				</select>
				<small class="form-text text-muted">More expensive models may give better results but cost more.</small>
			</div>
			<div class="d-grid">
				<button type="submit" id="startLearningButton" class="btn btn-primary btn-lg">
					<span id="startLearningSpinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
					Start Learning
				</button>
			</div>
		</form>
	</div>
@endsection

@push('scripts')
	<script src="{{ asset('js/subject_input.js') }}"></script>
@endpush
