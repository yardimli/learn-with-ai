// Store all questions fetched from the backend in the current "round"
let allQuestionsFromCurrentFetch = [];
// Store IDs of questions answered incorrectly in the current "pass" through currentQuestions
let incorrectQuestionIdsInThisPass = [];
let keyboardFocusIndex = -1;

// New variables to track difficulty progression
let questionsByDifficulty = {
	'easy': [],
	'medium': [],
	'hard': []
};
let currentDifficulty = 'easy'; // Start with easy questions
let difficultyOrder = ['easy', 'medium', 'hard'];


function loadLessonQustions() {
	if (isLoading) return;
	setLoadingState(true, `Loading questions...`);
	setErrorState(null);
	
	// Reset for a new round of fetching questions from the backend
	allQuestionsFromCurrentFetch = [];
	currentQuestions = [];
	questionsByDifficulty = {
		'easy': [],
		'medium': [],
		'hard': []
	};
	incorrectQuestionIdsInThisPass = [];
	currentQuestionIndex = -1;
	currentQuestion = null;
	currentDifficulty = 'easy'; // Always start with easy
	
	updateProgressBar();
	
	fetch(`/lesson/${lessonId}/questions`, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
			'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
			'Accept': 'application/json',
		},
		body: JSON.stringify([]) // Body might be used for future filtering if needed
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
			setLoadingState(false);
			
			if (!data.success) {
				throw new Error(data.message || 'Failed to fetch questions.');
			}
			
			allQuestionsFromCurrentFetch = data.questions || [];
			
			if (allQuestionsFromCurrentFetch.length === 0) {
				console.log("Backend returned no questions. Checking overall lesson state.");
				
				if (currentState.status === 'completed') {
					showCompletionScreen();
				} else {
					setErrorState(`No questions found for this lesson.`);
					toggleElement(questionArea, false);
					showIntro();
				}
				return;
			}
			
			// Group questions by difficulty level
			allQuestionsFromCurrentFetch.forEach(question => {
				if (question.difficulty_level && difficultyOrder.includes(question.difficulty_level)) {
					// Only add questions that should not be skipped
					if (!question.should_skip) {
						questionsByDifficulty[question.difficulty_level].push(question);
					}
				} else {
					console.warn(`Question ${question.id} has invalid difficulty: ${question.difficulty_level}`);
				}
			});
			
			// Set current questions to the first difficulty level with available questions
			for (const difficulty of difficultyOrder) {
				if (questionsByDifficulty[difficulty].length > 0) {
					currentDifficulty = difficulty;
					currentQuestions = questionsByDifficulty[difficulty];
					break;
				}
			}
			
			console.log(`Loaded ${allQuestionsFromCurrentFetch.length} questions from backend.`);
			console.log(`Questions by difficulty:`, {
				easy: questionsByDifficulty.easy.length,
				medium: questionsByDifficulty.medium.length,
				hard: questionsByDifficulty.hard.length
			});
			
			if (currentQuestions.length === 0) {
				console.log("No active questions available. Showing completion.");
				showCompletionScreen();
				return;
			}
			
			currentQuestionIndex = 0;
			showQuestionScreen();
			displayQuestionAtIndex(currentQuestionIndex);
		})
		.catch(error => {
			console.error('Error loading questions:', error);
			setErrorState(`Error: ${error.message}`);
			setLoadingState(false);
			toggleElement(questionArea, false);
			toggleElement(IntroArea, false);
		});
}

