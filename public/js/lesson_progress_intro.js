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
	
	// Stop video if playing (both types)
	if (introVideoPlayer && !introVideoPlayer.paused) {
		introVideoPlayer.pause();
	}
	if (youtubeIframePlayer && youtubeEmbedContainer && !youtubeEmbedContainer.classList.contains('d-none')) {
		// To stop YouTube, set src to empty. It will be restored if needed when intro is reshown.
		youtubeIframePlayer.src = '';
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
	toggleElement(introVideoArea, false); // Hide the main video area container first
	toggleElement(introVideoPlayer, false); // Hide self-hosted player
	toggleElement(youtubeEmbedContainer, false); // Hide YouTube embed container
	toggleElement(introSentencesArea, false);
	toggleElement(introFullTextDisplayArea, false);
	toggleElement(introPlaybackControls, false); // Hide sentence playback controls by default
	
	
	if (introData.has_video && introData.video_url) {
		console.log("Displaying intro video. URL:", introData.video_url, "Is YouTube Embed:", introData.is_youtube_embed);
		toggleElement(introVideoArea, true); // Show the main video area container
		
		if (introData.is_youtube_embed) {
			if (youtubeIframePlayer) {
				youtubeIframePlayer.src = introData.video_url;
				toggleElement(youtubeEmbedContainer, true); // Show YouTube embed
			}
		} else {
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
				toggleElement(introVideoPlayer, true); // Show self-hosted player
			}
		}
	} else if (introData.has_audio && introData.sentences && introData.sentences.length > 0) {
		console.log("Displaying intro sentences with audio.");
		toggleElement(introSentencesArea, true);
		if (IntroTextContainer) {
			IntroTextContainer.innerHTML = ''; // Clear previous content
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
	
	// Stop and hide video if it was playing/visible (both types)
	if (introVideoPlayer && !introVideoPlayer.paused) {
		introVideoPlayer.pause();
		// Optionally reset video: introVideoPlayer.currentTime = 0;
	}
	if (youtubeIframePlayer && youtubeEmbedContainer && !youtubeEmbedContainer.classList.contains('d-none')) {
		// Stop YouTube video by setting src to empty or a blank page
		youtubeIframePlayer.src = ''; // Clear src to stop playback
		console.log("Stopped YouTube iframe player.");
	}
	
	toggleElement(introVideoArea, false); // Ensure video area is hidden (hides both types)
	toggleElement(introVideoPlayer, false); // Explicitly hide self-hosted
	toggleElement(youtubeEmbedContainer, false); // Explicitly hide YouTube embed
	
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
