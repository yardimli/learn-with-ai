@extends('layouts.app')

@section('title', 'Existing Lessons - Learn with AI')

@section('content')
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h1>Existing Lessons</h1>
		<div>
			<a href="{{ route('home') }}" class="btn btn-primary mb-1 mb-md-0">
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
			No lessons created yet. <a href="{{ route('home') }}">Create your first lesson!</a>
		</div>
	@else
		<div class="accordion shadow-sm" id="lessonsAccordion">
			
			{{-- Handle Uncategorized Lessons First (Lessons with no Main Category) --}}
			@if (isset($groupedLessons[null]) && $groupedLessons[null]->isNotEmpty())
				@php $uncategorizedLessons = $groupedLessons[null]; @endphp
				<div class="accordion-item">
					<h2 class="accordion-header" id="headingUncategorized">
						<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
						        data-bs-target="#collapseUncategorized" aria-expanded="false"
						        aria-controls="collapseUncategorized">
							<i class="fas fa-question-circle me-2 text-muted"></i> Uncategorized Lessons
							<span class="badge bg-secondary ms-2">{{ $uncategorizedLessons->count() }}</span>
						</button>
					</h2>
					<div id="collapseUncategorized" class="accordion-collapse collapse"
					     aria-labelledby="headingUncategorized" data-bs-parent="#lessonsAccordion">
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
						// Determine if the main category should be expanded initially
						$startExpanded = $lessonsInMainCategory->count() > 5 || $lessonsGroupedBySubCategory->count() > 1;
					@endphp
					<div class="accordion-item">
						<h2 class="accordion-header" id="{{ $mainHeadingId }}">
							<button class="accordion-button {{ $startExpanded ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse"
							        data-bs-target="#{{ $mainCollapseId }}" aria-expanded="{{ $startExpanded ? 'true' : 'false' }}"
							        aria-controls="{{ $mainCollapseId }}">
								<i class="fas fa-folder-open me-2"></i> {{ $mainCategoryName }}
								<span class="badge bg-primary ms-2">{{ $lessonsInMainCategory->count() }}</span>
							</button>
						</h2>
						<div id="{{ $mainCollapseId }}" class="accordion-collapse collapse {{ $startExpanded ? 'show' : '' }}"
						     aria-labelledby="{{ $mainHeadingId }}" data-bs-parent="#lessonsAccordion">
							<div class="accordion-body p-0"> {{-- Remove padding from outer body --}}
								
								{{-- Handle Lessons in this Main Category WITHOUT a Sub Category (Collapsible) --}}
								@if (isset($lessonsGroupedBySubCategory['']) && $lessonsGroupedBySubCategory['']->isNotEmpty())
									@php
										$lessonsWithoutSub = $lessonsGroupedBySubCategory[''];
										$noSubCollapseId = "subCollapse-main{$mainCategoryId}-noSub";
									@endphp
									<div class="sub-category-group px-3 pt-2 pb-1"> {{-- Adjusted padding --}}
										<h4 class="sub-category-heading mb-0 collapse-trigger" {{-- mb-0 on heading --}}
										data-bs-toggle="collapse"
										    data-bs-target="#{{ $noSubCollapseId }}"
										    aria-expanded="false" {{-- Start collapsed --}}
										    aria-controls="{{ $noSubCollapseId }}"
										    role="button">
											<i class="fas fa-minus-circle me-2 text-muted"></i>
											(No Sub Category)
											<span class="badge bg-light text-dark ms-2">{{ $lessonsWithoutSub->count() }}</span>
											<i class="fas fa-chevron-down collapse-icon ms-auto"></i> {{-- Added Icon --}}
										</h4>
										<div class="collapse" id="{{ $noSubCollapseId }}">
											<div class="list-group list-group-flush mt-2 mb-2"> {{-- Added margin top --}}
												@foreach ($lessonsWithoutSub as $lesson)
													@include('partials.lesson_list_item', ['lesson' => $lesson])
												@endforeach
											</div>
										</div>
									</div>
									@php unset($lessonsGroupedBySubCategory['']); @endphp {{-- Remove from loop --}}
								@endif
								
								{{-- Loop Through Sub Categories within this Main Category (Collapsible) --}}
								@foreach ($lessonsGroupedBySubCategory as $subCategoryId => $lessonsInSubCategory)
									@php
										$subCategoryName = $lessonsInSubCategory->first()?->subCategory?->name ?? 'Unknown Sub Category';
										// Ensure subCategoryId is safe for ID (replace potential null/empty with string)
										$safeSubCategoryId = $subCategoryId ?: 'unknown';
										$subCollapseId = "subCollapse-main{$mainCategoryId}-sub{$safeSubCategoryId}";
									@endphp
									<div class="sub-category-group px-3 pt-2 pb-1"> {{-- Adjusted padding --}}
										<h4 class="sub-category-heading mb-0 collapse-trigger" {{-- mb-0 on heading --}}
										data-bs-toggle="collapse"
										    data-bs-target="#{{ $subCollapseId }}"
										    aria-expanded="false" {{-- Start collapsed --}}
										    aria-controls="{{ $subCollapseId }}"
										    role="button">
											<i class="fas fa-stream me-2 text-info"></i>
											{{ $subCategoryName }}
											<span class="badge bg-info text-dark ms-2">{{ $lessonsInSubCategory->count() }}</span>
											<i class="fas fa-chevron-down collapse-icon ms-auto"></i> {{-- Added Icon --}}
										</h4>
										<div class="collapse" id="{{ $subCollapseId }}">
											<div class="list-group list-group-flush mt-2 mb-2"> {{-- Added margin top --}}
												@foreach ($lessonsInSubCategory as $lesson)
													@include('partials.lesson_list_item', ['lesson' => $lesson])
												@endforeach
											</div>
										</div>
									</div>
								@endforeach
							
							</div> {{-- End accordion-body --}}
						</div> {{-- End main collapse div --}}
					</div> {{-- End accordion-item --}}
				@endif
			@endforeach
		
		</div> {{-- End Accordion --}}
	@endif
