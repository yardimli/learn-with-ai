@extends('layouts.app')

@section('title', 'Edit Lesson Assets: ' . $lesson->title)

@push('styles')
	<style>
      audio {
          max-width: 250px;
          height: 35px;
          vertical-align: middle;
      }

      .answer-list li {
          margin-bottom: 0.5rem;
          padding-bottom: 0.5rem;
          border-bottom: 1px solid var(--bs-tertiary-bg);
          font-size: 0.95em;
      }

      .answer-list li:last-child {
          border-bottom: none;
          margin-bottom: 0;
          padding-bottom: 0;
      }

      .answer-text-content, .feedback-text-content {
          display: inline;
      }

      .asset-container h6 {
          font-size: 1em;
      }

      .question-item p strong {
          display: inline;
          margin-bottom: 0;
          font-size: 1.05em;
      }

      .question-item .question-line {
          margin-bottom: 0.75rem;
      }

      .question-difficulty-group {
          border-left: 3px solid #eee;
          padding-left: 1rem;
          margin-top: 1.5rem;
      }

      .dark-mode .question-difficulty-group {
          border-left-color: #444;
      }

      .question-item {
          border: 1px solid var(--bs-border-color-translucent);
          border-radius: 0.375rem;
          padding: 1rem;
          margin-bottom: 1rem;
          background-color: var(--bs-body-bg);
      }

      /* Add background */
      .question-list-container .placeholder-text {
          color: var(--bs-secondary-color);
          font-style: italic;
          margin-bottom: 1rem;
      }

      /* Style for empty list text */
      .btn-delete-question { /* Ensure visibility */
      }
	</style>
@endpush

