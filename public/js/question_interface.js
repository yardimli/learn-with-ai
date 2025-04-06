function loadQuestionsForLevel(partIndex) {
	if (isLoading) return;
	setLoadingState(true, `Loading questions for Part ${partIndex + 1}...`);
	setErrorState(null);
	currentPartQuestions = []; // Clear old questions
	currentQuestionIndex = -1;
	currentQuestion = null;
	displayedPartIndex = partIndex;
	updateProgressBar();
	
	fetch(`/lesson/${subjectSessionId}/part-questions`, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
			'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
			'Accept': 'application/json',
		},
		body: JSON.stringify({partIndex}) // No difficulty parameter needed anymore
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
			
			if (!data.questions || data.questions.length === 0) {
				console.warn(`No questions returned for Part ${partIndex}.`);
				setErrorState(`No questions found for Part ${partIndex + 1}. You can try another part.`);
				toggleElement(questionArea, false);
				toggleElement(partIntroArea, false);
				setLoadingState(false);
				return;
			}
			
			console.log(`Loaded ${data.questions.length} questions for Part ${partIndex}`);
			
			// Filter out questions that should be skipped (correct in last attempt with no wrong answers)
			currentPartQuestions = data.questions.filter(question => !question.should_skip);
			
			if (currentPartQuestions.length === 0) {
				console.log("All questions in this part were correctly answered!");
				// Show part completion or move to next part
				showPartCompletionMessage(partIndex);
				setLoadingState(false);
				return;
			}
			
			currentQuestionIndex = 0;
			showQuestionScreen();
			displayQuestionAtIndex(currentQuestionIndex);
			setLoadingState(false);
		})
		.catch(error => {
			console.error('Error loading part questions:', error);
			setErrorState(`Error: ${error.message}`);
			setLoadingState(false);
			toggleElement(questionArea, false);
			toggleElement(partIntroArea, false);
		});
}

function showPartCompletionMessage(partIndex) {
	setErrorState(null);
	toggleElement(questionArea, false);
	toggleElement(partIntroArea, false);
	
	// If we have completion message element
	const partCompletionMsg = document.getElementById('partCompletionMessage');
	if (partCompletionMsg) {
		partCompletionMsg.innerHTML = `
            <div class="alert alert-success" role="alert">
                <h4 class="alert-heading"><i class="fas fa-check-circle me-2"></i>Part ${partIndex + 1} Complete!</h4>
                <p>You've successfully answered all questions in this part.</p>
                <hr>
                <div class="d-flex justify-content-center">
                    ${partIndex < totalParts - 1 ?
			`<button id="continueToNextPartBtn" class="btn btn-primary me-2">Continue to Part ${partIndex + 2}</button>` :
			''}
                </div>
            </div>
        `;
		toggleElement(partCompletionMsg, true);
		
		// Add event listeners
		const continueBtn = document.getElementById('continueToNextPartBtn');
		if (continueBtn && partIndex < totalParts - 1) {
			continueBtn.addEventListener('click', () => {
				toggleElement(partCompletionMsg, false);
				showPartIntro(partIndex + 1);
			});
		}
	} else {
		// Fallback if no dedicated element
		if (partIndex < totalParts - 1) {
			showPartIntro(partIndex + 1);
		} else {
			showCompletionScreen();
		}
	}
}

function displayQuestionAtIndex(index) {
	if (index < 0 || index >= currentPartQuestions.length) {
		console.error(`Invalid question index requested: ${index}`);
		checkStateAndTransition();
		return;
	}
	
	currentQuestionIndex = index;
	currentQuestion = currentPartQuestions[index];
	selectedIndex = null;
	
	// Store the current attempt number for this question
	currentAttemptNumber = currentQuestion.next_attempt_number || 1;
	
	console.log(`Displaying question index ${index} (ID: ${currentQuestion.id}, Attempt: ${currentAttemptNumber})`);
	
	// Randomize answer order before displaying (while preserving correct answer)
	if (currentQuestion.answers && Array.isArray(currentQuestion.answers)) {
		//shuffleAnswers();
	}
	
	updateUIForQuestion();
	
	// Reset button styles from previous feedback
	questionAnswersContainer.querySelectorAll('.answer-btn').forEach(button => {
		button.classList.remove('selected', 'correct', 'incorrect', 'btn-success', 'btn-danger', 'btn-outline-secondary');
		button.classList.add('btn-outline-primary');
	});
	
	setInteractionsDisabled(true);
	buildPlaybackQueue(currentQuestion);
	startPlaybackSequence();
}

