function loadQuestionsForLevel(partIndex, difficulty) {
	if (isLoading) return;
	setLoadingState(true, `Loading ${difficulty} questions for Part ${partIndex + 1}...`);
	setErrorState(null);
	feedbackData = null; // Clear feedback
	currentPartQuizzes = []; // Clear old quizzes
	currentQuizIndex = -1;
	currentQuiz = null;
	
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
				throw new Error(`No questions found for Part ${partIndex + 1} (${difficulty}). Cannot proceed.`);
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
	feedbackData = null;
	
	console.log(`Displaying quiz index ${index} (ID: ${currentQuiz.id})`);
	
	updateUIForQuiz();
	
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
	
	// Reset & Hide Feedback Section initially
	toggleElement(feedbackSection, false);
	feedbackData = null;
	updateButtonStates();
}

function checkStateAndTransition() {
	console.log("Checking state and transitioning. Current State:", currentState);
	// This function runs after feedback is processed (audio ended/skipped)
	// or when moving from the last question of a local set.
	
	const newState = currentState; // Use the state updated by submitAnswer response
	
	// Get details of the level we *just finished* displaying/answering
	const previousQuizLevelPart = currentQuiz?.lesson_part_index ?? -1;
	const previousQuizLevelDiff = currentQuiz?.difficulty_level ?? null;
	
	const isCompleted = newState.status === 'completed';
	const levelOrPartChangedInState = (newState.partIndex !== previousQuizLevelPart || newState.difficulty !== previousQuizLevelDiff);
	const hasNextInLocalList = currentPartQuizzes.length > 0 && (currentQuizIndex + 1) < currentPartQuizzes.length;
	
	console.log(`CheckState: Completed: ${isCompleted}, Level/Part Changed: ${levelOrPartChangedInState}, HasNextLocal: ${hasNextInLocalList}, PrevPart: ${previousQuizLevelPart}, NewPart: ${newState.partIndex}, PrevDiff: ${previousQuizLevelDiff}, NewDiff: ${newState.difficulty}`);
	
	if (isCompleted || levelOrPartChangedInState || !hasNextInLocalList) {
		console.log("Clearing feedback data because state changed, lesson complete, or no more local questions.");
		feedbackData = null;
		toggleElement(feedbackSection, false);
	} else {
		console.log("Keeping feedback visible as there are more questions locally and state didn't change.");
	}
	
	if (isCompleted) {
		console.log("Transition: Lesson Completed");
		showCompletionScreen();
		setInteractionsDisabled(false);
	} else if (levelOrPartChangedInState) {
		console.log("Transition: State indicates level/part change");
		// We need to load the next level/part set
		if (newState.partIndex !== previousQuizLevelPart && previousQuizLevelPart !== -1) {
			console.log(`Transition: Moving to Part ${newState.partIndex} Intro`);
			showPartIntro(newState.partIndex); // This function will fetch intro details based on the state.
		} else {
			// Only difficulty changed within the same part
			console.log(`Transition: Loading next difficulty '${newState.difficulty}' for Part ${newState.partIndex}`);
			loadQuestionsForLevel(newState.partIndex, newState.difficulty); // Load the new set immediately
		}
	} else if (hasNextInLocalList) {
		console.log("Transition: More questions available in the current local list. Enabling Next button.");
		// Still questions left in the *current* loaded set.
		// Enable interaction and the 'Next Question' button should be visible/enabled via updateUI/updateButtonStates.
		setInteractionsDisabled(false); // Enable interaction
		updateUI(); // Refresh UI state (especially Next button)
	} else {
		// No more local questions, state didn't change (e.g., threshold not met but answered all once)
		console.warn("Transition: No more local questions and state hasn't advanced. What now?");
		
		setErrorState("You've completed the available questions for this section, but haven't met the requirement to advance yet. Please review the material or contact support if you believe this is an error."); // More specific message needed maybe
		toggleElement(nextQuestionButton, false); // Hide next button in this ambiguous state
		setInteractionsDisabled(false); // Ensure user isn't stuck
		updateProgressBar(); // Update progress bar based on final state
	}
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
			
			feedbackData = {
				was_correct: data.was_correct,
				correct_index: data.correct_index,
				feedback_text: data.feedback_text,
				feedback_audio_url: data.feedback_audio_url,
				level_advanced: data.level_advanced,
				lesson_completed: data.lesson_completed
			};
			currentState = data.newState;
			
			setInteractionsDisabled(true);
			updateUI();
			
			if (feedbackData.feedback_audio_url && feedbackAudioPlayer) {
				playFeedbackAudio();
			} else {
				setTimeout(checkStateAndTransition, 500); // 500ms delay
			}
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
			updateUI();
		});
}