function checkStateAndTransition() {
	console.log("Checking state and transitioning. CurrentState:", currentState, "Feedback:", feedbackData);
	
	const wasCorrect = feedbackData.was_correct || false;
	
	// Always trust the backend's assessment of overall lesson completion
	if (currentState.status === 'completed') {
		console.log("Transition: Lesson Overall Completed (confirmed by backend state)");
		showCompletionScreen();
		return;
	}
	
	const currentQuestionId = currentQuestion.id;
	
	if (wasCorrect) {
		// If the question was previously marked incorrect in this pass, remove it
		incorrectQuestionIdsInThisPass = incorrectQuestionIdsInThisPass.filter(id => id !== currentQuestionId);
		
		if (currentQuestionIndex >= currentQuestions.length - 1) {
			// Last question in the current difficulty pass
			console.log(`Last question in '${currentDifficulty}' difficulty answered correctly.`);
			
			if (incorrectQuestionIdsInThisPass.length > 0) {
				console.log(`Looping back to incorrectly answered questions in '${currentDifficulty}' difficulty:`, incorrectQuestionIdsInThisPass);
				
				// Filter current difficulty questions to get only incorrectly answered ones
				const incorrectQuestions = questionsByDifficulty[currentDifficulty].filter(
					q => incorrectQuestionIdsInThisPass.includes(q.id) && !q.should_skip
				);
				
				if (incorrectQuestions.length > 0) {
					currentQuestions = incorrectQuestions;
					incorrectQuestionIdsInThisPass = []; // Clear for the new pass
					currentQuestionIndex = 0;
					console.log(`Restarting pass with incorrect '${currentDifficulty}' questions:`, currentQuestions.map(q => q.id));
					displayQuestionAtIndex(currentQuestionIndex);
				} else {
					// Move to the next difficulty level
					moveToNextDifficulty();
				}
			} else {
				// All questions in current difficulty answered correctly, move to next difficulty
				moveToNextDifficulty();
			}
		} else {
			// Move to the next question in the current difficulty
			console.log(`Moving to next question (index ${currentQuestionIndex + 1} in '${currentDifficulty}' difficulty)`);
			displayQuestionAtIndex(currentQuestionIndex + 1);
		}
	} else {
		// Incorrect answer
		// Add to incorrect list if not already there for this pass
		if (!incorrectQuestionIdsInThisPass.includes(currentQuestionId)) {
			incorrectQuestionIdsInThisPass.push(currentQuestionId);
		}
		
		console.log(`Wrong answer for '${currentDifficulty}' question. Allowing another attempt. Incorrect IDs this pass:`, incorrectQuestionIdsInThisPass);
		
		// Reset the buttons for another attempt
		questionAnswersContainer.querySelectorAll('.answer-btn').forEach(button => {
			if (!button.classList.contains('incorrect')) {
				// Only re-enable non-incorrect buttons
				button.classList.remove('selected');
				button.classList.add('btn-outline-primary');
				button.disabled = false;
			}
		});
		
		setInteractionsDisabled(false);
	}
	
	updateProgressBar();
}

function moveToNextDifficulty() {
	const currentDifficultyIndex = difficultyOrder.indexOf(currentDifficulty);
	
	// Check if there's a next difficulty level
	if (currentDifficultyIndex < difficultyOrder.length - 1) {
		// Find the next difficulty with available questions
		for (let i = currentDifficultyIndex + 1; i < difficultyOrder.length; i++) {
			const nextDifficulty = difficultyOrder[i];
			if (questionsByDifficulty[nextDifficulty].length > 0) {
				console.log(`Moving to next difficulty: ${currentDifficulty} -> ${nextDifficulty}`);
				currentDifficulty = nextDifficulty;
				currentQuestions = questionsByDifficulty[nextDifficulty];
				currentQuestionIndex = 0;
				incorrectQuestionIdsInThisPass = []; // Clear incorrect IDs for new difficulty
				displayQuestionAtIndex(currentQuestionIndex);
				return;
			}
		}
	}
	
	// If we reach here, there are no more difficulties with questions
	console.log("All difficulty levels completed. Checking with server...");
	loadLessonQustions(); // Refresh questions from server to verify completion
}

