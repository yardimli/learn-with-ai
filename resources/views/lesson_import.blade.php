@extends('layouts.app')

@section('title', 'Import Lessons from JSON - Learn with AI')

@section('content')
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h1 class="mb-0">Import Lessons from JSON</h1>
		<a href="{{ route('home') }}" class="btn btn-outline-secondary">
			<i class="fas fa-plus"></i> Create Single Lesson
		</a>
	</div>
	
	@if (session('success'))
		<div class="alert alert-success alert-dismissible fade show" role="alert">
			{{ session('success') }}
			<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
		</div>
	@endif
	@if (session('error'))
		<div class="alert alert-danger alert-dismissible fade show" role="alert">
			{{ session('error') }}
			<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
		</div>
	@endif
	@if ($errors->any())
		<div class="alert alert-danger alert-dismissible fade show" role="alert">
			<strong>Please fix the following errors:</strong>
			<ul>
				@foreach ($errors->all() as $error)
					<li>{{ $error }}</li>
				@endforeach
			</ul>
			<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
		</div>
	@endif
	
	
	<div class="content-card shadow-sm">
		<form action="{{ route('lesson.import.process') }}" method="POST">
			@csrf
			
			<p class="mb-4">Select the default settings that will apply to all imported lessons. Paste your JSON data containing an array of lesson objects below.</p>
			
			<div class="row mb-3">
				{{-- Main Category Selection --}}
				<div class="col-md-6 mb-3">
					<label for="mainCategorySelect" class="form-label">Main Category (Required):</label>
					<select class="form-select" id="mainCategorySelect" name="main_category_id" required>
						<option value="" selected disabled>Select a main category</option>
						@foreach ($mainCategories as $mainCategory)
							<option value="{{ $mainCategory->id }}" {{ old('main_category_id') == $mainCategory->id ? 'selected' : '' }}>
								{{ $mainCategory->name }}
							</option>
						@endforeach
					</select>
					<small class="form-text text-muted">This main category will be assigned to all imported lessons.</small>
				</div>
				
				{{-- Lesson Language --}}
				<div class="col-md-6 mb-3">
					<label for="languageSelect" class="form-label">Lesson Language (Required):</label>
					<select class="form-select" id="languageSelect" name="language" required>
						<option value="English" {{ old('language', 'English') == 'English' ? 'selected' : '' }}>English</option>
						<option value="Türkçe" {{ old('language') == 'Türkçe' ? 'selected' : '' }}>Türkçe</option>
						<option value="Deutsch" {{ old('language') == 'Deutsch' ? 'selected' : '' }}>Deutsch</option>
						<option value="Français" {{ old('language') == 'Français' ? 'selected' : '' }}>Français</option>
						<option value="Español" {{ old('language') == 'Español' ? 'selected' : '' }}>Español</option>
						<option value="繁體中文" {{ old('language') == '繁體中文' ? 'selected' : '' }}>繁體中文</option>
					</select>
					<small class="form-text text-muted">Primary language for the lesson content.</small>
				</div>
			</div>
			
			<div class="row mb-3">
				{{-- LLM Selection --}}
				<div class="col-md-6 mb-3">
					<label for="preferredLlmSelect" class="form-label">Preferred AI Model (Required):</label>
					<select class="form-select" id="preferredLlmSelect" name="preferred_llm" required>
						@php $defaultLlmId = old('preferred_llm', env('DEFAULT_LLM', '')); @endphp
						@forelse ($llms ?? [] as $llm)
							<option value="{{ $llm['id'] }}" {{ $llm['id'] === $defaultLlmId ? 'selected' : '' }}>
								{{ $llm['name'] }}
							</option>
						@empty
							<option value="" disabled>No AI models available</option>
						@endforelse
					</select>
					<small class="form-text text-muted">Model used for generating content later.</small>
				</div>
			</div>
			
			
			<div class="row mb-3">
				{{-- TTS Engine Selection --}}
				<div class="col-md-4 mb-3">
					<label for="ttsEngineSelect" class="form-label">Text-to-Speech Engine (Required):</label>
					<select class="form-select" id="ttsEngineSelect" name="tts_engine" required>
						<option value="openai" {{ old('tts_engine', env('DEFAULT_TTS_ENGINE', 'google')) === 'openai' ? 'selected' : '' }}>OpenAI TTS</option>
						<option value="google" {{ old('tts_engine', env('DEFAULT_TTS_ENGINE', 'google')) === 'google' ? 'selected' : '' }}>Google Cloud TTS</option>
					</select>
				</div>
				
				{{-- TTS Voice Selection (Static for now) --}}
				<div class="col-md-4 mb-3">
					<label for="ttsVoiceSelect" class="form-label">Text-to-Speech Voice (Required):</label>
					<select class="form-select" id="ttsVoiceSelect" name="tts_voice" required>
						{{-- Keep both groups visible, user selects based on engine choice --}}
						{{-- Consider adding JS later to hide/show based on engine --}}
						<optgroup label="OpenAI Voices">
							<option value="alloy" {{ old('tts_voice', 'alloy') == 'alloy' ? 'selected' : '' }}>Alloy (Neutral)</option>
							<option value="echo" {{ old('tts_voice') == 'echo' ? 'selected' : '' }}>Echo (Male)</option>
							<option value="fable" {{ old('tts_voice') == 'fable' ? 'selected' : '' }}>Fable (Male)</option>
							<option value="onyx" {{ old('tts_voice') == 'onyx' ? 'selected' : '' }}>Onyx (Male)</option>
							<option value="nova" {{ old('tts_voice') == 'nova' ? 'selected' : '' }}>Nova (Female)</option>
							<option value="shimmer" {{ old('tts_voice') == 'shimmer' ? 'selected' : '' }}>Shimmer (Female)</option>
						</optgroup>
						<optgroup label="Google Voices">
							<option value="en-US-Studio-O" {{ old('tts_voice') == 'en-US-Studio-O' ? 'selected' : '' }}>en-US-Studio-O (Female)</option>
							<option value="en-US-Studio-Q" {{ old('tts_voice') == 'en-US-Studio-Q' ? 'selected' : '' }}>en-US-Studio-Q (Male)</option>
							<option value="tr-TR-Wavenet-A" {{ old('tts_voice') == 'tr-TR-Wavenet-A' ? 'selected' : '' }}>tr-TR-Wavenet-A (Female)</option>
							<option value="tr-TR-Wavenet-B" {{ old('tts_voice') == 'tr-TR-Wavenet-B' ? 'selected' : '' }}>tr-TR-Wavenet-B (Male)</option>
							<option value="tr-TR-Standard-A" {{ old('tts_voice') == 'tr-TR-Standard-A' ? 'selected' : '' }}>tr-TR-Standard-A (Female)</option>
							<option value="tr-TR-Standard-B" {{ old('tts_voice') == 'tr-TR-Standard-B' ? 'selected' : '' }}>tr-TR-Standard-B</option>
							<option value="cmn-CN-Wavenet-A" {{ old('tts_voice') == 'cmn-CN-Wavenet-A' ? 'selected' : '' }}>cmn-CN-Wavenet-A (Female)</option>
							<option value="cmn-CN-Wavenet-B" {{ old('tts_voice') == 'cmn-CN-Wavenet-B' ? 'selected' : '' }}>cmn-CN-Wavenet-B (Male)</option>
							<option value="cmn-TW-Standard-A" {{ old('tts_voice') == 'cmn-TW-Standard-A' ? 'selected' : '' }}>cmn-TW-Standard-A (Female)</option>
							<option value="cmn-TW-Standard-B" {{ old('tts_voice') == 'cmn-TW-Standard-B' ? 'selected' : '' }}>cmn-TW-Standard-B (Male)</option>
						</optgroup>
					</select>
				</div>
				
				{{-- TTS Language Code Selection --}}
				<div class="col-md-4 mb-3">
					<label for="ttsLanguageCodeSelect" class="form-label">Speech Language Code (Required):</label>
					<select class="form-select" id="ttsLanguageCodeSelect" name="tts_language_code" required>
						<option value="en-US" {{ old('tts_language_code', 'en-US') == 'en-US' ? 'selected' : '' }}>English (United States)</option>
						<option value="tr-TR" {{ old('tts_language_code') == 'tr-TR' ? 'selected' : '' }}>Turkish (Turkey)</option>
						<option value="cmn-TW" {{ old('tts_language_code') == 'cmn-TW' ? 'selected' : '' }}>Chinese (Mandarin, Taiwan)</option>
						<option value="cmn-CN" {{ old('tts_language_code') == 'cmn-CN' ? 'selected' : '' }}>Chinese (Mandarin, China)</option>
						<option value="fr-FR" {{ old('tts_language_code') == 'fr-FR' ? 'selected' : '' }}>French (France)</option>
						<option value="de-DE" {{ old('tts_language_code') == 'de-DE' ? 'selected' : '' }}>German (Germany)</option>
						<option value="es-ES" {{ old('tts_language_code') == 'es-ES' ? 'selected' : '' }}>Spanish (Spain)</option>
						<option value="it-IT" {{ old('tts_language_code') == 'it-IT' ? 'selected' : '' }}>Italian (Italy)</option>
						<option value="ja-JP" {{ old('tts_language_code') == 'ja-JP' ? 'selected' : '' }}>Japanese (Japan)</option>
						<option value="ko-KR" {{ old('tts_language_code') == 'ko-KR' ? 'selected' : '' }}>Korean (South Korea)</option>
						<option value="zh-CN" {{ old('tts_language_code') == 'zh-CN' ? 'selected' : '' }}>Chinese (Mandarin, Simplified)</option>
						<option value="zh-TW" {{ old('tts_language_code') == 'zh-TW' ? 'selected' : '' }}>Chinese (Mandarin, Traditional)</option>
					</select>
					<small class="form-text text-muted">Primarily used by Google TTS.</small>
				</div>
			</div>
			
			
			{{-- JSON Input --}}
			<div class="mb-3">
				<label for="lessonsJsonInput" class="form-label fs-5">Lessons JSON Data (Required):</label>
				<textarea class="form-control font-monospace" id="lessonsJsonInput" name="lessons_json" rows="15" placeholder='[
  {
    "title": "Lesson Title 1",
    "description": "Subject or description for lesson 1.",
    "period": "Optional info like date or context"
  },
  {
    "title": "Lesson Title 2",
    "description": "Subject or description for lesson 2."
  }
]' required>{{ old('lessons_json') }}</textarea>
				<small class="form-text text-muted">Paste an array of lesson objects. Each object must have a "title" (string) and "description" (string). "period" (string) is optional.</small>
			</div>
			
			
			<div class="d-grid">
				<button type="submit" class="btn btn-primary btn-lg">
					<i class="fas fa-file-import"></i> Import Lessons
				</button>
			</div>
		</form>
	</div>
@endsection

{{-- @push('scripts')
    // Optional: Add JS here if needed for dynamic voice selection based on engine
@endpush --}}