function showQuizScreen() {
	isPartIntroVisible = false;
	feedbackData = null; // Ensure feedback is cleared
	
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
	
	// Handle completion state (redundant check, but safe)
	if (currentState.status === 'completed') {
		if (!completionMessage.classList.contains('d-none')) return; // Already visible
		showCompletionScreen();
		return;
	}
	
	// If quiz area should be visible but no current quiz (e.g., during load), do nothing yet
	if (quizArea.classList.contains('d-none') || !currentQuiz) {
		updateButtonStates();
		return;
	}
	
	// --- Update Feedback Section ---
	const showFeedback = feedbackData != null;
	toggleElement(feedbackSection, showFeedback);
	
	if (showFeedback) {
		const isCorrect = feedbackData.was_correct;
		feedbackHeading.textContent = isCorrect ? 'Correct!' : 'Not Quite!';
		feedbackHeading.className = isCorrect ? 'text-success mb-2' : 'text-danger mb-2';
		feedbackTextEl.textContent = feedbackData.feedback_text;
		
		// Update answer button styles based on feedback
		quizAnswersContainer?.querySelectorAll('.answer-btn').forEach(button => {
			const btnIndex = parseInt(button.dataset.index);
			button.classList.remove('selected', 'correct', 'incorrect', 'btn-outline-primary', 'btn-primary'); // Reset styles first
			
			// Always highlight the actual correct answer green
			if (btnIndex === feedbackData.correct_index) {
				button.classList.add('correct', 'btn-success'); // Use solid success color
			}
			// Highlight the user's *selected* answer
			else if (btnIndex === selectedIndex) {
				button.classList.add('selected'); // Mark as selected
				if (!isCorrect) {
					// If selection was wrong, mark it red
					button.classList.add('incorrect', 'btn-danger'); // Use solid danger color
				} else {
					// Selected and correct - already handled by correct check above
					// Maybe add a slightly different style? Or keep it just green.
				}
			}
			// For other non-selected, non-correct answers
			else {
				button.classList.add('btn-outline-secondary'); // Muted outline
			}
			
			button.disabled = true; // Keep buttons disabled while feedback is shown
		});
		
		
		// Show feedback messages based on state
		const required = currentState.requiredCorrect;
		const correctCount = currentState.correctCounts?.[currentState.difficulty] ?? 0;
		const remaining = Math.max(0, required - correctCount);
		
		// Show Incorrect message ONLY if incorrect AND next button will be shown (i.e. not auto-advancing)
		const willShowNextButton = !interactionsDisabled && !isLoading && !feedbackData.lesson_completed && !(feedbackData.level_advanced && feedbackData.was_correct); // Heuristic
		toggleElement(feedbackIncorrectMessage, !isCorrect && willShowNextButton);
		
		// Show Threshold message if correct BUT not advanced AND next button will be shown
		toggleElement(feedbackThresholdMessage, isCorrect && !feedbackData.level_advanced && willShowNextButton);
		if (remainingCorrectCount) remainingCorrectCount.textContent = remaining > 1 ? `${remaining} more` : `${remaining} more`;
		
		// Show Listen message ONLY if feedback audio is currently playing (interactionsDisabled + audio URL)
		toggleElement(feedbackListenMessage, interactionsDisabled && !!feedbackData.feedback_audio_url);
		
	} else {
		// Ensure feedback messages are hidden when no feedback data
		toggleElement(feedbackIncorrectMessage, false);
		toggleElement(feedbackThresholdMessage, false);
		toggleElement(feedbackListenMessage, false);
	}
	
	updateButtonStates();
}

// --- Event Listeners ---
function setupEventListeners() {
	quizAnswersContainer.addEventListener('click', (event) => {
		const targetButton = event.target.closest('.answer-btn');
		if (targetButton && !targetButton.disabled) {
			submitAnswer(parseInt(targetButton.dataset.index, 10));
		}
	});
	
	nextQuestionButton.addEventListener('click', () => {
		if (!isLoading && !interactionsDisabled) {
			console.log("Next Question button clicked.");
			
			console.log("Hiding feedback section on Next Question click.");
			feedbackData = null; // Clear data
			toggleElement(feedbackSection, false); // Hide section visually
			
			const nextIndex = currentQuizIndex + 1;
			if (nextIndex < currentPartQuizzes.length) {
				displayQuizAtIndex(nextIndex);
			} else {
				console.warn("Next button clicked, but no more local quizzes. Re-checking state.");
				checkStateAndTransition(); // Re-run transition logic as a fallback
			}
		}
	});
}

// --- Initialization ---
function initQuizInterface() {
	console.log("Initializing Interactive Quiz...");
	console.log("Initial State:", currentState);
	// console.log("Initial Quiz:", currentQuiz); // Should be null now
	console.log("Total Parts:", totalParts);
	
	setLoadingState(true, 'Initializing...');
	
	if (!currentState || !subjectSessionId) {
		setErrorState("Failed to load initial quiz state. Please try refreshing the page.");
		setLoadingState(false);
		return;
	}
	
	setupEventListeners();
	
	// Determine initial view: Completion or Intro
	if (currentState.status === 'completed') {
		showCompletionScreen();
	} else if (currentState.partIndex >= 0 && currentState.partIndex < totalParts) {
		// Always show the intro for the current part first upon initial load or refresh
		showPartIntro(currentState.partIndex);
	} else {
		// Should not happen with valid state calculation, indicates an error
		setErrorState("Invalid starting state detected (Part index out of bounds). Please try refreshing.");
		toggleElement(partIntroArea, false); // Hide potentially broken intro
	}
	
	setLoadingState(false); // Done initializing
	console.log("Interactive Quiz Initialized.");
}
