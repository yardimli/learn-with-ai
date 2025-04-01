// JS for the Quiz Display Page
document.addEventListener('DOMContentLoaded', () => {
	console.log('Quiz JS Loaded - TTS Version');
	
	// --- State Variables ---
	let currentQuizId = window.initialQuizData?.quizId || null;
	const subjectId = window.subjectId || null;
	const subjectImageUrl = window.initialQuizData?.subjectImageUrl || null; // For static image
	let selectedIndex = null;
	let answered = null; // null, 'correct', 'incorrect', 'error'
	let correctIndex = null;
	let feedbackText = '';
	let feedbackAudioUrl = null; // For the feedback after answering
	let interactionsDisabled = false; // For loading, video/audio playback
	let isLoading = false;
	let showNextButton = false; // --- TTS Playback State ---
	let playbackQueue = []; // Array of {element: HTMLElement, url: string}
	let currentPlaybackIndex = -1;
	let isAutoPlaying = false;
	let currentHighlightElement = null; // --- DOM Element References ---
	const loadingOverlay = document.getElementById('loadingOverlay');
	const loadingMessageEl = document.getElementById('loadingMessage');
	const errorMessageArea = document.getElementById('errorMessageArea');
	const errorMessageText = document.getElementById('errorMessageText');
	const closeErrorButton = document.getElementById('closeErrorButton');
	
	// Visuals & Question
	const questionVisualsContainer = document.getElementById('questionVisualsContainer');
	const questionTextElement = document.getElementById('questionTextElement'); // Specific element for question text
	
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
	const ttsAudioPlayer = document.getElementById('ttsAudioPlayer'); // For sequence playback
	const feedbackAudioPlayer = document.getElementById('feedbackAudioPlayer'); // Separate for feedback after answer
	const reviewModal = document.getElementById('reviewModal'); // Reference to the new modal
	
	
	// --- Helper Functions ---
	function setLoadingState(loading, message = '') {
		isLoading = loading;
		setInteractionsDisabled(loading); // Loading always disables interactions
		if (loadingOverlay && loadingMessageEl) {
			loadingMessageEl.textContent = message;
			loadingOverlay.classList.toggle('d-none', !loading);
		}
		// No need to call updateQuizUI here, setInteractionsDisabled does it
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
		updateQuizUI(); // Reflect disabled state on buttons etc.
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
		// Also clear highlight from any other element just in case
		document.querySelectorAll('.reading-highlight').forEach(el => el.classList.remove('reading-highlight'));
		currentHighlightElement = null;
	}
	
	// --- TTS Playback Functions ---
	function buildPlaybackQueue(questionAudioUrl, answersData) {
		playbackQueue = [];
		currentPlaybackIndex = -1;
		// 1. Add Question
		if (questionAudioUrl && questionTextElement) {
			playbackQueue.push({ element: questionTextElement, url: questionAudioUrl });
		}
		// 2. Add Answers
		answersData.forEach((answer, index) => {
			const answerButton = document.getElementById(`answerBtn_${index}`);
			if (answer.answer_audio_url && answerButton) {
				playbackQueue.push({ element: answerButton, url: answer.answer_audio_url });
			}
		});
		console.log("Playback queue built:", playbackQueue);
	}
	
	function startPlaybackSequence() {
		if (playbackQueue.length === 0) {
			console.log("Playback queue empty, enabling interactions.");
			setInteractionsDisabled(false); // Nothing to play, enable interactions
			return;
		}
		stopPlaybackSequence(); // Stop any previous playback first
		console.log("Starting playback sequence...");
		isAutoPlaying = true;
		currentPlaybackIndex = 0;
		setInteractionsDisabled(true); // Disable interactions during sequence
		playNextInSequence();
	}
	
	function stopPlaybackSequence() {
		console.log("Stopping playback sequence.");
		isAutoPlaying = false;
		if (ttsAudioPlayer) {
			ttsAudioPlayer.pause();
			ttsAudioPlayer.currentTime = 0; // Reset
		}
		removeHighlight(); // Do NOT re-enable interactions here, let the calling context decide
		// (e.g., it might stop because loading started, or user clicked)
	}
	
	function playNextInSequence() {
		removeHighlight(); // Remove highlight from previous item
		
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
			//ttsAudioPlayer.src = ""; // Clear previous source first - Optional, might cause issues
			// Delay setting src slightly to help avoid race conditions on some browsers
			setTimeout(() => {
				if (!isAutoPlaying) return; // Check if stopped during timeout
				ttsAudioPlayer.src = item.url;
				ttsAudioPlayer.play().catch(error => {
					console.error(`Error playing TTS audio for index ${currentPlaybackIndex}:`, error);
					// Handle error: Stop playback, remove highlight, enable interactions
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
		// Don't remove highlight immediately, wait for next item or end
		// removeHighlight(); handled by playNextInSequence start
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
		
		// --- Question Visuals Logic (Simplified) ---
		// Hide all old video/image placeholders etc. Question text is always visible.
		// The static image added in the blade is just there.
		
		// --- Answer Buttons ---
		const answerButtons = quizAnswersContainer.querySelectorAll('.answer-btn');
		answerButtons.forEach((button) => {
			const index = parseInt(button.dataset.index, 10);
			
			// Disable if:
			// 1. Interactions are generally disabled (loading, playing sequence, playing feedback)
			// 2. The answer was CORRECT (can't re-answer)
			const disableButton = interactionsDisabled || (answered === 'correct');
			button.disabled = disableButton;
			
			// Keep visual styles based on 'answered' and 'selectedIndex'/'correctIndex'
			button.classList.toggle('selected', selectedIndex === index);
			button.classList.toggle('correct', answered === 'correct' && index === correctIndex);
			// Only show 'incorrect' style on the selected wrong answer
			button.classList.toggle('incorrect', answered === 'incorrect' && selectedIndex === index);
			// Don't apply reading-highlight here, it's handled by playback sequence
		});
		
		// --- Feedback Section ---
		// Show feedback ONLY if an answer was submitted AND it wasn't an error
		toggleElement(feedbackSection, answered !== null && answered !== 'error');
		
		if (answered !== null && answered !== 'error' && feedbackHeading && feedbackTextEl && playFeedbackButton) {
			const isCorrect = answered === 'correct';
			feedbackHeading.textContent = isCorrect ? 'Correct!' : 'Not Quite!';
			feedbackHeading.className = isCorrect ? 'text-success mb-2' : 'text-danger mb-2'; // Add margin
			feedbackTextEl.textContent = feedbackText;
			toggleElement(playFeedbackButton, !!feedbackAudioUrl); // Show button only if URL exists
			// Disable feedback button ONLY if interactions are disabled (i.e., sequence playing, loading, or feedback itself playing)
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
		
		// **NEW**: If already answered incorrectly, reset state before processing new click
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
		// Redundant checks, but safe
		if (isLoading || interactionsDisabled) { // Check interactionsDisabled here too
			// Don't submit if interactions are disabled (e.g., feedback playing)
			console.warn("Submit answer called while loading or interactions disabled.");
			return;
		}
		
		selectedIndex = index;
		setLoadingState(true, 'Checking answer...'); // Disables interactions
		setErrorState(null);
		// updateQuizUI(); // Show selection immediately - setLoadingState calls this
		console.log('Submitting answer index:', index, 'for quiz:', currentQuizId);
		
		try {
			const response = await fetch(`/quiz/${currentQuizId}/submit`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
					'Accept': 'application/json',
				},
				body: JSON.stringify({ selected_index: index })
			});
			
			const data = await response.json();
			setLoadingState(false); // Stop loading indicator BEFORE potentially playing audio
			
			if (!response.ok || !data.success) {
				// Handle specific errors first
				if (response.status === 409) { // Conflict (Already answered correctly)
					answered = 'error'; // Treat as error to prevent further action
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
					// updateQuizUI(); // Update UI to show feedback text/colors AND disable buttons appropriately
					playFeedbackAudio(); // This will call handleFeedbackAudioEnd which calls setInteractionsDisabled(false) -> updateQuizUI
				} else {
					// No feedback audio, feedback is instant. If correct, next button shows. If incorrect, buttons re-enable.
					setInteractionsDisabled(false); // Re-enable interactions now, calls updateQuizUI
					// updateQuizUI(); // Update UI fully (will show Next button if correct)
				}
			}
		} catch (error) {
			console.error('Error submitting answer:', error);
			setErrorState(`Failed to submit answer: ${error.message}`);
			selectedIndex = null; // Reset selection on error
			answered = 'error'; // Set answered state to error
			setLoadingState(false); // Stop loading, re-enables interactions
			// setInteractionsDisabled(false); // Re-enable on error - handled by setLoadingState(false)
			// updateQuizUI(); // Update UI to reflect error state - handled by setInteractionsDisabled
		}
	}
	
	function playFeedbackAudio() {
		if (!feedbackAudioUrl || !feedbackAudioPlayer) {
			console.warn("Cannot play feedback audio - no URL or player.");
			// Ensure interactions enabled if we can't play
			setInteractionsDisabled(false); // Calls updateQuizUI
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
		// Re-enable interactions (buttons etc.)
		setInteractionsDisabled(false); // This internally calls updateQuizUI
		// No need to explicitly hide feedback here. updateQuizUI handles button states.
	}
	
	async function handleNextQuestionClick() {
		if (isLoading || interactionsDisabled) return;
		setLoadingState(true, 'Generating next question...'); // Disables interactions
		setErrorState(null);
		showNextButton = false; // Hide button while loading
		// updateQuizUI(); // Show loading spinner - called by setLoadingState
		
		console.log('Requesting next quiz for subject:', subjectId);
		try {
			const response = await fetch(`/quiz/${subjectId}/next`, {
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
			
			// Update the UI (mostly button states, feedback section hidden)
			// updateQuizUI() is called by startPlaybackSequence -> setInteractionsDisabled(true)
			
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
			// updateQuizUI() is called by setInteractionsDisabled
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
		
		// Add listener for the review modal
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
		
		// Initial UI Render based on passed data
		// updateQuizUI(); // Called by setInteractionsDisabled(false) below
		
		// Build and Start Initial Playback Sequence
		buildPlaybackQueue(window.initialQuizData.questionAudioUrl, window.initialQuizData.answers);
		startPlaybackSequence(); // This will disable interactions initially
		
		// Hide loading overlay if it was somehow shown
		setLoadingState(false); // Will call updateQuizUI
		
		console.log('Quiz Page Initialized (TTS Version).');
	}
	
	// Start the quiz page logic
	initQuizPage();
	
});