@section('content')
	<div class="d-flex justify-content-between align-items-center mb-3">
		<a href="{{ route('home') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back to Home</a>
		<a href="{{ route('question.interface', ['lesson' => $lesson->session_id]) }}" class="btn btn-outline-success"><i
				class="fas fa-eye"></i> Start Lesson</a>
	</div>
	
	<div class="content-card mb-4">
		<h1 class="mb-1">Edit Lesson: {{ $lesson->title }}</h1>
		<p class="text-muted mb-3">Lesson: {{ $lesson->name }} (ID: {{ $lesson->id }}, Session: {{ $lesson->session_id }}
			)</p>
		
		<div class="row mb-3 border-top pt-3">
			{{-- Preferred LLM --}}
			<div class="col-md-6 col-lg-4 mb-2 mb-lg-0">
				<div class="d-flex align-items-center">
					<label for="preferredLlmSelect" class="form-label me-2 mb-0 text-nowrap"><i
							class="fas fa-robot text-primary me-1"></i>AI Model:</label>
					<select id="preferredLlmSelect" class="form-select form-select-sm">
						{{-- JS will load options, select current --}}
						<option value="{{ $lesson->preferredLlm }}"
						        selected>{{ $lesson->preferredLlm }}</option> {{-- Show current --}}
					</select>
				</div>
			</div>
			
			{{-- TTS Engine --}}
			<div class="col-md-6 col-lg-2 mb-2 mb-lg-0">
				<div class="d-flex align-items-center">
					<label for="ttsEngineSelect" class="form-label me-2 mb-0 text-nowrap"><i
							class="fas fa-cogs text-info me-1"></i>Engine:</label>
					<select id="ttsEngineSelect" class="form-select form-select-sm">
						<option value="google" {{ $lesson->ttsEngine == 'google' ? 'selected' : '' }}>Google</option>
						<option value="openai" {{ $lesson->ttsEngine == 'openai' ? 'selected' : '' }}>OpenAI</option>
					</select>
				</div>
			</div>
			
			{{-- TTS Voice --}}
			<div class="col-md-6 col-lg-3 mb-2 mb-md-0">
				<div class="d-flex align-items-center">
					<label for="ttsVoiceSelect" class="form-label me-2 mb-0 text-nowrap"><i
							class="fas fa-microphone text-success me-1"></i>Voice:</label>
					<select id="ttsVoiceSelect" class="form-select form-select-sm">
						{{-- JS will filter, select current --}}
						<optgroup label="Google Voices">
							<option value="en-US-Studio-O" {{ $lesson->ttsVoice == 'en-US-Studio-O' ? 'selected' : '' }}>
								en-US-Studio-O (Female)
							</option>
							<option value="en-US-Studio-Q" {{ $lesson->ttsVoice == 'en-US-Studio-Q' ? 'selected' : '' }}>
								en-US-Studio-Q (Male)
							</option>
							<option
								value="tr-TR-Chirp3-HD-Aoede" {{ $lesson->ttsVoice == 'tr-TR-Chirp3-HD-Aoede' ? 'selected' : '' }}>
								tr-TR-Chirp3-HD-Aoede (Female)
							</option>
							<option
								value="tr-TR-Chirp3-HD-Charon" {{ $lesson->ttsVoice == 'tr-TR-Chirp3-HD-Charon' ? 'selected' : '' }}>
								tr-TR-Chirp3-HD-Charon (Male)
							</option>
							<option value="tr-TR-Standard-A" {{ $lesson->ttsVoice == 'tr-TR-Standard-A' ? 'selected' : '' }}>
								tr-TR-Standard-A (Female)
							</option>
							<option value="tr-TR-Standard-B" {{ $lesson->ttsVoice == 'tr-TR-Standard-B' ? 'selected' : '' }}>
								tr-TR-Standard-B
							</option>
							<option
								value="cmn-CN-Chirp3-HD-Aoede" {{ $lesson->ttsVoice == 'cmn-CN-Chirp3-HD-Aoede' ? 'selected' : '' }}>
								cmn-CN-Chirp3-HD-Aoede (Female)
							</option>
							<option
								value="cmn-CN-Chirp3-HD-Charon" {{ $lesson->ttsVoice == 'cmn-CN-Chirp3-HD-Charon' ? 'selected' : '' }}>
								cmn-CN-Chirp3-HD-Charon (Male)
							</option>
							<option value="cmn-TW-Standard-A" {{ $lesson->ttsVoice == 'cmn-TW-Standard-A' ? 'selected' : '' }}>
								cmn-TW-Standard-A (Female)
							</option>
							<option value="cmn-TW-Standard-B" {{ $lesson->ttsVoice == 'cmn-TW-Standard-B' ? 'selected' : '' }}>
								cmn-TW-Standard-B (Male)
							</option>
						</optgroup>
						<optgroup label="OpenAI Voices">
							<option value="alloy" {{ $lesson->ttsVoice == 'alloy' ? 'selected' : '' }}>Alloy (N)</option>
							<option value="echo" {{ $lesson->ttsVoice == 'echo' ? 'selected' : '' }}>Echo (M)</option>
							<option value="fable" {{ $lesson->ttsVoice == 'fable' ? 'selected' : '' }}>Fable (M)</option>
							<option value="onyx" {{ $lesson->ttsVoice == 'onyx' ? 'selected' : '' }}>Onyx (M)</option>
							<option value="nova" {{ $lesson->ttsVoice == 'nova' ? 'selected' : '' }}>Nova (F)</option>
							<option value="shimmer" {{ $lesson->ttsVoice == 'shimmer' ? 'selected' : '' }}>Shimmer (F)</option>
						</optgroup>
					</select>
				</div>
			</div>
			
			{{-- TTS Language Code --}}
			<div class="col-md-4 col-lg-2 mb-2 mb-md-0">
				<div class="d-flex align-items-center">
					<label for="ttsLanguageCodeSelect" class="form-label me-2 mb-0 text-nowrap"><i
							class="fas fa-language text-warning me-1"></i>Lang:</label>
					<select class="form-select form-select-sm" id="ttsLanguageCodeSelect">
						<option value="en-US" {{ $lesson->ttsLanguageCode == 'en-US' ? 'selected' : '' }}>en-US</option>
						<option value="tr-TR" {{ $lesson->ttsLanguageCode == 'tr-TR' ? 'selected' : '' }}>tr-TR</option>
						<option value="cmn-TW" {{ $lesson->ttsLanguageCode == 'cmn-TW' ? 'selected' : '' }}>cmn-TW</option>
						<option value="cmn-CN" {{ $lesson->ttsLanguageCode == 'cmn-CN' ? 'selected' : '' }}>cmn-CN</option>
						<option value="fr-FR" {{ $lesson->ttsLanguageCode == 'fr-FR' ? 'selected' : '' }}>fr-FR</option>
						<option value="de-DE" {{ $lesson->ttsLanguageCode == 'de-DE' ? 'selected' : '' }}>de-DE</option>
						<option value="it-IT" {{ $lesson->ttsLanguageCode == 'it-IT' ? 'selected' : '' }}>it-IT</option>
						<option value="ja-JP" {{ $lesson->ttsLanguageCode == 'ja-JP' ? 'selected' : '' }}>ja-JP</option>
						<option value="ko-KR" {{ $lesson->ttsLanguageCode == 'ko-KR' ? 'selected' : '' }}>ko-KR</option>
						{{-- Add other common languages as needed --}}
					</select>
				</div>
			</div>
			
			{{-- Update Button --}}
			<div class="col-md-2 col-lg-1 d-flex align-items-end justify-content-end">
				<button class="btn btn-sm btn-primary" id="updateLessonSettingsBtn"
				        title="Save AI Model and Voice Settings for this Lesson">
					<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
					<i class="fas fa-save me-1"></i>Save
				</button>
			</div>
		</div>
		
		<p><small>Use the buttons below to generate video, add questions, or manage question assets (audio, images). Click
				audio icons (<i class="fas fa-play text-primary"></i>) to listen. Click images to enlarge. Use <i
					class="fas fa-trash-alt text-danger"></i> to delete questions.</small></p>
	</div>
	
	@if (!empty($lesson->lesson_parts))
		@foreach($lesson->lesson_parts as $partIndex => $part)
			@php
				$partTitle = $part['title'] ?? 'Part ' . ($partIndex + 1);
				$partText = $part['text'] ?? '';
			@endphp
			<div class="content-card mb-4">
				<h3 class="mb-3 d-flex justify-content-between align-items-center">
					<span>Lesson Part {{ $partIndex + 1 }}: {{ $partTitle }}</span>
					<button class="btn btn-sm btn-outline-secondary edit-part-text-btn"
					        data-bs-toggle="modal" data-bs-target="#editPartModal"
					        data-part-index="{{ $partIndex }}" data-part-title="{{ $partTitle }}"
					        title="Edit Part Title & Text">
						<i class="fas fa-edit"></i> Edit Part
					</button>
				</h3>
				<p id="part-text-display-{{ $partIndex }}">{{ $partText }}</p>
				
				<div class="asset-container mb-4 generated-video-container border-top pt-3 mt-3">
					<h6><i class="fas fa-film me-2 text-primary"></i>Part Video</h6>
					@php
						$videoPath = $part['video_path'] ?? null;
						$videoUrl = $part['video_url'] ?? null;
						$videoExists = $videoPath && $videoUrl && Storage::disk('public')->exists($videoPath);
					@endphp
					<div class="mb-2 text-center video-display-area" id="video-display-{{ $partIndex }}"
					     style="{{ !$videoExists ? 'display: none;' : '' }}">
						@if($videoExists)
							<video controls preload="metadata" src="{{ $videoUrl }}" class="generated-video"
							       style="max-width: 100%; max-height: 300px;"> Your browser does not support the video tag.
							</video>
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
						<div class="asset-generation-error text-danger small mt-1" id="video-error-{{ $partIndex }}"
						     style="display: none;"></div>
						@if(!$videoExists)
							<small class="text-muted d-block mt-1">Generates a short talking head video based on this part's
								text.</small>
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
							<div class="asset-generation-error text-danger small mt-1"
							     id="question-gen-error-{{ $difficulty }}-{{ $partIndex }}" style="display: none;"></div>
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
									<p class="placeholder-text" id="placeholder-{{ $difficulty }}-{{ $partIndex }}">No {{ $difficulty }}
										questions created yet for this part.</p>
								@endforelse
							</div>
						</div>
					@endforeach
				</div> {{-- /.questions-section --}}
			</div> {{-- /.content-card for part --}}
		@endforeach
	@else
		<div class="alert alert-warning">Lesson part data is missing or invalid for this lesson. Cannot display edit
			options.
		</div>
	@endif
	
	<template id="question-item-template">
		@include('partials._question_edit_item', ['question' => null])
	</template>
	
	
	<div class="modal fade" id="freepikSearchModal" tabindex="-1" aria-labelledby="freepikSearchModalLabel"
	     aria-hidden="true" data-bs-backdrop="static">
		<div class="modal-dialog modal-xl modal-dialog-scrollable">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="freepikSearchModalLabel">Search Freepik for Question Image</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<input type="hidden" id="freepikModalQuestionId" value="{{$lesson->id}}">
					<div class="input-group mb-3">
						<input type="text" id="freepikSearchQuery" class="form-control"
						       placeholder="Enter search term (e.g., 'science experiment', 'cat studying')">
						<button class="btn btn-primary" type="button" id="freepikSearchExecuteBtn">
							<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
							<i class="fas fa-search"></i> Search
						</button>
					</div>
					<div id="freepikSearchError" class="alert alert-danger d-none" role="alert"></div>
					
					<div id="freepikSearchResults" class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3"
					     style="min-height: 200px;">
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
					<small class="text-muted me-auto">Image search powered by Freepik. Ensure compliance with Freepik's
						terms.</small>
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
				</div>
			</div>
		</div>
	</div>
	
	<div class="modal fade" id="questionBatchSuccessModal" tabindex="-1" aria-labelledby="questionBatchSuccessModalLabel"
	     aria-hidden="true">
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
	
	<div class="modal fade" id="editTextsModal" tabindex="-1" aria-labelledby="editTextsModalLabel" aria-hidden="true"
	     data-bs-backdrop="static">
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
	
	<!-- Edit Part Modal -->
	<div class="modal fade" id="editPartModal" tabindex="-1" aria-labelledby="editPartModalLabel" aria-hidden="true"
	     data-bs-backdrop="static">
		<div class="modal-dialog modal-lg">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="editPartModalLabel">Edit Lesson Part</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<form id="editPartForm">
						<input type="hidden" id="editPartIndex" value="">
						<div class="mb-3">
							<label for="editPartTitle" class="form-label">Part Title</label>
							<input type="text" class="form-control" id="editPartTitle" required>
							<div class="invalid-feedback">Part title is required.</div>
						</div>
						<div class="mb-3">
							<label for="editPartText" class="form-label">Part Text</label>
							<textarea class="form-control" id="editPartText" rows="8" required></textarea>
							<div class="invalid-feedback">Part text is required (minimum 10 characters)</div>
						</div>
					</form>
					<div id="editPartError" class="alert alert-danger mt-3 d-none"></div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="button" class="btn btn-primary" id="savePartBtn">
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
		
		let preferredLlmSelect = null;
		let ttsEngineSelect = null;
		let ttsLanguageCodeSelect = null;
		let updateSettingsBtn = null;
		
		const lessonSessionId = @json($lesson->session_id);
		const updateSettingsUrl = @json(route('lesson.update.settings', ['lesson' => $lesson->session_id]));
		const llmsListUrl = @json(route('api.llms.list'));
	
	</script>
	<script src="{{ asset('js/edit_lesson.js') }}"></script>
	<script src="{{ asset('js/edit_lesson_top_settings.js') }}"></script>
	<script src="{{ asset('js/edit_lesson_question.js') }}"></script>
	<script src="{{ asset('js/edit_lesson_audio.js') }}"></script>
	<script src="{{ asset('js/edit_lesson_freepik_functions.js') }}"></script>
@endpush
