document.addEventListener('DOMContentLoaded', () => {
	console.log('Interactive Quiz JS Loaded');
	
	// --- State Variables ---
	const subjectSessionId = document.getElementById('subjectSessionId')?.value;
	const subjectId = document.getElementById('subjectId')?.value; // For potential logging/debugging
	const totalParts = window.totalLessonParts || 0;
	const difficulties = ['easy', 'medium', 'hard'];
	
	let currentState = window.quizInitialState || null; // { partIndex, difficulty, correctCounts, status, requiredCorrect, currentPartIntroText, currentPartVideoUrl }
	let currentQuiz = window.initialQuizData || null; // { id, question_text, image_url, answers, ... }
	let selectedIndex = null; // User's selection on the current quiz
	let feedbackData = null; // { was_correct, correct_index, feedback_text, feedback_audio_url }
	let isLoading = false; // For AJAX requests
	let interactionsDisabled = false; // For audio playback, loading etc.
	let isPartIntroVisible = false; // Track if intro or quiz area is shown
	let hasIntroVideoPlayed = false; // Track if intro video played (per part)
	
	// TTS Playback State
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
	
	// Progress & Navigation
	const progressBar = document.getElementById('progressBar');
	const partIndicatorContainer = document.getElementById('partIndicatorContainer');
	
	// Part Intro Area
	const partIntroArea = document.getElementById('partIntroArea');
	const partIntroTitle = document.getElementById('partIntroTitle');
	const partIntroVideo = document.getElementById('partIntroVideo');
	const partIntroVideoPlaceholder = document.getElementById('partIntroVideoPlaceholder');
	const partIntroText = document.getElementById('partIntroText');
	const startPartQuizButton = document.getElementById('startPartQuizButton');
	
	// Quiz Area
	const quizArea = document.getElementById('quizArea');
	const questionDifficulty = document.getElementById('questionDifficulty');
	const questionTextElement = document.getElementById('questionTextElement');
	const questionImageElement = document.getElementById('questionImageElement');
	const noImagePlaceholder = document.getElementById('noImagePlaceholder');
	const quizAnswersContainer = document.getElementById('quizAnswersContainer');
	
	// Feedback Area
	const feedbackSection = document.getElementById('feedbackSection');
	const feedbackHeading = document.getElementById('feedbackHeading');
	const feedbackTextEl = document.getElementById('feedbackText');
	const playFeedbackButton = document.getElementById('playFeedbackButton');
	const feedbackIncorrectMessage = document.getElementById('feedbackIncorrectMessage');
	const feedbackThresholdMessage = document.getElementById('feedbackThresholdMessage');
	const remainingCorrectCount = document.getElementById('remainingCorrectCount');
	const feedbackListenMessage = document.getElementById('feedbackListenMessage');
	const nextQuestionButton = document.getElementById('nextQuestionButton');
	const nextQuestionSpinner = document.getElementById('nextQuestionSpinner');
	
	// Completion Area
	const completionMessage = document.getElementById('completionMessage');
	
	// Audio Players
	const ttsAudioPlayer = document.getElementById('ttsAudioPlayer');
	const feedbackAudioPlayer = document.getElementById('feedbackAudioPlayer');
	
	// --- Helper Functions ---
	function setLoadingState(loading, message = 'Loading...') {
		isLoading = loading;
		setInteractionsDisabled(loading); // Loading disables interactions
		if (loadingOverlay && loadingMessageEl) {
			loadingMessageEl.textContent = message;
			loadingOverlay.classList.toggle('d-none', !loading);
		}
		// Disable next button specifically during loading
		if (nextQuestionButton) nextQuestionButton.disabled = loading;
		if (nextQuestionSpinner) nextQuestionSpinner.classList.toggle('d-none', !loading);
	}
	
	function setErrorState(message) {
		if (errorMessageArea && errorMessageText) {
			errorMessageText.textContent = message || '';
			errorMessageArea.classList.toggle('d-none', !message);
		}
	}
	
	function setInteractionsDisabled(disabled) {
		interactionsDisabled = disabled || isLoading || isAutoPlaying;
		// console.log(`Interactions Disabled: ${interactionsDisabled} (Req: ${disabled}, Load: ${isLoading}, AutoPlay: ${isAutoPlaying})`);
		updateUI(); // Update button states etc. based on this
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
	
	function capitalizeFirstLetter(string) {
		return string.charAt(0).toUpperCase() + string.slice(1);
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
		answersData?.forEach((answer, index) => {
			const answerButton = document.getElementById(`answerBtn_${index}`);
			if (answer.answer_audio_url && answerButton) {
				playbackQueue.push({ element: answerButton, url: answer.answer_audio_url });
			}
		});
		// console.log("Playback queue built:", playbackQueue);
	}
	
	function startPlaybackSequence() {
		if (playbackQueue.length === 0) {
			// console.log("Playback queue empty, enabling interactions.");
			setInteractionsDisabled(false);
			return;
		}
		stopPlaybackSequence(); // Stop any previous sequence
		// console.log("Starting playback sequence...");
		isAutoPlaying = true;
		currentPlaybackIndex = 0;
		setInteractionsDisabled(true); // Disable interactions during playback
		playNextInSequence();
	}
	
	function stopPlaybackSequence() {
		if (!isAutoPlaying && !ttsAudioPlayer?.paused) { // Only log if actually stopping something
			// console.log("Stopping playback sequence.");
		}
		isAutoPlaying = false;
		if (ttsAudioPlayer) {
			ttsAudioPlayer.pause();
			ttsAudioPlayer.currentTime = 0; // Reset
		}
		removeHighlight();
		// Do NOT re-enable interactions here, happens when sequence *ends* or is explicitly stopped by user action
	}
	
	function playNextInSequence() {
		removeHighlight();
		if (!isAutoPlaying || currentPlaybackIndex < 0 || currentPlaybackIndex >= playbackQueue.length) {
			// console.log("Playback sequence finished or stopped.");
			isAutoPlaying = false;
			setInteractionsDisabled(false); // Enable interactions after sequence naturally ends
			return;
		}
		const item = playbackQueue[currentPlaybackIndex];
		if (!item || !item.element || !item.url) {
			console.warn("Skipping invalid item in playback queue:", item);
			currentPlaybackIndex++;
			playNextInSequence(); // Try next item
			return;
		}
		
		// console.log(`Playing item ${currentPlaybackIndex}:`, item.url);
		highlightElement(item.element, true);
		
		if (ttsAudioPlayer) {
			// Small delay before playing next item
			setTimeout(() => {
				if (!isAutoPlaying) return; // Check if stopped during timeout
				ttsAudioPlayer.src = item.url;
				ttsAudioPlayer.play().catch(error => {
					console.error(`Error playing TTS audio for index ${currentPlaybackIndex}:`, error);
					stopPlaybackSequence();
					setErrorState("An error occurred during audio playback.");
					setInteractionsDisabled(false); // Re-enable on error
				});
			}, 300); // 300ms delay
		} else {
			console.error("TTS Audio Player not found!");
			stopPlaybackSequence();
			setInteractionsDisabled(false);
		}
	}
	
	function handleTtsAudioEnded() {
		if (!isAutoPlaying) return; // Ignore if manually stopped
		// console.log(`Finished item ${currentPlaybackIndex}`);
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
	
	// --- Feedback Audio ---
	function playFeedbackAudio() {
		if (!feedbackData?.feedback_audio_url || !feedbackAudioPlayer) {
			// console.warn("Cannot play feedback audio - no URL or player.");
			setInteractionsDisabled(false); // Ensure interactions enabled if no audio
			return;
		}
		// Interactions should already be disabled here
		// console.log("Playing feedback audio:", feedbackData.feedback_audio_url);
		feedbackAudioPlayer.src = feedbackData.feedback_audio_url;
		feedbackAudioPlayer.play().catch(e => {
			console.error("Feedback audio playback error:", e);
			handleFeedbackAudioEnd(); // Treat error same as end for interaction flow
		});
	}
	
	function handleFeedbackAudioEnd() {
		// console.log("Feedback audio finished or failed.");
		setInteractionsDisabled(false); // Re-enable interactions after feedback audio
		// Check if level advanced to trigger next question automatically
		if (feedbackData?.level_advanced && feedbackData?.was_correct) {
			// console.log("Level advanced, fetching next question automatically.");
			// Add a small delay before fetching next question
			setTimeout(fetchNextQuestion, 500);
		} else {
			updateUI(); // Just update UI state (e.g., enable buttons after incorrect)
		}
	}
	
	// --- Core Logic ---
	
	function fetchNextQuestion() {
		if (isLoading) return;
		setLoadingState(true, 'Loading next question...');
		setErrorState(null);
		feedbackData = null; // Clear previous feedback
		
		fetch(`/lesson/${subjectSessionId}/next-question`, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
				'Accept': 'application/json',
			}
		})
			.then(response => response.json())
			.then(data => {
				setLoadingState(false);
				if (!data.success) {
					throw new Error(data.message || 'Failed to fetch next question.');
				}
				
				// console.log("Next question/state data received:", data);
				
				// Update state and quiz data
				currentState = data.state; // Update with the new state from backend
				currentQuiz = data.quiz; // Update with the new quiz data (or null if completed)
				selectedIndex = null; // Reset selection
				feedbackData = null; // Clear feedback
				
				// Check for completion
				if (currentState.status === 'completed') {
					showCompletionScreen();
				} else {
					// Check if part intro needs to be shown
					// Compare currentQuiz's part index with the new state's part index? Or rely on backend state?
					// Let's assume if state changes partIndex, we show intro
					const previousPartIndex = quizArea.dataset.currentPartIndex || -1; // Store previous index
					if (currentState.partIndex !== parseInt(previousPartIndex)) {
						showPartIntro(currentState.partIndex);
					} else {
						// Still in the same part, just load the quiz
						showQuizScreen(); // This calls updateUI which handles rendering
						// Build and start TTS sequence for the new quiz
						buildPlaybackQueue(currentQuiz.question_audio_url, currentQuiz.answers);
						startPlaybackSequence();
					}
				}
			})
			.catch(error => {
				console.error('Error fetching next question:', error);
				setErrorState(`Error: ${error.message}`);
				setLoadingState(false);
			});
	}
	
	function submitAnswer(index) {
		if (isLoading || interactionsDisabled || feedbackData != null ) { // Prevent submission if feedback is showing
			// console.warn("Submit answer called while loading/disabled/feedback showing.");
			return;
		}
		stopPlaybackSequence(); // Stop TTS if user selects an answer
		
		selectedIndex = index;
		setLoadingState(true, 'Checking answer...');
		setErrorState(null);
		
		fetch(`/quiz/${currentQuiz.id}/submit`, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
				'Accept': 'application/json',
			},
			body: JSON.stringify({ selected_index: index })
		})
			.then(response => response.json())
			.then(data => {
				setLoadingState(false); // Stop loading indicator BEFORE potentially playing audio
				if (!data.success) {
					// Handle specific errors like 409 Conflict (already correct) if needed
					if (response.status === 409) {
						setErrorState(data.message || 'Already answered correctly.');
					} else {
						throw new Error(data.message || `HTTP error! status: ${response.status}`);
					}
					feedbackData = null; // No feedback on error
					setInteractionsDisabled(false); // Re-enable on error
				} else {
					// console.log('Answer feedback received:', data);
					// Store feedback and new state
					feedbackData = {
						was_correct: data.was_correct,
						correct_index: data.correct_index,
						feedback_text: data.feedback_text,
						feedback_audio_url: data.feedback_audio_url,
						level_advanced: data.level_advanced, // Store this flag
						lesson_completed: data.lesson_completed
					};
					currentState = data.newState; // Update state based on backend calculation
					
					// Update UI immediately to show feedback text/styles
					updateUI();
					
					// Play feedback audio if available
					if (feedbackData.feedback_audio_url && feedbackAudioPlayer) {
						setInteractionsDisabled(true); // Disable interactions *during* feedback audio
						playFeedbackAudio(); // This calls handleFeedbackAudioEnd which re-enables interactions
					} else {
						// No feedback audio, feedback is instant.
						setInteractionsDisabled(false); // Re-enable interactions now
						// Check if level advanced to trigger next question automatically
						if (feedbackData.level_advanced && feedbackData.was_correct) {
							// console.log("Level advanced (no audio), fetching next question.");
							// Add a small delay before fetching next question
							setTimeout(fetchNextQuestion, 500);
						} else {
							updateUI(); // Ensure UI reflects post-feedback state (e.g. incorrect message)
						}
					}
				}
			})
			.catch(error => {
				console.error('Error submitting answer:', error);
				setErrorState(`Failed to submit answer: ${error.message}`);
				selectedIndex = null; // Reset selection on error
				feedbackData = null;
				setLoadingState(false); // Stop loading, re-enables interactions
			});
	}
	
	// --- UI Update Functions ---
	
	function updateProgressBar() {
		if (!progressBar || !partIndicatorContainer || !currentState) return;
		
		const currentPart = currentState.partIndex;
		const currentDifficultyIndex = difficulties.indexOf(currentState.difficulty);
		const correctInCurrentDiff = currentState.correctCounts[currentState.difficulty] || 0;
		const required = currentState.requiredCorrect;
		
		// Calculate overall progress percentage
		const partsCompleted = currentPart;
		const difficultiesCompletedInPart = currentDifficultyIndex; // 0 (easy), 1 (medium), 2 (hard)
		const progressInCurrentDifficulty = required > 0 ? (correctInCurrentDiff / required) : 1; // Fraction complete in current diff
		
		const totalDifficultySteps = totalParts * difficulties.length;
		const stepsCompleted = (partsCompleted * difficulties.length) + difficultiesCompletedInPart;
		
		// Total progress calculation: steps completed + fraction of current step / total steps
		let overallProgress = 0;
		if (currentState.status === 'completed') {
			overallProgress = 100;
		} else if (totalDifficultySteps > 0) {
			overallProgress = Math.round(((stepsCompleted + progressInCurrentDifficulty) / totalDifficultySteps) * 100);
		}
		
		// Clamp progress between 0 and 100
		overallProgress = Math.min(100, Math.max(0, overallProgress));
		
		progressBar.style.width = `${overallProgress}%`;
		progressBar.textContent = `${overallProgress}%`;
		progressBar.setAttribute('aria-valuenow', overallProgress);
		
		// Update Part Indicators
		for (let i = 0; i < totalParts; i++) {
			const label = document.getElementById(`partLabel_${i}`);
			if (label) {
				label.classList.remove('active', 'completed');
				if (i < currentPart) {
					label.classList.add('completed');
				} else if (i === currentPart && currentState.status !== 'completed') {
					label.classList.add('active');
				}
				// If state is completed, all should maybe look normal or completed?
				if (currentState.status === 'completed') {
					label.classList.add('completed');
				}
			}
		}
	}
	
	function showPartIntro(partIndexToShow) {
		// console.log(`Showing intro for part ${partIndexToShow}`);
		stopPlaybackSequence(); // Stop any quiz audio
		feedbackData = null; // Clear any lingering feedback
		
		isPartIntroVisible = true;
		hasIntroVideoPlayed = false; // Reset video played flag for the new part
		
		// Hide Quiz Area, Show Intro Area
		toggleElement(quizArea, false);
		toggleElement(completionMessage, false);
		toggleElement(partIntroArea, true);
		
		// Populate Intro Content
		const partNumber = partIndexToShow + 1;
		partIntroTitle.textContent = `Part ${partNumber}: Introduction`; // Assuming generic title
		partIntroText.textContent = currentState.currentPartIntroText || "Loading intro text...";
		startPartQuizButton.textContent = `Start Part ${partNumber} Quiz`;
		startPartQuizButton.disabled = false; // Enable button
		
		// Handle Video
		if (currentState.currentPartVideoUrl) {
			partIntroVideo.src = currentState.currentPartVideoUrl;
			toggleElement(partIntroVideo, true);
			toggleElement(partIntroVideoPlaceholder, false);
			// Optional: Automatically play intro video?
			// partIntroVideo.play().catch(e => console.warn("Autoplay failed:", e));
		} else {
			toggleElement(partIntroVideo, false);
			toggleElement(partIntroVideoPlaceholder, true);
			hasIntroVideoPlayed = true; // No video, treat as played
		}
		
		updateUI(); // Update general UI state (like progress bar)
	}
	
	function showQuizScreen() {
		// console.log("Showing quiz screen");
		isPartIntroVisible = false;
		
		// Hide Intro Area, Show Quiz Area
		toggleElement(partIntroArea, false);
		toggleElement(completionMessage, false);
		toggleElement(quizArea, true);
		quizArea.dataset.currentPartIndex = currentState.partIndex; // Store current part index
		
		feedbackData = null; // Ensure feedback is cleared when showing a new quiz
		
		updateUI(); // Render the quiz details
	}
	
	function showCompletionScreen() {
		// console.log("Showing completion screen");
		stopPlaybackSequence(); // Stop any audio
		
		isPartIntroVisible = false;
		toggleElement(partIntroArea, false);
		toggleElement(quizArea, false);
		toggleElement(completionMessage, true);
		
		updateProgressBar(); // Ensure progress bar shows 100%
	}
	
	function updateUI() {
		// console.log("Updating UI. Current State:", currentState, "Current Quiz:", currentQuiz?.id, "Feedback:", feedbackData);
		
		updateProgressBar();
		
		// If intro is visible, most quiz updates are skipped
		if (isPartIntroVisible) {
			// Only potentially disable the "Start Part Quiz" button if interactions are disabled
			if (startPartQuizButton) startPartQuizButton.disabled = interactionsDisabled;
			return;
		}
		
		// Handle completion state
		if (currentState.status === 'completed' || !currentQuiz) {
			showCompletionScreen();
			return; // Nothing more to render if completed
		}
		
		// --- Update Quiz Elements ---
		if (questionDifficulty) {
			questionDifficulty.textContent = `Part ${currentState.partIndex + 1} - ${capitalizeFirstLetter(currentState.difficulty)}`;
		}
		if (questionTextElement) {
			questionTextElement.textContent = currentQuiz.question_text;
		}
		if (questionImageElement && noImagePlaceholder) {
			if (currentQuiz.image_url) {
				questionImageElement.src = currentQuiz.image_url;
				toggleElement(questionImageElement, true);
				toggleElement(noImagePlaceholder, false);
			} else {
				toggleElement(questionImageElement, false);
				toggleElement(noImagePlaceholder, true);
			}
		}
		
		// Update Answer Buttons
		quizAnswersContainer.innerHTML = ''; // Clear old buttons
		currentQuiz.answers?.forEach((answer, index) => {
			const button = document.createElement('button');
			button.type = 'button';
			button.id = `answerBtn_${index}`;
			button.classList.add('btn', 'btn-outline-primary', 'btn-lg', 'answer-btn');
			button.dataset.index = index;
			button.textContent = answer.text;
			
			// Disable button?
			// Disable if interactions disabled OR feedback is showing (meaning an answer was just submitted)
			const disableButton = interactionsDisabled || feedbackData != null;
			button.disabled = disableButton;
			
			// Visual Styles based on feedback
			if (feedbackData) {
				button.classList.toggle('selected', selectedIndex === index);
				button.classList.toggle('correct', feedbackData.was_correct && index === feedbackData.correct_index);
				// Highlight the actual correct answer green
				if(index === feedbackData.correct_index) button.classList.add('correct');
				// Highlight the selected incorrect answer red
				button.classList.toggle('incorrect', !feedbackData.was_correct && selectedIndex === index);
				
			} else {
				// Remove feedback styles if no feedback data
				button.classList.remove('selected', 'correct', 'incorrect');
			}
			
			quizAnswersContainer.appendChild(button);
		});
		
		// --- Update Feedback Section ---
		const showFeedback = feedbackData != null;
		toggleElement(feedbackSection, showFeedback);
		
		if (showFeedback) {
			const isCorrect = feedbackData.was_correct;
			feedbackHeading.textContent = isCorrect ? 'Correct!' : 'Not Quite!';
			feedbackHeading.className = isCorrect ? 'text-success mb-2' : 'text-danger mb-2';
			feedbackTextEl.textContent = feedbackData.feedback_text;
			
			// Play Feedback Button
			toggleElement(playFeedbackButton, !!feedbackData.feedback_audio_url);
			playFeedbackButton.disabled = interactionsDisabled; // Disable if audio is playing
			
			// Show 'Next Question' button ONLY if the level/lesson advanced due to this correct answer
			const showNext = feedbackData.level_advanced && isCorrect;
			toggleElement(nextQuestionButton, showNext);
			nextQuestionButton.disabled = interactionsDisabled || isLoading; // Disable if loading/audio playing
			
			// Show feedback messages based on state
			const required = currentState.requiredCorrect;
			const correctCount = currentState.correctCounts[currentState.difficulty];
			const remaining = Math.max(0, required - correctCount);
			
			// Incorrect message: Show if incorrect AND next button is hidden
			toggleElement(feedbackIncorrectMessage, !isCorrect && !showNext && !interactionsDisabled);
			
			// Threshold message: Show if correct, BUT level didn't advance (need more) AND next is hidden
			toggleElement(feedbackThresholdMessage, isCorrect && !feedbackData.level_advanced && !showNext && !interactionsDisabled);
			if(remainingCorrectCount) remainingCorrectCount.textContent = remaining;
			
			// Listen message: Show only if feedback audio is playing
			toggleElement(feedbackListenMessage, interactionsDisabled && !!feedbackData.feedback_audio_url);
			
		} else {
			// Ensure all feedback elements are hidden if no feedback data
			toggleElement(nextQuestionButton, false);
			toggleElement(feedbackIncorrectMessage, false);
			toggleElement(feedbackThresholdMessage, false);
			toggleElement(feedbackListenMessage, false);
		}
		
	} // End updateUI
	
	// --- Event Listeners ---
	function setupEventListeners() {
		if (closeErrorButton) {
			closeErrorButton.addEventListener('click', () => toggleElement(errorMessageArea, false));
		}
		
		if (quizAnswersContainer) {
			quizAnswersContainer.addEventListener('click', (event) => {
				const targetButton = event.target.closest('.answer-btn');
				if (targetButton && !targetButton.disabled) {
					submitAnswer(parseInt(targetButton.dataset.index, 10));
				}
			});
		}
		
		if (nextQuestionButton) {
			nextQuestionButton.addEventListener('click', () => {
				if (!isLoading && !interactionsDisabled) fetchNextQuestion();
			});
		}
		
		if (playFeedbackButton && feedbackAudioPlayer) {
			playFeedbackButton.addEventListener('click', () => {
				if(!interactionsDisabled) playFeedbackAudio();
			});
			feedbackAudioPlayer.addEventListener('ended', handleFeedbackAudioEnd);
			feedbackAudioPlayer.addEventListener('error', handleFeedbackAudioEnd); // Treat error same as end
		}
		
		if (ttsAudioPlayer) {
			ttsAudioPlayer.addEventListener('ended', handleTtsAudioEnded);
			ttsAudioPlayer.addEventListener('error', handleTtsAudioError);
			ttsAudioPlayer.addEventListener('play', () => {
				if (isAutoPlaying && !interactionsDisabled) {
					setInteractionsDisabled(true); // Ensure disabled when TTS starts playing
				}
			});
			ttsAudioPlayer.addEventListener('pause', () => {
				// If paused during auto-play, we might want to stop the sequence.
				// For now, rely on ended/error and manual stops via user action.
				// console.log("TTS Audio Player paused.");
			});
		}
		
		if (startPartQuizButton) {
			startPartQuizButton.addEventListener('click', () => {
				if (!isLoading && !interactionsDisabled) {
					showQuizScreen();
					// Build and start TTS sequence for the first quiz of the part
					if(currentQuiz) {
						buildPlaybackQueue(currentQuiz.question_audio_url, currentQuiz.answers);
						startPlaybackSequence();
					} else {
						console.error("Cannot start quiz, currentQuiz data is missing.");
						setErrorState("Error loading quiz question for this part.");
					}
				}
			});
		}
		
		if(partIntroVideo) {
			partIntroVideo.addEventListener('play', () => hasIntroVideoPlayed = true);
			// Optional: Require video play before enabling startPartQuizButton
		}
		
	}
	
	// --- Initialization ---
	function initQuizInterface() {
		console.log("Initializing Interactive Quiz...");
		setLoadingState(true, 'Initializing...');
		
		if (!currentState || !subjectSessionId) {
			setErrorState("Failed to load initial quiz state. Please try again.");
			setLoadingState(false);
			return;
		}
		
		setupEventListeners();
		
		// Determine initial view: Completion, Intro, or Quiz
		if (currentState.status === 'completed') {
			showCompletionScreen();
		} else if (currentQuiz) {
			// If initial load has a quiz, decide if we show intro first or jump straight in.
			// Let's always show the intro for the current part first.
			showPartIntro(currentState.partIndex);
			// If we wanted to jump straight to quiz:
			// showQuizScreen();
			// buildPlaybackQueue(currentQuiz.question_audio_url, currentQuiz.answers);
			// startPlaybackSequence();
		} else {
			// State is in progress, but no initial quiz loaded (error condition?)
			setErrorState("Could not load the first quiz question for your current progress.");
			// Maybe try fetching?
			fetchNextQuestion();
		}
		
		
		setLoadingState(false);
		console.log("Interactive Quiz Initialized.");
	}
	
	// Start the interface
	initQuizInterface();
	
});
