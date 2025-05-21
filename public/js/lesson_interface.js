function loadQuestionsForLevel() {
	if (isLoading) return;
	setLoadingState(true, `Loading questions...`);
	setErrorState(null);
	currentQuestions = []; // Clear old questions
	currentQuestionIndex = -1;
	currentQuestion = null;
	updateProgressBar();
	
	fetch(`/lesson/${lessonId}/questions`, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
			'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
			'Accept': 'application/json',
		},
		body: JSON.stringify([])
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
				throw new Error(data.message || 'Failed to fetch questions.');
			}
			
			if (!data.questions || data.questions.length === 0) {
				console.warn(`No questions returned.`);
				setErrorState(`No questions found.`);
				toggleElement(questionArea, false);
				toggleElement(IntroArea, false);
				setLoadingState(false);
				return;
			}
			
			console.log(`Loaded ${data.questions.length} questions`);
			// Filter out questions that should be skipped (correct in last attempt with no wrong answers)
			currentQuestions = data.questions.filter(question => !question.should_skip);
			
			if (currentQuestions.length === 0) {
				console.log("All questions were correctly answered!");
				showCompletionMessage();
				setLoadingState(false);
				return;
			}
			
			currentQuestionIndex = 0;
			showQuestionScreen();
			displayQuestionAtIndex(currentQuestionIndex);
			setLoadingState(false);
		})
		.catch(error => {
			console.error('Error loading questions:', error);
			setErrorState(`Error: ${error.message}`);
			setLoadingState(false);
			toggleElement(questionArea, false);
			toggleElement(IntroArea, false);
		});
}

function showCompletionMessage() {
	setErrorState(null);
	toggleElement(questionArea, false);
	toggleElement(IntroArea, false);
	
	// If we have completion message element
	const CompletionMsg = document.getElementById('CompletionMessage');
	if (CompletionMsg) {
		CompletionMsg.innerHTML = `
            <div class="alert alert-success" role="alert">
                <h4 class="alert-heading"><i class="fas fa-check-circle me-2"></i>Complete!</h4>
                <p>You've successfully answered all questions.</p>
                <hr>
            </div>
        `;
		toggleElement(CompletionMsg, true);
	} else {
		showIntro();
	}
}

function displayQuestionAtIndex(index) {
	if (index < 0 || index >= currentQuestions.length) {
		console.error(`Invalid question index requested: ${index}`);
		checkStateAndTransition();
		return;
	}
	
	currentQuestionIndex = index;
	currentQuestion = currentQuestions[index];
	selectedIndex = null;
	
	// Store the current attempt number for this question
	currentAttemptNumber = currentQuestion.next_attempt_number || 1;
	
	console.log(`Displaying question index ${index} (ID: ${currentQuestion.id}, Attempt: ${currentAttemptNumber})`);
	
	updateUIForQuestion();
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
		questionDifficulty.textContent = `${capitalizeFirstLetter(currentQuestion.difficulty_level)}`;
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
		button.id = `answerBtn_${answer.index}`;
		button.classList.add('btn', 'btn-outline-primary', 'btn-lg', 'answer-btn', 'w-100', 'mb-2');
		button.dataset.index = answer.index;
		button.textContent = answer.text;
		button.disabled = interactionsDisabled; // Initial state based on current interaction status
		buttons.push(button); // Add the created button to our array
	});
	
	
	buttons.forEach(button => {
		questionAnswersContainer.appendChild(button);
	});
	
	console.log("Answer buttons created and shuffled.");
	
	
	updateButtonStates(4);
}

