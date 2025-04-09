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
