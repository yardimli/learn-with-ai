@extends('layouts.app')

@section('title', 'Existing Lessons - Learn with AI')

@section('content')
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h1>Existing Lessons</h1>
		<a href="{{ route('home') }}" class="btn btn-primary">
			<i class="fas fa-plus"></i> Create New Lesson
		</a>
	</div>
	
	@if(!isset($lessons) || $lessons->isEmpty())
		<div class="alert alert-info text-center" role="alert">
			No lessons created yet. <a href="{{ route('home') }}">Create your first lesson!</a>
		</div>
	@else
		<div class="list-group shadow-sm">
			@foreach($lessons as $lesson)
				<div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center row p-2">
					{{-- Lesson Details Column --}}
					<div class="col-md-7 col-lg-8">
						<h5 class="mb-1">{{ $lesson->title }}</h5>
						<p class="mb-1"><small class="text-muted">Subject: {{ $lesson->name }} |
								Created: {{ $lesson->created_at->format('M d, Y H:i') }}</small></p>
						{{-- Optionally display saved settings --}}
						<p class="mb-0"><small class="text-muted">
								Model: <span class="badge bg-secondary">{{ $lesson->preferredLlm }}</span> |
								Voice: <span class="badge bg-secondary">{{ $lesson->ttsEngine }}/{{ $lesson->ttsVoice }} ({{ $lesson->ttsLanguageCode }})</span>
							</small></p>
					</div>
					
					{{-- Action Buttons Column --}}
					<div class="col-md-5 col-lg-4 text-md-end mt-2 mt-md-0">
						<div class="btn-group" role="group" aria-label="Lesson Actions">
							<a href="{{ route('question.interface', ['lesson' => $lesson->session_id]) }}"
							   class="btn btn-sm btn-success me-1" title="Start Learning">
								<i class="fas fa-play"></i> <span class="d-none d-md-inline">Learn</span>
							</a>
							<a href="{{ route('lesson.edit', ['lesson' => $lesson->session_id]) }}"
							   class="btn btn-sm btn-primary me-1" title="Edit Lesson Structure & Questions">
								<i class="fas fa-edit"></i> <span class="d-none d-md-inline">Edit</span>
							</a>
							<a href="{{ route('progress.show', ['lesson' => $lesson->session_id]) }}" class="btn btn-sm btn-info me-1"
							   title="View Learning Progress">
								<i class="fas fa-chart-line"></i> <span class="d-none d-md-inline">Progress</span>
							</a>
							{{-- Archive button remains here, logic moved to common.js --}}
							<button type="button" class="btn btn-sm btn-warning archive-progress-btn" title="Archive Progress & Reset"
							        data-lesson-session-id="{{ $lesson->session_id }}"
							        data-archive-url="{{ route('lesson.archive', ['lesson' => $lesson->session_id]) }}">
								<i class="fas fa-archive"></i> <span class="d-none d-md-inline">Archive</span>
							</button>
							
							<form action="{{-- route('lesson.destroy', $lesson->id) --}}" method="POST" class="d-inline"
							      onsubmit="return confirm('Are you sure you want to delete this entire lesson and all its data? This cannot be undone.');">
								@csrf
								@method('DELETE')
								<button type="submit" class="btn btn-sm btn-danger" title="Delete Lesson">
									<i class="fas fa-trash"></i> <span class="d-none d-md-inline">Delete</span>
								</button>
							</form>
						</div>
					</div>
				</div>
			@endforeach
		</div>
	@endif
@endsection

@push('scripts')
	{{-- No specific scripts needed here anymore IF archive logic is in common.js --}}
	{{-- If you prefer keeping JS separate: <script src="{{ asset('js/lessons_list.js') }}"></script> --}}
@endpush
