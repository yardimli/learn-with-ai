@extends('layouts.app')

@section('title', 'Edit Lesson Assets: ' . $lesson->title)

@push('styles')
	<style>
      audio { max-width: 250px; height: 35px; vertical-align: middle; }
      .answer-list li { margin-bottom: 0.5rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--bs-tertiary-bg); font-size: 0.95em; }
      .answer-list li:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
      .answer-text-content, .feedback-text-content { display: inline; }
      .asset-container h6 { font-size: 1em; }
      .question-item p strong { display: inline; margin-bottom: 0; font-size: 1.05em; }
      .question-item .question-line { margin-bottom: 0.75rem; }
      .question-difficulty-group { border-left: 3px solid #eee; padding-left: 1rem; margin-top: 1.5rem; }
      .dark-mode .question-difficulty-group { border-left-color: #444; }
      .question-item { border: 1px solid var(--bs-border-color-translucent); border-radius: 0.375rem; padding: 1rem; margin-bottom: 1rem; background-color: var(--bs-body-bg); } /* Add background */
      .question-list-container .placeholder-text { color: var(--bs-secondary-color); font-style: italic; margin-bottom: 1rem; } /* Style for empty list text */
      .btn-delete-question { /* Ensure visibility */ }
	</style>
@endpush

@section('content')
	<div class="d-flex justify-content-between align-items-center mb-3">
		<a href="{{ route('home') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back to Home</a>
		<a href="{{ route('question.interface', ['lesson' => $lesson->session_id]) }}" class="btn btn-outline-success"><i class="fas fa-eye"></i> Start Lesson</a>
	</div>
	
	<div class="content-card mb-4">
		<h1 class="mb-1">Edit Lesson: {{ $lesson->title }}</h1>
		<p class="text-muted mb-3">Lesson: {{ $lesson->name }} (ID: {{ $lesson->id }}, Session: {{ $lesson->session_id }})</p>
		
		<!-- New Settings Row -->
		<div class="row mb-3 border-top pt-3">
			<div class="col-md-6 mb-2 mb-md-0">
				<div class="d-flex align-items-center">
					<label for="llmSelector" class="form-label me-2 mb-0"><i class="fas fa-robot text-primary me-1"></i>AI Model:</label>
					<select id="llmSelector" class="form-select form-select-sm" style="max-width: 400px;">
						<!-- This will be filled by JS using the $llms data passed to view -->
						<option value="{{ $llm }}">Current: {{ $llm }}</option>
					</select>
					<button class="btn btn-sm btn-outline-secondary ms-2" id="updateLLMBtn" title="Apply AI Model change">
						<i class="fas fa-check"></i>
					</button>
				</div>
			</div>
			<div class="col-md-6">
				<div class="d-flex align-items-center">
					<label for="voiceSelector" class="form-label me-2 mb-0"><i class="fas fa-microphone text-success me-1"></i>Voice:</label>
					<select id="voiceSelector" class="form-select form-select-sm" style="max-width: 300px;">
						<optgroup label="Google Voices">
							<option value="en-US-Standard-A">en-US-Standard-A (Male)</option>
							<option value="en-US-Standard-B">en-US-Standard-B (Male)</option>
							<option value="en-US-Standard-C">en-US-Standard-C (Female)</option>
							<option value="en-US-Standard-D">en-US-Standard-D (Male)</option>
							<option value="en-US-Standard-E">en-US-Standard-E (Female)</option>
							<option value="en-US-Standard-F">en-US-Standard-F (Female)</option>
							<option value="en-US-Standard-G">en-US-Standard-G (Female)</option>
							<option value="en-US-Standard-H">en-US-Standard-H (Female)</option>
							<option value="en-US-Studio-O" selected>en-US-Studio-O (Female)</option>
						</optgroup>
						<optgroup label="OpenAI Voices">
							<option value="alloy">Alloy (Neutral)</option>
							<option value="echo">Echo (Male)</option>
							<option value="fable">Fable (Male)</option>
							<option value="onyx">Onyx (Male)</option>
							<option value="nova">Nova (Female)</option>
							<option value="shimmer">Shimmer (Female)</option>
						</optgroup>
					</select>
					<button class="btn btn-sm btn-outline-secondary ms-2" id="updateVoiceBtn" title="Apply Voice change">
						<i class="fas fa-check"></i>
					</button>
					<select id="ttsEngineSelector" class="form-select form-select-sm ms-2" style="max-width: 140px;">
						<option value="google">Google</option>
						<option value="openai">OpenAI</option>
					</select>
				</div>
			</div>
		</div>
		
		<p><small>Use the buttons below to generate video, add questions, or manage question assets (audio, images). Click audio icons (<i class="fas fa-play text-primary"></i>) to listen. Click images to enlarge. Use <i class="fas fa-trash-alt text-danger"></i> to delete questions.</small></p>
	</div>
	
	@if (!empty($lesson->lesson_parts))
		@foreach($lesson->lesson_parts as $partIndex => $part)
			<div class="content-card mb-4">
				<h3 class="mb-3">Lesson Part {{ $partIndex + 1 }}: {{ $part['title'] }}</h3>
				<p>{{ $part['text'] }}</p>
				
				<div class="asset-container mb-4 generated-video-container border-top pt-3 mt-3">
					<h6><i class="fas fa-film me-2 text-primary"></i>Part Video</h6>
					@php
						$videoPath = $part['video_path'] ?? null;
						$videoUrl = $part['video_url'] ?? null;
						$videoExists = $videoPath && $videoUrl && Storage::disk('public')->exists($videoPath);
					@endphp
					<div class="mb-2 text-center video-display-area" id="video-display-{{ $partIndex }}" style="{{ !$videoExists ? 'display: none;' : '' }}">
						@if($videoExists)
							<video controls preload="metadata" src="{{ $videoUrl }}" class="generated-video" style="max-width: 100%; max-height: 300px;"> Your browser does not support the video tag. </video>
							<p><small class="text-muted d-block mt-1">Video available. Path: {{ $videoPath }}</small></p>
						@endif
					</div>
					<div class="video-placeholder mt-3" id="video-placeholder-{{ $partIndex }}" style="display: none;"></div>
					<div class="text-center video-button-area" id="video-button-area-{{ $partIndex }}">
						<button class="btn btn-outline-info generate-part-video-btn"
						        data-lesson-id="{{ $lesson->session_id }}"
						        data-part-index="{{ $partIndex }}"
						        data-generate-url="{{ route('lesson.part.generate.video', ['lesson' => $lesson->session_id, 'partIndex' => $partIndex]) }}">
							<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
							<i class="fas fa-video me-1"></i> {{ $videoExists ? 'Regenerate Video' : 'Generate Video' }}
						</button>
						<div class="asset-generation-error text-danger small mt-1" id="video-error-{{ $partIndex }}" style="display: none;"></div>
						@if(!$videoExists)
							<small class="text-muted d-block mt-1">Generates a short talking head video based on this part's text.</small>
						@endif
					</div>
				</div>
				
				<div class="questions-section border-top pt-3 mt-4">
					<h4 class="mt-0 mb-3">Questions for this Part</h4>
					
					<div class="mb-4">
						<h5 class="mb-2">Generate New Questions</h5>
						<div class="btn-group" role="group" aria-label="Generate Question Buttons">
							@foreach(['easy', 'medium', 'hard'] as $difficulty)
								<button class="btn btn-outline-success add-question-batch-btn"
								        data-lesson-id="{{ $lesson->session_id }}"
								        data-part-index="{{ $partIndex }}"
								        data-difficulty="{{ $difficulty }}"
								        data-generate-url="{{ route('question.generate.batch', ['lesson' => $lesson->session_id, 'partIndex' => $partIndex, 'difficulty' => $difficulty]) }}"
								        data-target-list-id="question-list-{{ $difficulty }}-{{ $partIndex }}"
								        data-error-area-id="question-gen-error-{{ $difficulty }}-{{ $partIndex }}">
									<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
									<i class="fas fa-plus-circle me-1"></i> Add 3 {{ ucfirst($difficulty) }}
								</button>
							@endforeach
						</div>
						@foreach(['easy', 'medium', 'hard'] as $difficulty)
							<div class="asset-generation-error text-danger small mt-1" id="question-gen-error-{{ $difficulty }}-{{ $partIndex }}" style="display: none;"></div>
						@endforeach
					</div>
					
					@foreach(['easy', 'medium', 'hard'] as $difficulty)
						<div class="question-difficulty-group">
							<h5 class="d-flex justify-content-between align-items-center">
								<span>{{ ucfirst($difficulty) }} Questions</span>
								<span class="badge bg-secondary rounded-pill">
                         {{ count($groupedQuestions[$partIndex][$difficulty] ?? []) }}
                     </span>
							</h5>
							<div class="question-list-container mt-2" id="question-list-{{ $difficulty }}-{{ $partIndex }}">
								@php $questionsForDifficulty = $groupedQuestions[$partIndex][$difficulty] ?? []; @endphp
								@forelse($questionsForDifficulty as $question)
									@include('partials._question_edit_item', ['question' => $question])
								@empty
									<p class="placeholder-text" id="placeholder-{{ $difficulty }}-{{ $partIndex }}">No {{ $difficulty }} questions created yet for this part.</p>
								@endforelse
							</div>
						</div>
					@endforeach
				</div> {{-- /.questions-section --}}
			</div> {{-- /.content-card for part --}}
		@endforeach
	@else
		<div class="alert alert-warning">Lesson part data is missing or invalid for this lesson. Cannot display edit options.</div>
	@endif
	
	<template id="question-item-template">
		@include('partials._question_edit_item', ['question' => null])
	</template>
	
	
	<div class="modal fade" id="freepikSearchModal" tabindex="-1" aria-labelledby="freepikSearchModalLabel" aria-hidden="true" data-bs-backdrop="static">
		<div class="modal-dialog modal-xl modal-dialog-scrollable">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="freepikSearchModalLabel">Search Freepik for Question Image</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<input type="hidden" id="freepikModalQuestionId" value="{{$lesson->id}}">
					<div class="input-group mb-3">
						<input type="text" id="freepikSearchQuery" class="form-control" placeholder="Enter search term (e.g., 'science experiment', 'cat studying')">
						<button class="btn btn-primary" type="button" id="freepikSearchExecuteBtn">
							<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
							<i class="fas fa-search"></i> Search
						</button>
					</div>
					<div id="freepikSearchError" class="alert alert-danger d-none" role="alert"></div>
					
					<div id="freepikSearchResults" class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3" style="min-height: 200px;">
						<div class="col-12 text-center text-muted d-none" id="freepikSearchPlaceholder">
							Enter a search term above to find images.
						</div>
						<div class="col-12 text-center d-none" id="freepikSearchLoading">
							<div class="spinner-border text-primary" role="status"></div>
							<p>Loading images...</p>
						</div>
						<div class="col-12 text-center text-muted d-none" id="freepikSearchNoResults">
							No images found for that search term.
						</div>
					</div>
					
					<nav aria-label="Freepik Search Pagination" class="mt-3 d-none" id="freepikPaginationContainer">
						<ul class="pagination justify-content-center" id="freepikPagination">
						</ul>
					</nav>
				
				</div>
				<div class="modal-footer">
					<small class="text-muted me-auto">Image search powered by Freepik. Ensure compliance with Freepik's terms.</small>
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
				</div>
			</div>
		</div>
	</div>
	
	<div class="modal fade" id="questionBatchSuccessModal" tabindex="-1" aria-labelledby="questionBatchSuccessModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="questionBatchSuccessModalLabel">
						<i class="fas fa-check-circle text-success me-2"></i>Success
					</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<p id="questionBatchSuccessMessage">Questions were generated successfully.</p>
					<p class="mb-0">The page will reload to show the new questions.</p>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-primary" id="questionBatchSuccessConfirm">
						<i class="fas fa-sync-alt me-2"></i>Reload Now
					</button>
				</div>
			</div>
		</div>
	</div>
	
	<div class="modal fade" id="editTextsModal" tabindex="-1" aria-labelledby="editTextsModalLabel" aria-hidden="true" data-bs-backdrop="static">
		<div class="modal-dialog modal-lg">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="editTextsModalLabel">Edit Question Texts</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<form id="editTextsForm">
						<input type="hidden" id="editQuestionId" value="">
						
						<div class="mb-3">
							<label for="editQuestionText" class="form-label">Question Text</label>
							<textarea class="form-control" id="editQuestionText" rows="3" required></textarea>
							<div class="invalid-feedback">Question text is required (minimum 5 characters)</div>
						</div>
						
						<div id="editAnswersContainer">
							<!-- Answers will be dynamically populated by JS -->
						</div>
					</form>
					
					<div id="editTextsError" class="alert alert-danger mt-3 d-none"></div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="button" class="btn btn-primary" id="saveTextsBtn">
						<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
						Save Changes
					</button>
				</div>
			</div>
		</div>
	</div>

@endsection

@push('scripts')
	<script>
		let sharedAudioPlayer = null;
		let imageModal = null;
		let currentlyPlayingButton = null;
		let existingPlayButtons = null;
	
	</script>
	<script src="{{ asset('js/edit_lesson.js') }}"></script>
	<script src="{{ asset('js/edit_lesson_audio.js') }}"></script>
	<script src="{{ asset('js/edit_lesson_freepik_functions.js') }}"></script>
@endpush