function checkStateAndTransition() {
	console.log("Checking state and transitioning after feedback");
	
	const wasCorrect = feedbackData.was_correct || false;
	const lessonCompleted = feedbackData.lesson_completed || false;
	
	if (lessonCompleted) {
		console.log("Transition: Lesson Completed");
		showCompletionScreen();
		setInteractionsDisabled(false);
		return;
	}
	
	if (wasCorrect) {
		// If this was the last question in the current batch
		if (currentQuestionIndex >= currentQuestions.length - 1) {
			console.log("Last question answered correctly");
			showCompletionScreen();
			setInteractionsDisabled(false);
			return;
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
			if (!button.classList.contains('incorrect')) {
				button.classList.remove('selected'); //'correct',
				button.classList.add('btn-outline-primary');
				button.disabled = false;
			}
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
			feedbackData = data;
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
	
	let feedbackAudioCompleted = !feedbackResult.feedback_audio_url; // True if no audio to play
	
	// Update modal content
	feedbackModalLabel.textContent = isCorrect ? 'Correct!' : 'Not Quite...';
	feedbackModalLabel.className = isCorrect ? 'modal-title text-success' : 'modal-title text-danger'; // Add color
	feedbackModalText.textContent = feedbackResult.feedback_text || (isCorrect ? 'Well done!' : 'Please try again.');
	
	// Update answer button styles based on feedback BEFORE showing modal
	console.log("Updating answer button styles based on feedback, and disabling them");
	questionAnswersContainer.querySelectorAll('.answer-btn').forEach(button => {
		if (button.classList.contains('incorrect')) return;
		const btnIndex = parseInt(button.dataset.index);
		button.classList.remove('selected', 'correct', 'incorrect', 'btn-outline-primary', 'btn-primary', 'btn-outline-secondary');
		button.disabled = true; // Keep disabled
		
		if (btnIndex === feedbackResult.correct_index) {
			button.classList.add('correct'); // Solid green for correct
		} else if (btnIndex === selectedIndex) { // User's incorrect selection
			button.classList.add('incorrect'); // Solid red for selected incorrect
		} else {
			button.classList.add('btn-outline-secondary'); // Muted outline for others
		}
	});
	
	toggleElement(modalTryAgainButton, !isCorrect); // Show "Try Again" if incorrect
	toggleElement(modalNextButton, isCorrect); // Show "Next Question" if correct
	
	
	// Configure feedback audio button
	if (feedbackResult.feedback_audio_url) {
		playFeedbackModalButton.dataset.audioUrl = feedbackResult.feedback_audio_url;
		toggleElement(playFeedbackModalButton, true);
		playFeedbackModalButton.innerHTML = '<i class="fas fa-volume-up me-1"></i> Play Feedback Audio';
		toggleElement(feedbackAudioError, false);
		
		// Disable next/try again buttons initially if auto-play is enabled
		if (isAutoPlayEnabled) {
			if (modalTryAgainButton) modalTryAgainButton.disabled = true;
			if (modalNextButton) modalNextButton.disabled = true;
			
			// Auto-play the feedback audio
			setTimeout(() => {
				playFeedbackModalButton.click();
			}, 300);
		}
	} else {
		toggleElement(playFeedbackModalButton, false);
		playFeedbackModalButton.dataset.audioUrl = '';
		
		// No audio, so buttons should be enabled
		if (modalTryAgainButton) modalTryAgainButton.disabled = false;
		if (modalNextButton) modalNextButton.disabled = false;
	}
	
	// Show the modal
	feedbackModalInstance.show();
	// Interactions are disabled via the 'shown.bs.modal' event listener
}

function showQuestionScreen() {
	isIntroVisible = false;
	feedbackData = null; // Ensure feedback is cleared
	
	// Hide Intro Area, Show Question Area
	toggleElement(IntroArea, false);
	toggleElement(completionMessage, false);
	toggleElement(questionArea, true);
}

function showCompletionScreen() {
	console.log("Showing completion screen");
	stopPlaybackSequence(true); // Stop any audio
	isIntroVisible = false;
	feedbackData = null;
	currentQuestions = [];
	currentQuestionIndex = -1;
	currentQuestion = null;
	
	toggleElement(IntroArea, false);
	toggleElement(questionArea, false);
	toggleElement(completionMessage, true);
	
	updateProgressBar(); // Ensure progress bar shows 100%
	setInteractionsDisabled(false); // Ensure interactions enabled on final screen
	updateButtonStates(5);
}

function updateUI() {
	updateProgressBar();
	
	// If intro is visible, only update its buttons and return
	if (isIntroVisible) {
		updateButtonStates(6);
		return;
	}
	
	if (currentState.status === 'completed') {
		if (!completionMessage.classList.contains('d-none')) return; // Already visible
		showCompletionScreen();
		return;
	}
	
	if (questionArea.classList.contains('d-none') || !currentQuestion) {
		updateButtonStates(7);
		return;
	}
	
	updateButtonStates(8);
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
	
	setLoadingState(true, 'Initializing...');
	
	if (!currentState || !lessonId) {
		setErrorState("Failed to load initial question state. Please try refreshing the page.");
		setLoadingState(false);
		return;
	}
	console.log(lessonIntro);
	// Determine initial view: Completion or Intro
	if (currentState.status === 'completed') {
		showCompletionScreen();
	} else if (currentState.status === 'inprogress') {
			showIntro();
	} else {
		setErrorState("Invalid starting state detected. Please try refreshing.");
		toggleElement(IntroArea, false);
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
			updateButtonStates(9);
			checkStateAndTransition();
		});
		feedbackModal.addEventListener('shown.bs.modal', () => {
			isModalVisible = true;
			setInteractionsDisabled(true); // Ensure interactions are off while modal is shown
			updateButtonStates(10);
		});
	}
	
	if (modalNextButton) {
		modalNextButton.addEventListener('click', () => {
			console.log('Next Question clicked');
			feedbackModalInstance.hide();
			// Now trigger the state transition logic
		});
	}
	
	if (playFeedbackModalButton && feedbackAudioPlayer) {
		playFeedbackModalButton.addEventListener('click', () => {
			const audioUrl = playFeedbackModalButton.dataset.audioUrl;
			toggleElement(feedbackAudioError, false);
			
			if (audioUrl) {
				if (!feedbackAudioPlayer.paused) {
					feedbackAudioPlayer.pause();
					feedbackAudioPlayer.currentTime = 0;
					
					// If user manually stops, enable buttons
					if (modalTryAgainButton) modalTryAgainButton.disabled = false;
					if (modalNextButton) modalNextButton.disabled = false;
				} else {
					// Disable buttons when starting playback
					if (isAutoPlayEnabled) {
						if (modalTryAgainButton) modalTryAgainButton.disabled = true;
						if (modalNextButton) modalNextButton.disabled = true;
					}
					
					feedbackAudioPlayer.src = audioUrl;
					feedbackAudioPlayer.play().catch(e => {
						console.error("Feedback audio playback error:", e);
						feedbackAudioError.textContent = 'Audio playback error.';
						toggleElement(feedbackAudioError, true);
						
						// Enable buttons if playback fails
						if (modalTryAgainButton) modalTryAgainButton.disabled = false;
						if (modalNextButton) modalNextButton.disabled = false;
					});
				}
			}
		});
		
		// Update event listeners for feedback audio player
		feedbackAudioPlayer.onended = () => {
			console.log('Feedback audio ended.');
			playFeedbackModalButton.innerHTML = '<i class="fas fa-volume-up me-1"></i> Play Feedback Audio';
			
			// Enable buttons after audio completes
			if (modalTryAgainButton) modalTryAgainButton.disabled = false;
			if (modalNextButton) modalNextButton.disabled = false;
		};
		
		feedbackAudioPlayer.onpause = () => {
			playFeedbackModalButton.innerHTML = '<i class="fas fa-volume-up me-1"></i> Play Feedback Audio';
		};
		
		feedbackAudioPlayer.onplaying = () => {
			playFeedbackModalButton.innerHTML = '<i class="fas fa-pause me-1"></i> Pause Feedback';
		};
		
		feedbackAudioPlayer.onerror = () => {
			playFeedbackModalButton.innerHTML = '<i class="fas fa-volume-up me-1"></i> Play Feedback Audio';
			feedbackAudioError.textContent = 'Audio playback error.';
			toggleElement(feedbackAudioError, true);
			
			// Enable buttons if there's an error
			if (modalTryAgainButton) modalTryAgainButton.disabled = false;
			if (modalNextButton) modalNextButton.disabled = false;
		};
	}
}

function setupStartOverIntroButtonListener() {
	if (startOverIntroButton) {
		startOverIntroButton.addEventListener('click', () => {
			console.log("Start Over Intro clicked");
			
			if (introData && introData.sentences && introData.sentences.length > 0 && introData.has_audio) {
				stopPlaybackSequence(false); // Stop current playback, don't enable interactions yet
				buildIntroPlaybackQueue(introData.sentences); // Rebuild the queue
				startPlaybackSequence(true); // Start from the beginning
			} else {
				console.warn("No audio sentences found to start over.");
			}
		});
	}
}
