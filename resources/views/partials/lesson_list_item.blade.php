<div class="list-group-item list-group-item-action d-flex flex-column flex-md-row justify-content-between align-items-md-center p-3">
	{{-- Lesson Details --}}
	<div class="mb-2 mb-md-0 me-md-3 flex-grow-1">
		<h5 class="mb-1">{{ $lesson->title }}</h5>
		<p class="mb-1">
			<small class="text-muted">
				Subject: {{ $lesson->name }} | Created: {{ $lesson->created_at->format('M d, Y H:i') }}
			</small>
			<small class="text-muted">
				Language: <span class="badge bg-secondary">{{ $lesson->language ?? 'N/A' }}</span> |
				Total Questions: <span class="badge bg-light text-dark">{{ $lesson->questions_count ?? 0 }}</span>
			</small>
		</p>
		
		{{-- Current Progress Section -- START --}}
		@if(isset($lesson->currentProgress) && $lesson->currentProgress['total_questions'] > 0)
			@php
				$currentScore = $lesson->currentProgress['score'];
				$totalQuestions = $lesson->currentProgress['total_questions'];
				$percentage = round(($currentScore / $totalQuestions) * 100);
			@endphp
			<div class="mt-2">
				<small class="text-muted">Current Progress (First Attempt Score): {{ $currentScore }} / {{ $totalQuestions }}</small>
				<div class="progress mt-1" role="progressbar" aria-label="Current Lesson Progress" aria-valuenow="{{ $percentage }}" aria-valuemin="0" aria-valuemax="100" style="height: 10px;">
					<div class="progress-bar bg-primary" style="width: {{ $percentage }}%;"></div>
				</div>
			</div>
		@elseif($lesson->questions_count > 0)
			<div class="mt-2">
				<small class="text-muted">Current Progress: Not started yet.</small>
				<div class="progress mt-1" role="progressbar" aria-label="Current Lesson Progress" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="height: 10px;">
					<div class="progress-bar bg-primary" style="width: 0%;"></div>
				</div>
			</div>
		@else
			<div class="mt-2">
				<small class="text-muted">No questions added to this lesson yet.</small>
			</div>
		@endif
		{{-- Current Progress Section -- END --}}
	
	</div>
	
	
	{{-- Action Buttons --}}
	<div class="text-md-end mt-2 mt-md-0 flex-shrink-0">
		<div class="btn-group" role="group" aria-label="Lesson Actions for {{ $lesson->title }}">
			{{-- Buttons remain the same --}}
			<a href="{{ route('question.interface', ['lesson' => $lesson->session_id]) }}" class="btn btn-sm btn-success me-1" title="Start Learning">
				<i class="fas fa-play"></i> <span class="d-none d-lg-inline">Learn</span>
			</a>
			<a href="{{ route('lesson.edit', ['lesson' => $lesson->session_id]) }}" class="btn btn-sm btn-primary me-1" title="Edit Lesson Structure & Questions">
				<i class="fas fa-edit"></i> <span class="d-none d-lg-inline">Edit</span>
			</a>
			<a href="{{ route('progress.show', ['lesson' => $lesson->session_id]) }}" class="btn btn-sm btn-info me-1" title="View Learning Progress">
				<i class="fas fa-chart-line"></i> <span class="d-none d-lg-inline">Progress</span>
			</a>
			<button type="button" class="btn btn-sm btn-warning archive-progress-btn me-1" title="Archive Progress & Reset" data-lesson-session-id="{{ $lesson->session_id }}" data-archive-url="{{ route('lesson.archive', ['lesson' => $lesson->session_id]) }}">
				<i class="fas fa-archive"></i> <span class="d-none d-lg-inline">Archive</span>
			</button>
			<form action="{{ route('lesson.delete', $lesson->session_id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this entire lesson? This cannot be undone.');">
				@csrf
				@method('DELETE')
				<button type="submit" class="btn btn-sm btn-danger" title="Delete Lesson">
					<i class="fas fa-trash"></i> <span class="d-none d-lg-inline">Delete</span>
				</button>
			</form>
		</div>
	</div>
</div>
