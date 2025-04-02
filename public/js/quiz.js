// JS for the Quiz Display Page
document.addEventListener('DOMContentLoaded', () => {
	console.log('Quiz JS Loaded - TTS Version');
	
	// --- State Variables ---
	let currentQuizId = window.initialQuizData?.quizId || null;
	const subjectSessionId = window.subjectSessionId || null;
	let selectedIndex = null;
	let answered = null; // null, 'correct', 'incorrect', 'error'
	let correctIndex = null;
	let feedbackText = '';
	let feedbackAudioUrl = null; // For the feedback after answering
	let interactionsDisabled = false; // For loading, video/audio playback
	let isLoading = false;
	let showNextButton = false;
	
	// --- TTS Playback State ---
	let playbackQueue = [];
	let currentPlaybackIndex = -1;
	let isAutoPlaying = false;
	let currentHighlightElement = null;
	
	// --- DOM Element References ---
	const loadingOverlay = document.getElementById('loadingOverlay');
	const loadingMessageEl = document.getElementById('loadingMessage');
	const errorMessageArea = document.getElementById('errorMessageArea');
	const errorMessageText = document.getElementById('errorMessageText');
	const closeErrorButton = document.getElementById('closeErrorButton');
	
	// Visuals & Question
	const questionImageElement = document.getElementById('questionImageElement');
	const noImagePlaceholder = document.getElementById('noImagePlaceholder');
	const questionTextElement = document.getElementById('questionTextElement');
	
	// Answers & Feedback
	const quizAnswersContainer = document.getElementById('quizAnswersContainer');
	const feedbackSection = document.getElementById('feedbackSection');
	const feedbackHeading = document.getElementById('feedbackHeading');
	const feedbackTextEl = document.getElementById('feedbackText');
	const playFeedbackButton = document.getElementById('playFeedbackButton');
	const nextQuestionButton = document.getElementById('nextQuestionButton');
	const nextQuestionSpinner = document.getElementById('nextQuestionSpinner');
	const feedbackIncorrectMessage = document.getElementById('feedbackIncorrectMessage');
	const feedbackListenMessage = document.getElementById('feedbackListenMessage');
	
	// Audio Players
	const ttsAudioPlayer = document.getElementById('ttsAudioPlayer');
	const feedbackAudioPlayer = document.getElementById('feedbackAudioPlayer');
	const reviewModal = document.getElementById('reviewModal');
	
	// --- Helper Functions ---
	function setLoadingState(loading, message = '') {
		isLoading = loading;
		setInteractionsDisabled(loading);
		if (loadingOverlay && loadingMessageEl) {
			loadingMessageEl.textContent = message;
			loadingOverlay.classList.toggle('d-none', !loading);
		}
	}
	
	function setErrorState(message) {
		if (errorMessageArea && errorMessageText) {
			errorMessageText.textContent = message || '';
			errorMessageArea.classList.toggle('d-none', !message);
		}
	}
	
	function setInteractionsDisabled(disabled) {
		// Disable if explicitly asked, OR if loading, OR if auto-playing sequence
		interactionsDisabled = disabled || isLoading || isAutoPlaying;
		console.log(`Interactions Disabled: ${interactionsDisabled} (Requested: ${disabled}, Loading: ${isLoading}, AutoPlaying: ${isAutoPlaying})`);
		updateQuizUI();
	}
	
	function toggleElement(element, show) {
		if (!element) return;
		element.classList.toggle('d-none', !show);
	}
	
	function highlightElement(element, shouldHighlight) {
		if (!element) return;
		element.classList.toggle('reading-highlight', shouldHighlight); // Use CSS class
		if (shouldHighlight) {
			currentHighlightElement = element; // Track highlighted element
		} else if (currentHighlightElement === element) {
			currentHighlightElement = null;
		}
	}
	
	function removeHighlight() {
		if (currentHighlightElement) {
			highlightElement(currentHighlightElement, false);
		}
		document.querySelectorAll('.reading-highlight').forEach(el => el.classList.remove('reading-highlight'));
		currentHighlightElement = null;
	}
	
	// --- TTS Playback Functions ---
	function buildPlaybackQueue(questionAudioUrl, answersData) {
		playbackQueue = [];
		currentPlaybackIndex = -1;
		// 1. Add Question
		if (questionAudioUrl && questionTextElement) {
			playbackQueue.push({element: questionTextElement, url: questionAudioUrl});
		}
		// 2. Add Answers
		answersData.forEach((answer, index) => {
			const answerButton = document.getElementById(`answerBtn_${index}`);
			if (answer.answer_audio_url && answerButton) {
				playbackQueue.push({element: answerButton, url: answer.answer_audio_url});
			}
		});
		console.log("Playback queue built:", playbackQueue);
	}
	
	function startPlaybackSequence() {
		if (playbackQueue.length === 0) {
			console.log("Playback queue empty, enabling interactions.");
			setInteractionsDisabled(false);
			return;
		}
		stopPlaybackSequence();
		console.log("Starting playback sequence...");
		isAutoPlaying = true;
		currentPlaybackIndex = 0;
		setInteractionsDisabled(true);
		playNextInSequence();
	}
	
	function stopPlaybackSequence() {
		console.log("Stopping playback sequence.");
		isAutoPlaying = false;
		if (ttsAudioPlayer) {
			ttsAudioPlayer.pause();
			ttsAudioPlayer.currentTime = 0; // Reset
		}
		removeHighlight();
	}
	
	function playNextInSequence() {
		removeHighlight();
		
		if (!isAutoPlaying || currentPlaybackIndex < 0 || currentPlaybackIndex >= playbackQueue.length) {
			console.log("Playback sequence finished or stopped.");
			isAutoPlaying = false;
			setInteractionsDisabled(false); // Enable interactions after sequence ends
			return;
		}
		
		const item = playbackQueue[currentPlaybackIndex];
		if (!item || !item.element || !item.url) {
			console.warn("Skipping invalid item in playback queue:", item);
			currentPlaybackIndex++;
			playNextInSequence(); // Try next item
			return;
		}
		
		console.log(`Playing item ${currentPlaybackIndex}:`, item.url);
		highlightElement(item.element, true);
		
		if (ttsAudioPlayer) {
			setTimeout(() => {
				if (!isAutoPlaying) return; // Check if stopped during timeout
				ttsAudioPlayer.src = item.url;
				ttsAudioPlayer.play().catch(error => {
					console.error(`Error playing TTS audio for index ${currentPlaybackIndex}:`, error);
					stopPlaybackSequence();
					setInteractionsDisabled(false);
				});
			}, 2000);
			
		} else {
			console.error("TTS Audio Player not found!");
			stopPlaybackSequence();
			setInteractionsDisabled(false);
		}
	}
	
	function handleTtsAudioEnded() {
		if (!isAutoPlaying) return; // Ignore if manually stopped
		console.log(`Finished item ${currentPlaybackIndex}`);
		currentPlaybackIndex++;
		playNextInSequence(); // Play the next item
	}
	
	function handleTtsAudioError(event) {
		console.error("TTS Audio Player Error:", event);
		if (isAutoPlaying) {
			stopPlaybackSequence();
			setErrorState("An error occurred during audio playback.");
			setInteractionsDisabled(false); // Ensure interactions are re-enabled
		}
	}
	
	// --- Main UI Update Function ---
	function updateQuizUI() {
		if (!currentQuizId && !isLoading) {
			console.error("No current quiz ID found.");
			setErrorState("Could not load quiz. Please try starting over.");
			setInteractionsDisabled(true); // Disable everything if no quiz
			return;
		}
		
		// Close Error message button listener
		if (closeErrorButton && !closeErrorButton.getAttribute('listener')) {
			closeErrorButton.addEventListener('click', () => toggleElement(errorMessageArea, false));
			closeErrorButton.setAttribute('listener', 'true');
		}
		
		// --- Answer Buttons ---
		const answerButtons = quizAnswersContainer.querySelectorAll('.answer-btn');
		answerButtons.forEach((button) => {
			const index = parseInt(button.dataset.index, 10);
			
			// Disable button if interactions are disabled OR if the quiz is marked as correctly answered
			const disableButton = interactionsDisabled || (answered === 'correct');
			button.disabled = disableButton;
			
			// Visual Styles
			button.classList.toggle('selected', selectedIndex === index);
			button.classList.toggle('correct', answered === 'correct' && index === correctIndex);
			button.classList.toggle('incorrect', answered === 'incorrect' && selectedIndex === index);
		});
		
		// --- Feedback Section ---
		// Show feedback ONLY if an answer has been processed (correct/incorrect)
		const showFeedback = answered === 'correct' || answered === 'incorrect';
		toggleElement(feedbackSection, showFeedback);
		
		if (showFeedback && feedbackHeading && feedbackTextEl && playFeedbackButton) {
			const isCorrect = answered === 'correct';
			feedbackHeading.textContent = isCorrect ? 'Correct!' : 'Not Quite!';
			feedbackHeading.className = isCorrect ? 'text-success mb-2' : 'text-danger mb-2';
			feedbackTextEl.textContent = feedbackText;
			
			toggleElement(playFeedbackButton, !!feedbackAudioUrl);
			playFeedbackButton.disabled = interactionsDisabled;
			
			// Next Question Button and Messages
			// Show next button only if correct AND interactions are NOT disabled (feedback finished or no audio)
			showNextButton = (answered === 'correct' && !interactionsDisabled);
			toggleElement(nextQuestionButton, showNextButton);
			nextQuestionButton.disabled = isLoading || interactionsDisabled; // Also disable during loading/interactions
			toggleElement(nextQuestionSpinner, isLoading && showNextButton); // Show spinner ON next button only when loading next
			
			
			// Show helper messages only when the next button isn't visible
			// Show "Incorrect" message if answered incorrectly AND interactions are now enabled (meaning feedback finished or no audio)
			toggleElement(feedbackIncorrectMessage, answered === 'incorrect' && !interactionsDisabled);
			// Show "Listen" message only if correct AND feedback audio exists AND interactions are disabled (meaning feedback is playing)
			toggleElement(feedbackListenMessage, answered === 'correct' && !!feedbackAudioUrl && interactionsDisabled);
		} else {
			// Ensure messages are hidden if feedback section is hidden
			toggleElement(feedbackIncorrectMessage, false);
			toggleElement(feedbackListenMessage, false);
			toggleElement(nextQuestionButton, false); // Hide next button if no feedback yet
		}
	}
	
	
	// --- Event Handlers ---
	function handleAnswerClick(event) {
		// Allow clicking only on buttons that are not disabled
		const targetButton = event.target.closest('.answer-btn'); // Find the button itself
		if (!targetButton || targetButton.disabled) {
			console.log(`Click ignored: not on enabled button or interactions disabled=${interactionsDisabled}`);
			return;
		}
		
		stopPlaybackSequence(); // Stop auto-play if user clicks an answer
		
		if (answered === 'incorrect') {
			console.log("Retrying after incorrect answer. Resetting feedback state.");
			answered = null;
			selectedIndex = null;
			// Don't reset correctIndex here, it's needed for comparison in submit if they click the right one now
			feedbackText = '';
			feedbackAudioUrl = null;
			toggleElement(feedbackSection, false); // Hide old feedback
			// Remove incorrect/selected styles from previous attempt
			quizAnswersContainer.querySelectorAll('.answer-btn').forEach(btn => {
				btn.classList.remove('selected', 'incorrect');
			});
			// Make sure buttons become immediately clickable if they weren't disabled for other reasons
			if (!isLoading && !isAutoPlaying) {
				setInteractionsDisabled(false); // Re-enable interactions to allow immediate re-click processing
			}
		}
		
		const index = parseInt(targetButton.dataset.index, 10);
		console.log(`Answer clicked: Index ${index}`);
		submitAnswer(index);
	}
	
	async function submitAnswer(index) {
		if (isLoading || interactionsDisabled) {
			console.warn("Submit answer called while loading or interactions disabled.");
			return;
		}
		
		selectedIndex = index;
		setLoadingState(true, 'Checking answer...'); // Disables interactions
		setErrorState(null);
		console.log('Submitting answer index:', index, 'for quiz:', currentQuizId);
		
		try {
			const response = await fetch(`/quiz/${currentQuizId}/submit`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
					'Accept': 'application/json',
				},
				body: JSON.stringify({selected_index: index})
			});
			
			const data = await response.json();
			setLoadingState(false); // Stop loading indicator BEFORE potentially playing audio
			
			if (!response.ok || !data.success) {
				if (response.status === 409) { // Conflict (Already answered correctly)
					answered = 'error';
					setErrorState(data.message || 'Quiz already answered correctly.');
				} else if (response.status === 403) { // Forbidden
					answered = 'error';
					setErrorState(data.message || 'Permission denied for this quiz.');
				} else if (response.status === 422) { // Validation error
					answered = 'error';
					setErrorState(data.message || 'Invalid data submitted.');
				} else { // Generic error
					answered = 'error'; // Mark as error state
					throw new Error(data.message || `HTTP error! status: ${response.status}`);
				}
				setInteractionsDisabled(false); // Re-enable interactions on error display
				// updateQuizUI() called by setInteractionsDisabled
			} else {
				// Success
				console.log('Answer feedback received:', data);
				answered = data.was_correct ? 'correct' : 'incorrect';
				correctIndex = data.correct_index; // Store the correct index sent from backend
				feedbackText = data.feedback_text;
				feedbackAudioUrl = data.feedback_audio_url; // Use the separate feedback audio URL
				
				// Play feedback audio *if* available
				if (feedbackAudioUrl && feedbackAudioPlayer) {
					// Interactions disabled BEFORE playing, buttons updated by updateQuizUI call inside setInteractionsDisabled
					setInteractionsDisabled(true); // This calls updateQuizUI
					playFeedbackAudio(); // This will call handleFeedbackAudioEnd which calls setInteractionsDisabled(false) -> updateQuizUI
				} else {
					// No feedback audio, feedback is instant. If correct, next button shows. If incorrect, buttons re-enable.
					setInteractionsDisabled(false); // Re-enable interactions now, calls updateQuizUI
				}
			}
		} catch (error) {
			console.error('Error submitting answer:', error);
			setErrorState(`Failed to submit answer: ${error.message}`);
			selectedIndex = null; // Reset selection on error
			answered = 'error'; // Set answered state to error
			setLoadingState(false); // Stop loading, re-enables interactions
		}
	}
	
	function playFeedbackAudio() {
		if (!feedbackAudioUrl || !feedbackAudioPlayer) {
			console.warn("Cannot play feedback audio - no URL or player.");
			setInteractionsDisabled(false);
			return;
		}
		
		// Interactions should already be disabled here
		console.log("Playing feedback audio:", feedbackAudioUrl);
		feedbackAudioPlayer.src = feedbackAudioUrl;
		feedbackAudioPlayer.play().catch(e => {
			console.error("Feedback audio playback error:", e);
			handleFeedbackAudioEnd(); // Treat error same as end for interaction flow
		});
	}
	
	function handleFeedbackAudioEnd() {
		console.log("Feedback audio finished or failed.");
		setInteractionsDisabled(false);
	}
	
	async function handleNextQuestionClick() {
		if (isLoading || interactionsDisabled) return;
		
		setLoadingState(true, 'Generating next question...');
		setErrorState(null);
		showNextButton = false;
		
		console.log('Requesting next quiz for subject:', subjectSessionId);
		try {
			const response = await fetch(`/quiz/${subjectSessionId}/next`, {
				method: 'POST',
				headers: { // Add required headers
					'Content-Type': 'application/json',
					'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
					'Accept': 'application/json',
				},
				// body: JSON.stringify({ llm: 'optional_llm_override' }) // If allowing override
			});
			const data = await response.json();
			
			if (!response.ok || !data.success) {
				throw new Error(data.message || `HTTP error! status: ${response.status}`);
			}
			console.log('Next quiz data received:', data);
			
			// --- Reset State and Update with New Quiz Data ---
			currentQuizId = data.quiz_id;
			// Update hidden input if it exists and is needed elsewhere (though currentQuizId variable is primary)
			const currentQuizIdInput = document.getElementById('currentQuizId');
			if (currentQuizIdInput) currentQuizIdInput.value = currentQuizId;
			
			selectedIndex = null;
			answered = null;
			correctIndex = null;
			feedbackText = '';
			feedbackAudioUrl = null; // Reset feedback audio
			showNextButton = false; // Reset next button visibility
			
			// Update Question Text
			if (questionTextElement) {
				questionTextElement.textContent = data.question_text;
			}
			
			if (questionImageElement) {
				const newImageUrl = data.question_image_url;
				if (newImageUrl) {
					questionImageElement.src = newImageUrl;
					questionImageElement.alt = `Visual aid for question: ${data.question_text.substring(0, 50)}...`;
					toggleElement(questionImageElement, true); // Show image element
					toggleElement(noImagePlaceholder, false); // Hide text placeholder
				} else {
					// Option 1: Hide image element entirely
					toggleElement(questionImageElement, false);
					// Option 2: Set to a default placeholder image src
					// questionImageElement.src = '/images/placeholder_q.png';
					// toggleElement(questionImageElement, true); // Keep element visible with placeholder
					
					toggleElement(noImagePlaceholder, true); // Show text placeholder if desired
				}
			}
			
			// Update Answer Buttons
			quizAnswersContainer.innerHTML = ''; // Clear old buttons
			if (data.answers && Array.isArray(data.answers)) {
				data.answers.forEach((answer, index) => {
					const button = document.createElement('button');
					button.type = 'button';
					// Add ID for highlighting and reference
					button.id = `answerBtn_${index}`;
					button.classList.add('btn', 'btn-outline-primary', 'btn-lg', 'answer-btn');
					button.dataset.index = index;
					button.textContent = answer.text;
					quizAnswersContainer.appendChild(button);
				});
			}
			
			// Hide feedback section explicitly
			toggleElement(feedbackSection, false);
			
			setLoadingState(false); // Stop loading *before* starting playback, enables interactions briefly
			
			// --- Build and Start New Playback Sequence ---
			buildPlaybackQueue(data.question_audio_url, data.answers);
			startPlaybackSequence(); // This will disable interactions again
			
			
		} catch (error) {
			console.error('Error generating next quiz:', error);
			setErrorState(`Failed to generate next quiz: ${error.message}`);
			setLoadingState(false); // Re-enables interactions
			// setInteractionsDisabled(false); // Re-enable on error - handled by setLoadingState
			// updateQuizUI(); // Update UI to show error - handled by setInteractionsDisabled
		}
	}
	
	
	// --- Initialization ---
	function initQuizPage() {
		console.log('Initializing Quiz Page (TTS Version)...');
		
		if (!currentQuizId) {
			setErrorState("Failed to load initial quiz data.");
			setLoadingState(false); // Ensure loading overlay is hidden
			setInteractionsDisabled(true); // Disable page
			return;
		}
		
		// Add event listeners
		if (quizAnswersContainer) {
			quizAnswersContainer.addEventListener('click', handleAnswerClick);
		}
		
		if (nextQuestionButton) {
			nextQuestionButton.addEventListener('click', handleNextQuestionClick);
		}
		
		if (playFeedbackButton && feedbackAudioPlayer) {
			playFeedbackButton.addEventListener('click', playFeedbackAudio);
			feedbackAudioPlayer.addEventListener('ended', handleFeedbackAudioEnd);
			feedbackAudioPlayer.addEventListener('error', handleFeedbackAudioEnd); // Treat error same as end
		}
		
		if (reviewModal) {
			const reviewVideo = reviewModal.querySelector('.review-video');
			reviewModal.addEventListener('show.bs.modal', () => {
				console.log("Review modal opening - pausing audio");
				stopPlaybackSequence(); // Stop question/answer sequence
				feedbackAudioPlayer?.pause(); // Pause feedback if playing
			});
			reviewModal.addEventListener('hide.bs.modal', () => {
				reviewVideo?.pause(); // Pause video when closing modal
			});
		}
		
		
		if (ttsAudioPlayer) {
			ttsAudioPlayer.addEventListener('ended', handleTtsAudioEnded);
			ttsAudioPlayer.addEventListener('error', handleTtsAudioError);
			// Add listener for when playback starts to ensure interactions are disabled
			ttsAudioPlayer.addEventListener('play', () => {
				if (isAutoPlaying) { // Only disable if part of the sequence
					// Check if already disabled to prevent redundant calls
					if (!interactionsDisabled) {
						setInteractionsDisabled(true);
					}
				}
			});
			// Add listener for pause - may need refinement if manual pause is allowed
			ttsAudioPlayer.addEventListener('pause', () => {
				// If paused during auto-play (e.g. user navigates away?),
				// we might want to stop the sequence. Or just let setInteractionsDisabled handle it.
				// For now, rely on the ended/error events primarily.
				console.log("TTS Audio Player paused.");
			});
		}
		
		// --- *** Check if already answered correctly on load *** ---
		const alreadyCorrect = window.isAlreadyAnsweredCorrectly || false;
		
		if (alreadyCorrect) {
			console.log('Quiz already answered correctly on load.');
			answered = 'correct';
			// Find the correct answer details from initial data
			const answers = window.initialQuizData?.answers || [];
			correctIndex = answers.findIndex(a => a.is_correct === true);
			
			if (correctIndex !== -1) {
				const correctAnswerData = answers[correctIndex];
				feedbackText = correctAnswerData?.feedback || "You previously answered this correctly.";
				feedbackAudioUrl = correctAnswerData?.feedback_audio_url || null;
				// Highlight the correct answer button immediately (optional)
				const correctButton = document.getElementById(`answerBtn_${correctIndex}`);
				if (correctButton) {
					correctButton.classList.add('correct');
				}
			} else {
				console.warn("Could not find correct answer details in initial data despite flag being true.");
				feedbackText = "You previously answered this correctly.";
				feedbackAudioUrl = null;
			}
			
			setLoadingState(false); // Ensure loading is off
			setInteractionsDisabled(false); // Enable interactions to show button/allow feedback play
			updateQuizUI(); // Render the correct state immediately
			// DO NOT start the playback sequence
			
		} else {
			// Normal flow: Build and Start Initial Playback Sequence
			console.log('Quiz not previously answered correctly. Starting playback sequence.');
			buildPlaybackQueue(window.initialQuizData.questionAudioUrl, window.initialQuizData.answers);
			startPlaybackSequence(); // This will disable interactions initially
			setLoadingState(false);
		}
		
		console.log('Quiz Page Initialized (TTS Version).');
	}
	
	// Start the quiz page logic
	initQuizPage();
	
});
