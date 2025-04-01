@extends('layouts.app')

@section('title', 'Quiz: ' . ($subject->title ?? ''))

@section('content')
	{{-- Hidden input to store current Subject ID --}}
	<input type="hidden" id="subjectId" value="{{ $subject->id }}">
	{{-- Hidden input to store current Quiz ID --}}
	<input type="hidden" id="currentQuizId" value="{{ $quiz->id ?? '' }}">
	
	{{-- Single Audio Player for TTS --}}
	<audio id="ttsAudioPlayer" style="display: none;" preload="auto"></audio> {{-- Preload auto --}}
	
	<div class="quiz-card"> {{-- Use consistent card class --}}
		<h2 class="text-center mb-3">Quiz Time!</h2>
		{{-- Add Subject Name for Context --}}
		<p class="text-center text-muted mb-4">Subject: {{ $subject->name }}</p>
		
		<div class="row">
			<!-- Left Column: Question Text -->
			<div id="leftColumn" class="col-12 col-md-5 text-center text-md-start mb-3 mb-md-0"> {{-- Adjusted column size --}}
				<div id="questionVisualsContainer" class="p-3 border rounded bg-light position-relative"> {{-- Add position-relative for button positioning --}}
					<h4 class="mb-3">Question:</h4>
					{{-- Add ID for highlighting --}}
					<p id="questionTextElement" class="fs-5 mt-2">{{ $quiz->question_text ?? 'Loading question...' }}</p>
					
					{{-- Optional: Add subject image as static visual aid --}}
					@if ($subject->generatedImage?->smallUrl)
						<img src="{{ $subject->generatedImage->smallUrl }}" class="img-fluid rounded mt-3 mb-3" style="max-height: 150px;" alt="Subject Visual Aid"> {{-- Add mb-3 --}}
					@endif
					
					{{-- Add Review Content Button --}}
					@if($subject->main_text || $subject->initial_video_url)
						<button type="button" class="btn btn-sm btn-outline-secondary mt-2" data-bs-toggle="modal" data-bs-target="#reviewModal">
							<i class="fas fa-book-open me-1"></i> Review Content
						</button>
					@endif
				</div>
			</div> <!-- End Left Column -->
			
			<!-- Right Column: Answers & Feedback -->
			<div id="rightColumn" class="col-12 col-md-7"> {{-- Adjusted column size --}}
				<div id="quizArea">
					<!-- Answer Buttons -->
					<h4 class="mb-3">Select your answer:</h4>
					<div id="quizAnswersContainer" class="d-grid gap-3 mb-4">
						@if ($quiz && isset($quiz->answers))
							@foreach ($quiz->answers as $index => $answer)
								{{-- Add ID for highlighting --}}
								<button type="button" id="answerBtn_{{ $index }}" class="btn btn-outline-primary btn-lg answer-btn" data-index="{{ $index }}">
									{{ $answer['text'] }}
								</button>
							@endforeach
						@else
							<p>Loading answers...</p>
						@endif
					</div>
					
					<!-- Feedback Section -->
					<div id="feedbackSection" class="mt-4 feedback-section d-none">
						<h4 id="feedbackHeading" class=""></h4>
						<p id="feedbackText"></p>
						<button id="playFeedbackButton" class="btn btn-sm btn-secondary d-none">
							<i class="fas fa-volume-up me-1"></i> Play Feedback
						</button>
						<hr>
						{{-- Messages for Next button state --}}
						<p id="feedbackIncorrectMessage" class="text-muted small mt-2 d-none">
							Please select the correct answer to proceed, or try another answer.
						</p>
						<p id="feedbackListenMessage" class="text-muted small mt-2 d-none">
							Listen to the feedback to continue.
						</p>
						<button id="nextQuestionButton" class="btn btn-info w-100 d-none">
							<span id="nextQuestionSpinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
							Next Question
						</button>
					</div>
				
				</div> <!-- End Quiz Area -->
			</div> <!-- End Right Column -->
		</div> <!-- End Row -->
	</div> <!-- End Quiz Card -->

@endsection

{{-- Add Review Content Modal --}}
@if($subject->main_text || $subject->initial_video_url)
	<div class="modal fade" id="reviewModal" tabindex="-1" aria-labelledby="reviewModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"> {{-- Larger, centered, scrollable --}}
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="reviewModalLabel">Review: {{ $subject->title ?? $subject->name }}</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<div class="row">
						<!-- Video Column -->
						<div class="col-md-6 mb-3 mb-md-0">
							@if ($subject->initial_video_url)
								<h5>Intro Video</h5>
								<video controls width="100%" class="rounded review-video" src="{{ $subject->initial_video_url }}" preload="metadata">
									Your browser does not support the video tag.
								</video>
							@else
								<p class="text-muted">No intro video available.</p>
							@endif
						</div>
						<!-- Text Column -->
						<div class="col-md-6">
							<h5>Introduction</h5>
							<p>{{ $subject->main_text ?? 'No introductory text available.' }}</p>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
				</div>
			</div>
		</div>
	</div>
@endif


@push('styles')
	{{-- Add any quiz-specific styles here --}}
@endpush

@push('scripts')
	{{-- Pass initial quiz data to JS --}}
	<script>
		// Make initial data available to quiz.js
		window.initialQuizData = {
			quizId: @json($quiz->id ?? null),
			questionText: @json($quiz->question_text ?? ''),
			questionAudioUrl: @json($quiz->question_audio_path ?? null), // Using path which should resolve to URL via storage link
			answers: <?php
				         // Ensure answers is an array, even if null from DB initially
				         $answersData = $quiz->answers ?? [];
				         if (!is_array($answersData)) $answersData = [];

				         echo json_encode(
					         collect($answersData)->map(function ($answer, $index) use ($quiz) {
						         // Default values if answer structure is missing elements
						         $text = $answer['text'] ?? 'Missing text';
						         // Use the model's accessor method to get the URL
						         $answerAudioUrl = $quiz->getAnswerAudioUrl($index);
						         // Also get feedback url using model accessor if needed elsewhere,
						         // but processAnswersWithTTS adds it directly now.
						         $feedbackAudioUrl = $answer['feedback_audio_url'] ?? null;

						         return [
							         'text' => $text,
							         'answer_audio_url' => $answerAudioUrl, // Use accessor result
							         // 'feedback_audio_path' => $answer['feedback_audio_path'] ?? null, // Keep path if needed
							         'feedback_audio_url' => $feedbackAudioUrl, // Send pre-generated URL
						         ];
					         })->all()
				         );
			         ?>,
			subjectImageUrl: @json($subject->generatedImage?->mediumUrl ?? null) // Keep for potential static image
		};
		window.subjectId = @json($subject->id); // Make subject ID available
	</script>
	<script src="{{ asset('js/quiz.js') }}"></script> {{-- Ensure this is loaded --}}
@endpush
