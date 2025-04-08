@extends('layouts.app')

@section('title', 'Enter Subject - Learn with AI')

@section('content')
	<h1 class="text-center mb-4">Learn Something New with AI</h1>
	
	<div class="content-card">
		{{-- Point form action to the preview route --}}
		<form id="subjectForm" action="{{ route('lesson.generate.structure') }}" method="POST">
			@csrf
			{{-- Add hidden input for the actual create URL for JS --}}
			<input type="hidden" id="saveStructureUrl" value="{{ route('lesson.save.structure') }}">
			
			<div class="mb-3">
				<label for="subjectInput" class="form-label fs-5">Enter a Subject:</label>
				<input type="text" class="form-control form-control-lg" id="subjectInput" name="subject"
				       placeholder="e.g., Quantum Physics, Photosynthesis" value="cats" required>
			</div>
			
			<div class="mb-3">
				<label for="llmSelect" class="form-label">Choose AI Model (Optional):</label>
				<select class="form-select" id="llmSelect" name="llm">
					@php
						// Find the default LLM name to display
						$defaultLlmId = env('DEFAULT_LLM', '');
						$defaultLlmName = 'Not Set';
						if ($defaultLlmId && !empty($llms)) {
								foreach ($llms as $llm) {
										if ($llm['id'] === $defaultLlmId) {
												$defaultLlmName = $llm['name'];
												break;
										}
								}
						 }
					@endphp
					<option value="{{$defaultLlmId}}">Use Default - {{ $defaultLlmName }}</option>
					@foreach ($llms ?? [] as $llm)
						{{-- Filter out the default if already shown, or handle duplicates --}}
						@if($llm['id'] !== $defaultLlmId)
							<option value="{{ $llm['id'] }}">{{ $llm['name'] }}</option>
						@endif
					@endforeach
				</select>
				<small class="form-text text-muted">Experimenting with different models might yield better lesson plans.</small>
			</div>
			
			<div class="d-grid">
				<button type="submit" id="startLearningButton" class="btn btn-primary btn-lg">
					<span id="startLearningSpinner" class="spinner-border spinner-border-sm d-none" role="status"
					      aria-hidden="true"></span>
					Generate Lesson Preview
				</button>
			</div>
		</form>
	</div>
	
	
	{{-- NEW: List of Existing Lessons --}}
	<hr>
	<h2 class="text-center my-4">Existing Lessons</h2>
	
	@if(!isset($subjects) || $subjects->isEmpty())
		<p class="text-center text-muted">No lessons created yet.</p>
	@else
 		<div class="list-group">
			@foreach($subjects as $subject)
				<div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center row p-1">
					<div class="col-lg-8">
						<h5 class="mb-1">{{ $subject->title }}</h5>
						<p class="mb-1"><small class="text-muted">Subject: {{ $subject->name }} |
								Created: {{ $subject->created_at->format('M d, Y H:i') }}</small></p>
					</div>
					<div class="col-lg-4 text-end">
						{{-- Add a button to preview the lesson --}}
						<a href="{{ route('question.interface', ['subject' => $subject->session_id]) }}"
						   class="btn btn-sm btn-outline-success me-2" title="Start Learning">
							<i class="fas fa-eye"></i> Start Learning
						</a>
						<a href="{{ route('lesson.edit', ['subject' => $subject->session_id]) }}"
						   class="btn btn-sm btn-outline-primary" title="Edit">
							<i class="fas fa-edit"></i> Edit
						</a>
						<a href="{{ route('progress.show', ['subject' => $subject->session_id]) }}"
						   class="btn btn-sm btn-outline-info" title="View Progress">
							<i class="fas fa-chart-line"></i> <span class="d-none d-md-inline">Progress</span>
						</a>
						{{-- Add Delete button later if needed --}}
						<button type="button" class="btn btn-sm btn-outline-warning archive-progress-btn"
						        title="Archive Progress & Reset" data-subject-session-id="{{ $subject->session_id }}"
						        data-archive-url="{{ route('lesson.archive', ['subject' => $subject->session_id]) }}">
							<i class="fas fa-archive"></i> <span class="d-none d-md-inline">Archive</span>
						</button>
					
					</div>
				</div>
			@endforeach
		</div>
	@endif
	
	
	<!-- Lesson Plan Preview Modal -->
	<div class="modal fade" id="lessonPreviewModal" tabindex="-1" aria-labelledby="lessonPreviewModalLabel"
	     aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false"> {{-- Static backdrop --}}
		<div class="modal-dialog modal-lg modal-dialog-scrollable"> {{-- Larger & Scrollable --}}
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="lessonPreviewModalLabel">Lesson Plan Preview</h5>
					{{-- <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button> --}} {{-- Disable closing via X --}}
				</div>
				<div class="modal-body" id="lessonPreviewBody">
					{{-- Content will be loaded via JavaScript --}}
					<div class="text-center">
						<div class="spinner-border text-primary" role="status">
							<span class="visually-hidden">Loading preview...</span>
						</div>
						<p class="mt-2">Generating lesson preview...</p>
					</div>
				</div>
				<div class="modal-footer">
                 <span id="modalLoadingIndicator" class="me-auto d-none">
                     <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                     Creating Lesson...
                 </span>
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="cancelPreviewButton">Cancel
					</button>
					<button type="button" class="btn btn-primary" id="confirmPreviewButton" disabled>Confirm & Create Lesson
					</button> {{-- Disabled initially --}}
				</div>
			</div>
		</div>
	</div>
@endsection

@push('scripts')
	<script src="{{ asset('js/subject_input.js') }}"></script>
@endpush