function displayQuestionAtIndex(index) {
	// This function now operates on `currentQuestions` which might be the full set or a filtered incorrect set
	if (index < 0 || index >= currentQuestions.length) {
		console.error(`Invalid question index requested: ${index} for currentQuestions of length ${currentQuestions.length}`);
		// This could happen if currentQuestions becomes empty unexpectedly.
		// Fallback to checking state, which might reload or show completion.
		checkStateAndTransition(); // This will evaluate what to do next
		return;
	}
	
	currentQuestionIndex = index;
	currentQuestion = currentQuestions[index];
	selectedIndex = null;
	currentAttemptNumber = currentQuestion.next_attempt_number || 1;
	keyboardFocusIndex = -1;
	console.log(`Displaying question index ${index} (ID: ${currentQuestion.id}, Attempt: ${currentAttemptNumber}) from current batch of ${currentQuestions.length} questions.`);
	
	// Update difficulty badge
	const difficultyBadge = document.getElementById('currentDifficultyBadge');
	if (difficultyBadge) {
		difficultyBadge.textContent = capitalizeFirstLetter(currentDifficulty);
		
		// Optional: Change badge color based on difficulty
		difficultyBadge.className = 'badge';
		if (currentDifficulty === 'easy') {
			difficultyBadge.classList.add('bg-info');
		} else if (currentDifficulty === 'medium') {
			difficultyBadge.classList.add('bg-warning');
		} else {
			difficultyBadge.classList.add('bg-danger');
		}
	}
	
	updateUIForQuestion();
	setInteractionsDisabled(true); // Disable interactions while auto-playing
	buildPlaybackQueue(currentQuestion);
	startPlaybackSequence(); // Auto-play question audio if enabled
}

function updateUIForQuestion() {
	if (!currentQuestion) {
		console.error("updateUIForQuestion called but currentQuestion is null");
		toggleElement(questionArea, false); // Hide question area if no question
		return;
	}
	if (questionDifficulty) {
		questionDifficulty.textContent = `${capitalizeFirstLetter(currentQuestion.difficulty_level)}`;
	}
	if (questionTextElement) {
		questionTextElement.textContent = currentQuestion.question_text;
	}
	if (questionImageElement && noImagePlaceholder) {
		if (currentQuestion.image_url) {
			questionImageElement.src = currentQuestion.image_url;
			toggleElement(questionImageElement, true);
			toggleElement(noImagePlaceholder, false);
		} else {
			toggleElement(questionImageElement, false);
			toggleElement(noImagePlaceholder, true);
		}
	}
	questionAnswersContainer.innerHTML = '';
	const buttons = [];
	currentQuestion.answers.forEach((answer) => { // Removed unused 'idx'
		const button = document.createElement('button');
		button.type = 'button';
		button.id = `answerBtn_${answer.index}`;
		button.classList.add('btn', 'btn-outline-primary', 'btn-lg', 'answer-btn', 'w-100', 'mb-2');
		button.dataset.index = answer.index;
		button.textContent = answer.text;
		button.disabled = interactionsDisabled;
		buttons.push(button);
	});
	buttons.forEach(button => {
		questionAnswersContainer.appendChild(button);
	});
	updateButtonStates(4);
}

