function formatTime(seconds) {
	if (isNaN(seconds) || !isFinite(seconds)) {
		return '--:--'; // Handle invalid duration
	}
	if (seconds < 1) {
		return seconds.toFixed(1) + 's'; // Show tenths of a second for very short clips
	}
	if (seconds < 60) {
		return Math.round(seconds) + 's'; // Show whole seconds if under a minute
	}
	const minutes = Math.floor(seconds / 60);
	const remainingSeconds = Math.round(seconds % 60);
	const formattedSeconds = remainingSeconds < 10 ? '0' + remainingSeconds : remainingSeconds;
	return `${minutes}:${formattedSeconds}`;
}

// Function to fetch and display audio duration on a button
function displayAudioDuration(buttonElement, audioUrl) {
	if (!buttonElement || !audioUrl) return;
	const durationSpan = buttonElement.querySelector('.audio-duration');
	if (!durationSpan) {
		console.warn("Button is missing the .audio-duration span", buttonElement);
		return;
	}
	
	// Show a temporary loading indicator
	durationSpan.textContent = '(...)';
	
	// Create a temporary audio element JUST for fetching metadata
	const tempAudio = new Audio(); // Use the Audio constructor
	
	const handleMetadataLoaded = () => {
		const duration = tempAudio.duration;
		durationSpan.textContent = `(${formatTime(duration)})`;
		// No need to explicitly clean up the tempAudio object here,
		// it will be garbage collected. Listeners are { once: true }.
	};
	
	const handleLoadError = (e) => {
		console.error(`Error loading metadata for ${audioUrl}:`, e);
		durationSpan.textContent = '(Err)';
		// Log the specific error detail if possible
		if (tempAudio.error) {
			console.error("MediaError details:", tempAudio.error);
		}
	};
	
	// Add listeners *to the temporary audio element*
	tempAudio.addEventListener('loadedmetadata', handleMetadataLoaded, { once: true });
	tempAudio.addEventListener('error', handleLoadError, { once: true });
	// Add stalled event listener for debugging network issues
	tempAudio.addEventListener('stalled', () => {
		console.warn(`Loading stalled for audio metadata: ${audioUrl}`);
		// Optionally set error state if stalled persists?
		// durationSpan.textContent = '(Stalled)';
	}, { once: true });
	
	
	// Set the source on the temporary audio element to trigger loading
	tempAudio.src = audioUrl;
	
	// Explicitly setting preload="metadata" might help some browsers prioritize
	tempAudio.preload = 'metadata';
	
	// DO NOT use the sharedAudioPlayer here for metadata loading.
	// Avoid calling tempAudio.load() unless necessary, setting src often suffices.
}

function playAudio(button, audioUrl) {
	if (!sharedAudioPlayer || !button || !audioUrl) return;
	
	if (currentlyPlayingButton && currentlyPlayingButton !== button) {
		sharedAudioPlayer.pause();
		resetPlayButton(currentlyPlayingButton);
	}
	
	if (currentlyPlayingButton === button) {
		sharedAudioPlayer.pause(); // Will trigger pause listener to reset state
	} else {
		sharedAudioPlayer.src = audioUrl;
		sharedAudioPlayer.play().then(() => {
			button.classList.add('playing');
			currentlyPlayingButton = button;
			if (button.dataset.errorAreaId) hideError(button.dataset.errorAreaId);
		}).catch(error => {
			console.error("Error playing audio:", error);
			if (button.dataset.errorAreaId) showError(button.dataset.errorAreaId, 'Playback failed.');
			resetPlayButton(button); // Reset button state on play error
			currentlyPlayingButton = null;
		});
	}
}

function resetPlayButton(button) {
	if (button) {
		button.classList.remove('playing');
	}
}

