function setupIntroEventListeners() {
	startPartQuestionButton.addEventListener('click', () => {
		if (!isLoading && !interactionsDisabled) {
			// Don't proceed if video hasn't finished playing
			if (!hasIntroVideoPlayed) {
				// Show a message to the user
				setErrorState("Please watch the video before proceeding.");
				
				// Try to play the video if it's not playing
				if (partIntroVideo && partIntroVideo.paused && isAutoPlayEnabled) {
					partIntroVideo.play().catch(err => {
						console.error("Could not play video:", err);
						// If we can't play the video, allow them to continue anyway
						hasIntroVideoPlayed = true;
						setErrorState(null);
					});
				}
				return;
			}
			
			// Existing code for starting questions
			console.log("Start Part Question button clicked for Part:", currentState.partIndex);
			if (currentState.partIndex === null) {
				setErrorState("Cannot start question: Invalid state (part missing).");
				return;
			}
			
			// Load questions for the current state's part
			loadQuestionsForLevel(currentState.partIndex);
		}
	});
	
	partIndicatorContainer.addEventListener('click', handlePartLabelClick);
	
	if (partIntroVideo) {
		// Update existing play event handler to enable button
		partIntroVideo.addEventListener('play', () => {
			console.log("Video started playing");
		});
		
		// Add ended event to enable the start button
		partIntroVideo.addEventListener('ended', () => {
			console.log("Video finished playing");
			hasIntroVideoPlayed = true;
			if (startPartQuestionButton) {
				startPartQuestionButton.disabled = false;
				startPartQuestionButton.innerHTML = `Start Part ${displayedPartIndex + 1} Question`;
			}
		});
		
		// Handle video errors - don't block progress if video fails
		partIntroVideo.addEventListener('error', () => {
			console.warn("Video playback error - enabling continue button");
			hasIntroVideoPlayed = true;
			if (startPartQuestionButton) {
				startPartQuestionButton.disabled = false;
				startPartQuestionButton.innerHTML = `Start Part ${displayedPartIndex + 1} Question`;
			}
		});
	}
}

function handlePartLabelClick(event) {
	const targetLabel = event.target.closest('.part-label');
	if (!targetLabel || isLoading) { // Don't process if not a label or if loading
		return;
	}
	
	const targetPartIndex = parseInt(targetLabel.dataset.partIndex, 10);
	if (isNaN(targetPartIndex)) return; // Invalid index
	
	console.log(`Part label clicked: Jumping to Part ${targetPartIndex + 1}`);
	
	// --- Prepare for jump ---
	stopPlaybackSequence(true); // Stop any TTS audio and enable interactions momentarily
	feedbackModalInstance.hide(); // Hide feedback modal if open
	feedbackData = null; // Clear feedback data
	setErrorState(null); // Clear any errors
	toggleElement(completionMessage, false); // Hide completion message if shown
	
	// --- Load 'easy' questions for the target part ---
	// Jumping always starts the part fresh at 'easy' difficulty
	//loadQuestionsForLevel(targetPartIndex, 'easy');
	showPartIntro(targetPartIndex);
}

function updateProgressBar() {
	if (!progressBar || !partIndicatorContainer || !currentState) return;
	
	const currentPart = currentState.partIndex; // The index of the first incomplete part, or last part if completed
	let overallProgress = 0;
	
	// Calculate progress based on OVERALL first-attempt correct answers
	if (currentState.status === 'completed' && currentState.overallTotalQuestions > 0) {
		overallProgress = 100; // If completed status and there were questions, it's 100%
	} else if (currentState.overallTotalQuestions > 0) {
		// Use the NEW OVERALL counts from the state object
		overallProgress = Math.round((currentState.overallCorrectCount / currentState.overallTotalQuestions) * 100);
	} else {
		// Handle 0 total questions case - 100% if completed/empty, 0% otherwise
		overallProgress = (currentState.status === 'completed' || currentState.status === 'empty') ? 100 : 0;
	}
	
	overallProgress = Math.min(100, Math.max(0, overallProgress)); // Clamp between 0 and 100
	
	progressBar.style.width = `${overallProgress}%`;
	progressBar.textContent = `${overallProgress}%`; // Display overall progress
	progressBar.setAttribute('aria-valuenow', overallProgress);
	
	// --- Update part labels ---
	// This logic correctly uses the currentPart index determined by the backend
	for (let i = 0; i < totalParts; i++) {
		const label = document.getElementById(`partLabel_${i}`);
		if (label) {
			label.classList.remove('active', 'completed');
			if (currentState.status === 'completed') {
				// If the whole lesson is complete, mark all parts as completed
				label.classList.add('completed');
			} else if (i < currentPart) {
				// Parts *before* the current active part are completed
				label.classList.add('completed');
			} else if (i === currentPart) {
				// The current *active* part (first incomplete one)
				label.classList.add('active');
			}
			// Parts *after* the current active part have no special class
		}
	}
}