function checkStateAndTransition() {
	console.log("Checking state and transitioning. CurrentState:", currentState, "Feedback:", feedbackData);
	const wasCorrect = feedbackData.was_correct || false;
	
	// Always trust the backend's assessment of overall lesson completion
	if (currentState.status === 'completed') {
		console.log("Transition: Lesson Overall Completed (confirmed by backend state)");
		showCompletionScreen();
		return;
	}
	
	const currentQuestionId = currentQuestion.id;
	
	if (wasCorrect) {
		// If the question was previously marked incorrect in this pass, remove it
		incorrectQuestionIdsInThisPass = incorrectQuestionIdsInThisPass.filter(id => id !== currentQuestionId);
		
		if (currentQuestionIndex >= currentQuestions.length - 1) { // Last question in the current pass
			console.log("Last question in current pass answered correctly.");
			
			if (incorrectQuestionIdsInThisPass.length > 0) {
				console.log("Looping back to incorrectly answered questions in this pass:", incorrectQuestionIdsInThisPass);
				
				// Filter `allQuestionsFromCurrentFetch` to get the questions that were incorrect in this pass
				// and are not now marked as should_skip by the backend (unlikely for just-answered).
				currentQuestions = allQuestionsFromCurrentFetch.filter(q =>
					incorrectQuestionIdsInThisPass.includes(q.id) && !q.should_skip
				);
				
				incorrectQuestionIdsInThisPass = []; // Clear for the new pass over these incorrect questions.
				
				if (currentQuestions.length > 0) {
					currentQuestionIndex = 0; // Reset index for the new filtered list
					console.log("Restarting pass with incorrect questions:", currentQuestions.map(q=>q.id));
					displayQuestionAtIndex(currentQuestionIndex);
				} else {
					// All questions that were marked incorrect in this pass are now somehow skipped or gone.
					// This implies they might have become 'should_skip' due to backend logic, or an issue.
					// Fallback: reload all pending questions from the backend.
					console.warn("No active questions remain from the incorrect list for this pass. Reloading all pending questions from backend.");
					loadLessonQustions();
				}
			} else {
				// All questions in the current pass (including any retries within it) were answered correctly.
				// Since the lesson is not overall complete (checked at the top),
				// we need to fetch a fresh set of *all* pending questions from the backend.
				// This signifies the end of a "round" based on `allQuestionsFromCurrentFetch`.
				console.log("All questions in this pass/round answered correctly. Lesson not overall complete. Loading next round of questions from backend.");
				loadLessonQustions();
			}
		} else {
			// Move to the next question in the current pass
			console.log(`Moving to next question (index ${currentQuestionIndex + 1} in current pass)`);
			displayQuestionAtIndex(currentQuestionIndex + 1);
		}
	} else { // Incorrect answer
		// Add to incorrect list if not already there for this pass
		if (!incorrectQuestionIdsInThisPass.includes(currentQuestionId)) {
			incorrectQuestionIdsInThisPass.push(currentQuestionId);
		}
		console.log("Wrong answer. Allowing another attempt on the same question. Incorrect IDs this pass:", incorrectQuestionIdsInThisPass);
		// Reset the buttons for another attempt
		questionAnswersContainer.querySelectorAll('.answer-btn').forEach(button => {
			if (!button.classList.contains('incorrect')) { // Only re-enable non-incorrect buttons
				button.classList.remove('selected');
				button.classList.add('btn-outline-primary');
				button.disabled = false;
			}
		});
		setInteractionsDisabled(false); // Re-enable interactions for retry
	}
	updateProgressBar(); // Update progress based on 'currentState' from backend
}


function submitAnswer(index) {
	if (isLoading || interactionsDisabled) {
		return;
	}
	stopPlaybackSequence(true);
	selectedIndex = index;
	setLoadingState(true, 'Checking answer...');
	setErrorState(null);
	
	// Highlight the selected button immediately
	questionAnswersContainer.querySelectorAll('.answer-btn').forEach(btn => {
		btn.classList.remove('selected');
		if (parseInt(btn.dataset.index) === selectedIndex) {
			btn.classList.add('selected');
		}
		btn.disabled = true; // Disable all buttons after selection
	});
	
	fetch(`/question/${currentQuestion.id}/submit`, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
			'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
			'Accept': 'application/json',
		},
		body: JSON.stringify({
			selected_index: index,
			attempt_number: currentAttemptNumber
		})
	})
		.then(response => {
			const status = response.status;
			return response.json().then(data => ({status, data}));
		})
		.then(({status, data}) => {
			setLoadingState(false);
			if (!data.success) {
				let errorMsg = data.message || `HTTP error! status: ${status}`;
				if (data.errors) {
					errorMsg += " " + Object.values(data.errors).flat().join(' ');
				}
				throw new Error(errorMsg);
			}
			console.log('Answer feedback received:', data);
			currentState = data.newState; // CRITICAL: Update global state
			feedbackData = data;         // Store feedback for checkStateAndTransition
			
			showFeedbackModal(data); // This will handle UI updates for correct/incorrect and then modal events trigger checkStateAndTransition
		})
		.catch(error => {
			console.error('Error submitting answer:', error);
			setErrorState(`Failed to submit answer: ${error.message}`);
			selectedIndex = null;
			feedbackData = null;
			// Re-enable buttons if submission failed before feedback
			questionAnswersContainer.querySelectorAll('.answer-btn').forEach(btn => {
				btn.disabled = false;
				btn.classList.remove('selected');
			});
			setLoadingState(false);
			setInteractionsDisabled(false); // Ensure interactions are re-enabled on error
		});
}

