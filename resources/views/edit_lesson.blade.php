@extends('layouts.app')

@section('title', 'Edit Lesson Assets: ' . ($lesson->user_title ?: $lesson->title))

@section('content')
	<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
		<div>
			<a href="{{ route('lessons.list') }}" class="btn btn-outline-secondary mb-1"><i class="fas fa-arrow-left"></i>
				Back to Lessons</a>
			<a href="{{ route('category_management.main.index') }}" class="btn btn-outline-info ms-2 mb-1">
				<i class="fas fa-tags"></i> Manage Categories
			</a>
			<a href="{{ route('question.interface', ['lesson' => $lesson->id]) }}"
			   class="btn btn-outline-success ms-2 mb-1"><i class="fas fa-eye"></i> Start Lesson</a>
		</div>
		<div class="btn-group mt-2 mt-md-0" role="group" aria-label="Lesson Actions">
			@php
				$mainCatNameForModal = '';
				if ($lesson->mainCategory) { // Directly selected main category
						$mainCatNameForModal = $lesson->mainCategory->name;
				} elseif ($lesson->subCategory && $lesson->subCategory->mainCategory) { // Main category via subcategory
						$mainCatNameForModal = $lesson->subCategory->mainCategory->name;
				}
			@endphp
			<button type="button" class="btn btn-success generate-ai-content-btn"
			        data-lesson-id="{{ $lesson->id }}"
			        data-user-title="{{ $lesson->user_title }}"
			        data-lesson-subject="{{ $lesson->subject }}"
			        data-notes="{{ $lesson->notes }}"
			        data-sub-category-id="{{ $lesson->sub_category_id ?? '' }}"
			        data-selected-main-category-id="{{ $lesson->selected_main_category_id ?? '' }}"
			        data-main-category-name="{{ $mainCatNameForModal }}"
			        data-sub-category-name="{{ $lesson->subCategory?->name ?? '' }}"
			        data-preferred-llm="{{ $lesson->preferredLlm ?? ($llms[0]['id'] ?? '') }}"
			        data-video-id="{{ $lesson->youtube_video_id ?? '' }}"
			        data-video-subtitles="{{ $lesson->video_subtitles_text ? base64_encode($lesson->video_subtitles_text) : '' }}"
			        data-bs-toggle="modal" data-bs-target="#generateContentModal"
			        title="{{ $lesson->ai_generated ? 'Regenerate AI Content' : 'Generate AI Content' }}">
				<i class="fas fa-magic"></i> {{ $lesson->ai_generated ? 'Regenerate Content' : 'Generate Content' }}
			</button>
			<button type="button" class="btn btn-info add-video-btn"
			        data-bs-toggle="modal" data-bs-target="#addVideoModal"
			        data-lesson-id="{{ $lesson->id }}"
			        data-lesson-title="{{ $lesson->user_title ?? $lesson->subject }}"
			        title="Add or Update YouTube Video">
				<i class="fab fa-youtube"></i> {{ $lesson->youtube_video_id ? 'Update Video' : 'Add Video' }}
			</button>
			<button type="button" class="btn btn-danger" id="deleteLessonButton"
			        data-lesson-id="{{ $lesson->id }}"
			        data-delete-url="{{ route('lesson.delete', $lesson->id) }}"
			        data-lesson-title="{{ $lesson->user_title ?? $lesson->subject }}"
			        title="Delete Lesson">
				<i class="fas fa-trash"></i> Delete
			</button>
		</div>
	</div>
	
	
	<div class="content-card mb-4" id="lessonSettingsCard" data-categories="{{ $categoriesData }}">
		<h5 class="mb-1">Edit Lesson</h5>
		<p class="text-muted mb-3">{{ $lesson->subject }} (ID: {{ $lesson->id }} )
			@if($lesson->youtube_video_id)
				<span class="ms-2 badge bg-info text-dark" title="YouTube Video ID: {{ $lesson->youtube_video_id }}"><i
						class="fab fa-youtube"></i> Video Linked</span>
			@endif
		</p>
		
		{{-- Settings Row --}}
		<div class="row mb-3 border-top pt-3 settings-row g-2">
			{{-- Main Category --}}
			<div class="col-md-6 col-lg-3 mb-2">
				<div class="d-flex align-items-center">
					<label for="editMainCategorySelect" class="form-label me-2 mb-0 text-nowrap">
						<i class="fas fa-folder text-info me-1"></i>Main Cat: <span class="text-danger">*</span>
					</label>
					<select id="editMainCategorySelect" class="form-select form-select-sm" required>
						<option value="" {{ is_null($lesson->selected_main_category_id) ? 'selected' : '' }} disabled>Select Main
							Category
						</option>
						@if(isset($allMainCategories) && $allMainCategories->isNotEmpty())
							@foreach ($allMainCategories as $mainCategory)
								<option
									value="{{ $mainCategory->id }}" {{ $lesson->selected_main_category_id == $mainCategory->id ? 'selected' : '' }}>
									{{ $mainCategory->name }}
								</option>
							@endforeach
						@else
							<option value="" disabled>No main categories available</option>
						@endif
					</select>
				</div>
			</div>
			{{-- Sub Category --}}
			<div class="col-md-6 col-lg-3 mb-2">
				<div class="d-flex align-items-center">
					<label for="editSubCategorySelect" class="form-label me-2 mb-0 text-nowrap">
						<i class="fas fa-tag text-info me-1"></i>Sub Cat:
					</label>
					<select id="editSubCategorySelect"
					        class="form-select form-select-sm" {{ is_null($lesson->selected_main_category_id) ? 'disabled' : '' }}>
						<option value="">-- None --</option>
						{{-- Populated by JS --}}
					</select>
				</div>
			</div>
			{{-- Language --}}
			<div class="col-md-6 col-lg-3 mb-2">
				<div class="d-flex align-items-center">
					<label for="editLanguageSelect" class="form-label me-2 mb-0 text-nowrap">
						<i class="fas fa-globe text-secondary me-1"></i>Lang:
					</label>
					<select id="editLanguageSelect" class="form-select form-select-sm" required>
						<option value="English" {{ $lesson->language == 'English' ? 'selected' : '' }}>English</option>
						<option value="Türkçe" {{ $lesson->language == 'Türkçe' ? 'selected' : '' }}>Türkçe</option>
						<option value="Deutsch" {{ $lesson->language == 'Deutsch' ? 'selected' : '' }}>Deutsch</option>
						<option value="Français" {{ $lesson->language == 'Français' ? 'selected' : '' }}>Français</option>
						<option value="Español" {{ $lesson->language == 'Español' ? 'selected' : '' }}>Español</option>
						<option value="繁體中文" {{ $lesson->language == '繁體中文' ? 'selected' : '' }}>繁體中文</option>
						@if (is_null($lesson->language))
							<option value="" disabled selected>Select</option>
						@endif
					</select>
				</div>
			</div>
		</div>
		{{-- User Title, Notes, Month, Year, Week --}}
		<div class="row mb-1 pt-0 settings-row g-2">
			<div class="col-md-6 col-lg-4 mb-2">
				<div class="d-flex align-items-center">
					<label for="editUserTitle" class="form-label me-2 mb-0 text-nowrap">
						<i class="fas fa-heading text-muted me-1"></i>User Title:
					</label>
					<input type="text" id="editUserTitle" class="form-control form-control-sm"
					       value="{{ old('user_title', $lesson->user_title) }}" placeholder="Optional custom title">
				</div>
			</div>
			<div class="col-md-3 col-lg-2 mb-2">
				<div class="d-flex align-items-center">
					<label for="editMonth" class="form-label me-2 mb-0 text-nowrap">
						<i class="fas fa-calendar-alt text-muted me-1"></i>Month:
					</label>
					<select id="editMonth" class="form-select form-select-sm">
						<option value="" {{ is_null($lesson->month) ? 'selected' : '' }}>Select</option>
						@for ($m = 1; $m <= 12; $m++)
							<option value="{{ $m }}" {{ old('month', $lesson->month) == $m ? 'selected' : '' }}>
								{{ date('F', mktime(0, 0, 0, $m, 10)) }}
							</option>
						@endfor
					</select>
				</div>
			</div>
			<div class="col-md-3 col-lg-2 mb-2">
				<div class="d-flex align-items-center">
					<label for="editYear" class="form-label me-2 mb-0 text-nowrap">
						<i class="fas fa-calendar-day text-muted me-1"></i>Year:
					</label>
					<select id="editYear" class="form-select form-select-sm">
						<option value="" {{ is_null($lesson->year) ? 'selected' : '' }}>Select</option>
						@php
							$currentYear = date('Y');
							$startYear = $currentYear - 10;
							$endYear = $currentYear + 5;
						@endphp
						@for ($y = $endYear; $y >= $startYear; $y--)
							<option value="{{ $y }}" {{ old('year', $lesson->year) == $y ? 'selected' : '' }}>
								{{ $y }}
							</option>
						@endfor
					</select>
				</div>
			</div>
			<div class="col-md-3 col-lg-2 mb-2">
				<div class="d-flex align-items-center">
					<label for="editWeek" class="form-label me-2 mb-0 text-nowrap">
						<i class="fas fa-calendar-week text-muted me-1"></i>Week:
					</label>
					<select id="editWeek" class="form-select form-select-sm">
						<option value="" {{ is_null($lesson->week) ? 'selected' : '' }}>Select</option>
						@for ($w = 1; $w <= 53; $w++)
							{{-- Max 53 weeks --}}
							<option value="{{ $w }}" {{ old('week', $lesson->week) == $w ? 'selected' : '' }}>
								{{ $w }}
							</option>
						@endfor
					</select>
				</div>
			</div>
		</div>
		<div class="row mb-1 pt-0 settings-row g-2">
			<div class="col-12">
				<div class="d-flex align-items-start">
					<label for="editSubject" class="form-label me-2 mb-0 text-nowrap pt-1">
						<i class="fas fa-sticky-note text-muted me-1"></i>Subject:
					</label>
					<textarea id="editSubject" class="form-control form-control-sm" rows="2"
					          placeholder="lesson subject">{{ old('subject', $lesson->subject) }}</textarea>
				</div>
			</div>
		</div>
		<div class="row mb-1 pt-0 settings-row g-2">
			<div class="col-12">
				<div class="d-flex align-items-start">
					<label for="editNotes" class="form-label me-2 mb-0 text-nowrap pt-1">
						<i class="fas fa-sticky-note text-muted me-1"></i>Notes:
					</label>
					<textarea id="editNotes" class="form-control form-control-sm" rows="2"
					          placeholder="Optional lesson notes">{{ old('notes', $lesson->notes) }}</textarea>
				</div>
			</div>
		</div>
		
		{{-- LLM and TTS Settings --}}
		<div class="row mb-3 pt-0 settings-row g-2">
			<div class="col-md-6 col-lg-4 mb-2 mb-lg-0">
				<div class="d-flex align-items-center">
					<label for="preferredLlmSelect" class="form-label me-2 mb-0 text-nowrap"><i
							class="fas fa-robot text-primary me-1"></i>AI Model:</label>
					<select id="preferredLlmSelect" class="form-select form-select-sm">
						@php $defaultLlmId = env('DEFAULT_LLM', ''); @endphp
						@forelse ($llms ?? [] as $llm_option)
							<option value="{{ $llm_option['id'] }}"
								{{ ($lesson->preferredLlm ?? $defaultLlmId) === $llm_option['id'] ? 'selected' : '' }}>
								{{ $llm_option['name'] }}
							</option>
						@empty
							<option value="" disabled>No AI models available</option>
						@endforelse
					</select>
				</div>
			</div>
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
			<div class="col-md-6 col-lg-3 mb-2 mb-md-0">
				<div class="d-flex align-items-center">
					<label for="ttsVoiceSelect" class="form-label me-2 mb-0 text-nowrap"><i
							class="fas fa-microphone text-success me-1"></i>Voice:</label>
					<select id="ttsVoiceSelect" class="form-select form-select-sm">
						<optgroup label="Google Voices">
							<option value="en-US-Studio-O" {{ $lesson->ttsVoice == 'en-US-Studio-O' ? 'selected' : '' }}>
								en-US-Studio-O (F)
							</option>
							<option value="en-US-Studio-Q" {{ $lesson->ttsVoice == 'en-US-Studio-Q' ? 'selected' : '' }}>
								en-US-Studio-Q (M)
							</option>
							<option value="tr-TR-Standard-A" {{ $lesson->ttsVoice == 'tr-TR-Standard-A' ? 'selected' : '' }}>
								tr-TR-Standard-A (F)
							</option>
							<option value="tr-TR-Standard-B" {{ $lesson->ttsVoice == 'tr-TR-Standard-B' ? 'selected' : '' }}>
								tr-TR-Standard-B (M)
							</option>
							<option value="cmn-TW-Standard-A" {{ $lesson->ttsVoice == 'cmn-TW-Standard-A' ? 'selected' : '' }}>
								cmn-TW-Standard-A (F)
							</option>
							<option value="cmn-TW-Standard-B" {{ $lesson->ttsVoice == 'cmn-TW-Standard-B' ? 'selected' : '' }}>
								cmn-TW-Standard-B (M)
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
			<div class="col-md-4 col-lg-3 mb-2 mb-md-0">
				<div class="d-flex align-items-center">
					<label for="ttsLanguageCodeSelect" class="form-label me-2 mb-0 text-nowrap"><i
							class="fas fa-language text-warning me-1"></i>TTS Lang:</label>
					<select class="form-select form-select-sm" id="ttsLanguageCodeSelect">
						<option value="en-US" {{ $lesson->ttsLanguageCode == 'en-US' ? 'selected' : '' }}>en-US</option>
						<option value="tr-TR" {{ $lesson->ttsLanguageCode == 'tr-TR' ? 'selected' : '' }}>tr-TR</option>
						<option value="cmn-TW" {{ $lesson->ttsLanguageCode == 'cmn-TW' ? 'selected' : '' }}>cmn-TW</option>
						<option value="cmn-CN" {{ $lesson->ttsLanguageCode == 'cmn-CN' ? 'selected' : '' }}>cmn-CN</option>
						<option value="fr-FR" {{ $lesson->ttsLanguageCode == 'fr-FR' ? 'selected' : '' }}>fr-FR</option>
						<option value="de-DE" {{ $lesson->ttsLanguageCode == 'de-DE' ? 'selected' : '' }}>de-DE</option>
					</select>
				</div>
			</div>
		</div>
		<div class="row mb-3 pt-0 settings-row g-2">
			<div class="col-md-4 col-lg-3 d-flex align-items-end justify-content-start">
				<button class="btn btn-sm btn-primary" id="updateLessonSettingsBtn" title="Save Lesson Settings">
					<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
					<i class="fas fa-save me-1"></i>Save Settings
				</button>
			</div>
		</div>
		<p><small>Use the buttons below to generate intro, add questions, or manage question assets (audio, images). Click
				audio icons (<i class="fas fa-play text-primary"></i>) to listen. Click images to enlarge. Use <i
					class="fas fa-trash-alt text-danger"></i> to delete questions.</small></p>
	</div>
	
	@php
		$lessonContent = $lesson->lesson_content;
		$contentText = $lessonContent['text'] ?? '';
		$sentences = $lessonContent['sentences'] ?? [];
		$audioGeneratedAt = isset($lessonContent['audio_generated_at']) ? \Carbon\Carbon::parse($lessonContent['audio_generated_at'])->diffForHumans() : null;
		$audioErrorCount = 0;
		if (!empty($sentences)) {
				foreach ($sentences as $sentence) {
						if (empty($sentence['audio_url'])) $audioErrorCount++;
				}
		}
	@endphp
	<div class="content-card mb-4">
		<h3 class="mb-3 d-flex justify-content-between align-items-center flex-wrap">
			<span class="me-3">{{ $lesson->title ?? $lesson->user_title ?? 'No Title' }}</span>
			<div class="btn-group btn-group-sm" role="group" aria-label="Lesson Content Actions">
				<button class="btn btn-outline-info" id="generate-lesson-sentence-assets-btn"
				        data-lesson-id="{{ $lesson->id }}"
				        data-generate-url="{{ route('lesson.content.generate.assets', ['lesson' => $lesson->id]) }}"
				        title="Generate audio & image prompts for each sentence. Replaces existing.">
					<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
					<i class="fas fa-microphone-alt"></i> {{ $audioGeneratedAt ? 'Regen Assets' : 'Gen Assets' }}
				</button>
				<button class="btn btn-outline-secondary edit-lesson-content-btn"
				        data-bs-toggle="modal" data-bs-target="#editContentModal"
				        title="Edit Lesson Content Text">
					<i class="fas fa-edit"></i> Edit Content
				</button>
			</div>
		</h3>
		<div id="lesson-content-audio-status" class="text-muted small mb-2">
			@if($audioGeneratedAt)
				Assets generated: {{ $audioGeneratedAt }}
				@if(!empty($sentences))
					({{ count($sentences) }} sentences)
					@if($audioErrorCount > 0)
						<span class="text-danger">({{ $audioErrorCount }} audio errors)</span>
					@endif
				@endif
			@else
					(Sentence assets not generated yet)
			@endif
		</div>
		<div id="lesson-content-error" class="text-danger small mb-2" style="display: none;"></div>
		
		<div class="sentences-list mt-3" id="sentences-list-lesson">
			@if(!empty($sentences))
				@foreach($sentences as $sentenceIndex => $sentence)
					@include('partials._sentence_edit_item', ['lesson' => $lesson, 'sentenceIndex' => $sentenceIndex, 'sentence' => $sentence])
				@endforeach
			@else
				@if($audioGeneratedAt)
					<p class="text-muted fst-italic" id="no-sentences-msg-lesson">No sentences found or generated for this
						lesson.</p>
				@endif
			@endif
		</div>
		<p class="text-muted mt-3 mb-0" style="padding-left:4px;">Lesson Content Text:</p>
		<p class="p-2 content-card" style=" white-space: pre-line;" id="content-text-display">{{ $contentText }}</p>
		
		
		<div class="questions-section border-top pt-3 mt-4">
			<h4 class="mt-0 mb-3">Questions for this Lesson</h4>
			<div class="mb-4">
				<div class="btn-group" role="group" aria-label="Generate Question Buttons">
					@foreach(['easy', 'medium', 'hard'] as $difficulty)
						<button class="btn btn-outline-success add-question-batch-btn"
						        data-lesson-id="{{ $lesson->id }}"
						        data-difficulty="{{ $difficulty }}"
						        data-generate-url="{{ route('question.generate.batch', ['lesson' => $lesson->id, 'difficulty' => $difficulty]) }}"
						        data-target-list-id="question-list-{{ $difficulty }}-lesson" {{-- MODIFIED: Target ID --}}
						        data-error-area-id="question-gen-error-{{ $difficulty }}-lesson"> {{-- MODIFIED: Error Area ID --}}
							<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
							<i class="fas fa-plus-circle me-1"></i> Add 3 {{ ucfirst($difficulty) }}
						</button>
					@endforeach
				</div>
				@foreach(['easy', 'medium', 'hard'] as $difficulty)
					<div class="asset-generation-error text-danger small mt-1" id="question-gen-error-{{ $difficulty }}-lesson"
					     style="display: none;"></div>
				@endforeach
			</div>
			
			@foreach(['easy', 'medium', 'hard'] as $difficulty)
				<div class="question-difficulty-group">
					<h5 class="d-flex justify-content-between align-items-center">
						<span>{{ ucfirst($difficulty) }} Questions</span>
						<span class="badge bg-secondary rounded-pill">
                            {{ count($groupedQuestions[$difficulty] ?? []) }}
                        </span>
					</h5>
					<div class="question-list-container mt-2" id="question-list-{{ $difficulty }}-lesson"> {{-- MODIFIED: ID --}}
						@php $questionsForDifficulty = $groupedQuestions[$difficulty] ?? []; @endphp
						@forelse($questionsForDifficulty as $question)
							@include('partials._question_edit_item', ['question' => $question])
						@empty
							<p class="placeholder-text" id="placeholder-{{ $difficulty }}-lesson">No {{ $difficulty }} questions
								created yet for this lesson.</p>
						@endforelse
					</div>
				</div>
			@endforeach
		</div>
	</div>
	
	<template id="question-item-template">
		@include('partials._question_edit_item', ['question' => null])
	</template>
	<template id="sentence-item-template">
		@include('partials._sentence_edit_item', ['lesson' => $lesson, 'sentenceIndex' => 'SENTENCE_INDEX_PLACEHOLDER', 'sentence' => null])
	</template>
	
	@include('partials._freepik_modal')
	@include('partials._question_batch_success_modal')
	@include('partials._edit_texts_modal')
	@include('partials._edit_content_modal')
	
	<form action="{{ route('lesson.delete', $lesson->id) }}" method="POST" class="d-none" id="delete-lesson-form-{{ $lesson->id }}">
		@csrf
		@method('DELETE')
	</form>
	
