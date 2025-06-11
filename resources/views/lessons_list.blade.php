@extends('layouts.app')

@section('title', 'Existing Lessons - Learn with AI')

@section('content')
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h1>Existing Lessons</h1>
		<div>
{{--			<button id="generateAllButton" class="btn btn-warning mb-1 mb-md-0 me-md-2"--}}
{{--			        title="Automatically generate content for all pending lessons">--}}
{{--				<i class="fas fa-robot"></i>--}}
{{--				<span id="generateAllText">Generate All Pending</span>--}}
{{--				<span id="generateAllSpinner" class="spinner-border spinner-border-sm d-none ms-1" role="status"--}}
{{--				      aria-hidden="true"></span>--}}
{{--			</button>--}}
			<a href="{{ route('create-lesson') }}" class="btn btn-primary mb-1 mb-md-0">
				<i class="fas fa-plus"></i> Create New Lesson
			</a>
			<a href="{{ route('category_management.main.index') }}" class="btn btn-outline-info ms-md-2 mb-1 mb-md-0">
				<i class="fas fa-tags"></i> Manage Categories
			</a>
		</div>
	</div>
	
	@include('partials.session_messages')
	
	@if (!isset($groupedLessons) || $groupedLessons->isEmpty())
		<div class="alert alert-info text-center" role="alert">
			No lessons created yet. <a href="{{ route('create-lesson') }}">Create your first lesson!</a>
		</div>
	@else
		<div class="accordion shadow-sm" id="lessonsAccordion">
			{{-- Handle Uncategorized Lessons First (Lessons with no Main Category association at all) --}}
			@if (isset($groupedLessons[null]) && $groupedLessons[null]->isNotEmpty())
				@php $uncategorizedLessons = $groupedLessons[null]; @endphp
				<div class="accordion-item">
					<h2 class="accordion-header" id="headingUncategorized">
						<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
						        data-bs-target="#collapseUncategorized" aria-expanded="false" aria-controls="collapseUncategorized">
							<i class="fas fa-question-circle me-2 text-muted"></i> Uncategorized Lessons
							<span class="badge bg-secondary ms-2">{{ $uncategorizedLessons->count() }}</span>
						</button>
					</h2>
					<div id="collapseUncategorized" class="accordion-collapse collapse" aria-labelledby="headingUncategorized"
					     data-bs-parent="#lessonsAccordion">
						<div class="accordion-body p-0">
							<div class="list-group list-group-flush">
								@foreach ($uncategorizedLessons as $lesson)
									@include('partials.lesson_list_item', ['lesson' => $lesson])
								@endforeach
							</div>
						</div>
					</div>
				</div>
			@endif
			
			{{-- Loop through Ordered Main Categories --}}
			@foreach ($orderedMainCategoryIds as $mainCategoryId)
				@if (isset($groupedLessons[$mainCategoryId]) && $groupedLessons[$mainCategoryId]->isNotEmpty())
					@php
						$mainCategoryName = $mainCategoryNames[$mainCategoryId] ?? 'Unknown Main Category';
						$lessonsInMainCategory = $groupedLessons[$mainCategoryId];
						$lessonsGroupedBySubCategory = $lessonsInMainCategory->groupBy('sub_category_id');
						$mainCollapseId = 'collapseMainCategory' . Str::slug($mainCategoryId);
						$mainHeadingId = 'headingMainCategory' . Str::slug($mainCategoryId);
						$startExpanded = $lessonsInMainCategory->count() > 5 || $lessonsGroupedBySubCategory->count() > 1;
					@endphp
					<div class="accordion-item">
						<h2 class="accordion-header" id="{{ $mainHeadingId }}">
							<button class="accordion-button {{ $startExpanded ? '' : 'collapsed' }}" type="button"
							        data-bs-toggle="collapse" data-bs-target="#{{ $mainCollapseId }}"
							        aria-expanded="{{ $startExpanded ? 'true' : 'false' }}" aria-controls="{{ $mainCollapseId }}">
								<i class="fas fa-folder-open me-2"></i> {{ $mainCategoryName }}
								<span class="badge bg-primary ms-2">{{ $lessonsInMainCategory->count() }}</span>
							</button>
						</h2>
						<div id="{{ $mainCollapseId }}" class="accordion-collapse collapse {{ $startExpanded ? 'show' : '' }}"
						     aria-labelledby="{{ $mainHeadingId }}" data-bs-parent="#lessonsAccordion">
							<div class="accordion-body p-0">
								@php
									$lessonsWithoutSub = collect();
									if (isset($lessonsGroupedBySubCategory[null])) {
											$lessonsWithoutSub = $lessonsWithoutSub->merge($lessonsGroupedBySubCategory[null]);
											unset($lessonsGroupedBySubCategory[null]);
									}
									if (isset($lessonsGroupedBySubCategory[''])) { // Check for empty string key
											$lessonsWithoutSub = $lessonsWithoutSub->merge($lessonsGroupedBySubCategory['']);
											unset($lessonsGroupedBySubCategory['']);
									}
								@endphp
								@if ($lessonsWithoutSub->isNotEmpty())
									@php $noSubCollapseId = "subCollapse-main{$mainCategoryId}-noSub"; @endphp
									<div class="sub-category-group px-3 pt-2 pb-1">
										<h4 class="sub-category-heading mb-0 collapse-trigger"
										    data-bs-toggle="collapse" data-bs-target="#{{ $noSubCollapseId }}"
										    aria-expanded="false" aria-controls="{{ $noSubCollapseId }}" role="button">
											<i class="fas fa-minus-circle me-2 text-muted"></i> (No Sub Category)
											<span class="badge bg-light text-dark ms-2">{{ $lessonsWithoutSub->count() }}</span>
											<i class="fas fa-chevron-down collapse-icon ms-auto"></i>
										</h4>
										<div class="collapse" id="{{ $noSubCollapseId }}">
											<div class="list-group list-group-flush mt-2 mb-2">
												@foreach ($lessonsWithoutSub as $lesson)
													@include('partials.lesson_list_item', ['lesson' => $lesson])
												@endforeach
											</div>
										</div>
									</div>
								@endif
								
								@foreach ($lessonsGroupedBySubCategory as $subCategoryId => $lessonsInSubCategory)
									@php
										$subCategoryName = $lessonsInSubCategory->first()?->subCategory?->name ?? 'Unknown Sub Category';
										$safeSubCategoryId = $subCategoryId ?: 'unknown';
										$subCollapseId = "subCollapse-main{$mainCategoryId}-sub{$safeSubCategoryId}";
									@endphp
									<div class="sub-category-group px-3 pt-2 pb-1">
										<h4 class="sub-category-heading mb-0 collapse-trigger"
										    data-bs-toggle="collapse" data-bs-target="#{{ $subCollapseId }}"
										    aria-expanded="false" aria-controls="{{ $subCollapseId }}" role="button">
											<i class="fas fa-stream me-2 text-info"></i> {{ $subCategoryName }}
											<span class="badge bg-danger text-dark ms-2">{{ $lessonsInSubCategory->count() }}</span>
											<i class="fas fa-chevron-down collapse-icon ms-auto"></i>
										</h4>
										<div class="collapse" id="{{ $subCollapseId }}">
											<div class="list-group list-group-flush mt-2 mb-2">
												@foreach ($lessonsInSubCategory as $lesson)
													@include('partials.lesson_list_item', ['lesson' => $lesson])
												@endforeach
											</div>
										</div>
									</div>
								@endforeach
							</div>
						</div>
					</div>
				@endif
			@endforeach
		</div>
	@endif
