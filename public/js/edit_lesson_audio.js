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

function updateLessonSentenceAssetsStatus(success, resultData = null) {
	console.log("updateLessonSentenceAssetsStatus called with success:", success, "resultData:", resultData);
	const statusEl = document.getElementById('lesson-content-audio-status');
	const errorArea = document.getElementById('lesson-content-error');
	const generateButton = document.getElementById('generate-lesson-sentence-assets-btn');
	const sentencesListContainer = document.getElementById('sentences-list-lesson');
	const noSentencesMsg = document.getElementById(`no-sentences-msg-lesson`);
	
	if (errorArea) hideError(errorArea);
	if (!statusEl || !sentencesListContainer) return;
	
	// Clear previous sentences and message
	sentencesListContainer.innerHTML = '';
	if(noSentencesMsg) noSentencesMsg.remove();
	
	let finalMessage = '';
	let toastType = 'info';
	
	if (success && resultData && resultData.sentences) {
		const sentences = resultData.sentences;
		let audioErrorCount = 0;
		
		if (sentences.length > 0) {
			const sentenceTemplate = document.getElementById('sentence-item-template').innerHTML;
			console.log("Sentence template:", sentenceTemplate, "Sentences:", sentences.length);
			sentences.forEach((sentence, index) => {
				if (!sentence.audio_url) audioErrorCount++;
				
				// Create new sentence item from template
				let sentenceHtml = sentenceTemplate
					.replace(/SENTENCE_INDEX_PLACEHOLDER/g, index)
					.replace('SENTENCE_TEXT_PLACEHOLDER', escapeHtml(sentence.text))
					.replace('data-image-id=""', `data-image-id="${sentence.generated_image_id || ''}"`)
					.replace(`value="" class="sentence-prompt-idea"`, `value="${escapeHtml(sentence.image_prompt_idea || '')}" class="sentence-prompt-idea"`)
					.replace(`value="" class="sentence-search-keywords"`, `value="${escapeHtml(sentence.image_search_keywords || '')}" class="sentence-search-keywords"`);
				
				// Set correct URLs (ensure routes exist and names match)
				const lessonId = generateButton.dataset.lessonId; // Assuming lesson ID is on button now
				const generateImageUrl = `/lesson/${lessonId}/sentence/${index}/generate-image`; // Construct URL manually or use route() via JS variable
				const uploadImageUrl = `/lesson/${lessonId}/sentence/${index}/upload-image`;
				const searchFreepikUrl = `/lesson/${lessonId}/sentence/${index}/search-freepik`;
				
				sentenceHtml = sentenceHtml.replace(`data-url="#"`, `data-url="${generateImageUrl}"`);
				sentenceHtml = sentenceHtml.replace(`data-freepik-search-url="#"`, `data-freepik-search-url="${searchFreepikUrl}"`);
				// Note: Upload URL isn't directly on button, it's triggered via file input's sibling button
				
				sentencesListContainer.insertAdjacentHTML('beforeend', sentenceHtml);
				
				// Get the newly added elements to update audio/image
				const newItemContainer = document.getElementById(`sentence-item-s${index}`);
				const audioControls = newItemContainer.querySelector('.sentence-audio-controls');
				const imageDisplay = newItemContainer.querySelector('.sentence-image-display');
				const fileInput = newItemContainer.querySelector('.sentence-image-file-input');
				
				
				// Update audio button state
				if (audioControls) {
					const audioErrorId = `sent-audio-error-s${index}`;
					if (sentence.audio_url) {
						const buttonHtml = `
                            <button class="btn btn-sm btn-outline-primary btn-play-pause" data-audio-url="${sentence.audio_url}" data-error-area-id="${audioErrorId}" title="Play Sentence Audio">
                                <i class="fas fa-play"></i><i class="fas fa-pause"></i>
                                <span class="audio-duration ms-1"></span>
                            </button>`;
						audioControls.innerHTML = buttonHtml;
						// Fetch duration for the new button
						const newButton = audioControls.querySelector('.btn-play-pause');
						if (newButton) displayAudioDuration(newButton, sentence.audio_url);
					} else {
						audioControls.innerHTML = `<span class="badge bg-light text-dark" title="Audio not generated"><i class="fas fa-volume-mute"></i></span>`;
					}
				}
				// Update File Input Data Attributes
				if (fileInput) {
					fileInput.dataset.sentenceIndex = index;
					fileInput.dataset.uploadUrl = uploadImageUrl; // Store upload URL if needed elsewhere
				}
				
				// Fetch and display image if ID exists
				if (sentence.generated_image_id && imageDisplay) {
					fetchAndDisplaySentenceImage(index, sentence.generated_image_id);
				} else if (imageDisplay) {
					// Ensure placeholder if no ID
					imageDisplay.innerHTML = '<i class="fas fa-image text-muted fa-lg"></i>';
				}
			});
			
			finalMessage = `Assets generated: now (${sentences.length} sentences)`;
			if (audioErrorCount > 0) {
				finalMessage += ` <span class="text-danger">(${audioErrorCount} audio errors)</span>`;
			}
			toastType = 'success';
			statusEl.innerHTML = finalMessage;
			
		} else {
			// Success response but no sentences returned (e.g., empty text input)
			finalMessage = resultData.message || 'No sentences processed.';
			toastType = 'warning';
			statusEl.textContent = finalMessage;
			sentencesListContainer.innerHTML = `<p class="text-muted fst-italic">${finalMessage}</p>`;
		}
		
	} else {
		// Generation failed
		finalMessage = resultData?.message || 'Asset generation failed.';
		toastType = 'error';
		statusEl.textContent = 'Asset generation failed.';
		if (errorArea) showError(errorArea, finalMessage);
		sentencesListContainer.innerHTML = `<p class="text-danger fst-italic">Could not generate assets.</p>`;
		
	}
	
	// Update generate button state
	if (generateButton) {
		showSpinner(generateButton, false);
		generateButton.innerHTML = `<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span> <i class="fas fa-microphone-alt"></i> Regen Assets`;
		generateButton.title = "Regenerate audio and image prompts for all sentences";
	}
	
	// Show toast notification based on outcome
	// Use the message from resultData if available, otherwise use the constructed finalMessage
	showToast(resultData?.message || finalMessage.replace(/<[^>]*>/g, ''), // Strip potential HTML for toast
		toastType === 'error' ? 'Error' : (toastType === 'warning' ? 'Warning' : 'Complete'),
		toastType);
}

