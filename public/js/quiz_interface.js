function loadQuestionsForLevel(partIndex, difficulty) {
	if (isLoading) return;
	setLoadingState(true, `Loading ${difficulty} questions for Part ${partIndex + 1}...`);
	setErrorState(null);
	currentPartQuizzes = []; // Clear old quizzes
	currentQuizIndex = -1;
	currentQuiz = null;
	
	displayedPartIndex = partIndex;
	updateProgressBar();
	
	fetch(`/lesson/${subjectSessionId}/part-questions`, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
			'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
			'Accept': 'application/json',
		},
		body: JSON.stringify({partIndex, difficulty})
	})
		.then(response => {
			if (!response.ok) {
				return response.json().then(err => {
					throw new Error(err.message || `HTTP error ${response.status}`)
				});
			}
			return response.json();
		})
		.then(data => {
			if (!data.success) {
				throw new Error(data.message || 'Failed to fetch questions for this part.');
			}
			
			if (!data.quizzes || data.quizzes.length === 0) {
				console.warn(`No quizzes returned for Part ${partIndex}, Difficulty ${difficulty}.`);
				// Don't throw error here, let the user potentially click another part
				setErrorState(`No ${difficulty} questions found for Part ${partIndex + 1}. You can try another part.`);
				toggleElement(quizArea, false); // Hide quiz area
				toggleElement(partIntroArea, false); // Keep intro hidden too
				setLoadingState(false); // Stop loading
				// Leave displayedPartIndex as is, let user click elsewhere
				return; // Stop processing further
			}
			
			console.log(`Loaded ${data.quizzes.length} quizzes for Part ${partIndex}, Diff ${difficulty}`);
			currentPartQuizzes = data.quizzes;
			currentQuizIndex = 0;
			
			// Hide intro (if visible), show quiz area
			showQuizScreen();
			
			// Display the first question from the newly loaded set
			displayQuizAtIndex(currentQuizIndex);
			
			setLoadingState(false); // Loading complete
		})
		.catch(error => {
			console.error('Error loading part questions:', error);
			setErrorState(`Error: ${error.message}`);
			setLoadingState(false);
			
			toggleElement(quizArea, false);
			toggleElement(partIntroArea, false);
		});
}

function displayQuizAtIndex(index) {
	if (index < 0 || index >= currentPartQuizzes.length) {
		console.error(`Invalid quiz index requested: ${index}`);
		checkStateAndTransition();
		return;
	}
	
	currentQuizIndex = index;
	currentQuiz = currentPartQuizzes[index];
	selectedIndex = null;
	
	console.log(`Displaying quiz index ${index} (ID: ${currentQuiz.id})`);
	
	updateUIForQuiz();
	
	// Reset button styles from previous feedback
	quizAnswersContainer?.querySelectorAll('.answer-btn').forEach(button => {
		button.classList.remove('selected', 'correct', 'incorrect', 'btn-success', 'btn-danger', 'btn-outline-secondary');
		button.classList.add('btn-outline-primary');
		// Disabled state will be handled by updateButtonStates or setInteractionsDisabled
	});
	
	setInteractionsDisabled(true);
	buildPlaybackQueue(currentQuiz);
	startPlaybackSequence();
}

function updateUIForQuiz() {
	if (!currentQuiz) {
		console.error("updateUIForQuiz called but currentQuiz is null");
		return; // Or hide quiz area
	}
	
	if (questionDifficulty) {
		questionDifficulty.textContent = `Part ${currentQuiz.lesson_part_index + 1} - ${capitalizeFirstLetter(currentQuiz.difficulty_level)}`;
	}
	if (questionTextElement) {
		questionTextElement.textContent = currentQuiz.question_text;
	}
	
	// Image Display
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
	
	// Answer Buttons
	quizAnswersContainer.innerHTML = ''; // Clear old buttons
	currentQuiz.answers?.forEach((answer, idx) => {
		const button = document.createElement('button');
		button.type = 'button';
		button.id = `answerBtn_${idx}`;
		button.classList.add('btn', 'btn-outline-primary', 'btn-lg', 'answer-btn', 'w-100', 'mb-2');
		button.dataset.index = idx;
		button.textContent = answer.text;
		button.disabled = interactionsDisabled; // Initial state based on current interaction status
		quizAnswersContainer.appendChild(button);
	});
	
	updateButtonStates();
}

