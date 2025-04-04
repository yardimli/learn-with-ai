@extends('layouts.app')

@section('title', 'Quiz: ' . $subject->title)

@push('styles')
	<style>
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

      #playFeedbackModalButton {
          margin-top: 0.5rem;
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
		<h3 class="text-center mb-3" id="lessonTitle">{{ $subject->title }}</h3>
		
		@include('partials.lesson_progress_intro', ['totalParts' => $totalParts])
		
		<div id="quizArea" class="d-none">
			<div class="row">
				<!-- Left Column: Question Text & Image -->
				<div class="col-12 col-md-5 text-center text-md-start mb-3 mb-md-0">
					<div id="quizQuestionContainer" class="p-3 border rounded question-container position-relative">
						<p id="questionDifficulty" class="text-muted small mb-2"></p>
						<p id="questionTextElement" class="quiz-question-text fs-5 mb-4">Loading question...</p>
						{{-- Question Image Display --}}
						<div class="mb-3 text-center">
							<img id="questionImageElement" src="{{ asset('images/placeholder_q.png') }}"
							     class="img-fluid rounded mb-2 d-none" style="max-height: 300px;" alt="Visual aid for the question">
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
		
		{{-- Auto-Play Audio Switch --}}
		<div class="auto-play-switch-container mb-3">
			<div class="form-check form-switch">
				<input class="form-check-input" type="checkbox" role="switch" id="autoPlayAudioSwitch"
				       checked> {{-- Default to checked --}}
				<label class="form-check-label small" for="autoPlayAudioSwitch">Auto-play Audio</label>
			</div>
		</div>
	
	
	</div> {{-- End Quiz Card --}}
	
	
	<div class="modal fade" id="feedbackModal" tabindex="-1" aria-labelledby="feedbackModalLabel" aria-hidden="true"
	     data-bs-backdrop="static" data-bs-keyboard="false"> {{-- Static backdrop, no keyboard close --}}
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
	{{-- Pass initial data from Controller to JS --}}
	<script>
		window.quizInitialState = @json($state);
		window.totalLessonParts = @json($totalParts);
		window.allPartIntros = @json($allPartIntros);
		
		// --- DOM Element References ---
		let quizArea = null;
		let questionDifficulty = null;
		let questionTextElement = null;
		let questionImageElement = null;
		let noImagePlaceholder = null;
		let quizAnswersContainer = null;
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
		let subjectSessionId = null;
		let subjectId = null;
		let isAutoPlayEnabled = true;
		let displayedPartIndex = -1;
		
		let totalParts = window.totalLessonParts || 0;
		let difficulties = ['easy', 'medium', 'hard'];
		
		let currentState = window.quizInitialState || null; // { partIndex, difficulty, correctCounts, status, requiredCorrect, currentPartIntroText, currentPartVideoUrl }
		let currentPartQuizzes = [];
		let currentQuizIndex = -1;
		let currentQuiz = null;
		
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
		let startPartQuizButton = null;
		
		let hasIntroVideoPlayed = false; // Track if intro video played (per part)
		let isPartIntroVisible = false; // Track if intro or quiz area is shown
		
		let loadingOverlay = null;
		let loadingMessageEl = null;
		let errorMessageArea = null;
		let errorMessageText = null;
		let closeErrorButton = null;
		
		document.addEventListener('DOMContentLoaded', () => {
			loadingOverlay = document.getElementById('loadingOverlay');
			loadingMessageEl = document.getElementById('loadingMessage');
			errorMessageArea = document.getElementById('errorMessageArea');
			errorMessageText = document.getElementById('errorMessageText');
			closeErrorButton = document.getElementById('closeErrorButton');
			
			// --- State Variables ---
			subjectSessionId = document.getElementById('subjectSessionId')?.value;
			subjectId = document.getElementById('subjectId')?.value;
			
			currentState = window.quizInitialState || null; // { partIndex, difficulty, correctCounts, status, requiredCorrect, currentPartIntroText, currentPartVideoUrl }
			currentPartQuizzes = [];
			currentQuizIndex = -1;
			currentQuiz = null;
			
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
			startPartQuizButton = document.getElementById('startPartQuizButton');
			
			ttsAudioPlayer = document.getElementById('ttsAudioPlayer');
			feedbackAudioPlayer = document.getElementById('feedbackAudioPlayer');
			autoPlayAudioSwitch = document.getElementById('autoPlayAudioSwitch');
			
			// --- DOM Element References ---
			quizArea = document.getElementById('quizArea');
			questionDifficulty = document.getElementById('questionDifficulty');
			questionTextElement = document.getElementById('questionTextElement');
			questionImageElement = document.getElementById('questionImageElement');
			noImagePlaceholder = document.getElementById('noImagePlaceholder');
			quizAnswersContainer = document.getElementById('quizAnswersContainer');
			
			feedbackModal = document.getElementById('feedbackModal');
			feedbackModalLabel = document.getElementById('feedbackModalLabel');
			feedbackModalText = document.getElementById('feedbackModalText');
			playFeedbackModalButton = document.getElementById('playFeedbackModalButton');
			feedbackAudioError = document.getElementById('feedbackAudioError');
			modalTryAgainButton = document.getElementById('modalTryAgainButton');
			modalNextButton = document.getElementById('modalNextButton');
			
			completionMessage = document.getElementById('completionMessage');
			
			if (feedbackModal) {
				feedbackModalInstance = new bootstrap.Modal(feedbackModal);
				
				// Add listener to stop audio when modal is hidden
				feedbackModal.addEventListener('hidden.bs.modal', () => {
					isModalVisible = false;
					if (feedbackAudioPlayer && !feedbackAudioPlayer.paused) {
						feedbackAudioPlayer.pause();
						feedbackAudioPlayer.currentTime = 0;
					}
					toggleElement(feedbackAudioError, false); // Hide error on close
					// Re-enable interactions only if not loading something else
					if (!isLoading) {
						setInteractionsDisabled(false);
					}
					updateButtonStates(); // Refresh button states after modal closes
				});
				feedbackModal.addEventListener('shown.bs.modal', () => {
					isModalVisible = true;
					setInteractionsDisabled(true); // Ensure interactions are off while modal is shown
					updateButtonStates(); // Refresh button states after modal opens
				});
			}
			
			console.log('Interactive Quiz JS Loaded');
			
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
			setupQuizAnswerEventListeners();
			setupHelperEventListeners();
			initQuizInterface();
			
		});
		
		function setupAutoPlaySwitchListener() {
			if (autoPlayAudioSwitch) {
				autoPlayAudioSwitch.addEventListener('change', () => {
					isAutoPlayEnabled = autoPlayAudioSwitch.checked;
					localStorage.setItem('autoPlayAudioEnabled', isAutoPlayEnabled);
					console.log('Auto-play audio:', isAutoPlayEnabled ? 'Enabled' : 'Disabled');
					// If user disables it *during* playback, stop it.
					if (!isAutoPlayEnabled && isAutoPlaying) {
						stopPlaybackSequence(true); // Stop and re-enable interactions
					}
				});
			}
		}
		
		
		function setupModalEventListeners() {
			if (modalTryAgainButton) {
				modalTryAgainButton.addEventListener('click', () => {
					console.log('Try Again clicked');
					feedbackModalInstance?.hide();
					selectedIndex = null; // Clear selection
					
					// Reset answer button styles and re-enable them
					quizAnswersContainer?.querySelectorAll('.answer-btn').forEach(button => {
						button.classList.remove('selected', 'correct', 'incorrect', 'btn-success', 'btn-danger', 'btn-outline-secondary');
						button.classList.add('btn-outline-primary');
						button.disabled = false; // Re-enable
					});
					// No state transition, just allow another attempt on the same question.
					// Interactions should be re-enabled by the 'hidden.bs.modal' listener if not loading.
				});
			}
			
			if (modalNextButton) {
				modalNextButton.addEventListener('click', () => {
					console.log('Next Question clicked');
					feedbackModalInstance?.hide();
					// Now trigger the state transition logic
					checkStateAndTransition();
				});
			}
			
			if (playFeedbackModalButton && feedbackAudioPlayer) {
				playFeedbackModalButton.addEventListener('click', () => {
					const audioUrl = playFeedbackModalButton.dataset.audioUrl;
					toggleElement(feedbackAudioError, false); // Hide previous error
					if (audioUrl) {
						if (!feedbackAudioPlayer.paused) {
							feedbackAudioPlayer.pause();
							feedbackAudioPlayer.currentTime = 0;
						} else {
							feedbackAudioPlayer.src = audioUrl;
							feedbackAudioPlayer.play().catch(e => {
								console.error("Feedback audio playback error:", e);
								feedbackAudioError.textContent = 'Audio playback error.';
								toggleElement(feedbackAudioError, true);
							});
						}
					}
				});
				
				// Optional: Update button text/icon during playback
				feedbackAudioPlayer.onplaying = () => {
					playFeedbackModalButton.innerHTML = '<i class="fas fa-pause me-1"></i> Pause Feedback';
				};
				feedbackAudioPlayer.onpause = () => { // Covers ended and manual pause
					playFeedbackModalButton.innerHTML = '<i class="fas fa-volume-up me-1"></i> Play Feedback Audio';
				};
				feedbackAudioPlayer.onerror = () => {
					playFeedbackModalButton.innerHTML = '<i class="fas fa-volume-up me-1"></i> Play Feedback Audio';
					feedbackAudioError.textContent = 'Audio playback error.';
					toggleElement(feedbackAudioError, true);
				}
			}
		}
	
	
	</script>
	<script src="{{ asset('js/quiz_helper_functions.js') }}"></script>
	<script src="{{ asset('js/lesson_audio_functions.js') }}"></script>
	<script src="{{ asset('js/lesson_progress_intro.js') }}"></script>
	<script src="{{ asset('js/quiz_interface.js') }}"></script>
@endpush