function showFeedbackModal(feedbackResult) {
	if (!feedbackModalInstance || !feedbackModalLabel || !feedbackModalText || !playFeedbackModalButton || !modalTryAgainButton || !modalNextButton) {
		console.error("Feedback modal elements not found!");
		// If modal can't show, proceed with state transition logic directly
		checkStateAndTransition();
		return;
	}
	const isCorrect = feedbackResult.was_correct;
	
	feedbackModalLabel.textContent = isCorrect ? 'Correct!' : 'Not Quite...';
	feedbackModalLabel.className = isCorrect ? 'modal-title text-success fw-bold' : 'modal-title text-danger fw-bold';
	feedbackModalText.innerHTML = escapeHtml(feedbackResult.feedback_text || (isCorrect ? 'Well done!' : 'Please try again.'));
	
	// Update answer button styles based on feedback BEFORE showing modal
	// This provides immediate visual feedback on the question screen itself.
	questionAnswersContainer.querySelectorAll('.answer-btn').forEach(button => {
		const btnIndex = parseInt(button.dataset.index);
		button.classList.remove('selected', 'correct', 'incorrect', 'btn-outline-primary', 'btn-primary', 'btn-outline-secondary');
		button.disabled = true; // Keep all buttons disabled while modal is up / feedback is shown
		
		if (btnIndex === feedbackResult.correct_index) {
			button.classList.add('correct'); // Solid green for correct
		} else if (btnIndex === selectedIndex && !isCorrect) { // User's incorrect selection
			button.classList.add('incorrect'); // Solid red for selected incorrect
		} else {
			button.classList.add('btn-outline-secondary'); // Muted outline for others
		}
	});
	
	toggleElement(modalTryAgainButton, !isCorrect);
	toggleElement(modalNextButton, isCorrect || currentState.status === 'completed'); // Show Next if correct OR if lesson is now complete
	
	if (feedbackResult.feedback_audio_url) {
		playFeedbackModalButton.dataset.audioUrl = feedbackResult.feedback_audio_url;
		toggleElement(playFeedbackModalButton, true);
		playFeedbackModalButton.innerHTML = '<i class="fas fa-volume-up me-1"></i> Play Feedback Audio';
		toggleElement(feedbackAudioError, false);
		
		if (isAutoPlayEnabled) {
			if (modalTryAgainButton) modalTryAgainButton.disabled = true;
			if (modalNextButton) modalNextButton.disabled = true;
			setTimeout(() => {
				if (document.getElementById('feedbackModal').classList.contains('show')) { // Check if modal is still visible
					playFeedbackModalButton.click();
				}
			}, 300);
		} else {
			if (modalTryAgainButton) modalTryAgainButton.disabled = false;
			if (modalNextButton) modalNextButton.disabled = false;
		}
	} else {
		toggleElement(playFeedbackModalButton, false);
		playFeedbackModalButton.dataset.audioUrl = '';
		if (modalTryAgainButton) modalTryAgainButton.disabled = false;
		if (modalNextButton) modalNextButton.disabled = false;
	}
	
	isModalVisible = true; // Set before showing
	feedbackModalInstance.show();
	// Interactions are managed by modal's shown/hidden events and setLoadingState
}

function showQuestionScreen() {
	isIntroVisible = false;
	feedbackData = null;
	toggleElement(IntroArea, false);
	toggleElement(completionMessage, false);
	toggleElement(questionArea, true);
	updateProgressBar(); // Ensure progress bar is updated
}

