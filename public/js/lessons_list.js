console.log("Lessons List JS loaded.");

document.addEventListener('DOMContentLoaded', () => {
	const generateContentModal = document.getElementById('generateContentModal');
	const sessionIdInput = document.getElementById('sessionIdForGeneration');
	const lessonSubjectTextarea = document.getElementById('lessonSubjectDisplay');
	const aiModelSelect = document.getElementById('aiModelSelect');
	const autoDetectCheckbox = document.getElementById('autoDetectCategoryCheck');
	const generatePreviewButton = document.getElementById('generatePreviewButton');
	const generatePreviewSpinner = document.getElementById('generatePreviewSpinner');
	const previewContentArea = document.getElementById('previewContentArea');
	const lessonPreviewBody = document.getElementById('lessonPreviewBody');
	const generationOptionsArea = document.getElementById('generationOptionsArea');
	const applyGenerationButton = document.getElementById('applyGenerationButton');
	const applyGenerationSpinner = document.getElementById('applyGenerationSpinner');
	const generationErrorMessage = document.getElementById('generationErrorMessage');
	const cancelGenerationButton = document.getElementById('cancelGenerationButton');
	const backToOptionsButton = document.getElementById('backToOptionsButton');
	const modalCategorySuggestionArea = document.getElementById('modalCategorySuggestionArea');
	const suggestedMainCategoryText = document.getElementById('suggestedMainCategoryText');
	const suggestedSubCategoryText = document.getElementById('suggestedSubCategoryText');
	
	// New elements for category display
	const existingCategoryDisplayArea = document.getElementById('existingCategoryDisplayArea');
	const existingMainCategoryNameSpan = document.getElementById('existingMainCategoryName');
	const existingSubCategoryNameSpan = document.getElementById('existingSubCategoryName');
	const autoDetectCheckboxArea = document.getElementById('autoDetectCheckboxArea');
	const currentSubCategoryIdInput = document.getElementById('currentSubCategoryId'); // Hidden input
	
	let currentGeneratedPlan = null; // Store the previewed plan
	let currentSuggestedMainCategory = null;
	let currentSuggestedSubCategory = null;
	let isAutoDetectingCategory = true; // Track state for apply action
	
	// --- Modal Setup ---
	if (generateContentModal) {
		generateContentModal.addEventListener('show.bs.modal', (event) => {
			const button = event.relatedTarget;
			const sessionId = button.dataset.sessionId;
			const userTitle = button.dataset.userTitle;
			const lessonSubject = button.dataset.lessonSubject;
			const notes = button.dataset.notes;
			const subCategoryId = button.dataset.subCategoryId; // Get pre-assigned sub-category ID
			const mainCategoryName = button.dataset.mainCategoryName; // Get pre-assigned main name
			const subCategoryName = button.dataset.subCategoryName; // Get pre-assigned sub name
			
			const categorySelectionMode = button.dataset.categorySelectionMode || 'ai_decide';
			const selectedMainCategoryId = button.dataset.selectedMainCategoryId || '';
			
			// Update UI based on category selection mode
			if (categorySelectionMode === 'ai_decide') {
				// Both categories will be auto-detected
				isAutoDetectingCategory = true;
				existingCategoryDisplayArea.classList.add('d-none');
				autoDetectCheckboxArea.classList.remove('d-none');
				autoDetectCheckbox.checked = true;
			}
			else if (categorySelectionMode === 'main_only') {
				// Only sub-category will be auto-detected
				isAutoDetectingCategory = true;
				existingCategoryDisplayArea.classList.remove('d-none');
				autoDetectCheckboxArea.classList.add('d-none');
				existingMainCategoryNameSpan.textContent = mainCategoryName;
				existingSubCategoryNameSpan.textContent = 'Will be auto-detected';
			}
			else if (categorySelectionMode === 'both') {
				// Both categories manually selected
				isAutoDetectingCategory = false;
				existingCategoryDisplayArea.classList.remove('d-none');
				autoDetectCheckboxArea.classList.add('d-none');
				existingMainCategoryNameSpan.textContent = mainCategoryName;
				existingSubCategoryNameSpan.textContent = subCategoryName;
			}
			
			// Additional state tracking
			// currentCategorySelectionMode = categorySelectionMode;
			// currentSelectedMainCategoryId = selectedMainCategoryId;
			
			// Populate basic fields
			sessionIdInput.value = sessionId;
			lessonTitleDisplay.value = userTitle || '';
			lessonSubjectTextarea.value = lessonSubject;
			lessonNotesDisplay.value = notes || '';
			currentSubCategoryIdInput.value = subCategoryId || ''; // Store original sub-category ID
			
			// Reset modal state
			previewContentArea.classList.add('d-none');
			lessonPreviewBody.innerHTML = ''; // Clear previous preview
			generationOptionsArea.classList.remove('d-none');
			applyGenerationButton.classList.add('d-none');
			backToOptionsButton.classList.add('d-none');
			cancelGenerationButton.textContent = 'Cancel'; // Reset button text
			generationErrorMessage.classList.add('d-none');
			generationErrorMessage.textContent = '';
			modalCategorySuggestionArea.classList.add('d-none');
			currentGeneratedPlan = null;
			currentSuggestedMainCategory = null;
			currentSuggestedSubCategory = null;
			generatePreviewButton.disabled = false;
			generatePreviewSpinner.classList.add('d-none');
			applyGenerationSpinner.classList.add('d-none');
			
			// --- Conditional Category Display ---
			if (subCategoryId && mainCategoryName && subCategoryName) {
				// Lesson already has a category assigned
				isAutoDetectingCategory = false;
				existingCategoryDisplayArea.classList.remove('d-none');
				autoDetectCheckboxArea.classList.add('d-none');
				autoDetectCheckbox.checked = false; // Ensure checkbox is off
				existingMainCategoryNameSpan.textContent = mainCategoryName;
				existingSubCategoryNameSpan.textContent = subCategoryName;
			} else {
				// Lesson needs category detection (or has none)
				isAutoDetectingCategory = true; // Default state when no category is pre-set
				existingCategoryDisplayArea.classList.add('d-none');
				autoDetectCheckboxArea.classList.remove('d-none');
				autoDetectCheckbox.checked = true; // Default to checked
				existingMainCategoryNameSpan.textContent = '';
				existingSubCategoryNameSpan.textContent = '';
			}
			
			// Update isAutoDetectingCategory based on checkbox change *only if* the checkbox area is visible
			if (!autoDetectCheckboxArea.classList.contains('d-none')) {
				autoDetectCheckbox.addEventListener('change', () => {
					isAutoDetectingCategory = autoDetectCheckbox.checked;
				});
				// Set initial state based on checkbox
				isAutoDetectingCategory = autoDetectCheckbox.checked;
			}
			
		});
		
		generateContentModal.addEventListener('hidden.bs.modal', () => {
			// Clear fields on close to prevent stale data
			sessionIdInput.value = '';
			lessonSubjectTextarea.value = '';
			currentSubCategoryIdInput.value = '';
			// Reset other states if necessary
		});
	}
	
	// --- Generate Preview Button ---
	if (generatePreviewButton) {
		generatePreviewButton.addEventListener('click', async () => {
			const sessionId = sessionIdInput.value;
			const userTitle = lessonTitleDisplay.value;
			const subject = lessonSubjectTextarea.value;
			const notes = lessonNotesDisplay.value;
			const llm = aiModelSelect.value;

			const autoDetect = !existingCategoryDisplayArea.classList.contains('d-none') ? false : autoDetectCheckbox.checked;
			
			
			if (!sessionId || !subject || !userTitle || !llm) {
				showToast('Missing required fields.', 'Error', 'error');
				return;
			}
			
			generatePreviewButton.disabled = true;
			generatePreviewSpinner.classList.remove('d-none');
			generationErrorMessage.classList.add('d-none');
			generationErrorMessage.textContent = '';
			previewContentArea.classList.remove('d-none');
			lessonPreviewBody.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading preview...</span>
                    </div>
                    <p class="mt-2">Generating preview (this may take a minute)...</p>
                </div>`;
			generationOptionsArea.classList.add('d-none'); // Hide options
			backToOptionsButton.classList.remove('d-none');
			cancelGenerationButton.textContent = 'Cancel Generation'; // Change cancel button text
			
			
			try {
				const response = await fetch(`/lesson/${sessionId}/generate-preview`, {
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
						auto_detect_category: autoDetect, // Send the determined flag
					}),
				});
				
				const result = await response.json();
				
				if (!response.ok || !result.success) {
					throw new Error(result.message || `HTTP error ${response.status}`);
				}
				
				currentGeneratedPlan = result.plan; // Store the plan
				currentSuggestedMainCategory = result.suggested_main_category;
				currentSuggestedSubCategory = result.suggested_sub_category;
				
				// Display the preview
				displayLessonPlanPreview(result.plan);
				
				// Show category suggestion only if auto-detect was intended
				if (autoDetect && currentSuggestedMainCategory && currentSuggestedSubCategory) {
					suggestedMainCategoryText.textContent = currentSuggestedMainCategory;
					suggestedSubCategoryText.textContent = currentSuggestedSubCategory;
					modalCategorySuggestionArea.classList.remove('d-none');
				} else {
					modalCategorySuggestionArea.classList.add('d-none'); // Hide if not auto-detecting or no suggestion
				}
				
				applyGenerationButton.classList.remove('d-none'); // Show apply button
				
			} catch (error) {
				console.error('Error generating preview:', error);
				lessonPreviewBody.innerHTML = `<div class="alert alert-danger">Failed to generate preview: ${escapeHtml(error.message)}</div>`;
				generationErrorMessage.textContent = `Error: ${error.message}`;
				generationErrorMessage.classList.remove('d-none');
				applyGenerationButton.classList.add('d-none'); // Hide apply button on error
			} finally {
				generatePreviewButton.disabled = false; // Re-enable original button
				generatePreviewSpinner.classList.add('d-none');
			}
		});
	}
	
	// --- Back to Options Button ---
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
			currentGeneratedPlan = null; // Clear plan
			currentSuggestedMainCategory = null;
			currentSuggestedSubCategory = null;
			// Re-enable generate button as we are back to options
			generatePreviewButton.disabled = false;
		});
	}
	
	// --- Apply Generation Button ---
	if (applyGenerationButton) {
		applyGenerationButton.addEventListener('click', async () => {
			if (!currentGeneratedPlan) {
				showToast('No lesson plan data available to apply.', 'Error', 'error');
				return;
			}
			
			const originalSubCategoryId = currentSubCategoryIdInput.value; // Get original ID
			const sessionId = sessionIdInput.value;
			
			// Determine categoryInput based on modal state during *preview generation*
			const categoryInput = isAutoDetectingCategory ? 'auto' : (originalSubCategoryId || 'auto'); // Fallback to auto if original ID missing
			
			applyGenerationButton.disabled = true;
			applyGenerationSpinner.classList.remove('d-none');
			generationErrorMessage.classList.add('d-none');
			generationErrorMessage.textContent = '';
			cancelGenerationButton.disabled = true; // Prevent cancel during apply
			backToOptionsButton.disabled = true;
			
			try {
				const payload = {
					plan: currentGeneratedPlan,
					category_input: categoryInput,
					// Only include suggested names if categoryInput is 'auto'
					suggested_main_category: categoryInput === 'auto' ? currentSuggestedMainCategory : null,
					suggested_sub_category: categoryInput === 'auto' ? currentSuggestedSubCategory : null,
				};
				
				const response = await fetch(`/lesson/${sessionId}/apply-plan`, {
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
				// Optionally close modal and refresh list or redirect
				const modalInstance = bootstrap.Modal.getInstance(generateContentModal);
				modalInstance.hide();
				// Simple refresh for now:
				window.location.reload();
				// Or redirect if provided:
				// if (result.redirectUrl) {
				//     window.location.href = result.redirectUrl;
				// } else {
				//     window.location.reload();
				// }
				
			} catch (error) {
				console.error('Error applying generated plan:', error);
				generationErrorMessage.textContent = `Failed to apply content: ${escapeHtml(error.message)}`;
				generationErrorMessage.classList.remove('d-none');
				applyGenerationButton.disabled = false; // Re-enable on error
				cancelGenerationButton.disabled = false;
				backToOptionsButton.disabled = false;
			} finally {
				applyGenerationSpinner.classList.add('d-none');
			}
		});
	}
	
	
	// --- Helper to display lesson plan ---
	function displayLessonPlanPreview(plan) {
		if (!plan || !plan.lesson_parts || plan.lesson_parts.length === 0) {
			lessonPreviewBody.innerHTML = '<div class="alert alert-warning">Could not generate a valid lesson plan structure.</div>';
			return;
		}
		
		let previewHtml = `<h4>${escapeHtml(plan.main_title || 'Lesson Preview')}</h4>`;
		previewHtml += `<p><strong>Main Image Idea:</strong> ${escapeHtml(plan.image_prompt_idea || 'N/A')}</p>`;
		previewHtml += '<hr>';
		
		plan.lesson_parts.forEach((part, index) => {
			previewHtml += `
                <div class="mb-3 card">
                  <div class="card-header">
                    <strong>Part ${index + 1}: ${escapeHtml(part.title || 'Untitled Part')}</strong>
                  </div>
                  <div class="card-body">
                     <p class="card-text">${escapeHtml(part.text || 'No content generated.')}</p>
                     <p class="card-text"><small class="text-muted">Image Idea: ${escapeHtml(part.image_prompt_idea || 'N/A')}</small></p>
                  </div>
                </div>
            `;
		});
		
		lessonPreviewBody.innerHTML = previewHtml;
	}
	
});
