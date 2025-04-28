<div
	class="list-group-item list-group-item-action d-flex flex-column flex-md-row justify-content-between align-items-md-center p-3">
	{{-- Lesson Details --}}
	<div class="mb-2 mb-md-0 me-md-3 flex-grow-1">
		<h5 class="mb-1">
			{{ $lesson->user_title ?? 'Untitled' }}
			@if($lesson->title && $lesson->title != $lesson->user_title)
				<span class="badge bg-danger text-dark" title="AI-generated title">{{ $lesson->title }}</span>
			@endif
			
			@if(!$lesson->ai_generated)
				<span class="badge bg-warning text-dark">Needs AI Content</span>
			@endif
		</h5>
		<p class="mb-1">
			<small class="text-muted">
				Subject: {{ $lesson->subject }} | Created: {{ $lesson->created_at->format('M d, Y H:i') }}
			</small>
			<small class="text-muted">
				Language: <span class="badge bg-secondary">{{ $lesson->language ?? 'N/A' }}</span> |
				Total Questions: <span class="badge bg-light text-dark">{{ $lesson->questions_count ?? 0 }}</span>
				Year/Month: <span class="badge bg-light text-dark">{{ $lesson->year ?? '?' }} / {{ $lesson->month ?? '?' }}</span>
				Week: <span class="badge bg-light text-dark">{{ $lesson->week ?? '?' }}</span>
			</small>
		</p>
		@if($lesson->notes)
			<p class="mb-1 fst-italic small text-muted">Notes: {{ Str::limit($lesson->notes, 120) }}</p>
		@endif
		
		{{-- Current Progress Section -- START --}}
		@if(isset($lesson->currentProgress) && $lesson->currentProgress['total_questions'] > 0)
			@php
				$currentScore = $lesson->currentProgress['score'];
				$totalQuestions = $lesson->currentProgress['total_questions'];
				$percentage = round(($currentScore / $totalQuestions) * 100);
			@endphp
			<div class="mt-2">
				<small class="text-muted">Current Progress (First Attempt Score): {{ $currentScore }}
					/ {{ $totalQuestions }}</small>
				<div class="progress mt-1" role="progressbar" aria-label="Current Lesson Progress"
				     aria-valuenow="{{ $percentage }}" aria-valuemin="0" aria-valuemax="100" style="height: 10px;">
					<div class="progress-bar bg-primary" style="width: {{ $percentage }}%;"></div>
				</div>
			</div>
		@elseif($lesson->questions_count > 0)
			<div class="mt-2">
				<small class="text-muted">Current Progress: Not started yet.</small>
				<div class="progress mt-1" role="progressbar" aria-label="Current Lesson Progress"
				     aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="height: 10px;">
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
		<div class="btn-group" role="group" aria-label="Lesson Actions for {{ $lesson->title ?? $lesson->subject }}">
			@if(!$lesson->ai_generated)
				{{-- Generate AI Content Button for lessons without content --}}
				<button type="button" class="btn btn-sm btn-success me-1 generate-ai-content-btn"
				        data-lesson-id="{{ $lesson->id }}"
				        data-user-title="{{ $lesson->user_title }}"
				        data-lesson-subject="{{ $lesson->subject }}"
				        data-notes="{{ $lesson->notes }}"
				        data-sub-category-id="{{ $lesson->sub_category_id ?? '' }}"
				        data-selected-main-category-id="{{ $lesson->selected_main_category_id ?? '' }}"
				        data-main-category-name="{{ $lesson->subCategory?->mainCategory?->name ?? ($mainCategoryNames[$lesson->selected_main_category_id] ?? '') }}"
				        data-sub-category-name="{{ $lesson->subCategory?->name ?? '' }}"
				        data-preferred-llm="{{ $lesson->preferredLlm ?? '' }}"
				        data-bs-toggle="modal"
				        data-bs-target="#generateContentModal"
				        title="Generate AI Content">
					<i class="fas fa-magic"></i>
					<span class="d-none d-lg-inline">Generate</span>
				</button>
				<button type="button" class="btn btn-sm btn-info me-1 add-video-btn"
				        data-bs-toggle="modal"
				        data-bs-target="#addVideoModal"
				        data-lesson-id="{{ $lesson->id }}"
				        data-lesson-title="{{ $lesson->user_title ?? $lesson->subject }}"
				        title="Add YouTube Video">
					<i class="fab fa-youtube"></i> <span class="d-none d-lg-inline">Video</span>
				</button>
			@else
				{{-- Learn button for lessons with content --}}
				<a href="{{ route('question.interface', ['lesson' => $lesson->id]) }}"
				   class="btn btn-sm btn-success me-1" title="Start Learning">
					<i class="fas fa-play"></i>
					<span class="d-none d-lg-inline">Learn</span>
				</a>
			@endif
			
			<a href="{{ route('lesson.edit', ['lesson' => $lesson->id]) }}"
			   class="btn btn-sm btn-primary me-1" title="Edit Lesson Structure & Questions">
				<i class="fas fa-edit"></i>
				<span class="d-none d-lg-inline">Edit</span>
			</a>
			
			<a href="{{ route('progress.show', ['lesson' => $lesson->id]) }}"
			   class="btn btn-sm btn-info me-1" title="View Learning Progress">
				<i class="fas fa-chart-line"></i>
				<span class="d-none d-lg-inline">Progress</span>
			</a>
			
			<button type="button" class="btn btn-sm btn-warning archive-progress-btn me-1"
			        title="Archive Progress & Reset"
			        data-lesson-id="{{ $lesson->id }}"
			        data-archive-url="{{ route('lesson.archive', ['lesson' => $lesson->id]) }}">
				<i class="fas fa-archive"></i>
				<span class="d-none d-lg-inline">Archive</span>
			</button>
			
			<button type="button" class="btn btn-sm btn-danger delete-lesson-btn"
			        data-lesson-id="{{ $lesson->id }}"
			        data-delete-url="{{ route('lesson.delete', $lesson->id) }}"
			        data-lesson-title="{{ $lesson->user_title ?? $lesson->subject }}"
			        title="Delete Lesson">
				<i class="fas fa-trash"></i> <span class="d-none d-lg-inline">Delete</span>
			</button>
			<form action="{{ route('lesson.delete', $lesson->id) }}" method="POST" class="d-none"
			      id="delete-form-{{ $lesson->id }}">
				@csrf
				@method('DELETE')
			</form>
		</div>
	</div>
</div>