function showCompletionScreen() {
	console.log("Showing completion screen");
	stopPlaybackSequence(true);
	isIntroVisible = false;
	feedbackData = null;
	currentQuestions = [];
	allQuestionsFromCurrentFetch = [];
	incorrectQuestionIdsInThisPass = [];
	currentQuestionIndex = -1;
	currentQuestion = null;
	
	toggleElement(IntroArea, false);
	toggleElement(questionArea, false);
	toggleElement(completionMessage, true); // Use the general completionMessage element
	
	// Update the content of completionMessage if it's generic
	const completionMsgEl = document.getElementById('completionMessage');
	if (completionMsgEl) {
		completionMsgEl.innerHTML = `
            <div class="alert alert-success" role="alert">
                <h4 class="alert-heading"><i class="fas fa-check-circle me-2"></i> Lesson Complete!</h4>
                <p>Congratulations, you've successfully answered all questions for this lesson.</p>
                <hr>
                <a href="${document.querySelector('a.navbar-brand').href || '/lessons'}" class="btn btn-primary mt-2">Back to Lessons List</a>
            </div>`;
	}
	
	updateProgressBar(); // Ensure progress bar shows 100% or final state
	setInteractionsDisabled(false);
	updateButtonStates(5);
}

function updateUI() {
	updateProgressBar();
	if (isIntroVisible) {
		updateButtonStates(6);
		return;
	}
	// If completion message is already visible, no need to update further UI for questions
	if (!completionMessage.classList.contains('d-none')) {
		setInteractionsDisabled(false); // Ensure interactions are enabled on completion screen
		updateButtonStates(0); // Generic update
		return;
	}
	
	if (currentState.status === 'completed') {
		showCompletionScreen();
		return;
	}
	if (questionArea.classList.contains('d-none') || !currentQuestion) {
		// This might be between loading questions or if intro is supposed to be shown
		// updateButtonStates will handle disabling if interactionsDisabled is true
		updateButtonStates(7);
		return;
	}
	updateButtonStates(8);
}

function setupQuestionAnswerEventListeners() {
	questionAnswersContainer.addEventListener('click', (event) => {
		const targetButton = event.target.closest('.answer-btn');
		if (targetButton && !targetButton.disabled) {
			// Clear keyboard focus if a mouse click occurs
			if (keyboardFocusIndex !== -1) {
				const focusedButton = questionAnswersContainer.querySelector('.keyboard-focus');
				if (focusedButton) focusedButton.classList.remove('keyboard-focus');
				keyboardFocusIndex = -1;
			}
			
			// Disable all buttons immediately on click to prevent double submission
			questionAnswersContainer.querySelectorAll('.answer-btn').forEach(btn => btn.disabled = true);
			submitAnswer(parseInt(targetButton.dataset.index, 10));
		}
	});
}

function initQuestionInterface() {
	console.log("Initializing Interactive Question...");
	console.log("Initial State from backend:", currentState);
	setLoadingState(true, 'Initializing...');
	
	if (!currentState || !lessonId) {
		setErrorState("Failed to load initial question state. Please try refreshing the page.");
		setLoadingState(false);
		return;
	}
	
	// Initialize tracking arrays
	allQuestionsFromCurrentFetch = [];
	currentQuestions = [];
	incorrectQuestionIdsInThisPass = [];
	
	console.log("Lesson Intro data:", lessonIntro);
	
	if (currentState.status === 'completed') {
		showCompletionScreen();
	} else if (currentState.status === 'inprogress' || currentState.status === 'empty') { // Treat 'empty' (no questions yet) as needing intro
		showIntro(); // This will then lead to loadLessonQustions via "Start Questions" button
	} else {
		setErrorState("Invalid starting state detected. Please try refreshing.");
		toggleElement(IntroArea, false);
		toggleElement(questionArea, false);
	}
	setLoadingState(false);
	console.log("Interactive Question Initialized.");
}

function setupAutoPlaySwitchListener() {
	if (autoPlayAudioSwitch) {
		autoPlayAudioSwitch.addEventListener('change', () => {
			isAutoPlayEnabled = autoPlayAudioSwitch.checked;
			localStorage.setItem('autoPlayAudioEnabled', isAutoPlayEnabled);
			console.log('Auto-play audio:', isAutoPlayEnabled ? 'Enabled' : 'Disabled');
			if (!isAutoPlayEnabled && isAutoPlaying) {
				stopPlaybackSequence(true);
			}
		});
	}
}

