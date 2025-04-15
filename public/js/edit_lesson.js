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

async function uploadSentenceImage(partIndex, sentenceIndex, file, errorAreaId) {
	const formData = new FormData();
	formData.append('sentence_image', file);
	// Construct URL dynamically or get from data attribute
	const lessonId = document.querySelector(`.generate-part-audio-btn[data-part-index="${partIndex}"]`).dataset.lessonId;
	const url = `/lesson/${lessonId}/part/${partIndex}/sentence/${sentenceIndex}/upload-image`;
	
	try {
		const response = await fetch(url, {
			method: 'POST',
			headers: {
				'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
				'Accept': 'application/json',
			},
			body: formData
		});
		const result = await response.json();
		if (!response.ok || !result.success) {
			throw new Error(result.message || `Upload failed. Status: ${response.status}`);
		}
		// Success
		updateSentenceImageDisplay(partIndex, sentenceIndex, result.image_urls, null, result.image_id, 'Image uploaded!'); // No prompt for uploads
		
	} catch (error) {
		console.error(`Error uploading image for sentence ${partIndex}-${sentenceIndex}:`, error);
		showError(errorAreaId, `Upload Failed: ${error.message}`);
	}
}

function updateSentenceImageDisplay(partIndex, sentenceIndex, imageUrls, prompt, imageId, successMessage = null) {
	const displayArea = document.getElementById(`sent-image-display-p${partIndex}-s${sentenceIndex}`);
	const sentenceItem = document.getElementById(`sentence-item-p${partIndex}-s${sentenceIndex}`);
	const errorArea = document.getElementById(`sent-image-error-p${partIndex}-s${sentenceIndex}`);
	const successArea = document.getElementById(`sent-image-success-p${partIndex}-s${sentenceIndex}`);
	const promptInput = document.getElementById(`sent-prompt-input-p${partIndex}-s${sentenceIndex}`);
	
	hideError(errorArea);
	hideSuccess(successArea); // Clear previous success
	
	if (!displayArea || !sentenceItem || !imageUrls) {
		console.error("Missing elements or URLs for sentence image update:", partIndex, sentenceIndex);
		showError(errorArea, "UI Update Error");
		return;
	}
	
	const altText = `Image for sentence: ${prompt || 'User provided'}`;
	const displayUrl = imageUrls.small || imageUrls.medium || imageUrls.original;
	
	if (!displayUrl) {
		displayArea.innerHTML = `<i class="fas fa-exclamation-triangle text-danger" title="Image URL missing"></i>`;
		showError(errorArea, "Image URL missing.");
		return;
	}
	
	// Update Image Display
	displayArea.innerHTML = `
         <a href="#" class="sentence-image-clickable d-block w-100 h-100" data-bs-toggle="modal" data-bs-target="#imageModal" data-image-url="${imageUrls.original || '#'}" data-image-alt="${altText}" title="Click to enlarge">
             <img src="${displayUrl}" alt="${altText}" class="img-fluid sentence-image-thumb" style="width: 100%; height: 100%; object-fit: contain;">
         </a>`;
	
	// Update Image ID on the container
	sentenceItem.dataset.imageId = imageId || '';
	
	// Update hidden prompt input if prompt was provided (e.g., from AI Gen)
	if (promptInput && prompt !== null) {
		promptInput.value = prompt;
	}
	
	// Reset AI generate button spinner
	const regenButton = sentenceItem.querySelector('.generate-sentence-image-btn');
	if (regenButton) {
		showSpinner(regenButton, false);
	}
	
	// Show success message
	if (successMessage) {
		showSuccess(successArea, successMessage, 2000); // Shorter timeout
	}
}