function updateQuestionAudioDisplay(questionId, audioUrl) {
	const controlsArea = document.getElementById(`q-audio-controls-${questionId}`);
	const errorAreaId = `q-audio-error-${questionId}`;
	if (!controlsArea) return;
	
	hideError(errorAreaId);
	
	// Create the button HTML
	const buttonHtml = `
        <button class="btn btn-sm btn-outline-primary btn-play-pause" data-audio-url="${audioUrl}" data-error-area-id="${errorAreaId}" title="Play Question Audio">
            <i class="fas fa-play"></i><i class="fas fa-pause"></i>
            <span class="audio-duration ms-1"></span>
        </button>`;
	controlsArea.innerHTML = buttonHtml; // Replace content with the new button
	
	// --- NEW: Get the newly created button and fetch its duration ---
	const newButton = controlsArea.querySelector('.btn-play-pause');
	if (newButton) {
		displayAudioDuration(newButton, audioUrl);
	}
	// --- END NEW ---
	
	// Ensure any associated generate button spinner is off
	const genButton = controlsArea.closest('.question-item').querySelector(`.generate-audio-asset-btn[data-question-id="${questionId}"][data-asset-type="question-audio"]`);
	if (genButton) {
		showSpinner(genButton, false);
		// Optionally change text or disable genButton here if needed
		genButton.innerHTML = `<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span> <i class="fas fa-microphone-alt"></i> Regen`;
		genButton.title = "Regenerate Question Audio";
		
	}
}

function updateAnswerAudioStatus(questionId, success = true, answersData = null) {
	const statusArea = document.getElementById(`a-audio-status-${questionId}`);
	const buttonContainer = document.getElementById(`a-audio-container-${questionId}`);
	const errorAreaId = `a-audio-error-${questionId}`;
	hideError(errorAreaId);
	
	if (!statusArea || !buttonContainer) return;
	
	const generateButton = buttonContainer.querySelector(`.generate-audio-asset-btn[data-question-id="${questionId}"][data-asset-type="answer-audio"]`);
	
	if (success) {
		statusArea.innerHTML = '<span class="text-success small"><i class="fas fa-check-circle me-1"></i>Generated</span>';
		showSpinner(generateButton, false); // Hide spinner
		
		if (generateButton){
			generateButton.innerHTML = `<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span> <i class="fas fa-microphone-alt"></i> Regen All`;
			generateButton.title = "Regenerate Audio for All Answers & Feedback";
		}
		
		// Update individual answer/feedback play buttons if data provided
		if (answersData && Array.isArray(answersData)) {
			const answerList = document.querySelector(`#question-item-${questionId} .answer-list`);
			if (answerList) {
				answersData.forEach((answer, index) => {
					const ansControls = answerList.querySelector(`#ans-audio-controls-${questionId}-${index}`);
					const fbControls = answerList.querySelector(`#fb-audio-controls-${questionId}-${index}`);
					
					// Update Answer Audio Button
					if (ansControls && answer.answer_audio_url) {
						const ansButtonHtml = `
                            <button class="btn btn-sm btn-outline-primary btn-play-pause" data-audio-url="${answer.answer_audio_url}" data-error-area-id="${errorAreaId}" title="Play Answer Audio">
                                <i class="fas fa-play"></i><i class="fas fa-pause"></i>
                                <span class="audio-duration ms-1"></span>
                            </button>`;
						ansControls.innerHTML = ansButtonHtml;
						// --- NEW: Fetch duration ---
						const newAnsButton = ansControls.querySelector('.btn-play-pause');
						if (newAnsButton) displayAudioDuration(newAnsButton, answer.answer_audio_url);
						// --- END NEW ---
					} else if (ansControls) {
						ansControls.innerHTML = `<span class="badge bg-light text-dark ms-1" title="Answer audio not available"><i class="fas fa-volume-mute"></i></span>`;
					}
					
					// Update Feedback Audio Button
					if (fbControls && answer.feedback_audio_url) {
						const fbButtonHtml = `
                            <button class="btn btn-sm btn-outline-primary btn-play-pause" data-audio-url="${answer.feedback_audio_url}" data-error-area-id="${errorAreaId}" title="Play Feedback Audio">
                                <i class="fas fa-play"></i><i class="fas fa-pause"></i>
                                <span class="audio-duration ms-1"></span>
                            </button>`;
						fbControls.innerHTML = fbButtonHtml;
						// --- NEW: Fetch duration ---
						const newFbButton = fbControls.querySelector('.btn-play-pause');
						if (newFbButton) displayAudioDuration(newFbButton, answer.feedback_audio_url);
						// --- END NEW ---
					} else if (fbControls) {
						fbControls.innerHTML = `<span class="badge bg-light text-dark ms-1" title="Feedback audio not available"><i class="fas fa-volume-mute"></i></span>`;
					}
				});
			}
		} else {
			console.warn(`Answer audio generated for question ${questionId}, but no answer data returned to update players.`);
			// Optionally trigger duration fetch for existing buttons if no data returned?
			// Might be needed if backend returns success but not URLs sometimes.
			const existingAnswerButtons = document.querySelectorAll(`#question-item-${questionId} .btn-play-pause[data-audio-url]`);
			existingAnswerButtons.forEach(button => {
				const audioUrl = button.dataset.audioUrl;
				if (audioUrl) {
					displayAudioDuration(button, audioUrl);
				}
			});
		}
	} else {
		statusArea.innerHTML = '<span class="text-danger small"><i class="fas fa-times-circle me-1"></i>Failed</span>';
		// Re-enable button on failure
		if (generateButton) showSpinner(generateButton, false);
	}
}

