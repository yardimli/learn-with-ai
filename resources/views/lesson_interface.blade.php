@extends('layouts.app')

@section('title', 'Question: ' . $lesson->title)

@push('styles')
	<style>
      /* Question Area */
      #questionQuestionContainer {
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

      #playFeedbackModalButton {
          margin-top: 0.5rem;
      }

      .dark-mode #completionMessage {
          background-color: #143625; /* Darker success */
          border-color: #198754;
      }

      /* Ensure answer buttons fill width */
      #questionAnswersContainer .answer-btn {
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
	<input type="hidden" id="lessonSessionId" value="{{ $lesson->session_id }}">
	<input type="hidden" id="lessonId" value="{{ $lesson->id }}">
	
	<audio id="ttsAudioPlayer" style="display: none;" preload="auto"></audio>
	<audio id="feedbackAudioPlayer" style="display: none;" preload="auto"></audio>
	
	<div class="question-card">
		<h3 class="text-center mb-3" id="lessonTitle">{{ $lesson->title }}</h3>
		
		@include('partials.lesson_progress_intro', ['totalParts' => $totalParts])
		
		<div id="questionArea" class="d-none">
			<div class="row">
				<!-- Left Column: Question Text & Image -->
				<div class="col-12 col-md-5 text-center text-md-start mb-3 mb-md-0">
					<div id="questionQuestionContainer" class="p-3 border rounded question-container position-relative">
						<p id="questionDifficulty" class="text-muted small mb-2"></p>
						<p id="questionTextElement" class="question-question-text fs-5 mb-4">Loading question...</p>
						<div class="mb-3 text-center">
							<img id="questionImageElement" src="{{ asset('images/placeholder_q.png') }}"
							     class="img-fluid rounded mb-2 d-none" style="max-height: 300px;" alt="Visual aid for the question">
							<p id="noImagePlaceholder" class="text-muted d-none">(No image for this question)</p>
						</div>
					</div>
				</div>
				
				<!-- Right Column: Answers & Feedback -->
				<div class="col-12 col-md-7">
					<!-- Answer Buttons -->
					<div id="questionAnswersContainer" class="d-grid gap-3 mb-4">
					</div>
				</div> <!-- End Right Column -->
			</div> <!-- End Row -->
		</div> <!-- End Question Area -->
		
		<div id="completionMessage" class="d-none mt-4">
			<h3 class="text-success"><i class="fas fa-check-circle me-2"></i>Lesson Complete!</h3>
			<p>Congratulations, you've successfully answered the required questions for all parts of this lesson.</p>
			<a href="{{ route('home') }}" class="btn btn-primary">Choose Another Lesson</a>
		</div>
		
		<div id="partCompletionMessage" class="d-none mt-4">
		</div>
		
		<div class="auto-play-switch-container mb-3">
			<div class="form-check form-switch">
				<input class="form-check-input" type="checkbox" role="switch" id="autoPlayAudioSwitch"
				       checked>
				<label class="form-check-label small" for="autoPlayAudioSwitch">Auto-play Audio</label>
			</div>
		</div>
	
	
	</div> {{-- End Question Card --}}
	
	
	<div class="modal fade" id="feedbackModal" tabindex="-1" aria-labelledby="feedbackModalLabel" aria-hidden="true"
	     data-bs-backdrop="static" data-bs-keyboard="false">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="feedbackModalLabel">Feedback</h5>
					{{-- No close button on header, force using footer buttons --}}
					{{-- <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button> --}}
				</div>
				<div class="modal-body">
					<p id="feedbackModalText">Your feedback text here.</p>
					<button id="playFeedbackModalButton" class="btn btn-sm btn-secondary d-none">
						<i class="fas fa-volume-up me-1"></i> Play Feedback Audio
					</button>
					<span id="feedbackAudioError" class="text-danger small ms-2 d-none">Audio error</span>
				</div>
				<div class="modal-footer">
					<button type="button" id="modalTryAgainButton" class="btn btn-warning d-none">Try Again</button>
					<button type="button" id="modalNextButton" class="btn btn-primary d-none">Next Question</button>
				</div>
			</div>
		</div>
	</div>

@endsection

