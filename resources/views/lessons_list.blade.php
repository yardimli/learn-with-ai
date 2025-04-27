@extends('layouts.app')

@section('title', 'Existing Lessons - Learn with AI')

@section('content')
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h1>Existing Lessons</h1>
		<div>
			<button id="generateAllButton" class="btn btn-warning mb-1 mb-md-0 me-md-2"
			        title="Automatically generate content for all pending lessons">
				<i class="fas fa-robot"></i>
				<span id="generateAllText">Generate All Pending</span>
				<span id="generateAllSpinner" class="spinner-border spinner-border-sm d-none ms-1" role="status"
				      aria-hidden="true"></span>
			</button>
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
						// Group lessons within this main category by their sub_category_id (null/empty string for those without)
						$lessonsGroupedBySubCategory = $lessonsInMainCategory->groupBy('sub_category_id');
						$mainCollapseId = 'collapseMainCategory' . Str::slug($mainCategoryId);
						$mainHeadingId = 'headingMainCategory' . Str::slug($mainCategoryId);
						// Determine if the main category should be expanded initially
						$startExpanded = $lessonsInMainCategory->count() > 5 || $lessonsGroupedBySubCategory->count() > 1;
					@endphp
					<div class="accordion-item">
						<h2 class="accordion-header" id="{{ $mainHeadingId }}">
							<button class="accordion-button {{ $startExpanded ? '' : 'collapsed' }}" type="button"
							        data-bs-toggle="collapse" data-bs-target="#{{ $mainCollapseId }}"
							        aria-expanded="{{ $startExpanded ? 'true' : 'false' }}"
							        aria-controls="{{ $mainCollapseId }}">
								<i class="fas fa-folder-open me-2"></i> {{ $mainCategoryName }}
								<span class="badge bg-primary ms-2">{{ $lessonsInMainCategory->count() }}</span>
							</button>
						</h2>
						<div id="{{ $mainCollapseId }}" class="accordion-collapse collapse {{ $startExpanded ? 'show' : '' }}"
						     aria-labelledby="{{ $mainHeadingId }}" data-bs-parent="#lessonsAccordion">
							<div class="accordion-body p-0"> {{-- Remove padding from outer body --}}
								
								{{-- Handle Lessons in this Main Category WITHOUT a Sub Category (Collapsible) --}}
								{{-- Check for both null and empty string keys just in case --}}
								@php
									$lessonsWithoutSub = collect();
									if (isset($lessonsGroupedBySubCategory[null])) {
											$lessonsWithoutSub = $lessonsWithoutSub->merge($lessonsGroupedBySubCategory[null]);
											unset($lessonsGroupedBySubCategory[null]); // Remove from main loop
									}
									if (isset($lessonsGroupedBySubCategory[''])) {
											 $lessonsWithoutSub = $lessonsWithoutSub->merge($lessonsGroupedBySubCategory['']);
											 unset($lessonsGroupedBySubCategory['']); // Remove from main loop
									}
								@endphp
								
								@if ($lessonsWithoutSub->isNotEmpty())
									@php $noSubCollapseId = "subCollapse-main{$mainCategoryId}-noSub"; @endphp
									<div class="sub-category-group px-3 pt-2 pb-1"> {{-- Adjusted padding --}}
										<h4 class="sub-category-heading mb-0 collapse-trigger" {{-- mb-0 on heading --}}
										data-bs-toggle="collapse" data-bs-target="#{{ $noSubCollapseId }}"
										    aria-expanded="false" {{-- Start collapsed --}}
										    aria-controls="{{ $noSubCollapseId }}" role="button">
											<i class="fas fa-minus-circle me-2 text-muted"></i> (No Sub Category)
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
								@endif
								
								{{-- Loop Through Sub Categories within this Main Category (Collapsible) --}}
								@foreach ($lessonsGroupedBySubCategory as $subCategoryId => $lessonsInSubCategory)
									@php
										// Ensure we have lessons and try to get the name
										$subCategoryName = $lessonsInSubCategory->first()?->subCategory?->name ?? 'Unknown Sub Category';
										// Ensure subCategoryId is safe for ID (replace potential null/empty with string) - Should not be needed now due to unset above
										$safeSubCategoryId = $subCategoryId ?: 'unknown';
										$subCollapseId = "subCollapse-main{$mainCategoryId}-sub{$safeSubCategoryId}";
									@endphp
									<div class="sub-category-group px-3 pt-2 pb-1"> {{-- Adjusted padding --}}
										<h4 class="sub-category-heading mb-0 collapse-trigger" {{-- mb-0 on heading --}}
										data-bs-toggle="collapse" data-bs-target="#{{ $subCollapseId }}"
										    aria-expanded="false" {{-- Start collapsed --}}
										    aria-controls="{{ $subCollapseId }}" role="button">
											<i class="fas fa-stream me-2 text-info"></i> {{ $subCategoryName }}
											<span class="badge bg-danger text-dark ms-2">{{ $lessonsInSubCategory->count() }}</span>
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
	
	
	
	<div class="modal fade" id="generateContentModal" tabindex="-1" aria-labelledby="generateContentModalLabel"
	     aria-hidden="true" data-bs-backdrop="static">
		<div class="modal-dialog modal-lg modal-dialog-scrollable">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="generateContentModalLabel">Generate Lesson Content</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<div id="generationOptionsArea">
						<input type="hidden" id="sessionIdForGeneration" value="">
						<input type="hidden" id="currentSubCategoryId" value="">
						<input type="hidden" id="currentSelectedMainCategoryId" value="">
						
						<div class="mb-3">
							<label for="lessonTitleDisplay" class="form-label">Lesson Title:</label>
							<input type="text" class="form-control" id="lessonTitleDisplay">
						</div>
						
						<div class="mb-3">
							<label for="lessonSubjectDisplay" class="form-label">Lesson Subject:</label>
							<textarea type="text" class="form-control" id="lessonSubjectDisplay"></textarea>
						</div>
						
						<div class="mb-3">
							<label for="lessonNotesDisplay" class="form-label">Additional Notes:</label>
							<textarea class="form-control" id="lessonNotesDisplay" rows="2"></textarea>
						</div>
						
						{{-- Area to display existing category --}}
						<div id="existingCategoryDisplayArea" class="mb-3 p-2 border rounded d-none">
							<p class="mb-1"><strong>Current Category:</strong></p>
							<p class="mb-0">
								Main: <span id="existingMainCategoryName" class="fw-bold"></span><br>
								Sub: <span id="existingSubCategoryName" class="fw-bold"></span>
							</p>
							<small class="text-muted" id="existingCategoryNote">Content will be generated for this category.</small>
						</div>
						
						{{-- Area for auto-detect checkbox --}}
						<div id="autoDetectCheckboxArea" class="mb-3 form-check">
							<input type="checkbox" class="form-check-input" id="autoDetectCategoryCheck" checked>
							<label class="form-check-label" for="autoDetectCategoryCheck">
								Auto-detect category based on content
							</label>
						</div>
						
						<div class="mb-3">
							<label for="aiModelSelect" class="form-label">AI Model for Generation:</label>
							<select class="form-select" id="aiModelSelect" required>
								@php $defaultLlmId = env('DEFAULT_LLM', ''); @endphp
								@forelse ($llms ?? [] as $llm)
									<option value="{{ $llm['id'] }}" {{ $llm['id'] === $defaultLlmId ? 'selected' : '' }}>
										{{ $llm['name'] }}
									</option>
								@empty
									<option value="" disabled>No AI models available</option>
								@endforelse
							</select>
						</div>
						
						<div class="d-grid">
							<button id="generatePreviewButton" class="btn btn-primary">
								<span id="generatePreviewSpinner" class="spinner-border spinner-border-sm d-none" role="status"
								      aria-hidden="true"></span>
								Generate Content Preview
							</button>
						</div>
					</div>
					
					<div id="previewContentArea" class="d-none">
						<div id="lessonPreviewBody">
							<div class="text-center">
								<div class="spinner-border text-primary" role="status">
									<span class="visually-hidden">Loading preview...</span>
								</div>
								<p class="mt-2">Generating preview...</p>
							</div>
						</div>
						
						<div id="modalCategorySuggestionArea" class="mt-3 p-2 border rounded d-none">
							<h6>AI Suggested Category:</h6>
							<p class="mb-1">
								Main: <span id="suggestedMainCategoryText" class="badge bg-danger text-dark"></span>
								<br>
								Sub: <span id="suggestedSubCategoryText" class="badge bg-light text-dark"></span>
							</p>
							<small class="text-muted">These categories will be created if they don't exist.</small>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<div id="generationErrorMessage" class="text-danger me-auto d-none"></div>
					<span id="applyGenerationSpinner" class="me-auto d-none">
                    <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                    Applying Content...
                </span>
					
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="cancelGenerationButton">
						Cancel
					</button>
					
					<button type="button" class="btn btn-primary d-none" id="backToOptionsButton">
						<i class="fas fa-arrow-left"></i> Back to Options
					</button>
					
					<button type="button" class="btn btn-success d-none" id="applyGenerationButton">
						Apply Content <i class="fas fa-check"></i>
					</button>
				</div>
			</div>
		</div>
	</div>
@endsection

@push('styles')
	<style>
      .sub-category-group {
          border-bottom: 1px solid var(--bs-border-color-translucent); /* Add separator between sub-groups */
          margin-left: 20px;
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
	<script src="{{ asset('js/lessons_list.js') }}"></script>
@endpush
