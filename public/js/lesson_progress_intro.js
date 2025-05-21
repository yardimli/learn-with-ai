function setupIntroEventListeners() {
	if (startQuestionButton) {
		startQuestionButton.addEventListener('click', startQuestions);
	}
}


function updateProgressBar() {
	if (!progressBar || !currentState) return;
	
	const overallProgress = currentState.overallTotalQuestions > 0
		? Math.round((currentState.overallCorrectCount / currentState.overallTotalQuestions) * 100)
		: (currentState.status === 'completed' ? 100 : 0);
	
	const displayProgress = Math.min(100, Math.max(0, overallProgress)); // Clamp 0-100
	progressBar.style.width = `${displayProgress}%`;
	progressBar.textContent = `${displayProgress}%`;
	progressBar.setAttribute('aria-valuenow', displayProgress);
	
}


function showIntro() {
	console.log(`Showing intro`);
	
	stopPlaybackSequence(true);
	feedbackData = null;
	isIntroVisible = true;
	
	const introData = window.lessonIntro;
	const introTitle = introData.title;
	
	// --- Populate Intro Area ---
	if (IntroTitle) IntroTitle.textContent = `${introTitle}`;
	
	// --- Reset Image Display ---
	if (introSentenceImage) {
		introSentenceImage.style.display = 'none'; // Hide initially
		introSentenceImage.src = ''; // Clear src
	}
	if (introSentenceImagePlaceholder) {
		introSentenceImagePlaceholder.style.display = 'block'; // Show placeholder
	}
	
	// Populate Sentence Spans
	if (IntroTextContainer && IntroText) {
		IntroTextContainer.innerHTML = ''; // Clear previous
		if (introData.sentences && introData.sentences.length > 0) {
			introData.sentences.forEach((sentence, index) => {
				const span = document.createElement('span');
				span.classList.add('intro-sentence');
				span.dataset.sentenceIndex = index;
				span.textContent = sentence.text + ' '; // Add space
				IntroTextContainer.appendChild(span);
			});
			toggleElement(IntroText, false); // Hide placeholder P tag
		} else {
			// Display full text if no playable sentences or just use a message
			IntroTextContainer.innerHTML = `<p class="text-muted">${introData.full_text || '(No introduction text available.)'}</p>`;
			toggleElement(IntroText, false);
		}
	} else if (IntroText) {
		// Fallback if container issue
		IntroText.textContent = introData.full_text || '(No introduction text available.)';
		toggleElement(IntroText, true);
	}
	
	
	// --- Show/Hide Elements ---
	toggleElement(IntroArea, true);
	toggleElement(questionArea, false);
	toggleElement(completionMessage, false);
	
	// --- Update Buttons and State ---
	if (startQuestionButton) {
		startQuestionButton.textContent = `Start Questions`;
		startQuestionButton.disabled = false;
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


function startQuestions() {
	if (isLoading || interactionsDisabled) return;
	
	// No need to check for video play state anymore
	
	console.log(`Starting questions`);
	isIntroVisible = false;
	stopPlaybackSequence(true); // Stop intro audio and enable interactions
	
	// Hide Intro Area, show loading state (which will then show question area)
	toggleElement(IntroArea, false);
	if (introSentenceImage) introSentenceImage.style.display = 'none';
	if (introSentenceImagePlaceholder) introSentenceImagePlaceholder.style.display = 'block';
	
	if (introPlaybackControls) toggleElement(introPlaybackControls, false); // Hide controls when leaving intro
	toggleElement(questionArea, false); // Hide question area initially
	
	loadQuestionsForLevel();
}