// New helper function to fetch image details and update display
async function fetchAndDisplaySentenceImage(sentenceIndex, imageId) {
	const displayArea = document.getElementById(`sent-image-display-s${sentenceIndex}`);
	const errorArea = document.getElementById(`sent-image-error-s${sentenceIndex}`);
	if (!displayArea || !imageId) return;
	
	displayArea.innerHTML = `<div class="spinner-border spinner-border-sm text-secondary" role="status"></div>`; // Loading indicator
	if (errorArea) hideError(errorArea);
	
	try {
		const response = await fetch(`/api/image-details/${imageId}`); // Replace with actual endpoint
		if (!response.ok) throw new Error('Failed to fetch image details');
		const imageData = await response.json();
		if (!imageData.success) throw new Error(imageData.message || 'Error fetching image data');
		
		const imageUrls = imageData.image_urls;
		const altText = imageData.alt || `Image for sentence ${sentenceIndex + 1}`;
		const displayUrl = imageUrls.small || imageUrls.medium || imageUrls.original; // Prefer small/medium
		
		if (displayUrl) {
			displayArea.innerHTML = `
                <a href="#" class="sentence-image-clickable d-block w-100 h-100" data-bs-toggle="modal" data-bs-target="#imageModal" data-image-url="${imageUrls.original || '#'}" data-image-alt="${altText}" title="Click to enlarge">
                    <img src="${displayUrl}" alt="${altText}" class="img-fluid sentence-image-thumb" style="width: 100%; height: 100%; object-fit: contain;">
                </a>`;
		} else {
			throw new Error('Image URL not found in fetched data.');
		}
		
	} catch (error) {
		console.error(`Error fetching/displaying image ID ${imageId} for sentence ${sentenceIndex}:`, error);
		displayArea.innerHTML = `<i class="fas fa-exclamation-triangle text-danger" title="Error loading image: ${error.message}"></i>`;
		if (errorArea) showError(errorArea, 'Load failed');
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
	
	document.querySelectorAll('.sentence-item[data-image-id]').forEach(item => {
		const imageId = item.dataset.imageId;
		if (imageId) {
			const sentenceIndex = item.dataset.sentenceIndex;
			// Check if display area is just the placeholder before fetching
			const displayArea = item.querySelector('.sentence-image-display');
			if(displayArea && displayArea.querySelector('i.fa-image')) {
				fetchAndDisplaySentenceImage(sentenceIndex, imageId);
			}
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
