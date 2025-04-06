async function uploadQuestionImage(questionId, file, errorAreaId, successAreaId) {
	const formData = new FormData();
	formData.append('question_image', file);
	
	const url = `/question/${questionId}/upload-image`; // Use the new route
	
	try {
		const response = await fetch(url, {
			method: 'POST',
			headers: {
				'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
				'Accept': 'application/json',
				// 'Content-Type' is automatically set by browser for FormData
			},
			body: formData
		});
		
		const result = await response.json();
		
		if (!response.ok || !result.success) {
			throw new Error(result.message || `Upload failed. Status: ${response.status}`);
		}
		
		// Success
		updateQuestionImageDisplay(questionId, result.image_urls, result.prompt, result.message || 'Image uploaded successfully!');
		
	} catch (error) {
		console.error(`Error uploading image for question ${questionId}:`, error);
		showError(errorAreaId, `Upload Failed: ${error.message}`);
		// Optionally hide success message if shown previously
		hideSuccess(successAreaId);
	}
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

function updateVideoDisplay(partIndex, videoUrl, videoPath) {
	const displayArea = document.getElementById(`video-display-${partIndex}`);
	const buttonArea = document.getElementById(`video-button-area-${partIndex}`);
	const button = buttonArea.querySelector('.generate-part-video-btn');
	
	if (!displayArea || !buttonArea || !button) return;
	
	hideError(`video-error-${partIndex}`); // Hide any previous errors
	
	displayArea.innerHTML = `
            <video controls preload="metadata" src="${videoUrl}" class="generated-video" style="max-width: 100%; max-height: 300px;">
                Your browser does not support the video tag.
            </video>
            <p><small class="text-muted d-block mt-1">Video available. Path: ${videoPath || 'N/A'}</small></p>`;
	displayArea.style.display = 'block'; // Ensure visible
	
	// Update button text to 'Regenerate' and ensure spinner is off
	button.innerHTML = `<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span><i class="fas fa-video me-1"></i> Regenerate Video`;
	showSpinner(button, false);
}

function updateQuestionAudioDisplay(questionId, audioUrl) {
	const controlsArea = document.getElementById(`q-audio-controls-${questionId}`);
	const errorAreaId = `q-audio-error-${questionId}`;
	if (!controlsArea) return;
	
	hideError(errorAreaId);
	controlsArea.innerHTML = `
            <button class="btn btn-sm btn-outline-primary btn-play-pause" data-audio-url="${audioUrl}" data-error-area-id="${errorAreaId}" title="Play Question Audio">
                <i class="fas fa-play"></i><i class="fas fa-pause"></i>
            </button>`;
	// Ensure any associated generate button is removed (might be handled by caller)
	const genButton = controlsArea.closest('.question-item').querySelector(`.generate-asset-btn[data-question-id="${questionId}"][data-asset-type="question-audio"]`);
	genButton.remove();
}

function updateAnswerAudioStatus(questionId, success = true, answersData = null) {
	const statusArea = document.getElementById(`a-audio-status-${questionId}`);
	const buttonContainer = document.getElementById(`a-audio-container-${questionId}`);
	const errorAreaId = `a-audio-error-${questionId}`;
	hideError(errorAreaId);
	
	if (!statusArea || !buttonContainer) return;
	
	const generateButton = buttonContainer.querySelector(`.generate-asset-btn[data-question-id="${questionId}"][data-asset-type="answer-audio"]`);
	
	if (success) {
		statusArea.innerHTML = '<span class="text-success small"><i class="fas fa-check-circle me-1"></i>Generated</span>';
		generateButton.remove(); // Remove the 'Generate All' button
		
		// Update individual answer/feedback play buttons if data provided
		if (answersData && Array.isArray(answersData)) {
			const answerList = document.querySelector(`#question-item-${questionId} .answer-list`);
			if (answerList) {
				answersData.forEach((answer, index) => {
					const ansControls = answerList.querySelector(`#ans-audio-controls-${questionId}-${index}`);
					const fbControls = answerList.querySelector(`#fb-audio-controls-${questionId}-${index}`);
					
					if (ansControls && answer.answer_audio_url) {
						ansControls.innerHTML = `<button class="btn btn-sm btn-outline-primary btn-play-pause" data-audio-url="${answer.answer_audio_url}" data-error-area-id="${errorAreaId}" title="Play Answer Audio"><i class="fas fa-play"></i><i class="fas fa-pause"></i></button>`;
					} else if (ansControls) {
						ansControls.innerHTML = `<span class="badge bg-light text-dark ms-1" title="Answer audio not available"><i class="fas fa-volume-mute"></i></span>`; // Show mute if failed/missing
					}
					
					if (fbControls && answer.feedback_audio_url) {
						fbControls.innerHTML = `<button class="btn btn-sm btn-outline-primary btn-play-pause" data-audio-url="${answer.feedback_audio_url}" data-error-area-id="${errorAreaId}" title="Play Feedback Audio"><i class="fas fa-play"></i><i class="fas fa-pause"></i></button>`;
					} else if (fbControls) {
						fbControls.innerHTML = `<span class="badge bg-light text-dark ms-1" title="Feedback audio not available"><i class="fas fa-volume-mute"></i></span>`; // Show mute if failed/missing
					}
				});
			}
		} else {
			// If no data, just show status and maybe suggest refresh?
			// statusArea.innerHTML += ' <a href="javascript:location.reload();" class="small">(Refresh page to view players)</a>';
			console.warn(`Answer audio generated for question ${questionId}, but no answer data returned to update players.`);
		}
	} else {
		statusArea.innerHTML = '<span class="text-danger small"><i class="fas fa-times-circle me-1"></i>Failed</span>';
		// Re-enable button on failure
		if (generateButton) showSpinner(generateButton, false);
	}
}

function updateQuestionImageDisplay(questionId, imageUrls, prompt, successMessage = null) {
	const displayArea = document.getElementById(`q-image-display-${questionId}`);
	const buttonContainer = document.getElementById(`q-image-container-${questionId}`); // Container of image+prompt+buttons
	const errorAreaId = `q-image-error-${questionId}`;
	const successAreaId = `q-image-success-${questionId}`; // ID for success message
	
	hideError(errorAreaId); // Hide previous errors
	hideSuccess(successAreaId); // Hide previous success messages
	
	if (!displayArea || !buttonContainer || !imageUrls) {
		console.error("Missing elements or image URLs for question image update:", questionId);
		showError(errorAreaId, "Internal error updating image display.");
		return;
	}
	
	const altText = `Question Image: ${prompt || 'User provided image'}`;
	// Prefer medium, then small, then original for display
	const displayUrl = imageUrls.medium || imageUrls.small || imageUrls.original;
	
	if (!displayUrl) {
		displayArea.innerHTML = `<span class="text-danger question-image-thumb d-flex align-items-center justify-content-center border rounded p-2 text-center" style="width: 100%; height: 100%;">Error loading image URL</span>`;
		showError(errorAreaId, "Generated/uploaded image URL is missing."); // Show error
		return;
	}
	
	// Update Image Display
	displayArea.innerHTML = `
        <a href="#" class="question-image-clickable" data-bs-toggle="modal" data-bs-target="#imageModal" data-image-url="${imageUrls.original || '#'}" data-image-alt="${altText}" title="Click to enlarge">
            <img src="${displayUrl}" alt="${altText}" class="img-thumbnail question-image-thumb" style="width: 100%; height: 100%; object-fit: contain;">
        </a>`;
	
	// Update Prompt Input (only if prompt is provided, might be null for uploads)
	const promptInput = document.getElementById(`prompt-input-${questionId}`);
	if (promptInput) {
		promptInput.value = prompt ?? ''; // Set to empty string if prompt is null
	}
	
	// Update AI Generate Button Text (might now be just 'Generate' if user uploaded)
	const regenButton = buttonContainer.querySelector('.regenerate-question-image-btn');
	if (regenButton) {
		regenButton.innerHTML = `<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span><i class="fas fa-magic"></i> Generate`; // Always show Generate now? Or check source? Simpler for now.
		regenButton.title = 'Generate image using AI and the prompt above';
		showSpinner(regenButton, false); // Ensure spinner off
	}
	
	// Show success message if provided
	if (successMessage) {
		showSuccess(successAreaId, successMessage);
	}
}


document.addEventListener('DOMContentLoaded', () => {
	// --- Shared Audio Player (Keep as is) ---
	sharedAudioPlayer = document.getElementById('sharedAudioPlayer');
	let currentlyPlayingButton = null;
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
	
	
	// --- Image Modal (Keep as is) ---
	imageModal = document.getElementById('imageModal');
	
	const imageModalDisplay = document.getElementById('imageModalDisplay');
	const imageModalLabel = document.getElementById('imageModalLabel');
	imageModal.addEventListener('show.bs.modal', function (event) {
		const triggerElement = event.relatedTarget;
		const imageUrl = triggerElement.getAttribute('data-image-url');
		const imageAlt = triggerElement.getAttribute('data-image-alt') || 'Image Preview';
		if (imageModalDisplay) imageModalDisplay.src = imageUrl;
		if (imageModalDisplay) imageModalDisplay.alt = imageAlt;
		if (imageModalLabel) imageModalLabel.textContent = imageAlt;
	});
	imageModal.addEventListener('hidden.bs.modal', function () {
		if (imageModalDisplay) imageModalDisplay.src = '';
	});
	
	
	// --- Event Listeners ---
	
	// Use event delegation for dynamically added elements
	document.body.addEventListener('click', async (event) => {
		
		// 1. Play/Pause Button Click
		const playPauseButton = event.target.closest('.btn-play-pause');
		if (playPauseButton) {
			const audioUrl = playPauseButton.dataset.audioUrl;
			playAudio(playPauseButton, audioUrl);
			return; // Stop processing further listeners
		}
		
		// 2. Generate Part Video Button Click
		const generateVideoBtn = event.target.closest('.generate-part-video-btn');
		if (generateVideoBtn) {
			const btn = generateVideoBtn;
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
					// Handle conflict/already exists specifically
					if (response.status === 200 && result.message.includes('already exists')) { // Backend now returns 200 on already exists
						console.warn(`Video for part ${partIndex} already exists.`);
						if (result.video_url && result.video_path) {
							updateVideoDisplay(partIndex, result.video_url, result.video_path); // Update UI anyway
						} else {
							// If no URL returned, just ensure button shows 'Regenerate'
							btn.innerHTML = `<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span><i class="fas fa-video me-1"></i> Regenerate Video`;
						}
						showSpinner(btn, false); // Make sure spinner is off
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
			}
			return; // Stop processing further listeners
		}
		
		// 3. Generate Single Asset Button Click (Question Audio, Answer Audio)
		const generateAssetBtn = event.target.closest('.generate-asset-btn');
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
							btn.remove(); // Fallback: remove the generate button
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
		
		// 4. Regenerate/Generate Question Image Button Click
		const regenImageBtn = event.target.closest('.regenerate-question-image-btn');
		if (regenImageBtn) {
			const btn = regenImageBtn;
			const url = btn.dataset.url;
			const questionId = btn.dataset.questionId;
			const promptInputId = btn.dataset.promptInputId;
			const targetAreaId = btn.dataset.targetAreaId;
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
				const response = await fetch(url, {
					method: 'POST',
					headers: {
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
						'Accept': 'application/json',
						'Content-Type': 'application/json'
					},
					body: JSON.stringify({new_prompt: newPrompt}) // Send prompt
				});
				const result = await response.json();
				
				if (!response.ok || !result.success) {
					// Handle conflict/already exists specifically
					if (response.status === 200 && result.message.includes('already exists')) { // Backend returns 200
						console.warn(`Image for question ${questionId} already exists.`);
						if (result.image_urls && result.prompt) {
							updateQuestionImageDisplay(questionId, result.image_urls, result.prompt); // Update UI anyway
						} else {
							// Fallback if no URLs returned
							btn.innerHTML = `<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span><i class="fas fa-sync-alt"></i> Regen`;
						}
						showSpinner(btn, false); // Make sure spinner is off
					} else {
						throw new Error(result.message || `HTTP error ${response.status}`);
					}
				} else {
					// Success
					updateQuestionImageDisplay(questionId, result.image_urls, result.prompt); // Use prompt from result
				}
			} catch (error) {
				console.error(`Error generating/regenerating image for question ${questionId}:`, error);
				showError(errorAreaId, `Failed: ${error.message}`);
				showSpinner(btn, false); // Ensure button enabled on error
			}
			return; // Stop processing further listeners
		}
		
		
		// 5. NEW: Add Question Batch Button Click
		const addQuestionBatchBtn = event.target.closest('.add-question-batch-btn');
		if (addQuestionBatchBtn) {
			const btn = addQuestionBatchBtn;
			const url = btn.dataset.generateUrl;
			const difficulty = btn.dataset.difficulty;
			const partIndex = btn.dataset.partIndex;
			const targetListId = btn.dataset.targetListId;
			const errorAreaId = btn.dataset.errorAreaId;
			const targetListElement = document.getElementById(targetListId);
			
			if (!targetListElement) {
				console.error(`Target list element #${targetListId} not found.`);
				return;
			}
			
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
					throw new Error(result.message || `HTTP error ${response.status}`);
				}
				
				// Success: Render the new questions
				if (result.questions && Array.isArray(result.questions)) {
					
					// Show success message using proper Bootstrap modal
					const successModal = new bootstrap.Modal(document.getElementById('questionBatchSuccessModal'));
					document.getElementById('questionBatchSuccessMessage').textContent =
						`Successfully generated ${result.questions.length} ${difficulty} questions for part ${parseInt(partIndex) + 1}.`;
					
					// Set up the reload action when modal is confirmed
					document.getElementById('questionBatchSuccessConfirm').onclick = function() {
						window.location.reload();
					};
					
					// Show the modal
					successModal.show();
					
				} else {
					console.warn("Question generation successful, but no question data returned.");
					alert("Questions generated, but no data returned. Please check the console for details.");
				}
				
			} catch (error) {
				console.error(`Error generating ${difficulty} questions for part ${partIndex}:`, error);
				showError(errorAreaId, `Failed: ${error.message}`);
			} finally {
				showSpinner(btn, false);
			}
			return; // Stop processing
		}
		
		// 6. NEW: Delete Question Button Click
		const deleteQuestionBtn = event.target.closest('.delete-question-btn');
		if (deleteQuestionBtn) {
			const btn = deleteQuestionBtn;
			const questionId = btn.dataset.questionId;
			const url = btn.dataset.deleteUrl;
			const questionItemElement = document.getElementById(`question-item-${questionId}`);
			
			if (!questionId || !url || !questionItemElement) {
				console.error("Missing data for question deletion.", {questionId, url, questionItemElement});
				showError(btn.parentElement, "Cannot delete question: internal error."); // Show error near button
				return;
			}
			
			// Confirmation
			if (!confirm(`Are you sure you want to delete this question? This action cannot be undone.`)) {
				return;
			}
			
			showSpinner(btn, true); // Show spinner on delete button
			hideError(btn.parentElement); // Hide previous errors near button
			
			try {
				const response = await fetch(url, {
					method: 'DELETE',
					headers: {
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
						'Accept': 'application/json',
					}
				});
				const result = await response.json();
				
				if (!response.ok || !result.success) {
					throw new Error(result.message || `HTTP error ${response.status}`);
				}
				
				// Success: Remove the element from the DOM
				const listContainer = questionItemElement.parentElement;
				questionItemElement.remove();
				
				// Update count badge
				const badge = listContainer.closest('.question-difficulty-group').querySelector('.badge');
				if (badge) {
					const currentCount = parseInt(badge.textContent) || 0;
					const newCount = Math.max(0, currentCount - 1); // Ensure count doesn't go below 0
					badge.textContent = newCount;
					// Show placeholder if list becomes empty
					if (newCount === 0 && listContainer) {
						const difficulty = listContainer.id.split('-')[1]; // Extract difficulty from ID like 'question-list-easy-0'
						listContainer.innerHTML = `<p class="placeholder-text">No ${difficulty} questions created yet for this part.</p>`;
					}
				}
				
				
			} catch (error) {
				console.error(`Error deleting question ${questionId}:`, error);
				showError(btn.parentElement, `Failed: ${error.message}`); // Show error near button
				showSpinner(btn, false); // Hide spinner on error
			}
			// No finally needed for spinner here, element is removed on success
			return; // Stop processing
		}
		
		
		// --- NEW: Image Upload Trigger ---
		const triggerUploadBtn = event.target.closest('.trigger-upload-btn');
		if (triggerUploadBtn) {
			const fileInputId = triggerUploadBtn.dataset.fileInputId;
			const fileInput = document.getElementById(fileInputId);
			if (fileInput) {
				fileInput.click(); // Open file dialog
			} else {
				console.error("File input not found for ID:", fileInputId);
			}
			return;
		}
		
		
	}); // End of delegated event listener
	
	
	document.body.addEventListener('change', async (event) => {
		const fileInput = event.target.closest('input[type="file"]');
		// Check if it's one of our specific question image inputs
		if (fileInput && fileInput.id.startsWith('file-input-')) {
			const questionId = fileInput.dataset.questionId;
			const errorAreaId = `q-image-error-${questionId}`;
			const successAreaId = `q-image-success-${questionId}`;
			
			if (fileInput.files.length > 0) {
				const file = fileInput.files[0];
				hideError(errorAreaId);
				hideSuccess(successAreaId);
				
				// Show temporary loading state near the button group maybe?
				const buttonGroup = fileInput.closest('.question-item').querySelector('.btn-group[aria-label="Image Actions"]');
				let tempSpinner;
				if (buttonGroup) {
					tempSpinner = document.createElement('span');
					tempSpinner.innerHTML = `<span class="spinner-border spinner-border-sm ms-2" role="status" aria-hidden="true"></span> Uploading...`;
					buttonGroup.parentNode.insertBefore(tempSpinner, buttonGroup.nextSibling);
				}
				
				
				await uploadQuestionImage(questionId, file, errorAreaId, successAreaId);
				
				if (tempSpinner) tempSpinner.remove(); // Remove temporary spinner
				fileInput.value = ''; // Reset file input
			}
		}
	});
}); // End DOMContentLoaded
