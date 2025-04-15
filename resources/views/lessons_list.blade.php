@extends('layouts.app')
@section('title', 'Existing Lessons - Learn with AI')

@section('content')
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h1>Existing Lessons</h1>
		<div>
			<a href="{{ route('home') }}" class="btn btn-primary mb-1 mb-md-0">
				<i class="fas fa-plus"></i> Create New Lesson
			</a>
			<a href="{{ route('category_management.main.index') }}" class="btn btn-outline-info ms-md-2 mb-1 mb-md-0"> {{-- Updated Route --}}
				<i class="fas fa-tags"></i> Manage Categories
			</a>
		</div>
	</div>
	
	@include('partials.session_messages')
	
	@if(!isset($groupedLessons) || $groupedLessons->isEmpty())
		<div class="alert alert-info text-center" role="alert">
			No lessons created yet. <a href="{{ route('home') }}">Create your first lesson!</a>
		</div>
	@else
		<div class="accordion shadow-sm" id="lessonsAccordion">
			
			{{-- Handle Uncategorized Lessons First --}}
			@if(isset($groupedLessons[null]) && $groupedLessons[null]->isNotEmpty())
				@php $uncategorizedLessons = $groupedLessons[null]; @endphp
				<div class="accordion-item">
					<h2 class="accordion-header" id="headingUncategorized">
						<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseUncategorized" aria-expanded="false" aria-controls="collapseUncategorized">
							<i class="fas fa-question-circle me-2 text-muted"></i> Uncategorized Lessons
							<span class="badge bg-secondary ms-2">{{ $uncategorizedLessons->count() }}</span>
						</button>
					</h2>
					<div id="collapseUncategorized" class="accordion-collapse collapse" aria-labelledby="headingUncategorized" data-bs-parent="#lessonsAccordion">
						<div class="accordion-body p-0">
							<div class="list-group list-group-flush">
								@foreach($uncategorizedLessons as $lesson)
									@include('partials.lesson_list_item', ['lesson' => $lesson])
								@endforeach
							</div>
						</div>
					</div>
				</div>
			@endif
			
			{{-- Loop through Ordered Main Categories --}}
			@foreach($orderedMainCategoryIds as $mainCategoryId)
				@if(isset($groupedLessons[$mainCategoryId]) && $groupedLessons[$mainCategoryId]->isNotEmpty())
					@php
						$mainCategoryName = $mainCategoryNames[$mainCategoryId] ?? 'Unknown Main Category';
						$lessonsInMainCategory = $groupedLessons[$mainCategoryId];
						$collapseId = 'collapseMainCategory' . Str::slug($mainCategoryId);
						$headingId = 'headingMainCategory' . Str::slug($mainCategoryId);
					@endphp
					<div class="accordion-item">
						<h2 class="accordion-header" id="{{ $headingId }}">
							<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}" aria-expanded="false" aria-controls="{{ $collapseId }}">
								<i class="fas fa-folder-open me-2"></i> {{ $mainCategoryName }}
								<span class="badge bg-primary ms-2">{{ $lessonsInMainCategory->count() }}</span>
							</button>
						</h2>
						<div id="{{ $collapseId }}" class="accordion-collapse collapse" aria-labelledby="{{ $headingId }}" data-bs-parent="#lessonsAccordion">
							<div class="accordion-body p-0">
								<div class="list-group list-group-flush">
									@foreach($lessonsInMainCategory as $lesson)
										@include('partials.lesson_list_item', ['lesson' => $lesson])
									@endforeach
								</div>
							</div>
						</div>
					</div>
				@endif
			@endforeach
		</div> {{-- End Accordion --}}
	@endif
@endsection

@push('scripts')
	{{-- No specific JS needed here if using data attributes for collapse --}}
@endpush