function showPartIntro(partIndexToShow) {
	console.log(`Showing intro for part ${partIndexToShow}`);
	if (partIndexToShow < 0 || partIndexToShow >= totalParts) {
		console.error("Invalid partIndexToShow:", partIndexToShow);
		setErrorState("Cannot display intro for invalid part index.");
		return;
	}
	
	stopPlaybackSequence(true);
	feedbackData = null;
	isPartIntroVisible = true;
	hasIntroVideoPlayed = false;
	currentPartQuestions = [];
	currentQuestionIndex = -1;
	currentQuestion = null;
	displayedPartIndex = partIndexToShow;
	currentState.partIndex = partIndexToShow;
	currentState.difficulty = 'easy';
	
	
	// Hide Question Area, Show Intro Area
	toggleElement(questionArea, false);
	toggleElement(completionMessage, false);
	toggleElement(partIntroArea, true);
	
	// Get intro content from pre-loaded data
	const introData = window.allPartIntros[partIndexToShow];
	const introTitle = introData.title ?? "Introduction Title Not Available";
	const introText = introData.text ?? "Introduction content not available.";
	const introVideoUrl = introData.videoUrl ?? null;
	
	// Populate Intro Content
	const partNumber = partIndexToShow + 1;
	if(partIntroTitle) partIntroTitle.textContent = `Part ${partNumber}: ${introTitle}`;
	if(partIntroText) partIntroText.textContent = introText;
	if(startPartQuestionButton) {
		startPartQuestionButton.textContent = `Start Part ${partNumber} Question`;
		startPartQuestionButton.disabled = false; // Should be enabled by default
	}
	
	if (partIntroVideo) {
		if (introVideoUrl) {
			partIntroVideo.src = introVideoUrl;
			toggleElement(partIntroVideo, true);
			if (partIntroVideoPlaceholder) toggleElement(partIntroVideoPlaceholder, false);
			
			// Set video to auto-play when auto-play is enabled
			if (isAutoPlayEnabled) {
				partIntroVideo.autoplay = true;
				// Disable start button until video completes
				if (startPartQuestionButton) {
					startPartQuestionButton.disabled = true;
					startPartQuestionButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Please watch the video';
					partIntroVideo.play().catch(err => {
						console.error("Could not play video:", err);
						// If we can't play the video, allow them to continue anyway
						hasIntroVideoPlayed = true;
						setErrorState(null);
					});
				}
			} else {
				partIntroVideo.autoplay = false;
				hasIntroVideoPlayed = true; // Skip requirement if auto-play is off
			}
		} else {
			// No video exists
			partIntroVideo.src = '';
			toggleElement(partIntroVideo, false);
			if (partIntroVideoPlaceholder) toggleElement(partIntroVideoPlaceholder, true);
			hasIntroVideoPlayed = true; // No video to play
		}
	} else {
		// Video element doesn't exist
		hasIntroVideoPlayed = true;
	}
	
	updateProgressBar(); // Update progress bar for the new part
	setInteractionsDisabled(false); // Ensure interactions are enabled for intro screen
	updateButtonStates(11);
}
