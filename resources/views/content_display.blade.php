@extends('layouts.app')

@section('title', $subject->title ?? 'View Content')

@section('content')
	<div class="content-card">
		<h1 id="contentTitle" class="text-center mb-4">{{ $subject->title ?? 'Content' }}</h1>
		
		<div class="row mb-4">
			<!-- Media Column -->
			<div class="col-12 text-center mb-3 mb-md-0" style="min-height: 250px;"> {{-- Add min-height to prevent layout jump --}}
				
				{{-- Video Container (Initially visible if video exists) --}}
				@if ($subject->initial_video_url)
					<div id="initialVideoWrapper" class="{{-- Visibility controlled by JS --}}">
						<video controls width="100%" id="initialVideoPlayer" class="rounded" src="{{ $subject->initial_video_url }}" preload="metadata"> {{-- Preload metadata --}}
							Your browser does not support the video tag. Try refreshing.
						</video>
					</div>
				@endif
				
				{{-- Image Container (Initially hidden if video exists, shown otherwise or after video ends) --}}
				@if ($subject->generatedImage && $subject->generatedImage->mediumUrl)
					<div id="initialImageContainer" class="image-container mb-3 {{ ($subject->initial_video_url) ? 'd-none' : '' }} {{-- Hide if video exists --}}">
						<img id="initialImage" src="{{ $subject->generatedImage->mediumUrl }}" class="img-fluid rounded" style="max-height: 350px;" alt="{{ $subject->generatedImage->image_alt ?? 'AI Generated Image (Click to play video)' }}">
						<div class="play-icon-overlay {{-- Toggled by JS --}}">
							<i class="fas fa-play"></i>
						</div>
					</div>
				@elseif (!$subject->initial_video_url) {{-- Placeholder if NO video AND NO image --}}
				<div class="video-placeholder">
					<p>No visual content available yet.</p>
					<small>(Image or video might still be generating, try refreshing)</small>
				</div>
				@endif
			</div>
			
			<!-- Text Column -->
			<div class="col-12">
				<p id="mainTextDisplay" class="lead">{{ $subject->main_text ?? 'No text generated.' }}</p>
			</div>
		</div>
		
		<hr>
		
		{{-- Button/Form to start the quiz --}}
		<div class="text-center">
			<form action="{{ route('quiz.start', $subject->session_id) }}" method="POST">
				@csrf
				<button type="submit" id="startQuizButton" class="btn btn-success btn-lg" {{-- Disabled state controlled by JS --}}>
					<span id="startQuizSpinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
					<span id="startQuizButtonText">Start Quiz</span>
				</button>
				<p id="startQuizMessage" class="text-muted mt-2 d-none">Generating the first question...</p>
			</form>
		</div>
	</div>
@endsection

@push('scripts')
	<script src="{{ asset('js/content_display.js') }}"></script>
@endpush