function setupModalEventListeners() {
	if (modalTryAgainButton) {
		modalTryAgainButton.addEventListener('click', () => {
			console.log('Try Again clicked');
			feedbackModalInstance.hide(); // Hiding the modal will trigger 'hidden.bs.modal'
		                                // which then calls checkStateAndTransition
			// No need to call checkStateAndTransition directly here, let the modal event handle it.
		});
	}
	
	if (feedbackModal) {
		if (!feedbackModalInstance) { // Ensure instance is created only once
			feedbackModalInstance = new bootstrap.Modal(feedbackModal, {
				backdrop: 'static', // Keep static backdrop
				keyboard: false     // Keep keyboard false
			});
		}
		
		feedbackModal.addEventListener('hidden.bs.modal', () => {
			console.log('Feedback modal hidden.');
			isModalVisible = false;
			if (feedbackAudioPlayer && !feedbackAudioPlayer.paused) {
				feedbackAudioPlayer.pause();
				feedbackAudioPlayer.currentTime = 0;
			}
			toggleElement(feedbackAudioError, false);
			
			// Crucially, call checkStateAndTransition to decide the next step
			// This is where logic for correct (next/reload) or incorrect (retry UI reset) happens.
			checkStateAndTransition();
			
			// Interactions are re-enabled by checkStateAndTransition or setLoadingState(false)
			// or explicitly if staying on the same question for retry.
		});
		
		feedbackModal.addEventListener('shown.bs.modal', () => {
			console.log('Feedback modal shown.');
			isModalVisible = true;
			setInteractionsDisabled(true); // Disable background interactions while modal is visible
			updateButtonStates(10);
			
			if (modalTryAgainButton && !modalTryAgainButton.classList.contains('d-none')) {
				modalTryAgainButton.focus();
			} else if (modalNextButton && !modalNextButton.classList.contains('d-none')) {
				modalNextButton.focus();
			}
		});
	}
	
	if (modalNextButton) {
		modalNextButton.addEventListener('click', () => {
			console.log('Next Question / Continue clicked');
			feedbackModalInstance.hide(); // Hiding the modal will trigger 'hidden.bs.modal'
		                                // which then calls checkStateAndTransition
		});
	}
	
	if (playFeedbackModalButton && feedbackAudioPlayer) {
		playFeedbackModalButton.addEventListener('click', () => {
			const audioUrl = playFeedbackModalButton.dataset.audioUrl;
			toggleElement(feedbackAudioError, false);
			if (audioUrl) {
				if (!feedbackAudioPlayer.paused) {
					feedbackAudioPlayer.pause();
					// If user manually pauses, re-enable modal buttons
					if (modalTryAgainButton) modalTryAgainButton.disabled = false;
					if (modalNextButton) modalNextButton.disabled = false;
				} else {
					// If auto-play is on, buttons might have been disabled. Keep them disabled during play.
					if (isAutoPlayEnabled) {
						if (modalTryAgainButton) modalTryAgainButton.disabled = true;
						if (modalNextButton) modalNextButton.disabled = true;
					}
					feedbackAudioPlayer.src = audioUrl;
					feedbackAudioPlayer.play().catch(e => {
						console.error("Feedback audio playback error:", e);
						feedbackAudioError.textContent = 'Audio playback error.';
						toggleElement(feedbackAudioError, true);
						// Re-enable buttons if playback fails
						if (modalTryAgainButton) modalTryAgainButton.disabled = false;
						if (modalNextButton) modalNextButton.disabled = false;
					});
				}
			}
		});
		
		feedbackAudioPlayer.onended = () => {
			console.log('Feedback audio ended.');
			playFeedbackModalButton.innerHTML = '<i class="fas fa-volume-up me-1"></i> Play Feedback Audio';
			// Re-enable modal buttons after audio finishes
			if (modalTryAgainButton) modalTryAgainButton.disabled = false;
			if (modalNextButton) modalNextButton.disabled = false;
		};
		feedbackAudioPlayer.onpause = () => { // When paused manually or by ending
			playFeedbackModalButton.innerHTML = '<i class="fas fa-volume-up me-1"></i> Play Feedback Audio';
		};
		feedbackAudioPlayer.onplaying = () => {
			playFeedbackModalButton.innerHTML = '<i class="fas fa-pause me-1"></i> Pause Feedback';
		};
		feedbackAudioPlayer.onerror = () => {
			playFeedbackModalButton.innerHTML = '<i class="fas fa-volume-up me-1"></i> Play Feedback Audio';
			feedbackAudioError.textContent = 'Audio playback error.';
			toggleElement(feedbackAudioError, true);
			// Re-enable modal buttons on error
			if (modalTryAgainButton) modalTryAgainButton.disabled = false;
			if (modalNextButton) modalNextButton.disabled = false;
		};
	}
}