@push('scripts')
	<script>
		window.questionInitialState = @json($state);
		window.totalLessonParts = @json($totalParts);
		window.allPartIntros = @json($allPartIntros);
		
		// --- DOM Element References ---
		let questionArea = null;
		let questionDifficulty = null;
		let questionTextElement = null;
		let questionImageElement = null;
		let noImagePlaceholder = null;
		let questionAnswersContainer = null;
		let completionMessage = null;
		
		let feedbackModal = null;
		let feedbackModalInstance = null; // To store the Bootstrap modal object
		let feedbackModalLabel = null;
		let feedbackModalText = null;
		let playFeedbackModalButton = null;
		let feedbackAudioError = null;
		let modalTryAgainButton = null;
		let modalNextButton = null;
		
		// --- State Variables ---
		let lessonSessionId = null;
		let lessonId = null;
		let isAutoPlayEnabled = true;
		let displayedPartIndex = -1;
		
		let totalParts = window.totalLessonParts || 0;
		let difficulties = ['easy', 'medium', 'hard'];
		
		let currentState = window.questionInitialState || null; // { partIndex, difficulty, correctCounts, status, requiredCorrect, currentPartIntroText, currentPartVideoUrl }
		let currentPartQuestions = [];
		let currentQuestionIndex = -1;
		let currentQuestion = null;
		
		let selectedIndex = null;
		let isLoading = false;
		let interactionsDisabled = false;
		
		let isModalVisible = false;
		
		// TTS Playback State
		let playbackQueue = [];
		let currentPlaybackIndex = -1;
		let isAutoPlaying = false;
		let currentHighlightElement = null;
		let ttsAudioPlayer = null;
		let feedbackAudioPlayer = null;
		let autoPlayAudioSwitch = null;
		
		// Progress and Intro
		let progressBar = null;
		let partIndicatorContainer = null;
		let partIntroArea = null;
		let partIntroTitle = null;
		let partIntroVideo = null;
		let partIntroVideoPlaceholder = null;
		let partIntroText = null;
		let startPartQuestionButton = null;
		
		let hasIntroVideoPlayed = false; // Track if intro video played (per part)
		let isPartIntroVisible = false; // Track if intro or question area is shown
		
		let loadingOverlay = null;
		let loadingMessageEl = null;
		let errorMessageArea = null;
		let errorMessageText = null;
		let closeErrorButton = null;
		
		let currentAttemptNumber = 1; // Track current attempt number
		let partCompletionMessage = null; // Reference to part completion element
		
		
		document.addEventListener('DOMContentLoaded', () => {
			loadingOverlay = document.getElementById('loadingOverlay');
			loadingMessageEl = document.getElementById('loadingMessage');
			errorMessageArea = document.getElementById('errorMessageArea');
			errorMessageText = document.getElementById('errorMessageText');
			closeErrorButton = document.getElementById('closeErrorButton');
			
			// --- State Variables ---
			lessonSessionId = document.getElementById('lessonSessionId').value;
			lessonId = document.getElementById('lessonId').value;
			
			currentState = window.questionInitialState || null; // { partIndex, difficulty, correctCounts, status, requiredCorrect, currentPartIntroText, currentPartVideoUrl }
			currentPartQuestions = [];
			currentQuestionIndex = -1;
			currentQuestion = null;
			
			selectedIndex = null;
			isLoading = false;
			interactionsDisabled = false;
			
			progressBar = document.getElementById('progressBar');
			partIndicatorContainer = document.getElementById('partIndicatorContainer');
			partIntroArea = document.getElementById('partIntroArea');
			partIntroTitle = document.getElementById('partIntroTitle');
			partIntroVideo = document.getElementById('partIntroVideo');
			partIntroVideoPlaceholder = document.getElementById('partIntroVideoPlaceholder');
			partIntroText = document.getElementById('partIntroText');
			startPartQuestionButton = document.getElementById('startPartQuestionButton');
			
			ttsAudioPlayer = document.getElementById('ttsAudioPlayer');
			feedbackAudioPlayer = document.getElementById('feedbackAudioPlayer');
			autoPlayAudioSwitch = document.getElementById('autoPlayAudioSwitch');
			
			// --- DOM Element References ---
			questionArea = document.getElementById('questionArea');
			questionDifficulty = document.getElementById('questionDifficulty');
			questionTextElement = document.getElementById('questionTextElement');
			questionImageElement = document.getElementById('questionImageElement');
			noImagePlaceholder = document.getElementById('noImagePlaceholder');
			questionAnswersContainer = document.getElementById('questionAnswersContainer');
			
			feedbackModal = document.getElementById('feedbackModal');
			feedbackModalLabel = document.getElementById('feedbackModalLabel');
			feedbackModalText = document.getElementById('feedbackModalText');
			playFeedbackModalButton = document.getElementById('playFeedbackModalButton');
			feedbackAudioError = document.getElementById('feedbackAudioError');
			modalTryAgainButton = document.getElementById('modalTryAgainButton');
			modalNextButton = document.getElementById('modalNextButton');
			
			completionMessage = document.getElementById('completionMessage');
			partCompletionMessage = document.getElementById('partCompletionMessage');
			
			
			console.log('Interactive Question JS Loaded');
			
			// --- Load Auto-Play Preference ---
			const savedAutoPlayPref = localStorage.getItem('autoPlayAudioEnabled');
			isAutoPlayEnabled = savedAutoPlayPref !== null ? (savedAutoPlayPref === 'true') : true; // Default true if not set
			if (autoPlayAudioSwitch) {
				autoPlayAudioSwitch.checked = isAutoPlayEnabled;
			}
			
			setupAutoPlaySwitchListener();
			setupIntroEventListeners();
			setupAudioEventListeners();
			setupModalEventListeners();
			setupQuestionAnswerEventListeners();
			setupHelperEventListeners();
			initQuestionInterface();
			
		});
		
	
	
	</script>
	<script src="{{ asset('js/lesson_helper_functions.js') }}"></script>
	<script src="{{ asset('js/lesson_audio_functions.js') }}"></script>
	<script src="{{ asset('js/lesson_progress_intro.js') }}"></script>
	<script src="{{ asset('js/lesson_interface.js') }}"></script>
@endpush
