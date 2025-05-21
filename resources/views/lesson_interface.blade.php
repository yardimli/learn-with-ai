@extends('layouts.app')

@section('title', 'Question: ' . $lesson->title)

@section('content')
	<input type="hidden" id="lessonId" value="{{ $lesson->id }}">
	<div class="question-card">
		<h3 class="text-center mb-3" id="lessonTitle">{{ $lesson->title }}</h3>
		
		@include('partials.lesson_progress_intro', [])
		
		<div id="questionArea" class="d-none">
			<div class="row">
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
				<div class="col-12 col-md-7">
					<div id="questionAnswersContainer" class="d-grid gap-3 mb-4">
					</div>
				</div>
			</div>
		</div>
		
		<div id="completionMessage" class="d-none mt-4">
			<h3 class="text-success"><i class="fas fa-check-circle me-2"></i>Lesson Complete!</h3>
			<p>Congratulations, you've successfully answered the required questions for this lesson.</p>
			<a href="{{ route('lessons.list') }}" class="btn btn-primary">Choose Another Lesson</a>
		</div>
		
		<div class="auto-play-switch-container mb-3">
			<div class="form-check form-switch">
				<input class="form-check-input" type="checkbox" role="switch" id="autoPlayAudioSwitch" checked>
				<label class="form-check-label small" for="autoPlayAudioSwitch">Auto-play Audio</label>
			</div>
		</div>
	</div>
	
	<div class="modal fade" id="feedbackModal" tabindex="-1" aria-labelledby="feedbackModalLabel" aria-hidden="true"
	     data-bs-backdrop="static" data-bs-keyboard="false">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="feedbackModalLabel">Feedback</h5>
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
		window.lessonId = @json($lesson->id);
		window.questionInitialState = @json($state);
		window.lessonIntro = @json($lessonIntro);
		
		let IntroTextContainer = null;
		let introPlaybackControls = null;
		let startOverIntroButton = null;
		let introSentenceImageContainer = null;
		let introSentenceImage = null;
		let introSentenceImagePlaceholder = null;
		let questionArea = null;
		let questionDifficulty = null;
		let questionTextElement = null;
		let questionImageElement = null;
		let noImagePlaceholder = null;
		let questionAnswersContainer = null;
		let completionMessage = null;
		let feedbackModal = null;
		let feedbackModalInstance = null;
		let feedbackModalLabel = null;
		let feedbackModalText = null;
		let playFeedbackModalButton = null;
		let feedbackAudioError = null;
		let modalTryAgainButton = null;
		let modalNextButton = null;
		
		let lessonId = null;
		let isAutoPlayEnabled = true;
		let difficulties = ['easy', 'medium', 'hard'];
		let currentState = window.questionInitialState || null;
		let currentLessonQuestions = [];
		let currentQuestionIndex = -1;
		let currentQuestion = null;
		let selectedIndex = null;
		let isLoading = false;
		let interactionsDisabled = false;
		let isModalVisible = false;
		
		let playbackQueue = [];
		let currentPlaybackIndex = -1;
		let isAutoPlaying = false;
		let currentHighlightElement = null;
		let ttsAudioPlayer = null;
		let feedbackAudioPlayer = null;
		let autoPlayAudioSwitch = null;
		
		let progressBar = null;
		let IntroArea = null;
		let IntroTitle = null;
		let IntroText = null;
		let startQuestionButton = null; // Renamed to startLessonQuestionButton
		let isIntroVisible = false; // Renamed to isLessonIntroVisible
		
		let loadingOverlay = null;
		let loadingMessageEl = null;
		let errorMessageArea = null;
		let errorMessageText = null;
		let closeErrorButton = null;
		let currentAttemptNumber = 1;
		
		document.addEventListener('DOMContentLoaded', () => {
			loadingOverlay = document.getElementById('loadingOverlay');
			loadingMessageEl = document.getElementById('loadingMessage');
			errorMessageArea = document.getElementById('errorMessageArea');
			errorMessageText = document.getElementById('errorMessageText');
			closeErrorButton = document.getElementById('closeErrorButton');
			
			lessonId = document.getElementById('lessonId').value;
			currentState = window.questionInitialState || null;
			currentLessonQuestions = [];
			currentQuestionIndex = -1;
			currentQuestion = null;
			selectedIndex = null;
			isLoading = false;
			interactionsDisabled = false;
			
			progressBar = document.getElementById('progressBar');
			IntroArea = document.getElementById('IntroArea');
			IntroTitle = document.getElementById('IntroTitle');
			IntroText = document.getElementById('IntroText');
			IntroTextContainer = document.getElementById('IntroTextContainer');
			introSentenceImageContainer = document.getElementById('introSentenceImageContainer');
			introSentenceImage = document.getElementById('introSentenceImage');
			introSentenceImagePlaceholder = document.getElementById('introSentenceImagePlaceholder');
			startQuestionButton = document.getElementById('startQuestionButton'); // Should be startLessonQuestionButton
			ttsAudioPlayer = document.getElementById('ttsAudioPlayer');
			feedbackAudioPlayer = document.getElementById('feedbackAudioPlayer');
			autoPlayAudioSwitch = document.getElementById('autoPlayAudioSwitch');
			
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
			
			introPlaybackControls = document.getElementById('introPlaybackControls');
			startOverIntroButton = document.getElementById('startOverIntroButton');
			
			console.log('Interactive Question JS Loaded');
			
			const savedAutoPlayPref = localStorage.getItem('autoPlayAudioEnabled');
			isAutoPlayEnabled = savedAutoPlayPref !== null ? (savedAutoPlayPref === 'true') : true;
			if (autoPlayAudioSwitch) {
				autoPlayAudioSwitch.checked = isAutoPlayEnabled;
			}
			
			setupAutoPlaySwitchListener();
			setupIntroEventListeners();
			setupAudioEventListeners();
			setupModalEventListeners();
			setupQuestionAnswerEventListeners();
			setupHelperEventListeners();
			setupStartOverIntroButtonListener();
			initQuestionInterface();
		});
	</script>
	<script src="{{ asset('js/lesson_helper_functions.js') }}"></script>
	<script src="{{ asset('js/lesson_audio_functions.js') }}"></script>
	<script src="{{ asset('js/lesson_progress_intro.js') }}"></script>
	<script src="{{ asset('js/lesson_interface.js') }}"></script>
@endpush