function setupStartOverIntroButtonListener() {
	// const introData = window.lessonIntro; // Already available globally
	// const introTitle = introData.title; // Not directly used here
	if (startOverIntroButton) {
		startOverIntroButton.addEventListener('click', () => {
			console.log("Start Over Intro clicked");
			if (window.lessonIntro && window.lessonIntro.sentences && window.lessonIntro.sentences.length > 0 && window.lessonIntro.has_audio) {
				stopPlaybackSequence(false);
				buildIntroPlaybackQueue(window.lessonIntro.sentences);
				startPlaybackSequence(true); // Force start playback even if global autoplay is off for this action
			} else {
				console.warn("No audio sentences found in lessonIntro to start over.");
			}
		});
	}
}

// Ensure all DOM elements are defined in the DOMContentLoaded event listener
// The global variable declarations at the top of lesson_interface.blade.php are fine.
// The assignments happen within DOMContentLoaded.

// Make sure escapeHtml is available if not already in common.js or defined here
function escapeHtml(text) {
	if (typeof text !== 'string') return '';
	const div = document.createElement('div');
	div.textContent = text;
	return div.innerHTML;
}

/**
 * Updates which answer button has keyboard focus.
 * @param {number} newIndex - The index in the `buttons` array to focus. -1 to clear.
 * @param {HTMLElement[]} buttons - The array of currently available buttons.
 */
function updateKeyboardFocus(newIndex, buttons) {
	// Remove focus from the previously focused button
	if (keyboardFocusIndex !== -1 && buttons[keyboardFocusIndex]) {
		buttons[keyboardFocusIndex].classList.remove('keyboard-focus');
	}
	
	keyboardFocusIndex = newIndex;
	
	// Add focus to the new button
	if (keyboardFocusIndex !== -1 && buttons[keyboardFocusIndex]) {
		const buttonToFocus = buttons[keyboardFocusIndex];
		buttonToFocus.classList.add('keyboard-focus');
		buttonToFocus.focus(); // Also set native browser focus for accessibility
	}
}

/**
 * Handles keyboard navigation for answer selection.
 */
function handleQuestionKeydown(event) {
	// Only act if the question area is visible and interactions are allowed
	if (isLoading || interactionsDisabled || isModalVisible || questionArea.classList.contains('d-none')) {
		return;
	}
	
	const availableButtons = Array.from(questionAnswersContainer.querySelectorAll('.answer-btn:not(:disabled)'));
	if (availableButtons.length === 0) {
		return;
	}
	
	let keyHandled = false;
	
	if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
		keyHandled = true;
		let nextIndex = keyboardFocusIndex;
		
		if (keyboardFocusIndex === -1) {
			// If no button is focused, ArrowDown starts at the first, ArrowUp at the last.
			nextIndex = (event.key === 'ArrowDown') ? 0 : availableButtons.length - 1;
		} else {
			if (event.key === 'ArrowDown') {
				nextIndex = (keyboardFocusIndex + 1) % availableButtons.length;
			} else { // ArrowUp
				nextIndex = (keyboardFocusIndex - 1 + availableButtons.length) % availableButtons.length;
			}
		}
		updateKeyboardFocus(nextIndex, availableButtons);
		
	} else if ((event.key === 'Enter' || event.key === ' ') && keyboardFocusIndex !== -1) {
		const focusedButton = availableButtons[keyboardFocusIndex];
		if (focusedButton) {
			keyHandled = true;
			focusedButton.click();
		}
	}
	
	if (keyHandled) {
		event.preventDefault();
	}
}