@endsection

@push('styles')
	<style>
      .sub-category-group {
          border-bottom: 1px solid var(--bs-border-color-translucent);
          margin-left: 20px;
      }

      .sub-category-group:last-child {
          border-bottom: none;
      }

      .sub-category-heading.collapse-trigger {
          font-size: 1.1rem;
          font-weight: 500;
          color: var(--bs-body-color);
          cursor: pointer;
          padding: 0.5rem 0;
          display: flex;
          align-items: center;
          transition: background-color 0.15s ease-in-out;
      }

      .sub-category-heading.collapse-trigger:hover {
          background-color: rgba(var(--bs-emphasis-color-rgb), 0.05);
      }

      .sub-category-heading .collapse-icon {
          transition: transform 0.3s ease;
      }

      .sub-category-heading.collapse-trigger[aria-expanded="true"] .collapse-icon {
          transform: rotate(-180deg);
      }

      .sub-category-group .list-group-flush .list-group-item {
          border-left: 0;
          border-right: 0;
          border-radius: 0;
      }

      .sub-category-group .list-group-flush .list-group-item:first-child {
          border-top: 0;
      }

      .sub-category-group .list-group-flush .list-group-item:last-child {
          border-bottom: 0;
      }

      .accordion-body.p-0 {
          padding: 0 !important;
      }
	</style>
@endpush

@push('scripts')
	<script src="{{ asset('js/lessons_list.js') }}"></script>
@endpush