document.addEventListener('DOMContentLoaded', () => {
	
	// --- Shared Audio Player (Keep as is) ---
	sharedAudioPlayer = document.getElementById('sharedAudioPlayer');
	// ... (audio player event listeners: ended, pause, error - unchanged) ...
	sharedAudioPlayer.addEventListener('ended', () => {
		resetPlayButton(currentlyPlayingButton);
		currentlyPlayingButton = null;
	});
	sharedAudioPlayer.addEventListener('pause', () => {
		if (!sharedAudioPlayer.ended && currentlyPlayingButton) {
			resetPlayButton(currentlyPlayingButton);
			currentlyPlayingButton = null;
		}
	});
	sharedAudioPlayer.addEventListener('error', (e) => {
		console.error("Audio Player Error:", e);
		const errorAreaId = currentlyPlayingButton.dataset.errorAreaId;
		if (errorAreaId) showError(errorAreaId, 'Audio playback error.');
		resetPlayButton(currentlyPlayingButton);
		currentlyPlayingButton = null;
	});
	
	existingPlayButtons = document.querySelectorAll('.btn-play-pause[data-audio-url]');
	existingPlayButtons.forEach(button => {
		const audioUrl = button.dataset.audioUrl;
		if (audioUrl) {
			displayAudioDuration(button, audioUrl);
		}
	});
	
	document.body.addEventListener('click', async (event) => {
		// 1. Play/Pause Button Click
		const playPauseButton = event.target.closest('.btn-play-pause');
		if (playPauseButton) {
			const audioUrl = playPauseButton.dataset.audioUrl;
			playAudio(playPauseButton, audioUrl);
			return; // Stop processing further listeners
		}
		
		const generateAssetBtn = event.target.closest('.generate-audio-asset-btn');
		if (generateAssetBtn) {
			const btn = generateAssetBtn;
			const url = btn.dataset.url;
			const assetType = btn.dataset.assetType; // 'question-audio', 'answer-audio'
			const questionId = btn.dataset.questionId;
			const targetAreaId = btn.dataset.targetAreaId;
			const errorAreaId = btn.dataset.errorAreaId;
			
			hideError(errorAreaId);
			showSpinner(btn, true);
			
			try {
				const response = await fetch(url, {
					method: 'POST',
					headers: {
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
						'Accept': 'application/json',
					}
				});
				const result = await response.json();
				
				if (!response.ok || !result.success) {
					// Handle conflict/already exists specifically
					if (response.status === 200 && result.message.includes('already exists')) { // Backend returns 200
						console.warn(`${assetType} for question ${questionId} already exists.`);
						if (assetType === 'question-audio' && result.audio_url) {
							updateQuestionAudioDisplay(questionId, result.audio_url); // Update UI to show player
						} else if (assetType === 'answer-audio') {
							updateAnswerAudioStatus(questionId, true, result.answers); // Mark as success, update players if data provided
						} else {
							//btn.remove(); // Fallback: remove the generate button
						}
						showSpinner(btn, false); // Ensure spinner is off
					} else {
						throw new Error(result.message || `HTTP error ${response.status}`);
					}
				} else {
					// --- Success ---
					if (assetType === 'question-audio' && result.audio_url) {
						updateQuestionAudioDisplay(questionId, result.audio_url);
						// Button is removed/replaced by updateQuestionAudioDisplay
					} else if (assetType === 'answer-audio') {
						updateAnswerAudioStatus(questionId, true, result.answers); // Pass returned answer data
						// Button is removed by updateAnswerAudioStatus
					}
				}
			} catch (error) {
				console.error(`Error generating ${assetType} for question ${questionId}:`, error);
				showError(errorAreaId, `Failed: ${error.message}`);
				showSpinner(btn, false); // Re-enable button on error
			}
			return; // Stop processing further listeners
		}
	}); // End of delegated event listener
	
});
