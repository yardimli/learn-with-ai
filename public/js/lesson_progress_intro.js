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
	
	partIntroVideo.addEventListener('play', () => hasIntroVideoPlayed = true);
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
	stopPlaybackSequence(true); // Stop quiz audio and enable interactions
	feedbackData = null; // Clear any lingering feedback
	isPartIntroVisible = true;
	hasIntroVideoPlayed = false; // Reset video played flag
	currentPartQuizzes = []; // Clear quizzes from previous part
	currentQuizIndex = -1;
	currentQuiz = null;
	
	// Hide Quiz Area, Show Intro Area
	toggleElement(quizArea, false);
	toggleElement(completionMessage, false);
	toggleElement(partIntroArea, true);
	
	// --- Fetch Intro Content dynamically ---
	// We need the Subject model's lesson_parts here.
	// Option 1: Pass full subject data initially (can be large)
	// Option 2: Make a small AJAX call to get part details (better)
	// Option 3: Assume `currentState` might hold it (less reliable if state only has counts)
	// Let's choose Option 2 (or enhance `currentState` structure if preferred).
	// For simplicity here, we'll assume `currentState` magically gets the text/video URL
	// from the backend during state calculation/submit response.
	// If not, an AJAX call here is needed.
	
	// Assuming currentState has the data needed (modify backend if needed)
	const introText = currentState.currentPartIntroText;
	const introVideoUrl = currentState.currentPartVideoUrl;
	
	// Populate Intro Content
	const partNumber = partIndexToShow + 1;
	partIntroTitle.textContent = `Part ${partNumber}: Introduction`;
	partIntroText.textContent = introText || "Loading introduction..."; // Add loading state?
	startPartQuizButton.textContent = `Start Part ${partNumber} Quiz`;
	startPartQuizButton.disabled = false; // Should be enabled by default
	
	// Handle Video
	if (introVideoUrl) {
		partIntroVideo.src = introVideoUrl;
		toggleElement(partIntroVideo, true);
		toggleElement(partIntroVideoPlaceholder, false);
	} else {
		toggleElement(partIntroVideo, false);
		toggleElement(partIntroVideoPlaceholder, true);
		hasIntroVideoPlayed = true; // No video, treat as played
	}
	
	updateProgressBar(); // Update progress bar for the new part
	setInteractionsDisabled(false); // Ensure interactions are enabled for intro screen
	updateButtonStates(); // Update button enabled/disabled
}