@endsection

@push('scripts')
	<script>
		// Global/shared variables for edit_lesson page
		let sharedAudioPlayer = null;
		let imageModal = null;
		let currentlyPlayingButton = null;
		
		// Settings related (can be scoped within edit_lesson_top_settings.js if preferred)
		let editMainCategorySelect = null;
		let editSubCategorySelect = null;
		let editLanguageSelect = null;
		let preferredLlmSelect = null; // For top settings bar
		let ttsEngineSelect = null;    // For top settings bar
		let ttsVoiceSelect = null;     // For top settings bar
		let ttsLanguageCodeSelect = null; // For top settings bar
		let updateSettingsBtn = null;
		
		const lessonId = @json($lesson->id);
		const updateSettingsUrl = @json(route('lesson.update.settings', ['lesson' => $lesson->id]));
		const llmsListUrl = @json(route('api.llms.list')); // For AI model dropdown in modal
		const initialSelectedMainCategoryId = @json($lesson->selected_main_category_id);
		const initialSelectedSubCategoryId = @json($lesson->sub_category_id);
		
		// Variables for modals (generateContentModal, addVideoModal)
		// These will be initialized in edit_lesson.js
		let generateContentModal = null;
		let lessonIdInput, lessonTitleDisplay, lessonSubjectTextarea, lessonNotesDisplay, additionalInstructionsTextarea, aiModelSelect;
		let autoDetectCheckbox, generatePreviewButton, generatePreviewSpinner, previewContentArea, lessonPreviewBody;
		let generationOptionsArea, applyGenerationButton, applyGenerationSpinner, generationErrorMessage,
			cancelGenerationButton, backToOptionsButton;
		let modalCategorySuggestionArea, suggestedMainCategoryText, suggestedSubCategoryText;
		let existingCategoryDisplayArea, existingMainCategoryNameSpan, existingSubCategoryNameSpan, existingCategoryNote,
			autoDetectCheckboxArea;
		let currentSubCategoryIdInput, currentSelectedMainCategoryIdInput;
		let generationSourceGroup, sourceSubjectRadio, sourceVideoRadio, videoSubtitlesDisplayArea, videoSubtitlesTextarea,
			videoSubtitlesBase64Input, generationSourceInput;
		
		let addVideoModal = null;
		let addVideoForm, lessonIdForVideoInput, lessonTitleForVideoSpan, youtubeVideoIdInputModal, submitVideoButton,
			submitVideoSpinner, addVideoError, addVideoProgress;
		
		let currentGeneratedPlan = null;
		let currentSuggestedMainCategory = null;
		let currentSuggestedSubCategory = null;
		let isAutoDetectingCategory = true; // Default for modal
	
	</script>
	<script src="{{ asset('js/edit_lesson.js') }}"></script>
	<script src="{{ asset('js/edit_lesson_top_settings.js') }}"></script>
	<script src="{{ asset('js/edit_lesson_question.js') }}"></script>
	<script src="{{ asset('js/edit_lesson_audio.js') }}"></script>
	<script src="{{ asset('js/edit_lesson_freepik_functions.js') }}"></script>
@endpush