document.addEventListener('DOMContentLoaded', () => {
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
	
	
	// --- Lesson Part Edit Modal Elements ---
	const editPartModalElement = document.getElementById('editPartModal');
	const editPartModal = editPartModalElement ? new bootstrap.Modal(editPartModalElement) : null;
	const editPartIndexInput = document.getElementById('editPartIndex');
	const editPartTitleInput = document.getElementById('editPartTitle');
	const editPartTextInput = document.getElementById('editPartText');
	const savePartBtn = document.getElementById('savePartBtn');
	const editPartError = document.getElementById('editPartError');
	
	
	// --- Event Listeners ---
	
	// Use event delegation for dynamically added elements
	document.body.addEventListener('click', async (event) => {
		
		const generatePartAudioBtn = event.target.closest('.generate-part-audio-btn');
		if (generatePartAudioBtn) {
			const btn = generatePartAudioBtn;
			const partIndex = btn.dataset.partIndex;
			const lessonId = btn.dataset.lessonId; // Using lesson ID now
			const generateUrl = btn.dataset.generateUrl;
			const statusEl = document.getElementById(`part-${partIndex}-audio-status`);
			const errorArea = document.getElementById(`part-${partIndex}-error`);
			
			if (!confirm(`Generate sentence assets (audio & image prompts) for Part ${parseInt(partIndex) + 1}? This replaces existing assets for this part.`)) {
				return;
			}
			showSpinner(btn, true);
			if (statusEl) statusEl.textContent = 'Generating assets...';
			if (errorArea) hideError(errorArea);
			
			try {
				const response = await fetch(generateUrl, {
					method: 'POST',
					headers: {
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
						'Accept': 'application/json',
					}
				});
				// Check for non-JSON response (e.g., server errors before JSON response)
				if (!response.headers.get('content-type')?.includes('application/json')) {
					throw new Error(`Server error: ${response.status} ${response.statusText}`);
				}
				
				const result = await response.json();
				
				// Update status using the modified function which now handles sentence rendering
				updatePartAudioStatus(partIndex, result.success, result);
				
				// Original toast logic is now inside updatePartAudioStatus
				
			} catch (error) {
				console.error(`Error generating assets for part ${partIndex}:`, error);
				// Update status to failed - pass error message
				updatePartAudioStatus(partIndex, false, { message: error.message });
				showToast(`Failed to generate assets: ${error.message}`, 'Error', 'error');
			} finally {
				// Spinner handling is now inside updatePartAudioStatus
			}
			return; // Stop processing
		}
		
		// --- Generate AI Image for SENTENCE ---
		const genSentenceImageBtn = event.target.closest('.generate-sentence-image-btn');
		if (genSentenceImageBtn) {
			const btn = genSentenceImageBtn;
			const sentenceItem = btn.closest('.sentence-item');
			const partIndex = sentenceItem.dataset.partIndex;
			const sentenceIndex = sentenceItem.dataset.sentenceIndex;
			const url = btn.dataset.url;
			const promptInputId = btn.dataset.promptInputId;
			const promptInput = document.getElementById(promptInputId);
			const prompt = promptInput ? promptInput.value.trim() : '';
			const errorAreaId = btn.dataset.errorAreaId; // Ensure this is set correctly in the template
			const imageDisplayId = `sent-image-display-p${partIndex}-s${sentenceIndex}`; // ID of the image display area
			const successAreaId = `sent-image-success-p${partIndex}-s${sentenceIndex}`; // Hypothetical success area ID
			
			
			if (!prompt) {
				showError(errorAreaId, 'Image prompt is empty.');
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
					body: JSON.stringify({ prompt: prompt }) // Send the prompt
				});
				const result = await response.json();
				
				if (!response.ok || !result.success) {
					throw new Error(result.message || `HTTP error ${response.status}`);
				}
				
				// Success: Update the specific sentence's image display
				updateSentenceImageDisplay(partIndex, sentenceIndex, result.image_urls, result.prompt, result.image_id, 'AI image generated!');
				
			} catch (error) {
				console.error(`Error generating AI image for sentence ${partIndex}-${sentenceIndex}:`, error);
				showError(errorAreaId, `AI Gen Failed: ${error.message}`);
			} finally {
				showSpinner(btn, false);
			}
			return;
		}
		
		// --- Trigger Upload for SENTENCE ---
		const triggerSentenceUploadBtn = event.target.closest('.trigger-sentence-upload-btn');
		if (triggerSentenceUploadBtn) {
			const fileInputId = triggerSentenceUploadBtn.dataset.fileInputId;
			const fileInput = document.getElementById(fileInputId);
			if (fileInput) {
				fileInput.click(); // Open file dialog
			} else {
				console.error("Sentence file input not found for ID:", fileInputId);
			}
			return;
		}
		
		
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
					document.getElementById('questionBatchSuccessConfirm').onclick = function () {
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
		
		const editPartBtn = event.target.closest('.edit-part-text-btn');
		if (editPartBtn && editPartModal) {
			const partIndex = editPartBtn.dataset.partIndex;
			const partTitle = editPartBtn.dataset.partTitle; // Get title from button data
			const partTextElement = document.getElementById(`part-text-display-${partIndex}`);
			const partText = partTextElement ? partTextElement.textContent : '';
			
			// Populate modal
			if (editPartIndexInput) editPartIndexInput.value = partIndex;
			if (editPartTitleInput) editPartTitleInput.value = partTitle;
			if (editPartTextInput) editPartTextInput.value = partText;
			if (editPartError) {
				editPartError.classList.add('d-none');
				editPartError.textContent = '';
			}
			if (savePartBtn) {
				showSpinner(savePartBtn, false); // Ensure spinner is off initially
				savePartBtn.disabled = false;
			}
		}
		
		// --- Save Lesson Part Button Click Handler ---
		if (savePartBtn && editPartModal && editPartIndexInput && editPartTitleInput && editPartTextInput && editPartError) {
			savePartBtn.addEventListener('click', async () => {
				const partIndex = editPartIndexInput.value;
				const partTitle = editPartTitleInput.value.trim(); // Get updated title
				const partText = editPartTextInput.value.trim();
				
				// Basic validation
				let isValid = true;
				editPartTitleInput.classList.remove('is-invalid');
				editPartTextInput.classList.remove('is-invalid');
				editPartError.classList.add('d-none');
				
				if (!partTitle) {
					editPartTitleInput.classList.add('is-invalid');
					isValid = false;
				}
				if (!partText || partText.length < 10) { // Example validation
					editPartTextInput.classList.add('is-invalid');
					isValid = false;
				}
				
				if (!isValid) {
					editPartError.textContent = 'Please fill in all fields correctly.';
					editPartError.classList.remove('d-none');
					return;
				}
				
				// Construct URL (Make sure lessonSessionId is globally available or passed differently)
				const updateUrl = `/lesson/${lessonSessionId}/part/${partIndex}/update-text`;
				
				showSpinner(savePartBtn, true);
				
				try {
					const response = await fetch(updateUrl, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
							'Accept': 'application/json',
						},
						body: JSON.stringify({
							part_title: partTitle,
							part_text: partText
						})
					});
					
					const result = await response.json();
					
					if (!response.ok || !result.success) {
						throw new Error(result.message || `HTTP error ${response.status}`);
					}
					
					// --- Success: Update the display on the main page ---
					const partTextElement = document.getElementById(`part-text-display-${partIndex}`);
					const titleElement = document.querySelector(`.edit-part-text-btn[data-part-index='${partIndex}']`).closest('h3').querySelector('span');
					const editButton = document.querySelector(`.edit-part-text-btn[data-part-index='${partIndex}']`);
					if (partTextElement) partTextElement.textContent = result.updated_part.text;
					if (titleElement) titleElement.textContent = `Lesson Part ${parseInt(partIndex) + 1}: ${result.updated_part.title}`;
					if (editButton) editButton.dataset.partTitle = result.updated_part.title; // Update button data attribute
					
					editPartModal.hide();
					showToast(result.message || 'Lesson part updated successfully!', 'Success', 'success');
					
				} catch (error) {
					console.error(`Error updating lesson part ${partIndex}:`, error);
					editPartError.textContent = `Update Failed: ${error.message}`;
					editPartError.classList.remove('d-none');
				} finally {
					showSpinner(savePartBtn, false);
				}
			});
		}
		
		
	}); // End of delegated event listener
	
	
	document.body.addEventListener('change', async (event) => {
		const fileInput = event.target.closest('input[type="file"]');
		if (!fileInput) return;
		
		// Determine if it's for a question or sentence
		const isSentenceUpload = fileInput.classList.contains('sentence-image-file-input');
		const isQuestionUpload = fileInput.id.startsWith('file-input-'); // Existing check
		
		if ((isSentenceUpload || isQuestionUpload) && fileInput.files.length > 0) {
			const file = fileInput.files[0];
			let uploadUrl, errorAreaId, successAreaId, partIndex, sentenceIndex, questionId;
			
			// Show temporary loading state
			const controlsContainer = fileInput.closest(isSentenceUpload ? '.sentence-image-controls' : '.question-image-actions'); // Find appropriate container
			let tempSpinner;
			if (controlsContainer) {
				tempSpinner = document.createElement('span');
				tempSpinner.innerHTML = `<span class="spinner-border spinner-border-sm ms-1" role="status" aria-hidden="true"></span>`;
				controlsContainer.appendChild(tempSpinner); // Append spinner
			}
			
			
			if (isSentenceUpload) {
				const sentenceItem = fileInput.closest('.sentence-item');
				partIndex = sentenceItem.dataset.partIndex;
				sentenceIndex = sentenceItem.dataset.sentenceIndex;
				const lessonId = sentenceItem.closest('.content-card').querySelector('.generate-part-audio-btn').dataset.lessonId; // Get lessonId from part button
				uploadUrl = `/lesson/${lessonId}/part/${partIndex}/sentence/${sentenceIndex}/upload-image`;
				errorAreaId = `sent-image-error-p${partIndex}-s${sentenceIndex}`;
				successAreaId = `sent-image-success-p${partIndex}-s${sentenceIndex}`;
				
				hideError(errorAreaId);
				// hideSuccess(successAreaId); // Success message handled by update function
				
				await uploadSentenceImage(partIndex, sentenceIndex, file, errorAreaId); // Call specific upload function
				
			} else if (isQuestionUpload) {
				questionId = fileInput.dataset.questionId;
				uploadUrl = `/question/${questionId}/upload-image`; // Existing question upload URL
				errorAreaId = `q-image-error-${questionId}`;
				successAreaId = `q-image-success-${questionId}`;
				
				hideError(errorAreaId);
				hideSuccess(successAreaId); // Hide previous success
				
				await uploadQuestionImage(questionId, file, errorAreaId, successAreaId); // Call existing function
			}
			
			if (tempSpinner) tempSpinner.remove(); // Remove temporary spinner
			fileInput.value = ''; // Reset file input
		}
	});

}); // End DOMContentLoaded
