// --- Helper Functions ---
function setLoadingState(loading, message = 'Loading...') {
	const wasLoading = isLoading;
	isLoading = loading;
	
	// Disable interactions when loading starts, re-enable depends on other factors when loading stops
	if (loading) {
		setInteractionsDisabled(true);
	} else if (!isModalVisible && !isAutoPlaying) { // Only re-enable if modal isn't shown and TTS isn't playing
		setInteractionsDisabled(false);
	}
	
	
	if (loadingOverlay && loadingMessageEl) {
		loadingMessageEl.textContent = message;
		loadingOverlay.classList.toggle('d-none', !loading);
	}
	
	if (startLessonButton) startLessonButton.disabled = loading || interactionsDisabled;
	
	// Update button states after loading state changes, as it affects interactionsDisabled
	if(wasLoading !== isLoading) updateButtonStates(1);
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
		updateButtonStates(2);
	}
}

function updateButtonStates(callerId) {
	// Update Answer Buttons enabled/disabled state
	console.log("Updating button states... state:", interactionsDisabled, "callerId:", callerId);
	questionAnswersContainer.querySelectorAll('.answer-btn').forEach(button => {
		if (button.classList.contains('incorrect') && !interactionsDisabled) return; // Skip the buttons flagged as incorrect
		button.disabled = interactionsDisabled;
		//console.log(`Button ${button.textContent} disabled: ${button.disabled}`);
	});
	
	// Update Start Button state
	if (startLessonButton) {
		startLessonButton.disabled = interactionsDisabled || isLoading;
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
