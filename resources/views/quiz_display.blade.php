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
			<div id="leftColumn"
			     class="col-12 col-md-5 text-center text-md-start mb-3 mb-md-0"> {{-- Adjusted column size --}}
				<div id="questionVisualsContainer" class="p-3 border rounded bg-light"> {{-- Add some styling --}}
					<h4 class="mb-3">Question:</h4>
					{{-- Add ID for highlighting --}}
					<p id="questionTextElement" class="fs-5 mt-2">{{ $quiz->question_text ?? 'Loading question...' }}</p>
					{{-- Optional: Add subject image as static visual aid --}}
					@if ($subject->generatedImage?->smallUrl)
						<img src="{{ $subject->generatedImage->smallUrl }}" class="img-fluid rounded mt-3"
						     style="max-height: 150px;" alt="Subject Visual Aid">
					@endif
				</div>
			</div>
			
			<!-- Right Column: Answers & Feedback -->
			<div id="rightColumn" class="col-12 col-md-7"> {{-- Adjusted column size --}}
				<div id="quizArea">
					<!-- Answer Buttons -->
					<h4 class="mb-3">Select your answer:</h4>
					<div id="quizAnswersContainer" class="d-grid gap-3 mb-4">
						@if ($quiz && isset($quiz->answers))
							@foreach ($quiz->answers as $index => $answer)
								{{-- Add ID for highlighting --}}
								<button type="button" id="answerBtn_{{ $index }}" class="btn btn-outline-primary btn-lg answer-btn"
								        data-index="{{ $index }}">
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
							Please select the correct answer to proceed.
						</p>
						<p id="feedbackListenMessage" class="text-muted small mt-2 d-none">
							Listen to the feedback to continue.
						</p>
						<button id="nextQuestionButton" class="btn btn-info w-100 d-none">
							<span id="nextQuestionSpinner" class="spinner-border spinner-border-sm d-none" role="status"
							      aria-hidden="true"></span>
							Next Question
						</button>
					</div>
				</div> <!-- End Quiz Area -->
			</div> <!-- End Right Column -->
		</div> <!-- End Row -->
	</div> <!-- End Quiz Card -->
@endsection

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
			questionAudioUrl: @json($quiz->question_audio_path ?? null),
			answers: <?php
				         echo json_encode(
					         collect($quiz->answers ?? [])->map(function ($answer, $index) use ($quiz) {
						         return [
							         'text' => $answer['text'] ?? '',
							         'answer_audio_url' => $quiz->getAnswerAudioUrl($index) ?? null,
							         'feedback_audio_path' => $answer['feedback_audio_path'] ?? null,
							         'feedback_audio_url' => $answer['feedback_audio_url'] ?? null,
						         ];
					         })->all()
				         )
				         ?>,
			subjectImageUrl: @json($subject->generatedImage?->mediumUrl ?? null) // Keep for potential static image
		};
		window.subjectId = @json($subject->id); // Make subject ID available
	</script>
	<script src="{{ asset('js/quiz.js') }}"></script> {{-- Ensure this is loaded --}}
@endpush
