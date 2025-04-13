{{-- resources/views/partials/lesson_list_item.blade.php --}}
{{-- Expects a $lesson variable --}}
<div class="list-group-item list-group-item-action d-flex flex-column flex-md-row justify-content-between align-items-md-center p-3">
	{{-- Lesson Details --}}
	<div class="mb-2 mb-md-0 me-md-3 flex-grow-1">
		<h5 class="mb-1">{{ $lesson->title }}</h5>
		<p class="mb-1">
			<small class="text-muted">
				Subject: {{ $lesson->name }} | Created: {{ $lesson->created_at->format('M d, Y H:i') }}
			</small>
		</p>
		{{-- Display Category (if available in this context) and Language --}}
		<p class="mb-1">
			<small class="text-muted">
				@if($lesson->category)
					Category: <span class="badge bg-info text-dark">{{ $lesson->category->name }}</span> |
				@endif
				Language: <span class="badge bg-secondary">{{ $lesson->language ?? 'N/A' }}</span> |
				Questions: <span class="badge bg-light text-dark">{{ $lesson->questions_count ?? 0 }}</span>
			</small>
		</p>
		{{-- Optionally display saved settings --}}
		<p class="mb-0">
			<small class="text-muted">
				Model: <span class="badge bg-secondary">{{ $lesson->preferredLlm }}</span> |
				Voice: <span class="badge bg-secondary">{{ $lesson->ttsEngine }}/{{ $lesson->ttsVoice }} ({{ $lesson->ttsLanguageCode }})</span>
			</small>
		</p>
	</div>
	
	{{-- Action Buttons --}}
	<div class="text-md-end mt-2 mt-md-0 flex-shrink-0"> {{-- flex-shrink-0 prevents buttons wrapping too early --}}
		<div class="btn-group" role="group" aria-label="Lesson Actions for {{ $lesson->title }}">
			<a href="{{ route('question.interface', ['lesson' => $lesson->session_id]) }}" class="btn btn-sm btn-success me-1" title="Start Learning">
				<i class="fas fa-play"></i> <span class="d-none d-lg-inline">Learn</span>
			</a>
			<a href="{{ route('lesson.edit', ['lesson' => $lesson->session_id]) }}" class="btn btn-sm btn-primary me-1" title="Edit Lesson Structure & Questions">
				<i class="fas fa-edit"></i> <span class="d-none d-lg-inline">Edit</span>
			</a>
			<a href="{{ route('progress.show', ['lesson' => $lesson->session_id]) }}" class="btn btn-sm btn-info me-1" title="View Learning Progress">
				<i class="fas fa-chart-line"></i> <span class="d-none d-lg-inline">Progress</span>
			</a>
			{{-- Archive button (uses common.js) --}}
			<button type="button" class="btn btn-sm btn-warning archive-progress-btn me-1" title="Archive Progress & Reset"
			        data-lesson-session-id="{{ $lesson->session_id }}"
			        data-archive-url="{{ route('lesson.archive', ['lesson' => $lesson->session_id]) }}">
				<i class="fas fa-archive"></i> <span class="d-none d-lg-inline">Archive</span>
			</button>
			{{-- Delete button --}}
			<form action="{{ route('lesson.delete', $lesson->session_id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this entire lesson and all its data (questions, progress, images, audio)? This cannot be undone.');">
				@csrf
				@method('DELETE')
				<button type="submit" class="btn btn-sm btn-danger" title="Delete Lesson">
					<i class="fas fa-trash"></i> <span class="d-none d-lg-inline">Delete</span>
				</button>
			</form>
		</div>
	</div>
</div>