function updateUIForQuestion() {
	if (!currentQuestion) {
		console.error("updateUIForQuestion called but currentQuestion is null");
		return; // Or hide question area
	}
	
	if (questionDifficulty) {
		questionDifficulty.textContent = `Part ${currentQuestion.lesson_part_index + 1} - ${capitalizeFirstLetter(currentQuestion.difficulty_level)}`;
	}
	if (questionTextElement) {
		questionTextElement.textContent = currentQuestion.question_text;
	}
	
	// Image Display
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
	
	// Answer Buttons
	console.log("Setting up answer buttons");
	questionAnswersContainer.innerHTML = ''; // Clear old buttons

// Create buttons and store them temporarily
	const buttons = []; // Array to hold the button elements
	currentQuestion.answers.forEach((answer, idx) => {
		const button = document.createElement('button');
		button.type = 'button';
		button.id = `answerBtn_${idx}`;
		button.classList.add('btn', 'btn-outline-primary', 'btn-lg', 'answer-btn', 'w-100', 'mb-2');
		button.dataset.index = idx; // Store the ORIGINAL index before shuffling
		button.textContent = answer.text;
		button.disabled = interactionsDisabled; // Initial state based on current interaction status
		buttons.push(button); // Add the created button to our array
	});

// --- Shuffle the buttons array ---
// Fisher-Yates (aka Knuth) Shuffle Algorithm
	for (let i = buttons.length - 1; i > 0; i--) {
		// Pick a random index from 0 to i
		const j = Math.floor(Math.random() * (i + 1));
		// Swap elements buttons[i] and buttons[j]
		[buttons[i], buttons[j]] = [buttons[j], buttons[i]];
	}
// --- End Shuffle ---

// Append the shuffled buttons to the container
	buttons.forEach(button => {
		questionAnswersContainer.appendChild(button);
	});
	
	console.log("Answer buttons created and shuffled.");
	
	
	updateButtonStates();
}

function checkStateAndTransition() {
	console.log("Checking state and transitioning after feedback");
	
	const wasCorrect = feedbackData.was_correct || false;
	const partCompleted = feedbackData.part_completed || false;
	const lessonCompleted = feedbackData.lesson_completed || false;
	
	if (lessonCompleted) {
		console.log("Transition: Lesson Completed");
		showCompletionScreen();
		setInteractionsDisabled(false);
		return;
	}
	
	if (wasCorrect) {
		// If this was the last question in the current batch
		if (currentQuestionIndex >= currentPartQuestions.length - 1) {
			console.log("Last question in current batch answered correctly");
			
			if (partCompleted) {
				console.log(`Part ${currentQuestion.lesson_part_index} completed!`);
				showPartCompletionMessage(currentQuestion.lesson_part_index);
			} else {
				console.log(`Part ${currentQuestion.lesson_part_index} not fully completed. Reloading questions.`);
				// Reload questions for the same part to get any remaining ones
				loadQuestionsForLevel(currentQuestion.lesson_part_index);
			}
		} else {
			// Move to next question in the current batch
			console.log(`Moving to next question (index ${currentQuestionIndex + 1})`);
			displayQuestionAtIndex(currentQuestionIndex + 1);
		}
	} else {
		// Wrong answer - stay on the same question for another attempt
		console.log("Wrong answer. Allowing another attempt on the same question.");
		// Reset the buttons for another attempt
		questionAnswersContainer.querySelectorAll('.answer-btn').forEach(button => {
			button.classList.remove('selected', 'correct', 'incorrect', 'btn-success', 'btn-danger');
			button.classList.add('btn-outline-primary');
			button.disabled = false;
		});
		setInteractionsDisabled(false);
	}
	
	updateProgressBar();
}

