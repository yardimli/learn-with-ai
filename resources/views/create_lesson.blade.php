@extends('layouts.app')
@section('title', 'Create New Lesson - Learn with AI')
@section('content')
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h1 class="mb-0">Learn Something New with AI</h1>
		<a href="{{ route('lessons.list') }}" class="btn btn-outline-secondary">
			<i class="fas fa-list"></i> View Existing Lessons
		</a>
	</div>
	
	<div class="content-card shadow-sm">
		<form id="lessonForm" action="{{ route('lesson.generate.structure') }}" method="POST">
			@csrf
			<input type="hidden" id="saveStructureUrl" value="{{ route('lesson.save.structure') }}">
			
			{{-- Lesson Subject --}}
			<div class="mb-3">
				<label for="lessonInput" class="form-label fs-5">Enter a Lesson Subject:</label>
				<textarea type="text" class="form-control form-control-lg" id="lessonInput" name="lesson" placeholder="e.g., Quantum Physics, Photosynthesis" value="cats" required></textarea>
			</div>
			
			{{-- LLM Selection --}}
			<div class="mb-3">
				<label for="preferredLlmSelect" class="form-label">Lesson Generation AI Model:</label>
				<select class="form-select" id="preferredLlmSelect" name="preferred_llm" required>
					{{-- Default option removed, force selection --}}
					@php $defaultLlmId = env('DEFAULT_LLM', ''); @endphp
					@forelse ($llms ?? [] as $llm)
						<option value="{{ $llm['id'] }}" {{ $llm['id'] === $defaultLlmId ? 'selected' : '' }}>
							{{ $llm['name'] }}
						</option>
					@empty
						<option value="" disabled>No AI models available</option>
					@endforelse
				</select>
				<small class="form-text text-muted">Select the AI model to generate the lesson content.</small>
			</div>
			
			{{-- TTS Engine Selection --}}
			<div class="mb-3">
				<label for="ttsEngineSelect" class="form-label">Text-to-Speech Engine:</label>
				<select class="form-select" id="ttsEngineSelect" name="tts_engine" required>
					<option value="openai" {{ env('DEFAULT_TTS_ENGINE', 'google') === 'openai' ? 'selected' : '' }}>OpenAI TTS</option>
					<option value="google" {{ env('DEFAULT_TTS_ENGINE', 'google') === 'google' ? 'selected' : '' }}>Google Cloud TTS</option>
				</select>
			</div>
			
			{{-- TTS Voice Selection (Dynamic) --}}
			<div class="mb-3">
				<label for="ttsVoiceSelect" class="form-label">Text-to-Speech Voice:</label>
				<select class="form-select" id="ttsVoiceSelect" name="tts_voice" required>
					{{-- Options will be populated by JS --}}
					<optgroup label="OpenAI Voices">
						<option value="alloy" selected>Alloy (Neutral)</option> {{-- Default selection --}}
						<option value="echo">Echo (Male)</option>
						<option value="fable">Fable (Male)</option>
						<option value="onyx">Onyx (Male)</option>
						<option value="nova">Nova (Female)</option>
						<option value="shimmer">Shimmer (Female)</option>
					</optgroup>
					<optgroup label="Google Voices" style="display: none;"> {{-- Hide initially --}}
						<option value="en-US-Studio-O">en-US-Studio-O (Female)</option>
						<option value="en-US-Studio-Q">en-US-Studio-Q (Male)</option>
						<option value="tr-TR-Chirp3-HD-Aoede">tr-TR-Chirp3-HD-Aoede (Female)</option>
						<option value="tr-TR-Chirp3-HD-Charon">tr-TR-Chirp3-HD-Charon (Male)</option>
						<option value="tr-TR-Standard-A">tr-TR-Standard-A (Female)</option>
						<option value="tr-TR-Standard-B">tr-TR-Standard-B</option>
						<option value="cmn-CN-Chirp3-HD-Aoede">cmn-CN-Chirp3-HD-Aoede (Female)</option>
						<option value="cmn-CN-Chirp3-HD-Charon">cmn-CN-Chirp3-HD-Charon (Male)</option>
						<option value="cmn-TW-Standard-A">cmn-TW-Standard-A (Female)</option>
						<option value="cmn-TW-Standard-B">cmn-TW-Standard-B (Male)</option>
					</optgroup>
				</select>
			</div>
			
			{{-- TTS Language Code Selection --}}
			<div class="mb-3">
				<label for="ttsLanguageCodeSelect" class="form-label">Speech Language:</label>
				<select class="form-select" id="ttsLanguageCodeSelect" name="tts_language_code" required>
					<option value="en-US" selected>English (United States)</option>
					<option value="tr-TR">Turkce</option>
					<option value="cmn-TW">Chinese (Taiwan)</option>
					<option value="cmn-CN">Chinese (China)</option>
					<option value="fr-FR">French (France)</option>
					<option value="de-DE">German (Germany)</option>
					<option value="it-IT">Italian (Italy)</option>
					<option value="ja-JP">Japanese</option>
					<option value="ko-KR">Korean</option>
					{{-- Add other common languages as needed --}}
				</select>
				<small class="form-text text-muted">Primarily used by Google TTS. OpenAI often auto-detects.</small>
			</div>
			
			
			<div class="d-grid">
				<button type="submit" id="startLearningButton" class="btn btn-primary btn-lg" disabled> {{-- Start disabled until subject entered --}}
					<span id="startLearningSpinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
					Generate Lesson Preview
				</button>
			</div>
		</form>
	</div>
	
	
	<!-- Lesson Plan Preview Modal -->
	{{-- Modal remains the same - it only shows the plan structure --}}
	<div class="modal fade" id="lessonPreviewModal" tabindex="-1" aria-labelledby="lessonPreviewModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
		<div class="modal-dialog modal-lg modal-dialog-scrollable">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="lessonPreviewModalLabel">Lesson Plan Preview</h5>
					{{-- <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button> --}} {{-- Disable closing via X --}}
				</div>
				<div class="modal-body" id="lessonPreviewBody">
					{{-- Content dynamically loaded by JS --}}
					<div class="text-center">
						<div class="spinner-border text-primary" role="status">
							<span class="visually-hidden">Loading preview...</span>
						</div>
						<p class="mt-2">Generating lesson preview...</p>
					</div>
				</div>
				<div class="modal-footer">
                <span id="modalLoadingIndicator" class="me-auto d-none">
                    <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Creating Lesson...
                </span>
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="cancelPreviewButton">Cancel </button>
					<button type="button" class="btn btn-primary" id="confirmPreviewButton" disabled>Confirm & Create Lesson </button>
				</div>
			</div>
		</div>
	</div>
@endsection

@push('scripts')
	<script src="{{ asset('js/create_lesson.js') }}"></script>
@endpush
