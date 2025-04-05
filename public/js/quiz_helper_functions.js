// --- Helper Functions ---
function setLoadingState(loading, message = 'Loading...') {
	isLoading = loading;
	// Also disable interactions when loading, re-enable takes care of isAutoPlaying check
	setInteractionsDisabled(loading || isAutoPlaying);
	if (loadingOverlay && loadingMessageEl) {
		loadingMessageEl.textContent = message;
		loadingOverlay.classList.toggle('d-none', !loading);
	}
	// Disable next button specifically during loading
	if (nextQuestionButton) nextQuestionButton.disabled = loading || interactionsDisabled;
	if (nextQuestionSpinner) nextQuestionSpinner.classList.toggle('d-none', !loading);
	
	// Disable start part button during loading
	if (startPartQuizButton) startPartQuizButton.disabled = loading || interactionsDisabled;
	
	// No general updateUI call here to prevent flicker, specific updates done where needed
}

function setErrorState(message) {
	if (errorMessageArea && errorMessageText) {
		errorMessageText.textContent = message || '';
		errorMessageArea.classList.toggle('d-none', !message);
	}
}

function setInteractionsDisabled(disabled) {
	const changed = interactionsDisabled !== disabled;
	interactionsDisabled = disabled;
	// console.log(`Interactions Disabled: ${interactionsDisabled} (Req: ${disabled}, Load: ${isLoading}, AutoPlay: ${isAutoPlaying})`);
	if (changed) {
		// Update button states etc. based on this change
		updateButtonStates();
	}
}

function updateButtonStates() {
	// Update Answer Buttons enabled/disabled state
	quizAnswersContainer?.querySelectorAll('.answer-btn').forEach(button => {
		// Disable if interactions generally disabled OR feedback is shown
		button.disabled = interactionsDisabled || feedbackData != null;
	});
	
	// Update Next Question Button state
	if (nextQuestionButton) {
		// Show if feedback is visible AND not currently loading
		const showNextButton = feedbackData != null && !isLoading;
		toggleElement(nextQuestionButton, showNextButton);
		// Disable if interactions are off OR loading
		nextQuestionButton.disabled = interactionsDisabled || isLoading;
	}
	
	// Update Play Feedback Button state
	if (playFeedbackButton) {
		toggleElement(playFeedbackButton, !!feedbackData?.feedback_audio_url);
		// Disable ONLY if interactions are disabled (e.g. audio playing/loading)
		playFeedbackButton.disabled = interactionsDisabled;
	}
	
	// Update Start Part Button state
	if (startPartQuizButton) {
		startPartQuizButton.disabled = interactionsDisabled || isLoading;
	}
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
	if (!string) return '';
	return string.charAt(0).toUpperCase() + string.slice(1);
}

function setupHelperEventListeners() {
	if (closeErrorButton) {
		closeErrorButton.addEventListener('click', () => setErrorState(null));
	}
}
