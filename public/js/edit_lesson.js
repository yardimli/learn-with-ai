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

async function uploadSentenceImage(sentenceIndex, file, errorAreaId) {
	const formData = new FormData();
	formData.append('sentence_image', file);
	// Construct URL dynamically or get from data attribute
	const lessonId = document.getElementById('generate-lesson-sentence-assets-btn').dataset.lessonId;
	const url = `/lesson/${lessonId}/sentence/${sentenceIndex}/upload-image`;
	
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
		updateSentenceImageDisplay(sentenceIndex, result.image_urls, null, result.image_id, 'Image uploaded!'); // No prompt for uploads
		
	} catch (error) {
		console.error(`Error uploading image for sentence ${sentenceIndex}:`, error);
		showError(errorAreaId, `Upload Failed: ${error.message}`);
	}
}

function updateSentenceImageDisplay(sentenceIndex, imageUrls, prompt, imageId, successMessage = null) {
	const displayArea = document.getElementById(`sent-image-display-s${sentenceIndex}`);
	const sentenceItem = document.getElementById(`sentence-item-s${sentenceIndex}`);
	const errorArea = document.getElementById(`sent-image-error-s${sentenceIndex}`);
	const successArea = document.getElementById(`sent-image-success-s${sentenceIndex}`);
	const promptInput = document.getElementById(`sent-prompt-input-s${sentenceIndex}`);
	
	hideError(errorArea);
	hideSuccess(successArea); // Clear previous success
	
	if (!displayArea || !sentenceItem || !imageUrls) {
		console.error("Missing elements or URLs for sentence image update:", sentenceIndex);
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

function displayLessonPlanPreview(plan) {
	if (!plan) {
		lessonPreviewBody.innerHTML = '<div class="alert alert-warning">Could not generate a valid lesson plan structure. Check AI model or prompt.</div>';
		return;
	}
	let previewHtml = `<h4>${escapeHtml(plan.title || 'Lesson Preview')}</h4>`;
	if (plan.image_prompt_idea) {
		previewHtml += `<p><strong>Image Idea:</strong> ${escapeHtml(plan.image_prompt_idea)}</p>`;
	}
	previewHtml += '<hr>';
	previewHtml += `
            <div class="mb-3 card">
                <div class="card-body">
                    <p class="card-text" style="white-space: pre-line;">${escapeHtml(plan.lesson_content || 'No content generated.')}</p>
                </div>
            </div>
        `;
	lessonPreviewBody.innerHTML = previewHtml;
}

document.addEventListener('DOMContentLoaded', () => {
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
	
	
	// --- Lesson Edit Modal Elements ---
	const editContentModalElement = document.getElementById('editContentModal');
	const editContentModal = editContentModalElement ? new bootstrap.Modal(editContentModalElement) : null;
	const editContentTextInput  = document.getElementById('editContentText');
	const saveContentBtn = document.getElementById('saveContentBtn');
	const editContentError = document.getElementById('editContentError');
	
	// --- MODAL Elements (copied from lessons_list.js, variable names already declared in blade push('scripts')) ---
	generateContentModal = document.getElementById('generateContentModal');
	lessonIdInput = document.getElementById('lessonIdForGeneration');
	lessonTitleDisplay = document.getElementById('lessonTitleDisplay');
	lessonSubjectTextarea = document.getElementById('lessonSubjectDisplay');
	lessonNotesDisplay = document.getElementById('lessonNotesDisplay');
	additionalInstructionsTextarea = document.getElementById('additionalInstructionsTextarea');
	aiModelSelectModal = document.getElementById('aiModelSelect'); // Use the modal's select ID
	autoDetectCheckbox = document.getElementById('autoDetectCategoryCheck');
	generatePreviewButton = document.getElementById('generatePreviewButton');
	generatePreviewSpinner = document.getElementById('generatePreviewSpinner');
	previewContentArea = document.getElementById('previewContentArea');
	lessonPreviewBody = document.getElementById('lessonPreviewBody');
	generationOptionsArea = document.getElementById('generationOptionsArea');
	applyGenerationButton = document.getElementById('applyGenerationButton');
	applyGenerationSpinner = document.getElementById('applyGenerationSpinner');
	generationErrorMessage = document.getElementById('generationErrorMessage');
	cancelGenerationButton = document.getElementById('cancelGenerationButton');
	backToOptionsButton = document.getElementById('backToOptionsButton');
	modalCategorySuggestionArea = document.getElementById('modalCategorySuggestionArea');
	suggestedMainCategoryText = document.getElementById('suggestedMainCategoryText');
	suggestedSubCategoryText = document.getElementById('suggestedSubCategoryText');
	existingCategoryDisplayArea = document.getElementById('existingCategoryDisplayArea');
	existingMainCategoryNameSpan = document.getElementById('existingMainCategoryName');
	existingSubCategoryNameSpan = document.getElementById('existingSubCategoryName');
	existingCategoryNote = document.getElementById('existingCategoryNote');
	autoDetectCheckboxArea = document.getElementById('autoDetectCheckboxArea');
	currentSubCategoryIdInput = document.getElementById('currentSubCategoryId');
	currentSelectedMainCategoryIdInput = document.getElementById('currentSelectedMainCategoryId');
	generationSourceGroup = document.getElementById('generationSourceGroup');
	sourceSubjectRadio = document.getElementById('sourceSubject');
	sourceVideoRadio = document.getElementById('sourceVideo');
	videoSubtitlesDisplayArea = document.getElementById('videoSubtitlesDisplayArea');
	videoSubtitlesTextarea = document.getElementById('videoSubtitlesTextarea');
	videoSubtitlesBase64Input = document.getElementById('videoSubtitlesBase64');
	generationSourceInput = document.getElementById('generationSourceInput');
	
	addVideoModal = document.getElementById('addVideoModal');
	addVideoForm = document.getElementById('addVideoForm');
	lessonIdForVideoInput = document.getElementById('lessonIdForVideo');
	lessonTitleForVideoSpan = document.getElementById('lessonTitleForVideo');
	youtubeVideoIdInputModal = document.getElementById('youtubeVideoIdInputModal'); // Use modal's input ID
	submitVideoButton = document.getElementById('submitVideoButton');
	submitVideoSpinner = document.getElementById('submitVideoSpinner');
	addVideoError = document.getElementById('addVideoError');
	addVideoProgress = document.getElementById('addVideoProgress');
	
	
	// --- Event Listener for "Generate AI Content" button (now on edit page) ---
	// This button is now outside the modal, directly on the page.
	// We use event delegation on document.body in case it's added dynamically,
	// or direct listeners if the buttons are always present.
	// For simplicity, assuming '.generate-ai-content-btn' is present on load.
	document.querySelectorAll('.generate-ai-content-btn').forEach(button => {
		button.addEventListener('click', function() {
			// This function will be called when the modal's 'show.bs.modal' event fires.
			// The data attributes from *this* button will populate the modal.
		});
	});
	document.querySelectorAll('.add-video-btn').forEach(button => {
		button.addEventListener('click', function() {
			// This function will be called when the modal's 'show.bs.modal' event fires.
		});
	});
	
	// --- generateContentModal Setup ---
	if (generateContentModal) {
		generateContentModal.addEventListener('show.bs.modal', async (event) => {
			const button = event.relatedTarget; // This is the .generate-ai-content-btn
			if (!button) return; // Should not happen if triggered by button
			
			const lessonId = button.dataset.lessonId;
			const userTitle = button.dataset.userTitle;
			const lessonSubject = button.dataset.lessonSubject;
			const notes = button.dataset.notes;
			const subCategoryId = button.dataset.subCategoryId;
			const mainCategoryName = button.dataset.mainCategoryName;
			const subCategoryName = button.dataset.subCategoryName;
			const selectedMainCategoryId = button.dataset.selectedMainCategoryId;
			const preferredLlm = button.dataset.preferredLlm;
			const videoId = button.dataset.videoId;
			const videoSubtitlesBase64 = button.dataset.videoSubtitles;
			
			// Reset modal state
			previewContentArea.classList.add('d-none');
			lessonPreviewBody.innerHTML = '';
			generationOptionsArea.classList.remove('d-none');
			applyGenerationButton.classList.add('d-none');
			backToOptionsButton.classList.add('d-none');
			cancelGenerationButton.textContent = 'Cancel';
			generationErrorMessage.classList.add('d-none');
			generationErrorMessage.textContent = '';
			modalCategorySuggestionArea.classList.add('d-none');
			currentGeneratedPlan = null;
			currentSuggestedMainCategory = null;
			currentSuggestedSubCategory = null;
			generatePreviewButton.disabled = false;
			generatePreviewSpinner.classList.add('d-none');
			applyGenerationSpinner.classList.add('d-none');
			existingCategoryDisplayArea.classList.add('d-none');
			autoDetectCheckboxArea.classList.add('d-none');
			autoDetectCheckbox.checked = false;
			isAutoDetectingCategory = false;
			additionalInstructionsTextarea.value = '';
			generationSourceGroup.classList.add('d-none');
			videoSubtitlesDisplayArea.classList.add('d-none');
			videoSubtitlesTextarea.value = '';
			videoSubtitlesBase64Input.value = '';
			sourceSubjectRadio.checked = true;
			generationSourceInput.value = 'subject';
			lessonSubjectTextarea.disabled = false;
			lessonNotesDisplay.disabled = false;
			lessonSubjectTextarea.classList.remove('d-none');
			lessonNotesDisplay.classList.remove('d-none');
			
			lessonIdInput.value = lessonId;
			lessonTitleDisplay.value = userTitle || '';
			lessonSubjectTextarea.value = lessonSubject;
			lessonNotesDisplay.value = notes || '';
			currentSubCategoryIdInput.value = subCategoryId || '';
			currentSelectedMainCategoryIdInput.value = selectedMainCategoryId || '';
			
			try {
				const response = await fetch('/user/llm-instructions', {
					method: 'GET',
					headers: {
						'Accept': 'application/json',
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
					}
				});
				if (!response.ok) throw new Error(`HTTP error ${response.status}`);
				const result = await response.json();
				if (result.success && result.instructions) {
					additionalInstructionsTextarea.value = result.instructions;
				}
			} catch (error) {
				console.error('Error fetching user instructions:', error);
			}
			
			if (aiModelSelectModal && preferredLlm) {
				const preferredOption = aiModelSelectModal.querySelector(`option[value="${preferredLlm}"]`);
				if (preferredOption) {
					aiModelSelectModal.value = preferredLlm;
				} else if (aiModelSelectModal.options.length > 0) {
					// Fallback if preferredLlm from button is not in modal's list (e.g. list updated)
					const defaultSelectedOption = aiModelSelectModal.querySelector('option[selected]');
					if (defaultSelectedOption) aiModelSelectModal.value = defaultSelectedOption.value;
					else aiModelSelectModal.selectedIndex = 0;
				}
			}
			
			
			if (videoId && videoSubtitlesBase64) {
				try {
					const decodedSubtitles = atob(videoSubtitlesBase64);
					videoSubtitlesTextarea.value = decodedSubtitles;
					videoSubtitlesBase64Input.value = videoSubtitlesBase64;
					generationSourceGroup.classList.remove('d-none');
					sourceVideoRadio.disabled = false;
				} catch (e) {
					console.error("Error decoding base64 subtitles:", e);
					generationSourceGroup.classList.add('d-none');
					sourceVideoRadio.disabled = true;
				}
			} else {
				generationSourceGroup.classList.add('d-none');
				sourceVideoRadio.disabled = true;
				sourceSubjectRadio.checked = true;
				generationSourceInput.value = 'subject';
			}
			
			if (subCategoryId && mainCategoryName && subCategoryName) {
				existingMainCategoryNameSpan.textContent = mainCategoryName;
				existingSubCategoryNameSpan.textContent = subCategoryName;
				existingCategoryNote.textContent = 'Content will be generated for this category.';
				existingCategoryDisplayArea.classList.remove('d-none');
				autoDetectCheckboxArea.classList.add('d-none');
				isAutoDetectingCategory = false;
			} else if (selectedMainCategoryId && mainCategoryName && !subCategoryId) {
				existingMainCategoryNameSpan.textContent = mainCategoryName;
				existingSubCategoryNameSpan.textContent = '(None - will be auto-detected)';
				existingCategoryNote.textContent = 'Sub-category will be auto-detected based on content.';
				existingCategoryDisplayArea.classList.remove('d-none');
				autoDetectCheckboxArea.classList.add('d-none');
				isAutoDetectingCategory = true;
			} else {
				existingCategoryDisplayArea.classList.add('d-none');
				autoDetectCheckboxArea.classList.remove('d-none');
				autoDetectCheckbox.checked = true;
				isAutoDetectingCategory = true;
			}
			
			const autoDetectChangeHandler = () => {
				if (!autoDetectCheckboxArea.classList.contains('d-none')) {
					isAutoDetectingCategory = autoDetectCheckbox.checked;
				}
			};
			autoDetectCheckbox.removeEventListener('change', autoDetectChangeHandler);
			autoDetectCheckbox.addEventListener('change', autoDetectChangeHandler);
			if (!autoDetectCheckboxArea.classList.contains('d-none')) {
				isAutoDetectingCategory = autoDetectCheckbox.checked;
			}
		});
		
		if (generationSourceGroup) {
			generationSourceGroup.addEventListener('change', (event) => {
				const selectedSource = event.target.value;
				generationSourceInput.value = selectedSource;
				if (selectedSource === 'video') {
					videoSubtitlesDisplayArea.classList.remove('d-none');
					// lessonSubjectTextarea.disabled = true;
					// lessonNotesDisplay.disabled = true;
				} else {
					videoSubtitlesDisplayArea.classList.add('d-none');
					// lessonSubjectTextarea.disabled = false;
					// lessonNotesDisplay.disabled = false;
				}
			});
		}
		
		generateContentModal.addEventListener('hidden.bs.modal', () => {
			lessonIdInput.value = '';
			lessonTitleDisplay.value = '';
			lessonSubjectTextarea.value = '';
			lessonNotesDisplay.value = '';
			additionalInstructionsTextarea.value = '';
			currentSubCategoryIdInput.value = '';
			currentSelectedMainCategoryIdInput.value = '';
			generationSourceGroup.classList.add('d-none');
			videoSubtitlesDisplayArea.classList.add('d-none');
			videoSubtitlesTextarea.value = '';
			videoSubtitlesBase64Input.value = '';
			sourceSubjectRadio.checked = true;
			generationSourceInput.value = 'subject';
			lessonSubjectTextarea.disabled = false;
			lessonNotesDisplay.disabled = false;
			lessonSubjectTextarea.classList.remove('d-none');
			lessonNotesDisplay.classList.remove('d-none');
		});
	}
	
	if (generatePreviewButton) {
		generatePreviewButton.addEventListener('click', async () => {
			const lessonId = lessonIdInput.value;
			const userTitle = lessonTitleDisplay.value;
			const subject = lessonSubjectTextarea.value;
			const notes = lessonNotesDisplay.value;
			const additionalInstructions = additionalInstructionsTextarea.value;
			const llm = aiModelSelectModal.value; // Use modal's select
			const autoDetect = isAutoDetectingCategory;
			const generationSource = generationSourceInput.value;
			let subtitles = null;
			
			if (generationSource === 'video') {
				subtitles = videoSubtitlesTextarea.value;
				if (!subtitles) {
					showToast('Video subtitles are missing or empty.', 'Error', 'error');
					return;
				}
			}
			
			if (!lessonId || !userTitle || !llm || (generationSource === 'subject' && !subject)) {
				showToast('Missing required fields (Title, AI Model, and Subject if not using subtitles).', 'Error', 'error');
				return;
			}
			
			generatePreviewButton.disabled = true;
			generatePreviewSpinner.classList.remove('d-none');
			generationErrorMessage.classList.add('d-none');
			generationErrorMessage.textContent = '';
			previewContentArea.classList.remove('d-none');
			lessonPreviewBody.innerHTML = `<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading preview...</span></div><p class="mt-2">Generating preview (this may take a minute)...</p></div>`;
			generationOptionsArea.classList.add('d-none');
			backToOptionsButton.classList.remove('d-none');
			cancelGenerationButton.textContent = 'Cancel Generation';
			
			try {
				const response = await fetch(`/lesson/${lessonId}/generate-preview`, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
						'Accept': 'application/json',
					},
					body: JSON.stringify({
						llm: llm,
						user_title: userTitle,
						subject: subject,
						notes: notes,
						auto_detect_category: autoDetect,
						additional_instructions: additionalInstructions,
						generation_source: generationSource,
						...(generationSource === 'video' && { video_subtitles: subtitles })
					}),
				});
				const result = await response.json();
				if (!response.ok || !result.success) {
					throw new Error(result.message || `HTTP error ${response.status}`);
				}
				currentGeneratedPlan = result.plan;
				currentSuggestedMainCategory = result.suggested_main_category;
				currentSuggestedSubCategory = result.suggested_sub_category;
				displayLessonPlanPreview(result.plan); // Ensure this function is available
				if (autoDetect && currentSuggestedMainCategory && currentSuggestedSubCategory) {
					suggestedMainCategoryText.textContent = currentSuggestedMainCategory;
					suggestedSubCategoryText.textContent = currentSuggestedSubCategory;
					modalCategorySuggestionArea.classList.remove('d-none');
				} else {
					modalCategorySuggestionArea.classList.add('d-none');
				}
				applyGenerationButton.classList.remove('d-none');
			} catch (error) {
				console.error('Error generating preview:', error);
				lessonPreviewBody.innerHTML = `<div class="alert alert-danger">Failed to generate preview: ${escapeHtml(error.message)}</div>`;
				generationErrorMessage.textContent = `Error: ${error.message}`;
				generationErrorMessage.classList.remove('d-none');
				applyGenerationButton.classList.add('d-none');
			} finally {
				generatePreviewSpinner.classList.add('d-none');
				// Re-enable buttons
				backToOptionsButton.disabled = false;
				cancelGenerationButton.disabled = false;
			}
		});
	}
	
	if (backToOptionsButton) {
		backToOptionsButton.addEventListener('click', () => {
			previewContentArea.classList.add('d-none');
			generationOptionsArea.classList.remove('d-none');
			applyGenerationButton.classList.add('d-none');
			backToOptionsButton.classList.add('d-none');
			cancelGenerationButton.textContent = 'Cancel';
			generationErrorMessage.classList.add('d-none');
			generationErrorMessage.textContent = '';
			modalCategorySuggestionArea.classList.add('d-none');
			currentGeneratedPlan = null;
			currentSuggestedMainCategory = null;
			currentSuggestedSubCategory = null;
			generatePreviewButton.disabled = false;
		});
	}
	
	if (applyGenerationButton) {
		applyGenerationButton.addEventListener('click', async () => {
			if (!currentGeneratedPlan) {
				showToast('No lesson plan data available to apply.', 'Error', 'error');
				return;
			}
			const lessonId = lessonIdInput.value;
			const categoryInput = isAutoDetectingCategory ? 'auto' : (currentSubCategoryIdInput.value || 'auto');
			
			applyGenerationButton.disabled = true;
			applyGenerationSpinner.classList.remove('d-none');
			generationErrorMessage.classList.add('d-none');
			generationErrorMessage.textContent = '';
			cancelGenerationButton.disabled = true;
			backToOptionsButton.disabled = true;
			
			try {
				const payload = {
					plan: currentGeneratedPlan,
					category_input: categoryInput,
					suggested_main_category: categoryInput === 'auto' ? currentSuggestedMainCategory : null,
					suggested_sub_category: categoryInput === 'auto' ? currentSuggestedSubCategory : null,
				};
				const response = await fetch(`/lesson/${lessonId}/apply-plan`, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
						'Accept': 'application/json',
					},
					body: JSON.stringify(payload),
				});
				const result = await response.json();
				if (!response.ok || !result.success) {
					throw new Error(result.message || `HTTP error ${response.status}`);
				}
				showToast(result.message || 'Lesson content applied successfully!', 'Success', 'success');
				const modalInstance = bootstrap.Modal.getInstance(generateContentModal);
				if (modalInstance) modalInstance.hide();
				window.location.reload(); // Reload to see changes on edit page
			} catch (error) {
				console.error('Error applying generated plan:', error);
				generationErrorMessage.textContent = `Failed to apply content: ${escapeHtml(error.message)}`;
				generationErrorMessage.classList.remove('d-none');
				applyGenerationButton.disabled = false;
				cancelGenerationButton.disabled = false;
				backToOptionsButton.disabled = false;
			} finally {
				applyGenerationSpinner.classList.add('d-none');
			}
		});
	}
	
	
	// --- addVideoModal Setup ---
	if (addVideoModal) {
		addVideoModal.addEventListener('show.bs.modal', (event) => {
			const button = event.relatedTarget; // This is the .add-video-btn
			if (!button) return;
			
			const lessonId = button.dataset.lessonId;
			const lessonTitle = button.dataset.lessonTitle;
			
			lessonIdForVideoInput.value = lessonId;
			lessonTitleForVideoSpan.textContent = lessonTitle;
			youtubeVideoIdInputModal.value = ''; // Use modal's input ID
			addVideoError.classList.add('d-none');
			addVideoError.textContent = '';
			addVideoProgress.classList.add('d-none');
			submitVideoButton.disabled = false;
			submitVideoSpinner.classList.add('d-none');
		});
	}
	
	if (addVideoForm) {
		addVideoForm.addEventListener('submit', async (event) => {
			event.preventDefault();
			const lessonId = lessonIdForVideoInput.value;
			let videoId = youtubeVideoIdInputModal.value.trim(); // Use modal's input ID
			
			if (!videoId) {
				addVideoError.textContent = 'Please enter a YouTube Video ID or URL.';
				addVideoError.classList.remove('d-none');
				return;
			}
			
			const urlPatterns = [
				/(?:https?:\/\/)?(?:www\.)?youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/,
				/(?:https?:\/\/)?(?:www\.)?youtu\.be\/([a-zA-Z0-9_-]{11})/,
				/(?:https?:\/\/)?(?:www\.)?youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/,
				/(?:https?:\/\/)?(?:www\.)?youtube\.com\/v\/([a-zA-Z0-9_-]{11})/
			];
			let extractedId = null;
			for (const pattern of urlPatterns) {
				const match = videoId.match(pattern);
				if (match && match[1]) {
					extractedId = match[1];
					break;
				}
			}
			if (!extractedId && /^[a-zA-Z0-9_-]{11}$/.test(videoId)) {
				extractedId = videoId;
			}
			
			if (!extractedId) {
				addVideoError.textContent = 'Invalid YouTube Video ID or URL format.';
				addVideoError.classList.remove('d-none');
				youtubeVideoIdInputModal.focus(); // Use modal's input ID
				return;
			}
			youtubeVideoIdInputModal.value = extractedId; // Use modal's input ID
			videoId = extractedId;
			
			submitVideoButton.disabled = true;
			submitVideoSpinner.classList.remove('d-none');
			addVideoError.classList.add('d-none');
			addVideoError.textContent = '';
			addVideoProgress.classList.remove('d-none');
			
			try {
				const response = await fetch(`/lesson/${lessonId}/add-youtube`, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
						'Accept': 'application/json',
					},
					body: JSON.stringify({ youtube_video_id: videoId }),
				});
				const result = await response.json();
				if (!response.ok || !result.success) {
					throw new Error(result.message || `HTTP error ${response.status}`);
				}
				showToast(`Video '${result.video_title || ''}' added successfully!`, 'Success', 'success');
				const modalInstance = bootstrap.Modal.getInstance(addVideoModal);
				if (modalInstance) modalInstance.hide();
				window.location.reload(); // Reload to see changes on edit page
			} catch (error) {
				console.error('Error adding YouTube video:', error);
				addVideoError.textContent = `Error: ${error.message}`;
				addVideoError.classList.remove('d-none');
			} finally {
				submitVideoButton.disabled = false;
				submitVideoSpinner.classList.add('d-none');
				addVideoProgress.classList.add('d-none');
			}
		});
	}
	
	
	// --- Event Listeners ---
	
	// Use event delegation for dynamically added elements
	document.body.addEventListener('click', async (event) => {
		
		const generateLessonSentenceAssetsBtn = event.target.closest('#generate-lesson-sentence-assets-btn');
		if (generateLessonSentenceAssetsBtn) {
			const btn = generateLessonSentenceAssetsBtn;
			const lessonId = btn.dataset.lessonId;
			const generateUrl = btn.dataset.generateUrl;
			const statusEl = document.getElementById(`audio-status`);
			const errorArea = document.getElementById(`error`);
			
			if (!confirm(`Generate sentence assets (audio & image prompts)? This replaces existing assets.`)) {
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
				updateLessonSentenceAssetsStatus(result.success, result);
				
				// Original toast logic is now inside updateLessonSentenceAssetsStatus
				
			} catch (error) {
				console.error(`Error generating assets:`, error);
				// Update status to failed - pass error message
				updateLessonSentenceAssetsStatus(false, { message: error.message });
				showToast(`Failed to generate assets: ${error.message}`, 'Error', 'error');
			} finally {
				// Spinner handling is now inside updateLessonSentenceAssetsStatus
			}
			return; // Stop processing
		}
		
		// --- Generate AI Image for SENTENCE ---
		const genSentenceImageBtn = event.target.closest('.generate-sentence-image-btn');
		if (genSentenceImageBtn) {
			const btn = genSentenceImageBtn;
			const sentenceItem = btn.closest('.sentence-item');
			const sentenceIndex = sentenceItem.dataset.sentenceIndex;
			const url = btn.dataset.url;
			const promptInputId = btn.dataset.promptInputId;
			const promptInput = document.getElementById(promptInputId);
			const prompt = promptInput ? promptInput.value.trim() : '';
			const errorAreaId = btn.dataset.errorAreaId; // Ensure this is set correctly in the template
			const imageDisplayId = `sent-image-display-s${sentenceIndex}`; // ID of the image display area
			const successAreaId = `sent-image-success-s${sentenceIndex}`; // Hypothetical success area ID
			
			
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
				updateSentenceImageDisplay(sentenceIndex, result.image_urls, result.prompt, result.image_id, 'AI image generated!');
				
			} catch (error) {
				console.error(`Error generating AI image for sentence ${sentenceIndex}:`, error);
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
						`Successfully generated ${result.questions.length} ${difficulty} questions.`;
					
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
				console.error(`Error generating ${difficulty} questions:`, error);
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
		
		const editLessonContentBtn = event.target.closest('.edit-lesson-content-btn');
		if (editLessonContentBtn && editContentModal) {
			console.log(editLessonContentBtn.dataset);
			const ContentTextElement = document.getElementById('content-text-display');
			const ContentText = ContentTextElement ? ContentTextElement.textContent : '';
			
			// Populate modal
			if (editContentTextInput) editContentTextInput.value = ContentText;
			if (editContentError) {
				editContentError.classList.add('d-none');
				editContentError.textContent = '';
			}
			if (saveContentBtn) {
				showSpinner(saveContentBtn, false); // Ensure spinner is off initially
				saveContentBtn.disabled = false;
			}
		}
		
		// --- Save Lesson Button Click Handler ---
		if (saveContentBtn && editContentModal && editContentTextInput && editContentError) {
			saveContentBtn.addEventListener('click', async () => {
				const ContentText = editContentTextInput.value.trim();
				
				// Basic validation
				let isValid = true;
				editContentTextInput.classList.remove('is-invalid');
				editContentError.classList.add('d-none');
				
				if (!ContentText || ContentText.length < 10) { // Example validation
					editContentTextInput.classList.add('is-invalid');
					isValid = false;
				}
				
				if (!isValid) {
					editContentError.textContent = 'Please fill in all fields correctly.';
					editContentError.classList.remove('d-none');
					return;
				}
				
				const updateContentUrl = `/lesson/${lessonId}/update-content`;
				
				showSpinner(saveContentBtn, true);
				
				try {
					const response = await fetch(updateContentUrl, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
							'Accept': 'application/json',
						},
						body: JSON.stringify({
							lesson_text: ContentText
						})
					});
					
					const result = await response.json();
					
					if (!response.ok || !result.success) {
						throw new Error(result.message || `HTTP error ${response.status}`);
					}
					
					// --- Success: Update the display on the main page ---
					const TextElement = document.getElementById(`text-display`);
					const titleElement = document.querySelector(`.edit-lesson-content-btn`).closest('h3').querySelector('span');
					const editButton = document.querySelector(`.edit-lesson-content-btn`);
					if (TextElement) TextElement.textContent = result.updated_content.text;
					if (titleElement) titleElement.textContent = result.updated_content.title;
					if (editButton) editButton.dataset.Title = result.updated_content.title; // Update button data attribute
					
					editContentModal.hide();
					showToast(result.message || 'Lesson updated successfully!', 'Success', 'success');
					
				} catch (error) {
					console.error(`Error updating lesson:`, error);
					editContentError.textContent = `Update Failed: ${error.message}`;
					editContentError.classList.remove('d-none');
				} finally {
					showSpinner(saveContentBtn, false);
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
			let uploadUrl, errorAreaId, successAreaId, sentenceIndex, questionId;
			
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
				sentenceIndex = sentenceItem.dataset.sentenceIndex;
				const lessonId = sentenceItem.closest('.content-card').getElementById('generate-lesson-sentence-assets-btn').dataset.lessonId; // Get lessonId from button
				uploadUrl = `/lesson/${lessonId}/sentence/${sentenceIndex}/upload-image`;
				errorAreaId = `sent-image-error--s${sentenceIndex}`;
				successAreaId = `sent-image-success-s${sentenceIndex}`;
				
				hideError(errorAreaId);
				// hideSuccess(successAreaId); // Success message handled by update function
				
				await uploadSentenceImage(sentenceIndex, file, errorAreaId); // Call specific upload function
				
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