@endsection

@push('styles')
	<style>
      .sub-category-group {
          border-bottom: 1px solid var(--bs-border-color-translucent); /* Add separator between sub-groups */
      }
      .sub-category-group:last-child {
          border-bottom: none; /* Remove border from the last one */
      }
      .sub-category-heading.collapse-trigger {
          font-size: 1.1rem;
          font-weight: 500;
          color: var(--bs-body-color);
          cursor: pointer;
          padding: 0.5rem 0; /* Add some padding for easier clicking */
          display: flex; /* Use flexbox for alignment */
          align-items: center; /* Vertically align items */
          transition: background-color 0.15s ease-in-out;
      }
      .sub-category-heading.collapse-trigger:hover {
          /* Optional: background-color: rgba(0,0,0,0.03); */
          /* For dark mode compatibility, use semi-transparent white */
          background-color: rgba(var(--bs-emphasis-color-rgb), 0.05);
      }

      .sub-category-heading .collapse-icon {
          transition: transform 0.3s ease;
      }

      /* Rotate icon when the collapsible section is shown (trigger's aria-expanded is true) */
      .sub-category-heading.collapse-trigger[aria-expanded="true"] .collapse-icon {
          transform: rotate(-180deg);
      }

      /* Ensure flush list group items have no top/bottom borders inside sub-groups */
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
      /* Remove accordion body padding as we add it to sub-groups */
      .accordion-body.p-0 {
          padding: 0 !important;
      }
      /* Adjust padding within the collapsible div if needed */
      .sub-category-group .collapse .list-group {
          /* padding-left: 1rem; */ /* Optional indent for lesson items */
      }
	
	</style>
@endpush

@push('scripts')
	{{-- No specific JS needed here as Bootstrap handles collapse via data attributes --}}
@endpush
