function setupIntroEventListeners() {
	if (startPartQuestionButton) {
		startPartQuestionButton.addEventListener('click', startPartQuestions);
	}
	// Part indicator clicks are handled during updatePartIndicators
	if (partIndicatorContainer) {
		// Initial setup of click listeners
		updatePartIndicators();
	}
}

// Removed handlePartLabelClick - logic moved to updatePartIndicators

function updateProgressBar() {
	if (!progressBar || !currentState) return;
	
	const overallProgress = currentState.overallTotalQuestions > 0
		? Math.round((currentState.overallCorrectCount / currentState.overallTotalQuestions) * 100)
		: (currentState.status === 'completed' ? 100 : 0);
	
	const displayProgress = Math.min(100, Math.max(0, overallProgress)); // Clamp 0-100
	progressBar.style.width = `${displayProgress}%`;
	progressBar.textContent = `${displayProgress}%`;
	progressBar.setAttribute('aria-valuenow', displayProgress);
	
	updatePartIndicators(); // Update labels whenever progress changes
}

function updatePartIndicators() {
	if (!partIndicatorContainer || !currentState) return;
	
	const partLabels = partIndicatorContainer.querySelectorAll('.part-label');
	const currentActivePart = currentState.status === 'completed' ? -1 : currentState.partIndex; // No active part if completed
	
	partLabels.forEach(label => {
		const index = parseInt(label.dataset.partIndex);
		label.classList.remove('active', 'completed');
		
		if (currentState.status === 'completed' || index < currentActivePart) {
			label.classList.add('completed');
		} else if (index === currentActivePart) {
			label.classList.add('active');
		}
		
		// Add click listener if not already added
		if (!label.dataset.listenerAdded) {
			label.addEventListener('click', () => {
				if (isLoading || isAutoPlaying) return; // Prevent clicks during loading/playback
				
				const targetPartIndex = parseInt(label.dataset.partIndex, 10);
				console.log(`Part indicator ${targetPartIndex + 1} clicked.`);
				
				// Allow jumping to current or already completed parts
				//const canJump = currentState.status === 'completed' || targetPartIndex <= currentState.partIndex;
				const canJump = true;
				if (canJump) {
					stopPlaybackSequence(true); // Stop any current playback
					if (feedbackModalInstance && isModalVisible) feedbackModalInstance.hide(); // Hide feedback modal
					feedbackData = null;
					setErrorState(null);
					toggleElement(completionMessage, false);
					showPartIntro(targetPartIndex); // Show the selected part's intro
				} else {
					showToast("Please complete the current part first.", "Info", "info");
				}
			});
			label.dataset.listenerAdded = 'true';
		}
	});
}


function showPartIntro(partIndexToShow) {
	console.log(`Showing intro for part ${partIndexToShow}`);
	if (partIndexToShow < 0 || partIndexToShow >= totalParts || !allPartIntros[partIndexToShow]) {
		console.error("Invalid partIndexToShow:", partIndexToShow);
		setErrorState("Cannot display intro for invalid part index.");
		toggleElement(partIntroArea, true);
		if(partIntroTitle) partIntroTitle.textContent = "Error";
		if(partIntroTextContainer) partIntroTextContainer.innerHTML = '<p class="text-danger">Could not load introduction content.</p>';
		if (introPlaybackControls) toggleElement(introPlaybackControls, false); // Hide controls on error
		return;
	}
	
	stopPlaybackSequence(true);
	feedbackData = null;
	isPartIntroVisible = true;
	displayedPartIndex = partIndexToShow;
	currentState.partIndex = partIndexToShow;
	
	const introData = window.allPartIntros[partIndexToShow];
	const introTitle = introData.title || `Part ${partIndexToShow + 1}`;
	const partNumber = partIndexToShow + 1;
	
	// --- Populate Intro Area ---
	if (partIntroTitle) partIntroTitle.textContent = `Part ${partNumber}: ${introTitle}`;
	
	// --- Reset Image Display ---
	if (introSentenceImage) {
		introSentenceImage.style.display = 'none'; // Hide initially
		introSentenceImage.src = ''; // Clear src
	}
	if (introSentenceImagePlaceholder) {
		introSentenceImagePlaceholder.style.display = 'block'; // Show placeholder
	}
	
	// Populate Sentence Spans
	if (partIntroTextContainer && partIntroText) {
		partIntroTextContainer.innerHTML = ''; // Clear previous
		if (introData.sentences && introData.sentences.length > 0) {
			introData.sentences.forEach((sentence, index) => {
				const span = document.createElement('span');
				span.classList.add('intro-sentence');
				span.dataset.sentenceIndex = index;
				span.textContent = sentence.text + ' '; // Add space
				partIntroTextContainer.appendChild(span);
			});
			toggleElement(partIntroText, false); // Hide placeholder P tag
		} else {
			// Display full text if no playable sentences or just use a message
			partIntroTextContainer.innerHTML = `<p class="text-muted">${introData.full_text || '(No introduction text available for this part.)'}</p>`;
			toggleElement(partIntroText, false);
		}
	} else if (partIntroText) {
		// Fallback if container issue
		partIntroText.textContent = introData.full_text || '(No introduction text available for this part.)';
		toggleElement(partIntroText, true);
	}
	
	
	// --- Show/Hide Elements ---
	toggleElement(partIntroArea, true);
	toggleElement(questionArea, false);
	toggleElement(completionMessage, false);
	toggleElement(partCompletionMessage, false);
	
	// --- Update Buttons and State ---
	if (startPartQuestionButton) {
		startPartQuestionButton.textContent = `Start Part ${partNumber} Questions`;
		startPartQuestionButton.disabled = false;
	}
	updateProgressBar();
	setInteractionsDisabled(false);
	updateButtonStates(11);
	
	// --- Handle Audio ---
	if (introData.has_audio && introData.sentences.length > 0) {
		buildIntroPlaybackQueue(introData.sentences); // Build queue from sentence data
		if (introPlaybackControls) {
			toggleElement(introPlaybackControls, true);
		}
		if (isAutoPlayEnabled) {
			console.log("Auto-playing intro sentences...");
			startPlaybackSequence(); // Start sequence if auto-play is on
		} else {
			console.log("Auto-play disabled for intro.");
			if (introSentenceImage) introSentenceImage.style.display = 'none';
			if (introSentenceImagePlaceholder) introSentenceImagePlaceholder.style.display = 'block';
		}
	} else {
		console.log("No audio available for this intro or no sentences.");
		if (introSentenceImage) introSentenceImage.style.display = 'none';
		if (introSentenceImagePlaceholder) introSentenceImagePlaceholder.style.display = 'block';
		if (introPlaybackControls) toggleElement(introPlaybackControls, false); // Hide manual controls
		playbackQueue = []; // Ensure queue is empty
	}
}


function startPartQuestions() {
	if (isLoading || interactionsDisabled) return;
	
	// No need to check for video play state anymore
	
	console.log(`Starting questions for Part ${displayedPartIndex + 1}`);
	isPartIntroVisible = false;
	stopPlaybackSequence(true); // Stop intro audio and enable interactions
	
	// Hide Intro Area, show loading state (which will then show question area)
	toggleElement(partIntroArea, false);
	if (introSentenceImage) introSentenceImage.style.display = 'none';
	if (introSentenceImagePlaceholder) introSentenceImagePlaceholder.style.display = 'block';
	
	if (introPlaybackControls) toggleElement(introPlaybackControls, false); // Hide controls when leaving intro
	toggleElement(questionArea, false); // Hide question area initially
	
	// Load questions for the currently displayed part index
	loadQuestionsForLevel(displayedPartIndex);
}
