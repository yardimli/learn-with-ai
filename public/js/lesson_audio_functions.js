function buildIntroPlaybackQueue(sentences) {
	console.log("Building intro playback queue");
	playbackQueue = []; // Clear existing queue
	currentPlaybackIndex = -1; // Reset index
	
	if (!sentences || sentences.length === 0) {
		console.warn("No sentences provided for intro playback queue.");
		return;
	}
	
	sentences.forEach((sentence, index) => {
		if (sentence.audio_url) {
			// Find the corresponding span element using the correct index
			const sentenceSpan = partIntroTextContainer?.querySelector(`.intro-sentence[data-sentence-index="${index}"]`);
			if (sentenceSpan) {
				playbackQueue.push({
					type: 'audio',
					url: sentence.audio_url,
					elementToHighlight: sentenceSpan,
					text: sentence.text,
					imageUrl: sentence.image_url || null
				});
			} else {
				console.warn(`Could not find span for sentence index ${index}`);
				// Optionally add anyway without highlight? For now, skip if no span.
			}
		}
	});
	console.log("Intro Playback Queue built:", playbackQueue);
}

function buildPlaybackQueue(questionData) {
	playbackQueue = [];
	currentPlaybackIndex = -1;
	if (!questionData) return;
	
	if (questionData.question_audio_url && questionTextElement) {
		playbackQueue.push({elementToHighlight: questionTextElement, url: questionData.question_audio_url});
	}
	questionData.answers.forEach((answer, index) => {
		const answerButton = document.getElementById(`answerBtn_${answer.index}`);
		if (answer.answer_audio_url && answerButton) {
			playbackQueue.push({elementToHighlight: answerButton, url: answer.answer_audio_url});
		}
	});
	// console.log("Playback queue built:", playbackQueue);
}

function startPlaybackSequence(ignoreAutoPlay = false) {
	if (!isAutoPlayEnabled && !ignoreAutoPlay) {
		console.log("Auto-play disabled. Skipping audio sequence.");
		setInteractionsDisabled(false); // Ensure interactions are enabled immediately
		return;
	}
	
	
	if (playbackQueue.length === 0) {
		console.log("Playback queue empty, enabling interactions.");
		setInteractionsDisabled(false);
		return;
	}
	
	stopPlaybackSequence();
	console.log("Starting playback sequence...");
	console.log("Playback queue:", playbackQueue);
	isAutoPlaying = true;
	currentPlaybackIndex = 0;
	setInteractionsDisabled(true);
	playNextInSequence();
}

function stopPlaybackSequence(reEnableInteractions = false) {
	const wasPlaying = isAutoPlaying || !ttsAudioPlayer.paused;

	if (!isAutoPlaying && ttsAudioPlayer.paused) return;
	isAutoPlaying = false;
	if (ttsAudioPlayer) {
		ttsAudioPlayer.pause();
		ttsAudioPlayer.currentTime = 0;
	}
	removeHighlight();
	
	// Hide sentence image and show placeholder when stopped
	if (introSentenceImage) {
		introSentenceImage.style.display = 'none';
		introSentenceImage.src = ''; // Clear src
	}
	if (introSentenceImagePlaceholder) {
		introSentenceImagePlaceholder.style.display = 'block';
	}
	
	if (reEnableInteractions) {
		setInteractionsDisabled(false);
	}
}

function playNextInSequence() {
	removeHighlight();
	
	if (introSentenceImage) {
		introSentenceImage.style.display = 'none';
		introSentenceImage.src = '';
	}
	if (introSentenceImagePlaceholder) {
		introSentenceImagePlaceholder.style.display = 'block';
	}
	
	if (!isAutoPlaying || currentPlaybackIndex < 0 || currentPlaybackIndex >= playbackQueue.length) {
		// console.log("Playback sequence finished or stopped.");
		isAutoPlaying = false;
		setInteractionsDisabled(false); // Enable interactions after sequence naturally ends
		
		// Hide image display when sequence ends
		if (introSentenceImage) introSentenceImage.style.display = 'none';
		if (introSentenceImagePlaceholder) introSentenceImagePlaceholder.style.display = 'block';
		
		return;
	}
	const item = playbackQueue[currentPlaybackIndex];
	if (!item || !item.elementToHighlight || !item.url) {
		console.warn("Skipping invalid item in playback queue:", item);
		currentPlaybackIndex++;
		if (isAutoPlaying) setTimeout(playNextInSequence, 50);
		else setInteractionsDisabled(false);
		return;
	}
	
	console.log(`Playing item ${currentPlaybackIndex}:`, item);
	
	// --- Update Image ---
	if (introSentenceImage && item.imageUrl) {
		console.log("Setting image source:", item.imageUrl);
		introSentenceImage.src = item.imageUrl;
		// introSentenceImage.classList.remove('hidden'); // Show with opacity transition
		introSentenceImage.style.display = 'block'; // Show image
		if (introSentenceImagePlaceholder) {
			introSentenceImagePlaceholder.style.display = 'none'; // Hide placeholder
		}
	} else {
		console.log("No image for this sentence.");
		// Ensure image is hidden and placeholder shown if no URL
		if (introSentenceImage) introSentenceImage.style.display = 'none';
		if (introSentenceImagePlaceholder) introSentenceImagePlaceholder.style.display = 'block';
	}
	
	highlightElement(item.elementToHighlight, true);
	if (ttsAudioPlayer) {
		setTimeout(() => {
			if (!isAutoPlaying) return;
			ttsAudioPlayer.src = item.url;
			ttsAudioPlayer.play().catch(error => {
				console.error(`Error playing TTS audio for index ${currentPlaybackIndex} (${item.url}):`, error);
				stopPlaybackSequence();
				setErrorState("An error occurred during audio playback.");
				setInteractionsDisabled(false);
			});
		}, 300);
	} else {
		console.error("TTS Audio Player not found!");
		stopPlaybackSequence();
		setInteractionsDisabled(false);
	}
}

function handleTtsAudioEnded() {
	if (!isAutoPlaying) return;
	currentPlaybackIndex++;
	playNextInSequence();
}

function handleTtsAudioError(event) {
	console.error("TTS Audio Player Error:", event);
	if (isAutoPlaying) {
		stopPlaybackSequence();
		setErrorState("An error occurred during audio playback.");
		setInteractionsDisabled(false);
	}
}

function setupAudioEventListeners() {
	if (ttsAudioPlayer) {
		ttsAudioPlayer.addEventListener('ended', handleTtsAudioEnded);
		ttsAudioPlayer.addEventListener('error', handleTtsAudioError);
		// Add pause handling if needed
	}
	
	if (feedbackAudioPlayer) {
		// These events are now primarily used to update the modal's play button state
		// They no longer trigger checkStateAndTransition
		feedbackAudioPlayer.addEventListener('ended', () => {
			console.log('Feedback audio ended.');
			// Update modal button state if modal is still visible
			if (isModalVisible && playFeedbackModalButton) {
				playFeedbackModalButton.innerHTML = '<i class="fas fa-volume-up me-1"></i> Play Feedback Audio';
			}
		});
		feedbackAudioPlayer.addEventListener('error', (e) => {
			console.error('Feedback audio player error:', e);
			if (isModalVisible && playFeedbackModalButton) {
				playFeedbackModalButton.innerHTML = '<i class="fas fa-volume-up me-1"></i> Play Feedback Audio';
				// Optionally show error in modal body near button
				if (feedbackAudioError) {
					feedbackAudioError.textContent = 'Audio playback error.';
					toggleElement(feedbackAudioError, true);
				}
			}
		});
	}
	
}
