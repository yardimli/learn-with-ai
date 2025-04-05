function setupIntroEventListeners() {
	startPartQuizButton.addEventListener('click', () => {
		if (!isLoading && !interactionsDisabled) {
			console.log("Start Part Quiz button clicked for Part:", currentState.partIndex, "Difficulty:", currentState.difficulty);
			if (currentState.partIndex === null || currentState.difficulty === null) {
				setErrorState("Cannot start quiz: Invalid state (part or difficulty missing).");
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
	feedbackModalInstance?.hide(); // Hide feedback modal if open
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
	const currentDifficulty = currentState.difficulty || 'easy';
	const currentDifficultyIndex = difficulties.indexOf(currentDifficulty);
	const correctInCurrentDiff = currentState.correctCounts?.[currentDifficulty] ?? 0;
	const required = currentState.requiredCorrect ?? 2;
	
	const partsCompleted = currentPart;
	const difficultiesCompletedInPart = currentDifficultyIndex < 0 ? 0 : currentDifficultyIndex;
	const progressInCurrentDifficulty = required > 0 ? Math.min(1, correctInCurrentDiff / required) : 1;
	const totalDifficultySteps = totalParts * difficulties.length;
	const stepsCompleted = (partsCompleted * difficulties.length) + difficultiesCompletedInPart;
	
	let overallProgress = 0;
	if (currentState.status === 'completed') {
		overallProgress = 100;
	} else if (totalDifficultySteps > 0) {
		overallProgress = Math.round(((stepsCompleted + progressInCurrentDifficulty) / totalDifficultySteps) * 100);
	}
	overallProgress = Math.min(100, Math.max(0, overallProgress));
	
	progressBar.style.width = `${overallProgress}%`;
	progressBar.textContent = `${overallProgress}%`;
	progressBar.setAttribute('aria-valuenow', overallProgress);
	
	for (let i = 0; i < totalParts; i++) {
		const label = document.getElementById(`partLabel_${i}`);
		if (label) {
			label.classList.remove('active', 'completed');
			if (i < currentPart) {
				label.classList.add('completed');
			} else if (i === currentPart && currentState.status !== 'completed') {
				label.classList.add('active');
			}
			if (currentState.status === 'completed') {
				label.classList.add('completed'); // Mark all parts complete
				label.classList.remove('active');
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
	
	stopPlaybackSequence(true); // Stop quiz audio and enable interactions
	feedbackData = null; // Clear any lingering feedback
	isPartIntroVisible = true;
	hasIntroVideoPlayed = false; // Reset video played flag
	currentPartQuizzes = []; // Clear quizzes from previous part
	currentQuizIndex = -1;
	currentQuiz = null;
	displayedPartIndex = partIndexToShow;
	currentState.partIndex = partIndexToShow;
	currentState.difficulty = 'easy'; // Reset difficulty to easy for intro
	
	
	// Hide Quiz Area, Show Intro Area
	toggleElement(quizArea, false);
	toggleElement(completionMessage, false);
	toggleElement(partIntroArea, true);
	
	// Get intro content from pre-loaded data
	const introData = window.allPartIntros?.[partIndexToShow];
	const introTitle = introData?.title ?? "Introduction Title Not Available";
	const introText = introData?.text ?? "Introduction content not available.";
	const introVideoUrl = introData?.videoUrl ?? null;
	
	// Populate Intro Content
	const partNumber = partIndexToShow + 1;
	if(partIntroTitle) partIntroTitle.textContent = `Part ${partNumber}: ${introTitle}`;
	if(partIntroText) partIntroText.textContent = introText;
	if(startPartQuizButton) {
		startPartQuizButton.textContent = `Start Part ${partNumber} Quiz`;
		startPartQuizButton.disabled = false; // Should be enabled by default
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