function checkStateAndTransition() {
	console.log("Checking state and transitioning (called after modal 'Next' click). Current State:", currentState);
	
	// This function runs AFTER user clicked "Next Question" in the modal.
	// The feedback modal is already hidden.
	
	const newState = currentState; // Use the state updated by submitAnswer response
	const previousQuizLevelPart = currentQuiz?.lesson_part_index ?? -1; // Get part from the question just answered
	// const previousQuizLevelDiff = currentQuiz?.difficulty_level ?? null; // Get diff from the question just answered
	
	const isCompleted = newState.status === 'completed';
	// Check if the *state* now points to a different part index than the question we just answered
	const partChangedInState = (newState.partIndex !== previousQuizLevelPart);
	// Check if the *state* points to a different difficulty OR part (covers level up)
	const levelOrPartChangedInState = (newState.partIndex !== previousQuizLevelPart || newState.difficulty !== currentQuiz?.difficulty_level);
	
	
	console.log(`CheckState: Completed: ${isCompleted}, Part Changed: ${partChangedInState}, PrevPart: ${previousQuizLevelPart}, NewPart: ${newState.partIndex}, PrevDiff: ${currentQuiz?.difficulty_level}, NewDiff: ${newState.difficulty}`);
	
	if (isCompleted) {
		console.log("Transition: Lesson Completed");
		showCompletionScreen();
		setInteractionsDisabled(false);
	} else if (levelOrPartChangedInState) {
		console.log("Transition: State indicates level/part change");
		if (partChangedInState && previousQuizLevelPart !== -1) {
			// Moving to a new part requires showing the intro screen
			console.log(`Transition: Moving to Part ${newState.partIndex} Intro`);
			showPartIntro(newState.partIndex); // This shows intro, user clicks 'Start Part Quiz'
		} else {
			// Only difficulty changed within the same part, load the new questions directly
			console.log(`Transition: Loading next difficulty '${newState.difficulty}' for Part ${newState.partIndex}`);
			loadQuestionsForLevel(newState.partIndex, newState.difficulty); // This loads & displays first question
		}
	} else {
		// State didn't change (e.g. correct answer but threshold not met), AND we came from clicking "Next"
		// This implies there SHOULD be a next question in the *current* local list.
		const nextIndex = currentQuizIndex + 1;
		if (nextIndex < currentPartQuizzes.length) {
			console.log(`Transition: Moving to next question in local list (Index: ${nextIndex})`);
			displayQuizAtIndex(nextIndex);
			// Interactions will be enabled after TTS playback finishes for the new question
		} else {
			console.warn("Transition: 'Next' clicked, state unchanged, but no more local quizzes. Re-fetching state's target level.");
			// As a fallback, try loading the level indicated by the current state again.
			loadQuestionsForLevel(newState.partIndex, newState.difficulty);
		}
	}
	updateProgressBar(); // Always update progress bar after a transition decision
}

function submitAnswer(index) {
	if (isLoading || interactionsDisabled || feedbackData != null) {
		return;
	}
	stopPlaybackSequence(true); // Stop TTS, allow interaction temporarily
	selectedIndex = index;
	setLoadingState(true, 'Checking answer...');
	setErrorState(null);
	
	// Update button visually immediately (optional, but good UX)
	quizAnswersContainer?.querySelectorAll('.answer-btn').forEach(btn => {
		btn.classList.remove('selected');
		if (parseInt(btn.dataset.index) === index) {
			btn.classList.add('selected');
		}
		btn.disabled = true; // Disable all buttons after selection
	});
	
	fetch(`/quiz/${currentQuiz.id}/submit`, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
			'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
			'Accept': 'application/json',
		},
		body: JSON.stringify({selected_index: index})
	})
		.then(response => {
			const status = response.status;
			return response.json().then(data => ({status, data}));
		})
		.then(({status, data}) => {
			setLoadingState(false); // Stop loading indicator *before* feedback audio
			
			if (!data.success) {
				let errorMsg = data.message || `HTTP error! status: ${status}`;
				throw new Error(errorMsg);
			}
			
			console.log('Answer feedback received:', data);
			currentState = data.newState;
			showFeedbackModal(data);
		})
		.catch(error => {
			console.error('Error submitting answer:', error);
			setErrorState(`Failed to submit answer: ${error.message}`);
			selectedIndex = null;
			feedbackData = null;
			// Re-enable buttons if submit failed?
			quizAnswersContainer?.querySelectorAll('.answer-btn').forEach(btn => {
				btn.disabled = false;
				btn.classList.remove('selected');
			});
			setLoadingState(false);
			showFeedbackModal(data);
		});
}