function submitAnswer(index) {
	if (isLoading || interactionsDisabled) {
		return;
	}
	
	stopPlaybackSequence(true); // Stop TTS, allow interaction temporarily
	selectedIndex = index;
	setLoadingState(true, 'Checking answer...');
	setErrorState(null);
	
	// Update button visually immediately
	console.log(`Submitting answer index ${index} for question ID ${currentQuestion.id} and disabling buttons`);
	questionAnswersContainer.querySelectorAll('.answer-btn').forEach(btn => {
		btn.classList.remove('selected');
		if (parseInt(btn.dataset.index) === index) {
			btn.classList.add('selected');
		}
		btn.disabled = true;
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
			attempt_number: currentAttemptNumber // Include the attempt number
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
				throw new Error(errorMsg);
			}
			
			console.log('Answer feedback received:', data);
			currentState = data.newState;
			feedbackData = data; // Store feedback data including part_completed status
			showFeedbackModal(data);
		})
		.catch(error => {
			console.error('Error submitting answer:', error);
			setErrorState(`Failed to submit answer: ${error.message}`);
			selectedIndex = null;
			feedbackData = null;
			
			questionAnswersContainer.querySelectorAll('.answer-btn').forEach(btn => {
				btn.disabled = false;
				btn.classList.remove('selected');
			});
			
			setLoadingState(false);
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
	console.log("Updating answer button styles based on feedback, and disabling them");
	questionAnswersContainer.querySelectorAll('.answer-btn').forEach(button => {
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

function showQuestionScreen() {
	isPartIntroVisible = false;
	feedbackData = null; // Ensure feedback is cleared
	
	if (currentQuestion) {
		displayedPartIndex = currentQuestion.lesson_part_index;
	} else if (currentPartQuestions.length > 0) {
		// Fallback if currentQuestion isn't set yet but we have the list
		displayedPartIndex = currentPartQuestions[0].lesson_part_index;
	}
	
	// Hide Intro Area, Show Question Area
	toggleElement(partIntroArea, false);
	toggleElement(completionMessage, false);
	toggleElement(questionArea, true);
	questionArea.dataset.currentPartIndex = currentState.partIndex; // Store current part index
}

function showCompletionScreen() {
	console.log("Showing completion screen");
	stopPlaybackSequence(true); // Stop any audio
	isPartIntroVisible = false;
	feedbackData = null;
	currentPartQuestions = [];
	currentQuestionIndex = -1;
	currentQuestion = null;
	
	displayedPartIndex = totalParts - 1;
	
	toggleElement(partIntroArea, false);
	toggleElement(questionArea, false);
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
	
	if (questionArea.classList.contains('d-none') || !currentQuestion) {
		updateButtonStates();
		return;
	}

	updateButtonStates();
}

// --- Event Listeners ---
function setupQuestionAnswerEventListeners() {
	questionAnswersContainer.addEventListener('click', (event) => {
		const targetButton = event.target.closest('.answer-btn');
		if (targetButton && !targetButton.disabled) {
			submitAnswer(parseInt(targetButton.dataset.index, 10));
		}
	});
}

// --- Initialization ---
function initQuestionInterface() {
	console.log("Initializing Interactive Question...");
	console.log("Initial State:", currentState);
	console.log("Total Parts:", totalParts);
	
	setLoadingState(true, 'Initializing...');
	
	if (!currentState || !subjectSessionId) {
		setErrorState("Failed to load initial question state. Please try refreshing the page.");
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
	console.log("Interactive Question Initialized.");
}


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
			feedbackModalInstance.hide();
			selectedIndex = null; // Clear selection
			
			// Reset answer button styles and re-enable them
			console.log("Resetting answer button styles for another attempt, enabling them");
			questionAnswersContainer.querySelectorAll('.answer-btn').forEach(button => {
				button.classList.remove('selected', 'correct', 'incorrect', 'btn-success', 'btn-danger', 'btn-outline-secondary');
				button.classList.add('btn-outline-primary');
				button.disabled = false; // Re-enable
			});
			// No state transition, just allow another attempt on the same question.
			// Interactions should be re-enabled by the 'hidden.bs.modal' listener if not loading.
		});
	}
	
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
				console.log('Modal closed, re-enabling interactions');
				setInteractionsDisabled(false);
			}
			console.log('Modal closed, refreshing button states');
			updateButtonStates(); // Refresh button states after modal closes
		});
		feedbackModal.addEventListener('shown.bs.modal', () => {
			isModalVisible = true;
			setInteractionsDisabled(true); // Ensure interactions are off while modal is shown
			updateButtonStates(); // Refresh button states after modal opens
		});
	}
	
	if (modalNextButton) {
		modalNextButton.addEventListener('click', () => {
			console.log('Next Question clicked');
			feedbackModalInstance.hide();
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
