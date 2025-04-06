function setupIntroEventListeners() {
	startPartQuestionButton.addEventListener('click', () => {
		if (!isLoading && !interactionsDisabled) {
			console.log("Start Part Question button clicked for Part:", currentState.partIndex, "Difficulty:", currentState.difficulty);
			if (currentState.partIndex === null || currentState.difficulty === null) {
				setErrorState("Cannot start question: Invalid state (part or difficulty missing).");
				return;
			}
			// Load questions for the current state's part/difficulty
			loadQuestionsForLevel(currentState.partIndex, currentState.difficulty);
		}
	});
	
	partIndicatorContainer.addEventListener('click', handlePartLabelClick);
	
	if (partIntroVideo) {
		partIntroVideo.addEventListener('play', () => hasIntroVideoPlayed = true);
	} else {
		console.warn("Part intro video element not found during event listener setup.");
		// If there's no video element, assume it's "played" or doesn't matter
		hasIntroVideoPlayed = true;
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
	
	const currentPart = currentState.partIndex;
	let totalProgress = 0;
	
	// Calculate progress based on first-attempt correct answers
	if (currentState.status === 'completed') {
		totalProgress = 100;
	} else if (currentState.totalQuestions > 0) {
		// Use first-attempt correct count for progress percentage
		totalProgress = Math.round((currentState.firstAttemptCorrectCount / currentState.totalQuestions) * 100);
	}
	
	totalProgress = Math.min(100, Math.max(0, totalProgress));
	
	progressBar.style.width = `${totalProgress}%`;
	progressBar.textContent = `${totalProgress}%`;
	progressBar.setAttribute('aria-valuenow', totalProgress);
	
	// Update part labels
	for (let i = 0; i < totalParts; i++) {
		const label = document.getElementById(`partLabel_${i}`);
		if (label) {
			label.classList.remove('active', 'completed');
			
			if (currentState.status === 'completed') {
				label.classList.add('completed');
			} else if (i < currentPart) {
				label.classList.add('completed');
			} else if (i === currentPart) {
				label.classList.add('active');
			}
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
	
	stopPlaybackSequence(true); // Stop question audio and enable interactions
	feedbackData = null; // Clear any lingering feedback
	isPartIntroVisible = true;
	hasIntroVideoPlayed = false; // Reset video played flag
	currentPartQuestions = []; // Clear questions from previous part
	currentQuestionIndex = -1;
	currentQuestion = null;
	displayedPartIndex = partIndexToShow;
	currentState.partIndex = partIndexToShow;
	currentState.difficulty = 'easy'; // Reset difficulty to easy for intro
	
	
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
	
	// Handle Video Element Existence and Content
	if (partIntroVideo) { // Check if the element exists first
		if (introVideoUrl) {
			partIntroVideo.src = introVideoUrl;
			toggleElement(partIntroVideo, true);
			if (partIntroVideoPlaceholder) toggleElement(partIntroVideoPlaceholder, false);
		} else {
			partIntroVideo.src = ''; // Clear src if no video
			toggleElement(partIntroVideo, false);
			if (partIntroVideoPlaceholder) toggleElement(partIntroVideoPlaceholder, true);
			hasIntroVideoPlayed = true; // No video, treat as played
		}
	} else {
		// Video element doesn't exist, ensure placeholder is shown if it exists
		if (partIntroVideoPlaceholder) toggleElement(partIntroVideoPlaceholder, true);
		hasIntroVideoPlayed = true; // No video element, treat as played
		console.warn("partIntroVideo element not found. Cannot display video.");
	}
	
	updateProgressBar(); // Update progress bar for the new part
	setInteractionsDisabled(false); // Ensure interactions are enabled for intro screen
	updateButtonStates(); // Update button enabled/disabled
}
