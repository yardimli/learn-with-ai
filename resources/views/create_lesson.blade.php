@extends('layouts.app')

@section('title', 'Create New Lesson - Learn with AI')

@section('content')
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h1 class="mb-0 me-3">Create Basic Lesson</h1> {{-- Added margin --}}
		<div class="d-flex flex-wrap gap-2"> {{-- Wrapper for buttons --}}
			<a href="{{ route('lesson.import.form') }}" class="btn btn-outline-success"> {{-- New Import Button --}}
				<i class="fas fa-file-import"></i> Import from JSON
			</a>
			<a href="{{ route('lessons.list') }}" class="btn btn-outline-secondary">
				<i class="fas fa-list"></i> View Existing Lessons
			</a>
			<a href="{{ route('category_management.main.index') }}" class="btn btn-outline-info">
				<i class="fas fa-tags"></i> Manage Categories
			</a>
		</div>
	</div>
	
	<div class="content-card shadow-sm">
		<form id="lessonForm" action="{{ route('lesson.save.basic') }}" method="POST">
			@csrf
			<div class="mb-3">
				<label for="userTitleInput" class="form-label fs-5">Lesson Title:</label>
				<input type="text" class="form-control form-control-lg" id="userTitleInput" name="user_title"
				       placeholder="Enter a title for your lesson" required>
				<small class="form-text text-muted">This is your own title for the lesson.</small>
			</div>
			
			{{-- Lesson Subject --}}
			<div class="mb-3">
				<label for="lessonSubject" class="form-label fs-5">Lesson Subject:</label>
				<textarea type="text" class="form-control form-control-lg" id="lessonSubject" name="lesson_subject"
				          placeholder="e.g., Quantum Physics, Photosynthesis" rows="4" required></textarea>
				<small class="form-text text-muted">Enter a subject. You'll generate content with AI later.</small>
			</div>
			
			<div class="mb-3">
				<label for="notesInput" class="form-label fs-5">Additional Notes:</label>
				<textarea class="form-control" id="notesInput" name="notes" rows="2"
				          placeholder="Optional: Add any specific points to include, target audience, etc."></textarea>
				<small class="form-text text-muted">Provide any additional context or requirements for the AI.</small>
			</div>
			
			{{-- YouTube Video ID --}}
			<div class="mb-3">
				<label for="youtubeVideoIdInput" class="form-label fs-5">YouTube Video ID or URL (Optional):</label>
				<input type="text" class="form-control" id="youtubeVideoIdInput" name="youtube_video_id"
				       placeholder="e.g., dQw4w9WgXcQ or full YouTube URL">
				<small class="form-text text-muted">If provided, video details and subtitles (if available) will be
					fetched.</small>
			</div>
			
			
			{{-- Row for Category and Language --}}
			<div class="row mb-3">
				<div class="col-md-6">
					<label for="categorySelectionMode" class="form-label">Category Selection:</label>
					<select class="form-select mb-2" id="categorySelectionMode" name="category_selection_mode">
						<option value="ai_decide" selected>Let AI decide main & sub-category</option>
						<option value="main_only">I'll select main category, AI suggests sub-category</option>
						<option value="both">I'll select both main & sub-category</option>
					</select>
					<div id="mainCategoryArea" class="mt-2 d-none">
						<label for="mainCategorySelect" class="form-label">Main Category:</label>
						<select class="form-select" id="mainCategorySelect" name="main_category_id" disabled>
							<option value="" selected disabled>Select a main category</option>
							@foreach ($mainCategories as $mainCategory)
								<option value="{{ $mainCategory->id }}">{{ $mainCategory->name }}</option>
							@endforeach
						</select>
					</div>
					<div id="subCategoryArea" class="mt-2 d-none">
						<label for="subCategorySelect" class="form-label">Sub-Category:</label>
						<select class="form-select" id="subCategorySelect" name="sub_category_id" disabled>
							<option value="" selected disabled>Select a sub-category</option>
							{{-- Sub-categories will be populated via JavaScript --}}
						</select>
					</div>
					<small class="form-text text-muted">Choose how you want to categorize this lesson.</small>
				</div>
				<div class="col-md-6">
					<label for="languageSelect" class="form-label">Lesson Language:</label>
					<select class="form-select" id="languageSelect" name="language" required>
						<option value="English" selected>English</option>
						<option value="Türkçe">Türkçe</option>
						<option value="Deutsch">Deutsch</option>
						<option value="Français">Français</option>
						<option value="Español">Español</option>
						<option value="繁體中文">繁體中文</option>
					</select>
					<small class="form-text text-muted">Primary language of the lesson content.</small>
				</div>
			</div>
			
			
			{{-- LLM Selection --}}
			<div class="mb-3">
				<label for="preferredLlmSelect" class="form-label">Preferred AI Model:</label>
				<select class="form-select" id="preferredLlmSelect" name="preferred_llm" required>
					@php
						$defaultLlmId = env('DEFAULT_LLM', '');
					@endphp
					@forelse ($llms ?? [] as $llm)
						<option value="{{ $llm['id'] }}" {{ $llm['id'] === $defaultLlmId ? 'selected' : '' }}>
							{{ $llm['name'] }}
						</option>
					@empty
						<option value="" disabled>No AI models available</option>
					@endforelse
				</select>
				<small class="form-text text-muted">Model to use for generating content later.</small>
			</div>
			
			{{-- TTS Engine Selection --}}
			<div class="mb-3">
				<label for="ttsEngineSelect" class="form-label">Text-to-Speech Engine:</label>
				<select class="form-select" id="ttsEngineSelect" name="tts_engine" required>
					<option value="openai" {{ env('DEFAULT_TTS_ENGINE', 'google') === 'openai' ? 'selected' : '' }}>OpenAI TTS
					</option>
					<option value="google" {{ env('DEFAULT_TTS_ENGINE', 'google') === 'google' ? 'selected' : '' }}>Google Cloud
						TTS
					</option>
				</select>
			</div>
			
			{{-- TTS Voice Selection (Dynamic) --}}
			<div class="mb-3">
				<label for="ttsVoiceSelect" class="form-label">Text-to-Speech Voice:</label>
				<select class="form-select" id="ttsVoiceSelect" name="tts_voice" required>
					<optgroup label="OpenAI Voices">
						<option value="alloy" selected>Alloy (Neutral)</option>
						<option value="echo">Echo (Male)</option>
						<option value="fable">Fable (Male)</option>
						<option value="onyx">Onyx (Male)</option>
						<option value="nova">Nova (Female)</option>
						<option value="shimmer">Shimmer (Female)</option>
					</optgroup>
					<optgroup label="Google Voices" style="display: none;">
						<option value="en-US-Studio-O">en-US-Studio-O (Female)</option>
						<option value="en-US-Studio-Q">en-US-Studio-Q (Male)</option>
						<option value="tr-TR-Wavenet-A">tr-TR-Wavenet-A (Female)</option>
						<option value="tr-TR-Wavenet-B">tr-TR-Wavenet-B (Male)</option>
						<option value="tr-TR-Standard-A">tr-TR-Standard-A (Female)</option>
						<option value="tr-TR-Standard-B">tr-TR-Standard-B</option>
						<option value="cmn-CN-Wavenet-A">cmn-CN-Wavenet-A (Female)</option>
						<option value="cmn-CN-Wavenet-B">cmn-CN-Wavenet-B (Male)</option>
						<option value="cmn-TW-Standard-A">cmn-TW-Standard-A (Female)</option>
						<option value="cmn-TW-Standard-B">cmn-TW-Standard-B (Male)</option>
					</optgroup>
				</select>
			</div>
			
			{{-- TTS Language Code Selection --}}
			<div class="mb-3">
				<label for="ttsLanguageCodeSelect" class="form-label">Speech Language Code:</label>
				<select class="form-select" id="ttsLanguageCodeSelect" name="tts_language_code" required>
					<option value="en-US" selected>English (United States)</option>
					<option value="tr-TR">Turkish (Turkey)</option>
					<option value="cmn-TW">Chinese (Mandarin, Taiwan)</option>
					<option value="cmn-CN">Chinese (Mandarin, China)</option>
					<option value="fr-FR">French (France)</option>
					<option value="de-DE">German (Germany)</option>
					<option value="es-ES">Spanish (Spain)</option>
					<option value="it-IT">Italian (Italy)</option>
					<option value="ja-JP">Japanese (Japan)</option>
				</select>
				<small class="form-text text-muted">Primarily used by Google TTS. OpenAI often auto-detects.</small>
			</div>
			
			
			<div class="d-grid">
				<button type="submit" id="createBasicLessonButton" class="btn btn-primary btn-lg" disabled>
                    <span id="createBasicLessonSpinner" class="spinner-border spinner-border-sm d-none" role="status"
                          aria-hidden="true"></span>
					Create Basic Lesson
				</button>
				<small class="form-text text-muted mt-2 text-center">
					You'll generate the lesson content with AI from the lessons list.
				</small>
			</div>
		</form>
		<div id="categoryDataContainer" class="d-none">
			@foreach ($mainCategories as $mainCategory)
				<div class="main-category-data" data-main-id="{{ $mainCategory->id }}"
				     data-main-name="{{ $mainCategory->name }}">
					@foreach ($mainCategory->subCategories as $subCategory)
						<div class="sub-category-data" data-sub-id="{{ $subCategory->id }}"
						     data-sub-name="{{ $subCategory->name }}"></div>
					@endforeach
				</div>
			@endforeach
		</div>
	</div>
@endsection

@push('scripts')
	<script src="{{ asset('js/create_lesson.js') }}"></script>
@endpush
