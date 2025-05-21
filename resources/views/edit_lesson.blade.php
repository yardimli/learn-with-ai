@extends('layouts.app')

@section('title', 'Edit Lesson Assets: ' . $lesson->title)

@section('content')
	<div class="d-flex justify-content-between align-items-center mb-3">
		<a href="{{ route('lessons.list') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back to
			Lessons</a>
		<a href="{{ route('category_management.main.index') }}" class="btn btn-outline-info ms-2">
			<i class="fas fa-tags"></i> Manage Categories
		</a>
		<a href="{{ route('question.interface', ['lesson' => $lesson->id]) }}" class="btn btn-outline-success"><i
				class="fas fa-eye"></i> Start Lesson</a>
	</div>
	
	<div class="content-card mb-4" id="lessonSettingsCard" data-categories="{{ $categoriesData }}">
		<h1 class="mb-1">Edit Lesson: {{ $lesson->user_title ?: $lesson->title }}</h1>
		<p class="text-muted mb-3">Lesson: {{ $lesson->subject }} (ID: {{ $lesson->id }} )</p>
		
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
						@php $currentYear = date('Y'); $startYear = $currentYear - 10; $endYear = $currentYear + 5; @endphp
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
						<option value="{{ $lesson->preferredLlm }}" selected>{{ $lesson->preferredLlm }}</option>
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
	
	{{-- MODIFIED: Display single lesson content block --}}
	@php
		// $lesson->lesson_content is now the single content object (array)
		$lessonContent = $lesson->lesson_content;
		$contentTitle = $lessonContent['title'] ?? 'Lesson Content';
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
			<span class="me-3">{{ $contentTitle }}</span>
			<div class="btn-group btn-group-sm" role="group" aria-label="Lesson Content Actions">
				<button class="btn btn-outline-info" id="generate-lesson-sentence-assets-btn"
				        data-lesson-id="{{ $lesson->id }}"
				        data-generate-url="{{ route('lesson.content.generate.assets', ['lesson' => $lesson->id]) }}"
				        title="Generate audio & image prompts for each sentence. Replaces existing.">
					<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
					<i class="fas fa-microphone-alt"></i> {{ $audioGeneratedAt ? 'Regen Assets' : 'Gen Assets' }}
				</button>
				<button class="btn btn-outline-secondary edit-lesson-content-btn"
				        data-bs-toggle="modal" data-bs-target="#editContentModal" {{-- Target new/repurposed modal --}}
				        data-content-title="{{ $contentTitle }}"
				        title="Edit Lesson Content Title & Text">
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
			@else (Sentence assets not generated yet)
			@endif
		</div>
		<div id="lesson-content-error" class="text-danger small mb-2" style="display: none;"></div>
		
		<div class="sentences-list mt-3" id="sentences-list-lesson"> {{-- MODIFIED: ID for sentences list --}}
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
		<p id="lesson-content-text-display">{{ $contentText }}</p> {{-- MODIFIED: ID for text display --}}
		
		<div class="questions-section border-top pt-3 mt-4">
			<h4 class="mt-0 mb-3">Questions for this Lesson</h4>
			<div class="mb-4">
				<h5 class="mb-2">Generate New Questions</h5>
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
						<span class="badge bg-secondary rounded-pill"> {{ count($groupedQuestions[$difficulty] ?? []) }} </span>
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
	@include('partials._edit_content_modal') {{-- Assuming you rename/create this for single content --}}

@endsection

@push('scripts')
	<script>
		let sharedAudioPlayer = null;
		let imageModal = null;
		let currentlyPlayingButton = null;
		// let existingPlayButtons = null; // This might be simplified
		let editMainCategorySelect = null;
		let editSubCategorySelect = null;
		let editLanguageSelect = null;
		let preferredLlmSelect = null;
		let ttsEngineSelect = null;
		let ttsVoiceSelect = null;
		let ttsLanguageCodeSelect = null;
		let updateSettingsBtn = null;
		
		const lessonId = @json($lesson->id);
		const updateSettingsUrl = @json(route('lesson.update.settings', ['lesson' => $lesson->id]));
		const llmsListUrl = @json(route('api.llms.list'));
		const initialSelectedMainCategoryId = @json($lesson->selected_main_category_id);
		const initialSelectedSubCategoryId = @json($lesson->sub_category_id);
	</script>
	<script src="{{ asset('js/edit_lesson.js') }}"></script>
	<script src="{{ asset('js/edit_lesson_top_settings.js') }}"></script>
	<script src="{{ asset('js/edit_lesson_question.js') }}"></script>
	<script src="{{ asset('js/edit_lesson_audio.js') }}"></script>
	<script src="{{ asset('js/edit_lesson_freepik_functions.js') }}"></script>
@endpush