function showFeedbackModal(feedbackResult) {
	if (!feedbackModalInstance || !feedbackModalLabel || !feedbackModalText || !playFeedbackModalButton || !modalTryAgainButton || !modalNextButton) {
		console.error("Feedback modal elements not found!");
		return;
	}
	
	const isCorrect = feedbackResult.was_correct;
	
	// Update modal content
	feedbackModalLabel.textContent = isCorrect ? 'Correct!' : 'Not Quite...';
	feedbackModalLabel.className = isCorrect ? 'modal-title text-success' : 'modal-title text-danger'; // Add color
	feedbackModalText.textContent = feedbackResult.feedback_text || (isCorrect ? 'Well done!' : 'Please try again.');
	
	// Update answer button styles based on feedback BEFORE showing modal
	quizAnswersContainer?.querySelectorAll('.answer-btn').forEach(button => {
		const btnIndex = parseInt(button.dataset.index);
		button.classList.remove('selected', 'correct', 'incorrect', 'btn-outline-primary', 'btn-primary', 'btn-outline-secondary');
		button.disabled = true; // Keep disabled
		
		if (btnIndex === feedbackResult.correct_index) {
			// button.classList.add('correct', 'btn-success'); // Solid green for correct
		} else if (btnIndex === selectedIndex) { // User's incorrect selection
			button.classList.add('incorrect', 'btn-danger'); // Solid red for selected incorrect
		} else {
			button.classList.add('btn-outline-secondary'); // Muted outline for others
		}
	});
	
	
	// Configure feedback audio button
	if (feedbackResult.feedback_audio_url) {
		playFeedbackModalButton.dataset.audioUrl = feedbackResult.feedback_audio_url;
		toggleElement(playFeedbackModalButton, true);
		playFeedbackModalButton.innerHTML = '<i class="fas fa-volume-up me-1"></i> Play Feedback Audio'; // Reset icon/text
		toggleElement(feedbackAudioError, false); // Hide error initially
	} else {
		toggleElement(playFeedbackModalButton, false);
		playFeedbackModalButton.dataset.audioUrl = '';
	}
	
	//call click on playFeedbackModalButton
	playFeedbackModalButton.click();
	
	// Configure footer buttons
	toggleElement(modalTryAgainButton, !isCorrect); // Show "Try Again" if incorrect
	toggleElement(modalNextButton, isCorrect); // Show "Next Question" if correct
	
	// Show the modal
	feedbackModalInstance.show();
	// Interactions are disabled via the 'shown.bs.modal' event listener
}

function showQuizScreen() {
	isPartIntroVisible = false;
	feedbackData = null; // Ensure feedback is cleared
	
	if (currentQuiz) {
		displayedPartIndex = currentQuiz.lesson_part_index;
	} else if (currentPartQuizzes.length > 0) {
		// Fallback if currentQuiz isn't set yet but we have the list
		displayedPartIndex = currentPartQuizzes[0].lesson_part_index;
	}
	
	// Hide Intro Area, Show Quiz Area
	toggleElement(partIntroArea, false);
	toggleElement(completionMessage, false);
	toggleElement(quizArea, true);
	quizArea.dataset.currentPartIndex = currentState.partIndex; // Store current part index
}

function showCompletionScreen() {
	console.log("Showing completion screen");
	stopPlaybackSequence(true); // Stop any audio
	isPartIntroVisible = false;
	feedbackData = null;
	currentPartQuizzes = [];
	currentQuizIndex = -1;
	currentQuiz = null;
	
	displayedPartIndex = totalParts - 1;
	
	toggleElement(partIntroArea, false);
	toggleElement(quizArea, false);
	toggleElement(completionMessage, true);
	
	updateProgressBar(); // Ensure progress bar shows 100%
	setInteractionsDisabled(false); // Ensure interactions enabled on final screen
	updateButtonStates();
}

function updateUI() {
	updateProgressBar();
	
	// If intro is visible, only update its buttons and return
	if (isPartIntroVisible) {
		updateButtonStates();
		return;
	}
	
	if (currentState.status === 'completed') {
		if (!completionMessage.classList.contains('d-none')) return; // Already visible
		showCompletionScreen();
		return;
	}
	
	if (quizArea.classList.contains('d-none') || !currentQuiz) {
		updateButtonStates();
		return;
	}

	updateButtonStates();
}

// --- Event Listeners ---
function setupQuizAnswerEventListeners() {
	quizAnswersContainer.addEventListener('click', (event) => {
		const targetButton = event.target.closest('.answer-btn');
		if (targetButton && !targetButton.disabled) {
			submitAnswer(parseInt(targetButton.dataset.index, 10));
		}
	});
}

// --- Initialization ---
function initQuizInterface() {
	console.log("Initializing Interactive Quiz...");
	console.log("Initial State:", currentState);
	console.log("Total Parts:", totalParts);
	
	setLoadingState(true, 'Initializing...');
	
	if (!currentState || !subjectSessionId) {
		setErrorState("Failed to load initial quiz state. Please try refreshing the page.");
		setLoadingState(false);
		return;
	}
	
	// Determine initial view: Completion or Intro
	if (currentState.status === 'completed') {
		displayedPartIndex = totalParts > 0 ? totalParts - 1 : 0;
		showCompletionScreen();
	} else if (currentState.partIndex >= 0 && currentState.partIndex < totalParts) {
		displayedPartIndex = currentState.partIndex;
		// Always show the intro for the current part first upon initial load or refresh
		showPartIntro(currentState.partIndex);
	} else {
		// Should not happen with valid state calculation, indicates an error
		displayedPartIndex = 0;
		setErrorState("Invalid starting state detected (Part index out of bounds). Please try refreshing.");
		toggleElement(partIntroArea, false); // Hide potentially broken intro
	}
	
	setLoadingState(false); // Done initializing
	console.log("Interactive Quiz Initialized.");
}
