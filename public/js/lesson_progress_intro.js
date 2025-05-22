function setupIntroEventListeners() {
	if (startLessonButton) {
		startLessonButton.addEventListener('click', startQuestions);
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
	stopPlaybackSequence(true); // Stop any sentence audio
	if (introVideoPlayer && !introVideoPlayer.paused) { // Stop video if playing
		introVideoPlayer.pause();
	}
	
	feedbackData = null;
	isIntroVisible = true;
	const introData = window.lessonIntro;
	const introTitle = introData.title;
	
	if (IntroTitle) IntroTitle.textContent = `${introTitle}`;
	
	// --- Reset Image Display (for sentences) ---
	if (introSentenceImage) {
		introSentenceImage.style.display = 'none';
		introSentenceImage.src = '';
	}
	if (introSentenceImagePlaceholder) {
		introSentenceImagePlaceholder.style.display = 'block';
	}
	
	// --- Hide all content areas initially ---
	toggleElement(introVideoArea, false);
	toggleElement(introSentencesArea, false);
	toggleElement(introFullTextDisplayArea, false);
	toggleElement(introPlaybackControls, false); // Hide sentence playback controls by default
	
	if (introData.has_video && introData.video_url) {
		console.log("Displaying intro video:", introData.video_url);
		if (introVideoPlayer) {
			const sourceElement = introVideoPlayer.querySelector('source') || document.createElement('source');
			sourceElement.setAttribute('src', introData.video_url);
			// Determine video type (basic check for now)
			let videoType = 'video/mp4';
			if (introData.video_url.endsWith('.webm')) {
				videoType = 'video/webm';
			} else if (introData.video_url.endsWith('.ogv')) {
				videoType = 'video/ogg';
			}
			sourceElement.setAttribute('type', videoType);
			
			if (!introVideoPlayer.querySelector('source')) {
				introVideoPlayer.appendChild(sourceElement);
			}
			introVideoPlayer.load(); // Important to load the new source
		}
		toggleElement(introVideoArea, true);
	} else if (introData.has_audio && introData.sentences && introData.sentences.length > 0) {
		console.log("Displaying intro sentences with audio.");
		toggleElement(introSentencesArea, true);
		if (IntroTextContainer) {
			IntroTextContainer.innerHTML = ''; // Clear previous content (like the <p id="IntroText">)
			introData.sentences.forEach((sentence, index) => {
				const span = document.createElement('span');
				span.classList.add('intro-sentence');
				span.dataset.sentenceIndex = index;
				span.textContent = sentence.text + ' '; // Add space
				IntroTextContainer.appendChild(span);
			});
		}
		buildIntroPlaybackQueue(introData.sentences);
		toggleElement(introPlaybackControls, true); // Show sentence playback controls
		if (isAutoPlayEnabled) {
			console.log("Auto-playing intro sentences...");
			startPlaybackSequence();
		} else {
			console.log("Auto-play disabled for intro sentences.");
			// Ensure image placeholder is visible if not auto-playing sentences
			if (introSentenceImage) introSentenceImage.style.display = 'none';
			if (introSentenceImagePlaceholder) introSentenceImagePlaceholder.style.display = 'block';
		}
	} else if (introData.full_text && introData.full_text.trim() !== '') {
		console.log("Displaying intro full text.");
		if (introFullTextContent) {
			introFullTextContent.textContent = introData.full_text;
		}
		toggleElement(introFullTextDisplayArea, true);
	} else {
		console.log("No intro video, sentences, or full text available.");
		if (introFullTextContent) {
			introFullTextContent.textContent = '(No introduction content available for this lesson.)';
		}
		toggleElement(introFullTextDisplayArea, true); // Show the area with the "no content" message
	}
	
	// --- Show/Hide Common Elements ---
	toggleElement(IntroArea, true);
	toggleElement(questionArea, false);
	toggleElement(completionMessage, false);
	
	// --- Update Buttons and State ---
	if (startLessonButton) {
		startLessonButton.textContent = `Start Questions`;
		startLessonButton.disabled = false;
	}
	updateProgressBar();
	setInteractionsDisabled(false);
	updateButtonStates(11);
}


function startQuestions() {
	if (isLoading || interactionsDisabled) return;
	
	console.log(`Starting questions`);
	isIntroVisible = false;
	stopPlaybackSequence(true); // Stop intro sentence audio and enable interactions
	
	// Stop and hide video if it was playing/visible
	if (introVideoPlayer && !introVideoPlayer.paused) {
		introVideoPlayer.pause();
		// Optionally reset video: introVideoPlayer.currentTime = 0;
	}
	toggleElement(introVideoArea, false); // Ensure video area is hidden
	
	// Hide sentence-specific elements
	toggleElement(introSentencesArea, false);
	if (introSentenceImage) introSentenceImage.style.display = 'none';
	if (introSentenceImagePlaceholder) introSentenceImagePlaceholder.style.display = 'block'; // Reset placeholder
	if (introPlaybackControls) toggleElement(introPlaybackControls, false);
	
	// Hide full text display area
	toggleElement(introFullTextDisplayArea, false);
	
	// Hide Intro Area, show loading state (which will then show question area)
	toggleElement(IntroArea, false);
	toggleElement(questionArea, false); // Hide question area initially
	loadLessonQustions();
}
