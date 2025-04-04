@extends('layouts.app')

@section('title', 'Quiz: ' . $subject->title)

@push('styles')
	<style>
      /* Progress Bar */
      .progress-container {
          margin-bottom: 1.5rem;
          padding: 0.5rem;
          background-color: var(--bs-tertiary-bg);
          border-radius: 0.375rem; /* Match Bootstrap's default */
      }
      .progress {
          height: 25px; /* Make progress bar thicker */
          font-size: 0.85rem; /* Adjust font size inside */
      }
      .progress-bar {
          transition: width 0.6s ease; /* Smooth transition */
      }
      .part-indicator {
          display: flex;
          justify-content: space-around;
          margin-top: 0.5rem;
          font-size: 0.9em;
      }
      .part-label {
          text-align: center;
          flex: 1; /* Distribute space */
          cursor: default; /* No clicking for now */
          padding: 0.2rem;
          border-radius: 0.25rem;
          transition: background-color 0.3s ease;
      }
      .part-label.active {
          font-weight: bold;
          background-color: rgba(var(--bs-primary-rgb), 0.2); /* Highlight active part */
      }
      .part-label.completed {
          color: var(--bs-secondary); /* Gray out completed */
          text-decoration: line-through;
      }


      /* Part Intro Area */
      #partIntroArea {
          background-color: var(--bs-light);
          border: 1px solid var(--bs-border-color);
          border-radius: 0.375rem;
          padding: 1.5rem;
          margin-bottom: 1.5rem;
          transition: opacity 0.5s ease-in-out;
      }
      .dark-mode #partIntroArea {
          background-color: var(--bs-secondary-bg);
          border-color: var(--bs-border-color);
      }
      #partIntroArea.d-none { /* Ensure smooth fade out */
          opacity: 0;
      }
      #partIntroVideo {
          max-width: 100%;
          max-height: 300px; /* Limit video height */
          border-radius: 0.25rem;
      }

      /* Quiz Area */
      #quizQuestionContainer {
          min-height: 150px; /* Prevent collapsing */
      }

      /* Completion Message */
      #completionMessage {
          text-align: center;
          padding: 2rem;
          background-color: var(--bs-success-bg-subtle);
          border: 1px solid var(--bs-success-border-subtle);
          border-radius: 0.5rem;
      }
      .dark-mode #completionMessage {
          background-color: #143625; /* Darker success */
          border-color: #198754;
      }

      /* Ensure answer buttons fill width */
      #quizAnswersContainer .answer-btn {
          width: 100%;
      }

      /* Refine feedback section */
      .feedback-section {
          border-left: 4px solid var(--bs-info); /* Use info color for feedback border */
          padding-left: 15px;
          margin-top: 15px;
      }
      .dark-mode .feedback-section {
          border-left-color: var(--bs-info-border-subtle);
      }
	
	</style>
@endpush

@section('content')
	{{-- Store initial data for JS --}}
	<input type="hidden" id="subjectSessionId" value="{{ $subject->session_id }}">
	<input type="hidden" id="subjectId" value="{{ $subject->id }}">
	
	{{-- Audio Players --}}
	<audio id="ttsAudioPlayer" style="display: none;" preload="auto"></audio>
	<audio id="feedbackAudioPlayer" style="display: none;" preload="auto"></audio>
	
	<div class="quiz-card"> {{-- Main container --}}
		<h2 class="text-center mb-3" id="lessonTitle">{{ $subject->title }}</h2>
		
		{{-- 1. Progress Bar and Part Indicators --}}
		<div class="progress-container shadow-sm">
			<div class="progress" role="progressbar" aria-label="Lesson Progress" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
				<div class="progress-bar progress-bar-striped progress-bar-animated" id="progressBar" style="width: 0%">0%</div>
			</div>
			<div class="part-indicator" id="partIndicatorContainer">
				@for ($i = 0; $i < $totalParts; $i++)
					<span class="part-label" id="partLabel_{{ $i }}">Part {{ $i + 1 }}</span>
				@endfor
			</div>
		</div>
		
		{{-- 2. Part Introduction Area (Video & Text) - Initially hidden/shown by JS --}}
		<div id="partIntroArea" class="d-none">
			<h4 id="partIntroTitle" class="mb-3"></h4>
			<div class="row align-items-center">
				<div class="col-md-5 text-center mb-3 mb-md-0">
					<video id="partIntroVideo" controls preload="metadata" class="d-none">
						Your browser does not support the video tag.
					</video>
					<p id="partIntroVideoPlaceholder" class="text-muted d-none">(No video for this part)</p>
				</div>
				<div class="col-md-7">
					<p id="partIntroText"></p>
				</div>
			</div>
			<hr>
			<div class="text-center">
				<button id="startPartQuizButton" class="btn btn-primary">Start Part {{-- Number added by JS --}} Quiz</button>
			</div>
		</div>
		
		
		{{-- 3. Quiz Question/Answer Area - Shown when intro is hidden --}}
		<div id="quizArea" class="d-none">
			<div class="row">
				<!-- Left Column: Question Text & Image -->
				<div class="col-12 col-md-5 text-center text-md-start mb-3 mb-md-0">
					<div id="quizQuestionContainer" class="p-3 border rounded question-container position-relative">
						<p id="questionDifficulty" class="text-muted small mb-2"></p>
						<p id="questionTextElement" class="quiz-question-text fs-5 mb-4">Loading question...</p>
						{{-- Question Image Display --}}
						<div class="mb-3 text-center">
							<img id="questionImageElement" src="{{ asset('images/placeholder_q.png') }}" class="img-fluid rounded mb-2 d-none" style="max-height: 300px;" alt="Visual aid for the question">
							<p id="noImagePlaceholder" class="text-muted d-none">(No image for this question)</p>
						</div>
						{{-- Review Button could go here if needed --}}
					</div>
				</div>
				
				<!-- Right Column: Answers & Feedback -->
				<div class="col-12 col-md-7">
					<!-- Answer Buttons -->
					<div id="quizAnswersContainer" class="d-grid gap-3 mb-4">
						{{-- Buttons loaded by JS --}}
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
						<p id="feedbackThresholdMessage" class="text-muted small mt-2 d-none">
							Keep going! Need <span id="remainingCorrectCount"></span> more correct answer(s) for this level.
						</p>
						<p id="feedbackListenMessage" class="text-muted small mt-2 d-none">
							Listen to the feedback.
						</p>
						<button id="nextQuestionButton" class="btn btn-info w-100 d-none">
							<span id="nextQuestionSpinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
							Next Question
						</button>
					</div>
				</div> <!-- End Right Column -->
			</div> <!-- End Row -->
		</div> <!-- End Quiz Area -->
		
		{{-- 4. Completion Message Area --}}
		<div id="completionMessage" class="d-none mt-4">
			<h3 class="text-success"><i class="fas fa-check-circle me-2"></i>Lesson Complete!</h3>
			<p>Congratulations, you've successfully answered the required questions for all parts of this lesson.</p>
			<a href="{{ route('home') }}" class="btn btn-primary">Choose Another Subject</a>
			{{-- Optionally add a link to review the lesson or see stats --}}
		</div>
	
	</div> {{-- End Quiz Card --}}

@endsection

@push('scripts')
	{{-- Pass initial data from Controller to JS --}}
	<script>
		window.quizInitialState = @json($state);
		window.totalLessonParts = @json($totalParts);
	</script>
	<script src="{{ asset('js/quiz_interface.js') }}"></script> {{-- Load the new JS file --}}
@endpush
