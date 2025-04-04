document.addEventListener('DOMContentLoaded', () => {
	
	// --- Shared Audio Player ---
	const sharedAudioPlayer = document.getElementById('sharedAudioPlayer');
	let currentlyPlayingButton = null;
	
	if (sharedAudioPlayer) {
		sharedAudioPlayer.addEventListener('ended', () => {
			resetPlayButton(currentlyPlayingButton);
			currentlyPlayingButton = null;
		});
		sharedAudioPlayer.addEventListener('pause', () => {
			// If paused externally or by ending, reset the button state
			if (!sharedAudioPlayer.ended) {
				resetPlayButton(currentlyPlayingButton);
				currentlyPlayingButton = null;
			}
		});
		sharedAudioPlayer.addEventListener('error', (e) => {
			console.error("Audio Player Error:", e);
			showError(currentlyPlayingButton?.dataset?.errorAreaId || null, 'Audio playback error.');
			resetPlayButton(currentlyPlayingButton);
			currentlyPlayingButton = null;
		});
	}
	
	function playAudio(button, audioUrl) {
		if (!sharedAudioPlayer || !button || !audioUrl) return;
		
		// Stop currently playing audio (if any and it's different)
		if (currentlyPlayingButton && currentlyPlayingButton !== button) {
			sharedAudioPlayer.pause();
			resetPlayButton(currentlyPlayingButton);
		}
		
		if (currentlyPlayingButton === button) {
			// If the clicked button is already playing, pause it
			sharedAudioPlayer.pause();
			resetPlayButton(button);
			currentlyPlayingButton = null;
		} else {
			// Start playing new audio
			sharedAudioPlayer.src = audioUrl;
			sharedAudioPlayer.play().then(() => {
				button.classList.add('playing');
				currentlyPlayingButton = button;
				// Hide errors associated with this button if any
				if(button.dataset.errorAreaId) hideError(button.dataset.errorAreaId);
			}).catch(error => {
				console.error("Error playing audio:", error);
				if(button.dataset.errorAreaId) showError(button.dataset.errorAreaId, 'Playback failed.');
				resetPlayButton(button);
				currentlyPlayingButton = null; // Ensure state is cleared
			});
		}
	}
	
	function resetPlayButton(button) {
		if (button) {
			button.classList.remove('playing');
		}
	}
	
	// --- Image Modal ---
	const imageModal = document.getElementById('imageModal');
	if (imageModal) {
		const imageModalDisplay = document.getElementById('imageModalDisplay');
		const imageModalLabel = document.getElementById('imageModalLabel');
		
		imageModal.addEventListener('show.bs.modal', function (event) {
			const triggerElement = event.relatedTarget; // Element that triggered the modal
			const imageUrl = triggerElement.getAttribute('data-image-url');
			const imageAlt = triggerElement.getAttribute('data-image-alt') || 'Image Preview';
			
			if (imageModalDisplay) imageModalDisplay.src = imageUrl;
			if (imageModalDisplay) imageModalDisplay.alt = imageAlt;
			if (imageModalLabel) imageModalLabel.textContent = imageAlt;
		});
		// Clear src on hide to stop potential background loading/playing if it was a video/gif
		imageModal.addEventListener('hidden.bs.modal', function () {
			if (imageModalDisplay) imageModalDisplay.src = '';
		});
	}
	
	
	// --- Helper Functions (Keep from original, potentially merge with common.js) ---
	function showSpinner(button, show = true) {
		if (!button) return;
		const spinner = button.querySelector('.spinner-border');
		if (spinner) spinner.classList.toggle('d-none', !show);
		button.disabled = show;
	}
	
	function showError(elementId, message) {
		const errorEl = document.getElementById(elementId);
		if (errorEl) {
			errorEl.textContent = message || 'An unknown error occurred.';
			errorEl.style.display = 'inline-block'; // Use inline-block for errors next to controls
		} else {
			console.warn(`Error element not found: ${elementId}`);
			// Fallback to general error area?
			// showMainError(message); // If you have a showMainError function
		}
	}
	
	function hideError(elementId) {
		const errorEl = document.getElementById(elementId);
		if (errorEl) {
			errorEl.style.display = 'none';
			errorEl.textContent = '';
		}
	}
	
	// --- Asset Display Updaters (Modified) ---
	function updateVideoDisplay(partIndex, videoUrl, videoPath) {
		// Keep the original video update logic
		const placeholder = document.getElementById(`video-placeholder-${partIndex}`);
		const displayArea = document.getElementById(`video-display-${partIndex}`);
		const buttonArea = document.getElementById(`video-button-area-${partIndex}`); // Area containing the button
		
		if (!displayArea) return;
		
		displayArea.innerHTML = ''; // Clear previous content
		
		const video = document.createElement('video');
		video.src = videoUrl; // Use the direct URL from response
		video.controls = true;
		video.preload = 'metadata';
		video.classList.add('generated-video');
		
		const pathText = document.createElement('p');
		pathText.innerHTML = `<small class="text-muted d-block mt-1">Video generated. Path: ${videoPath || 'N/A'}</small>`;
		
		displayArea.appendChild(video);
		displayArea.appendChild(pathText);
		displayArea.style.display = 'block';
		
		if (placeholder) placeholder.style.display = 'none'; // Hide placeholder
		
		// Find the button within the button area and update text/hide area
		if (buttonArea) {
			const button = buttonArea.querySelector('.generate-part-video-btn');
			if (button) {
				button.innerHTML = `<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span><i class="fas fa-video me-1"></i> Regenerate Video`;
				button.disabled = false; // Ensure button is enabled if needed later
				showSpinner(button, false); // Make sure spinner is off
			}
			buttonArea.style.display = 'block'; // Show button area (now contains regenerate) - or hide if preferred
			// buttonArea.style.display = 'none'; // Hide if you don't want regenerate immediately visible
		}
	}
	
	function updateQuestionAudioDisplay(quizId, audioUrl) {
		const controlsArea = document.getElementById(`q-audio-controls-${quizId}`); // Target the span
		const errorAreaId = `q-audio-error-${quizId}`;
		if (!controlsArea) return;
		
		hideError(errorAreaId);
		controlsArea.innerHTML = ''; // Clear 'Generate' button or old play button
		
		const playButton = document.createElement('button');
		playButton.classList.add('btn', 'btn-sm', 'btn-outline-primary', 'btn-play-pause');
		playButton.dataset.audioUrl = audioUrl;
		playButton.title = 'Play Question Audio';
		playButton.dataset.errorAreaId = errorAreaId; // Link error display
		playButton.innerHTML = '<i class="fas fa-play"></i><i class="fas fa-pause"></i>';
		
		controlsArea.appendChild(playButton);
	}
	
	function updateAnswerAudioStatus(quizId, success = true) {
		const statusArea = document.getElementById(`a-audio-status-${quizId}`);
		const buttonContainer = document.getElementById(`a-audio-container-${quizId}`); // The asset container
		const errorAreaId = `a-audio-error-${quizId}`;
		hideError(errorAreaId);
		
		if (!statusArea || !buttonContainer) return;
		
		if (success) {
			statusArea.innerHTML = '<span class="text-success small"><i class="fas fa-check-circle me-1"></i>Generated</span>';
			// Remove the 'Generate All' button if it exists
			const generateButton = buttonContainer.querySelector('.generate-asset-btn');
			if (generateButton) {
				generateButton.remove();
			}
			// IMPORTANT: Need to update the individual answer/feedback play buttons
			// Easiest might be to just reload the page or make another AJAX call
			// to fetch the updated quiz data with new audio URLs.
			// For now, just show status and suggest refresh.
			statusArea.innerHTML += ' <a href="javascript:location.reload();" class="small">(Refresh page to activate players)</a>';
			
		} else {
			statusArea.innerHTML = '<span class="text-danger small"><i class="fas fa-times-circle me-1"></i>Failed</span>';
			// Re-enable button on failure
			const generateButton = buttonContainer.querySelector('.generate-asset-btn');
			if (generateButton) showSpinner(generateButton, false);
		}
	}
	
	
	function updateQuizImageDisplay(quizId, imageUrls, prompt) {
		const displayArea = document.getElementById(`q-image-display-${quizId}`);
		const buttonContainer = document.getElementById(`q-image-container-${quizId}`); // Container has button & input
		const errorAreaId = `q-image-error-${quizId}`;
		hideError(errorAreaId);
		
		if (!displayArea || !buttonContainer) return;
		
		displayArea.innerHTML = ''; // Clear previous content (placeholder or old image)
		
		const link = document.createElement('a');
		link.href = '#'; // Prevent navigation
		link.classList.add('quiz-image-clickable');
		link.dataset.bsToggle = 'modal';
		link.dataset.bsTarget = '#imageModal';
		link.dataset.imageUrl = imageUrls.original || '#';
		link.dataset.imageAlt = `Generated image for prompt: ${prompt || 'Quiz Image'}`;
		link.title = 'Click to enlarge';
		
		const img = document.createElement('img');
		img.src = imageUrls.medium || imageUrls.small || imageUrls.original; // Fallback size
		img.alt = link.dataset.imageAlt;
		img.classList.add('img-thumbnail', 'quiz-image-thumb');
		link.appendChild(img);
		
		displayArea.appendChild(link);
		
		// Update the Regenerate button text if it exists
		const regenButton = buttonContainer.querySelector('.regenerate-quiz-image-btn');
		if (regenButton) {
			regenButton.innerHTML = `<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span><i class="fas fa-sync-alt"></i> Regen`;
			showSpinner(regenButton, false); // Ensure spinner off
		}
		// Update prompt input value (in case backend modifies it slightly)
		const promptInput = document.getElementById(`prompt-input-${quizId}`);
		if(promptInput) promptInput.value = prompt || '';
		
	}
	
	
	// --- Event Listeners ---
	
	// 1. Play/Pause Button Click (Event Delegation)
	document.body.addEventListener('click', (event) => {
		const target = event.target.closest('.btn-play-pause');
		if (target) {
			const audioUrl = target.dataset.audioUrl;
			playAudio(target, audioUrl);
		}
	});
	
	// 2. Generate Video (Keep original logic)
	document.querySelectorAll('.generate-part-video-btn').forEach(button => {
		button.addEventListener('click', async (event) => {
			const btn = event.currentTarget;
			const partIndex = btn.dataset.partIndex;
			const url = btn.dataset.generateUrl;
			const errorElId = `video-error-${partIndex}`;
			
			hideError(errorElId);
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
					if (response.status === 409 || result.message?.includes('already exists')) {
						console.warn(`Video for part ${partIndex} already exists or generation triggered elsewhere.`);
						if(result.video_url && result.video_path) { // Check if URL/Path returned even on conflict
							updateVideoDisplay(partIndex, result.video_url, result.video_path);
						} else {
							// Maybe just hide button?
							btn.closest('.video-button-area').style.display = 'none';
						}
					} else {
						throw new Error(result.message || `HTTP error ${response.status}`);
					}
				} else {
					// Success
					updateVideoDisplay(partIndex, result.video_url, result.video_path);
				}
			} catch (error) {
				console.error(`Error generating video for part ${partIndex}:`, error);
				showError(errorElId, `Failed: ${error.message}`);
				showSpinner(btn, false); // Ensure spinner hidden on error
			} finally {
				// Spinner is handled within success/error/conflict branches now
				// showSpinner(btn, false);
			}
		});
	});
	
	
	// 3. Generate Basic Assets (Question Audio, Answer Audio - using .generate-asset-btn)
	document.querySelectorAll('.generate-asset-btn').forEach(button => {
		button.addEventListener('click', async (event) => {
			const btn = event.currentTarget;
			const url = btn.dataset.url;
			const assetType = btn.dataset.assetType;
			const quizId = btn.dataset.quizId;
			const targetAreaId = btn.dataset.targetAreaId;
			// const buttonAreaId = btn.dataset.buttonAreaId; // Less relevant now
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
					if (response.status === 409 || result.message?.includes('already exists')) {
						console.warn(`${assetType} for quiz ${quizId} already exists.`);
						// Update UI if possible based on returned data
						if (assetType === 'question-audio' && result.audio_url) {
							updateQuestionAudioDisplay(quizId, result.audio_url);
						} else if (assetType === 'answer-audio') {
							updateAnswerAudioStatus(quizId, true); // Mark as success
						} else {
							// Fallback: just remove the button if it's still there
							btn.remove();
						}
					} else {
						throw new Error(result.message || `HTTP error ${response.status}`);
					}
				} else {
					// --- Success ---
					if (assetType === 'question-audio' && result.audio_url) {
						updateQuestionAudioDisplay(quizId, result.audio_url);
						btn.remove(); // Remove generate button on success
					} else if (assetType === 'answer-audio') {
						updateAnswerAudioStatus(quizId, true);
						// Button removed by updateAnswerAudioStatus logic
					}
					// Note: Image generation is handled by a different button now
				}
			} catch (error) {
				console.error(`Error generating ${assetType} for quiz ${quizId}:`, error);
				showError(errorAreaId, `Failed: ${error.message}`);
				showSpinner(btn, false); // Re-enable button on error
			} finally {
				// Spinner is generally handled within success/error/removal logic now
				// showSpinner(btn, false);
			}
		});
	});
	
	// 4. Regenerate Quiz Image Button
	document.querySelectorAll('.regenerate-quiz-image-btn').forEach(button => {
		button.addEventListener('click', async (event) => {
			const btn = event.currentTarget;
			const url = btn.dataset.url; // Reuse generate URL
			const quizId = btn.dataset.quizId;
			const promptInputId = btn.dataset.promptInputId;
			const targetAreaId = btn.dataset.targetAreaId; // Image display area
			const errorAreaId = btn.dataset.errorAreaId;
			
			const promptInput = document.getElementById(promptInputId);
			const newPrompt = promptInput ? promptInput.value.trim() : '';
			
			if (!newPrompt) {
				showError(errorAreaId, 'Image prompt cannot be empty.');
				return;
			}
			
			hideError(errorAreaId);
			showSpinner(btn, true);
			
			try {
				const response = await fetch(url, { // POST to the existing image generation route
					method: 'POST',
					headers: {
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
						'Accept': 'application/json',
						'Content-Type': 'application/json' // Send prompt as JSON
					},
					body: JSON.stringify({ new_prompt: newPrompt }) // Send the new prompt
				});
				const result = await response.json();
				
				if (!response.ok || !result.success) {
					throw new Error(result.message || `HTTP error ${response.status}`);
				} else {
					// Success
					updateQuizImageDisplay(quizId, result.image_urls, newPrompt); // Update with new image
				}
			} catch (error) {
				console.error(`Error regenerating image for quiz ${quizId}:`, error);
				showError(errorAreaId, `Failed: ${error.message}`);
				showSpinner(btn, false); // Ensure spinner off and button enabled on error
			} finally {
				// Spinner handled by update function on success, manually on error
				// showSpinner(btn, false);
			}
		});
	});
	
	
}); // End DOMContentLoaded
