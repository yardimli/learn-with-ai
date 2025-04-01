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
	let showNextButton = false;
	
	// --- TTS Playback State ---
	let playbackQueue = []; // Array of {element: HTMLElement, url: string}
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
		removeHighlight();
		// Do NOT re-enable interactions here, let the calling context decide
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
			//ttsAudioPlayer.src = ""; // Clear previous source first
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
			}, 2500); // Small delay
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
			// Disable if an answer has been given OR interactions are generally disabled (loading/playing)
			button.disabled = answered !== null || interactionsDisabled;
			button.classList.toggle('selected', selectedIndex === index);
			button.classList.toggle('correct', answered === 'correct' && index === correctIndex);
			button.classList.toggle('incorrect', answered === 'incorrect' && selectedIndex === index);
			// Don't apply reading-highlight here, it's handled by playback sequence
		});
		
		// --- Feedback Section ---
		toggleElement(feedbackSection, answered !== null && answered !== 'error');
		if (answered !== null && answered !== 'error' && feedbackHeading && feedbackTextEl && playFeedbackButton) {
			const isCorrect = answered === 'correct';
			feedbackHeading.textContent = isCorrect ? 'Correct!' : 'Not Quite!';
			feedbackHeading.className = isCorrect ? 'text-success mb-2' : 'text-danger mb-2'; // Add margin
			feedbackTextEl.textContent = feedbackText;
			toggleElement(playFeedbackButton, !!feedbackAudioUrl); // Show button only if URL exists
			playFeedbackButton.disabled = interactionsDisabled; // Disable button during sequence playback/loading
			
			// Next Question Button and Messages
			// Show next button only if correct AND interactions are NOT disabled (e.g., feedback audio finished)
			showNextButton = (answered === 'correct' && !interactionsDisabled);
			toggleElement(nextQuestionButton, showNextButton);
			nextQuestionButton.disabled = isLoading || interactionsDisabled; // Also disable during loading/interactions
			toggleElement(nextQuestionSpinner, isLoading && !showNextButton); // Show spinner on button only when loading next
			
			// Show helper messages only when the next button isn't visible
			toggleElement(feedbackIncorrectMessage, answered === 'incorrect' && !showNextButton);
			toggleElement(feedbackListenMessage, answered === 'correct' && !!feedbackAudioUrl && !showNextButton);
		} else {
			// Ensure messages are hidden if feedback section is hidden
			toggleElement(feedbackIncorrectMessage, false);
			toggleElement(feedbackListenMessage, false);
			toggleElement(nextQuestionButton, false); // Hide next button if no feedback yet
		}
	}
	
	// --- Event Handlers ---
	function handleAnswerClick(event) {
		if (!event.target.classList.contains('answer-btn') || interactionsDisabled || answered !== null) {
			// Ignore clicks if disabled, already answered, or not on a button
			console.log(`Click ignored: disabled=${interactionsDisabled}, answered=${answered}`);
			return;
		}
		
		stopPlaybackSequence(); // Stop auto-play if user clicks an answer
		
		const index = parseInt(event.target.dataset.index, 10);
		console.log(`Answer clicked: Index ${index}`);
		submitAnswer(index);
	}
	
	async function submitAnswer(index) {
		// Redundant checks, but safe
		if (answered !== null || isLoading || interactionsDisabled) {
			console.warn("Submit answer called while disabled/answered.");
			return;
		}
		
		selectedIndex = index;
		setLoadingState(true, 'Checking answer...');
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
				if (response.status === 409) {
					answered = 'error';
					setErrorState(data.message || 'Quiz already answered in this session.');
				} else if (response.status === 403) {
					answered = 'error';
					setErrorState(data.message || 'Permission denied for this quiz.');
				} else {
					answered = 'error'; // Mark as error state
					throw new Error(data.message || `HTTP error! status: ${response.status}`);
				}
				setInteractionsDisabled(false); // Re-enable interactions on error display
				updateQuizUI(); // Update UI to show error feedback styles if any
				
			} else {
				console.log('Answer feedback received:', data);
				answered = data.was_correct ? 'correct' : 'incorrect';
				correctIndex = data.correct_index;
				feedbackText = data.feedback_text;
				feedbackAudioUrl = data.feedback_audio_url; // Use the separate feedback audio URL
				
				// Play feedback audio *if* available, otherwise just update UI
				if (feedbackAudioUrl && feedbackAudioPlayer) {
					setInteractionsDisabled(true); // Disable interactions BEFORE playing feedback audio
					updateQuizUI(); // Update UI to show feedback text/colors AND disable buttons
					playFeedbackAudio(); // This will re-enable interactions on end/error
				} else {
					// No feedback audio, feedback is instant
					setInteractionsDisabled(false); // Re-enable interactions now
					updateQuizUI(); // Update UI fully (will show Next button if correct)
				}
			}
		} catch (error) {
			console.error('Error submitting answer:', error);
			setErrorState(`Failed to submit answer: ${error.message}`);
			selectedIndex = null; // Reset selection on error
			answered = 'error'; // Set answered state to error
			setLoadingState(false); // Stop loading
			setInteractionsDisabled(false); // Re-enable on error
			updateQuizUI(); // Update UI to reflect error state
		}
	}
	
	function playFeedbackAudio() {
		if (!feedbackAudioUrl || !feedbackAudioPlayer) {
			console.warn("Cannot play feedback audio - no URL or player.");
			setInteractionsDisabled(false); // Ensure interactions enabled if we can't play
			updateQuizUI();
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
		setInteractionsDisabled(false); // Re-enable interactions
		updateQuizUI(); // Update button states etc. (will show Next button if applicable)
	}
	
	async function handleNextQuestionClick() {
		if (isLoading || interactionsDisabled) return;
		setLoadingState(true, 'Generating next question...');
		setErrorState(null);
		showNextButton = false; // Hide button while loading
		updateQuizUI(); // Show loading spinner
		
		console.log('Requesting next quiz for subject:', subjectId);
		try {
			const response = await fetch(`/quiz/${subjectId}/next`, {
				method: 'POST',
				headers: { /* ... headers ... */ },
				// body: ... (if needed)
			});
			const data = await response.json();
			
			if (!response.ok || !data.success) {
				throw new Error(data.message || `HTTP error! status: ${response.status}`);
			}
			console.log('Next quiz data received:', data);
			
			// --- Reset State and Update with New Quiz Data ---
			currentQuizId = data.quiz_id;
			document.getElementById('currentQuizId').value = currentQuizId;
			selectedIndex = null;
			answered = null;
			correctIndex = null;
			feedbackText = '';
			feedbackAudioUrl = null; // Reset feedback audio
			showNextButton = false; // Reset next button visibility
			// No video URLs to handle
			
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
			
			setLoadingState(false); // Stop loading *before* starting playback
			
			// --- Build and Start New Playback Sequence ---
			buildPlaybackQueue(data.question_audio_url, data.answers);
			startPlaybackSequence(); // Will disable interactions again
			
			// Update the UI (mostly button states, feedback section hidden)
			updateQuizUI(); // Reflects initial state of new question
			
		} catch (error) {
			console.error('Error generating next quiz:', error);
			setErrorState(`Failed to generate next quiz: ${error.message}`);
			setLoadingState(false);
			setInteractionsDisabled(false); // Re-enable on error
			updateQuizUI(); // Update UI to show error
		}
	}
	
	// --- Initialization ---
	function initQuizPage() {
		console.log('Initializing Quiz Page (TTS Version)...');
		
		if (!currentQuizId) {
			setErrorState("Failed to load initial quiz data.");
			setInteractionsDisabled(true);
			updateQuizUI();
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
		if (ttsAudioPlayer) {
			ttsAudioPlayer.addEventListener('ended', handleTtsAudioEnded);
			ttsAudioPlayer.addEventListener('error', handleTtsAudioError);
			// Add listener for when playback starts to ensure interactions are disabled
			ttsAudioPlayer.addEventListener('play', () => {
				if (isAutoPlaying) { // Only disable if part of the sequence
					setInteractionsDisabled(true);
				}
			});
			// Add listener for pause - may need refinement if manual pause is allowed
			ttsAudioPlayer.addEventListener('pause', () => {
				// If paused during auto-play (e.g. user navigates away?),
				// we might want to stop the sequence. Or just let setInteractionsDisabled handle it.
				// For now, rely on the ended/error events primarily.
			});
		}
		
		// Initial UI Render based on passed data
		updateQuizUI();
		
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
