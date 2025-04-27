console.log("Lessons List JS loaded.");


document.addEventListener('DOMContentLoaded', () => {
	const generateContentModal = document.getElementById('generateContentModal');
	const sessionIdInput = document.getElementById('sessionIdForGeneration');
	const lessonTitleDisplay = document.getElementById('lessonTitleDisplay'); // Ensure this ID exists
	const lessonSubjectTextarea = document.getElementById('lessonSubjectDisplay');
	const lessonNotesDisplay = document.getElementById('lessonNotesDisplay'); // Ensure this ID exists
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
	
	// Elements for category display
	const existingCategoryDisplayArea = document.getElementById('existingCategoryDisplayArea');
	const existingMainCategoryNameSpan = document.getElementById('existingMainCategoryName');
	const existingSubCategoryNameSpan = document.getElementById('existingSubCategoryName');
	const existingCategoryNote = document.getElementById('existingCategoryNote');
	const autoDetectCheckboxArea = document.getElementById('autoDetectCheckboxArea');
	const currentSubCategoryIdInput = document.getElementById('currentSubCategoryId');
	const currentSelectedMainCategoryIdInput = document.getElementById('currentSelectedMainCategoryId'); // Added hidden input
	
	let currentGeneratedPlan = null; // Store the previewed plan
	let currentSuggestedMainCategory = null;
	let currentSuggestedSubCategory = null;
	let isAutoDetectingCategory = true; // Track state for apply action - reflects if auto-detect *was used* for preview
	
	// --- Modal Setup ---
	if (generateContentModal) {
		generateContentModal.addEventListener('show.bs.modal', (event) => {
			const button = event.relatedTarget;
			const sessionId = button.dataset.sessionId;
			const userTitle = button.dataset.userTitle;
			const lessonSubject = button.dataset.lessonSubject;
			const notes = button.dataset.notes;
			const subCategoryId = button.dataset.subCategoryId;
			const mainCategoryName = button.dataset.mainCategoryName;
			const subCategoryName = button.dataset.subCategoryName;
			const selectedMainCategoryId = button.dataset.selectedMainCategoryId; // Get the ID
			const preferredLlm = button.dataset.preferredLlm;
			
			// Reset modal state FIRST
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
			existingCategoryDisplayArea.classList.add('d-none'); // Hide by default
			autoDetectCheckboxArea.classList.add('d-none'); // Hide by default
			autoDetectCheckbox.checked = false; // Uncheck by default
			isAutoDetectingCategory = false; // Reset state tracking
			
			// Populate basic fields
			sessionIdInput.value = sessionId;
			lessonTitleDisplay.value = userTitle || '';
			lessonSubjectTextarea.value = lessonSubject;
			lessonNotesDisplay.value = notes || '';
			currentSubCategoryIdInput.value = subCategoryId || ''; // Store original sub-category ID
			currentSelectedMainCategoryIdInput.value = selectedMainCategoryId || ''; // Store original main category ID
			
			// --- Set AI Model Select ---
			// Reset to default first (which is set by the 'selected' attribute in HTML)
			const defaultOption = aiModelSelect.querySelector('option[selected]');
			if (defaultOption) {
				aiModelSelect.value = defaultOption.value;
			} else if (aiModelSelect.options.length > 0) {
				aiModelSelect.selectedIndex = 0; // Fallback to first option if no default marked
			}
			// Now, try to set the preferred LLM if it exists and is a valid option
			if (preferredLlm) {
				const preferredOption = aiModelSelect.querySelector(`option[value="${preferredLlm}"]`);
				if (preferredOption) {
					aiModelSelect.value = preferredLlm;
				}
			}
			
			// --- Determine Category Display and Auto-Detect State ---
			if (subCategoryId && mainCategoryName && subCategoryName) {
				// Case 1: Both Main and Sub categories are fully defined via SubCategory
				existingMainCategoryNameSpan.textContent = mainCategoryName;
				existingSubCategoryNameSpan.textContent = subCategoryName;
				existingCategoryNote.textContent = 'Content will be generated for this category.';
				existingCategoryDisplayArea.classList.remove('d-none');
				autoDetectCheckboxArea.classList.add('d-none'); // Hide checkbox
				isAutoDetectingCategory = false; // Not auto-detecting
			} else if (selectedMainCategoryId && mainCategoryName && !subCategoryId) {
				// Case 2: Only Main category is defined (via selected_main_category_id)
				existingMainCategoryNameSpan.textContent = mainCategoryName;
				existingSubCategoryNameSpan.textContent = '(None - will be auto-detected)';
				existingCategoryNote.textContent = 'Sub-category will be auto-detected based on content.';
				existingCategoryDisplayArea.classList.remove('d-none');
				autoDetectCheckboxArea.classList.add('d-none'); // Hide checkbox (auto-detect for sub is implied)
				isAutoDetectingCategory = true; // Auto-detecting sub-category
			} else {
				// Case 3: No category assigned, or incomplete data - Default to full auto-detect
				existingCategoryDisplayArea.classList.add('d-none'); // Hide existing display
				autoDetectCheckboxArea.classList.remove('d-none'); // Show checkbox
				autoDetectCheckbox.checked = true; // Default to checked
				isAutoDetectingCategory = true; // Auto-detecting both
			}
			
			// Update isAutoDetectingCategory based on checkbox change *only if* the checkbox area is visible
			// This listener should be inside 'show.bs.modal' to reset correctly each time
			const autoDetectChangeHandler = () => {
				if (!autoDetectCheckboxArea.classList.contains('d-none')) {
					isAutoDetectingCategory = autoDetectCheckbox.checked;
				}
			};
			// Remove previous listener if any to prevent duplicates
			autoDetectCheckbox.removeEventListener('change', autoDetectChangeHandler);
			// Add the listener
			autoDetectCheckbox.addEventListener('change', autoDetectChangeHandler);
			// Set initial state based on checkbox visibility and checked status
			if (!autoDetectCheckboxArea.classList.contains('d-none')) {
				isAutoDetectingCategory = autoDetectCheckbox.checked;
			} // else it's already set based on the cases above
			
		});
		
		generateContentModal.addEventListener('hidden.bs.modal', () => {
			// Clear fields on close to prevent stale data
			sessionIdInput.value = '';
			lessonTitleDisplay.value = '';
			lessonSubjectTextarea.value = '';
			lessonNotesDisplay.value = '';
			currentSubCategoryIdInput.value = '';
			currentSelectedMainCategoryIdInput.value = '';
			// Reset other states if necessary
			// (Most resets happen in 'show.bs.modal')
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
			// Determine the auto-detect flag to send based on the *current* state of the modal/checkbox
			const autoDetect = isAutoDetectingCategory; // Use the tracked state
			
			if (!sessionId || !subject || !userTitle || !llm) {
				showToast('Missing required fields (Title, Subject, AI Model).', 'Error', 'error');
				return;
			}
			
			generatePreviewButton.disabled = true;
			generatePreviewSpinner.classList.remove('d-none');
			generationErrorMessage.classList.add('d-none');
			generationErrorMessage.textContent = '';
			previewContentArea.classList.remove('d-none'); // Show preview area immediately
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
				
				// Show category suggestion only if auto-detect was intended *and* suggestions were provided
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
				// generatePreviewButton.disabled = false; // Keep disabled until back/cancel
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
			
			const originalSubCategoryId = currentSubCategoryIdInput.value;
			const sessionId = sessionIdInput.value;
			
			// Determine categoryInput based on whether auto-detect was active *during preview generation*
			// The 'isAutoDetectingCategory' flag holds this state.
			const categoryInput = isAutoDetectingCategory ? 'auto' : (originalSubCategoryId || 'auto');
			// If not auto-detecting, send the original sub-category ID.
			// If originalSubCategoryId is empty even when not auto-detecting (e.g., only main was selected),
			// sending 'auto' might still be the desired behavior for the backend to handle sub-category creation.
			// Or the backend could use the original selected_main_category_id if provided.
			// Let's stick to sending 'auto' if isAutoDetectingCategory is true, otherwise the original sub_id (even if empty).
			
			applyGenerationButton.disabled = true;
			applyGenerationSpinner.classList.remove('d-none');
			generationErrorMessage.classList.add('d-none');
			generationErrorMessage.textContent = '';
			cancelGenerationButton.disabled = true; // Prevent cancel during apply
			backToOptionsButton.disabled = true;
			
			try {
				const payload = {
					plan: currentGeneratedPlan,
					category_input: categoryInput, // 'auto' or the original sub_category_id
					// Only include suggested names if categoryInput is 'auto'
					suggested_main_category: categoryInput === 'auto' ? currentSuggestedMainCategory : null,
					suggested_sub_category: categoryInput === 'auto' ? currentSuggestedSubCategory : null,
					// No need to send original IDs here; backend uses category_input to decide how to proceed
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
				if (modalInstance) {
					modalInstance.hide();
				}
				
				// Simple refresh for now:
				window.location.reload();
				
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
			lessonPreviewBody.innerHTML = '<div class="alert alert-warning">Could not generate a valid lesson plan structure. Check AI model or prompt.</div>';
			return;
		}
		
		let previewHtml = `<h4>${escapeHtml(plan.main_title || 'Lesson Preview')}</h4>`;
		if (plan.image_prompt_idea) {
			previewHtml += `<p><strong>Main Image Idea:</strong> ${escapeHtml(plan.image_prompt_idea)}</p>`;
		}
		previewHtml += '<hr>';
		
		plan.lesson_parts.forEach((part, index) => {
			previewHtml += `
                <div class="mb-3 card">
                    <div class="card-header">
                        <strong>Part ${index + 1}: ${escapeHtml(part.title || 'Untitled Part')}</strong>
                    </div>
                    <div class="card-body">
                        <p class="card-text">${escapeHtml(part.text || 'No content generated.')}</p>
                        ${part.image_prompt_idea ? `<p class="card-text"><small class="text-muted">Image Idea: ${escapeHtml(part.image_prompt_idea)}</small></p>` : ''}
                    </div>
                </div>
            `;
		});
		
		lessonPreviewBody.innerHTML = previewHtml;
	}
	
	// --- Delete Lesson Button Handler ---
	document.querySelectorAll('.delete-lesson-btn').forEach(button => {
		button.addEventListener('click', function () {
			const sessionId = this.dataset.lessonSessionId;
			const deleteUrl = this.dataset.deleteUrl;
			const lessonTitle = this.dataset.lessonTitle;
			
			if (confirm(`Are you sure you want to delete the lesson "${lessonTitle}" and all its associated questions and progress? This action cannot be undone.`)) {
				// Use Fetch API for AJAX delete
				fetch(deleteUrl, {
					method: 'DELETE',
					headers: {
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
						'Accept': 'application/json',
					}
				})
					.then(response => response.json())
					.then(data => {
						if (data.success) {
							showToast(data.message || 'Lesson deleted successfully.', 'Success', 'success');
							// Remove the list item from the DOM
							const listItem = this.closest('.list-group-item');
							if (listItem) {
								listItem.remove();
								// Optionally check if the parent accordion body/collapse is now empty and update counts/UI
							} else {
								window.location.reload(); // Fallback refresh
							}
						} else {
							showToast(data.message || 'Failed to delete lesson.', 'Error', 'error');
						}
					})
					.catch(error => {
						console.error('Error deleting lesson:', error);
						showToast('An error occurred while deleting the lesson.', 'Error', 'error');
					});
				
				// If using the hidden form as fallback (not recommended with the fetch approach)
				// const formId = `delete-form-${sessionId}`;
				// const form = document.getElementById(formId);
				// if (form) {
				//     form.submit();
				// }
			}
		});
	});
	
	// --- Archive Progress Button Handler ---
	document.querySelectorAll('.archive-progress-btn').forEach(button => {
		button.addEventListener('click', function () {
			const archiveUrl = this.dataset.archiveUrl;
			const lessonSessionId = this.dataset.lessonSessionId; // For potential UI updates
			
			if (confirm('Are you sure you want to archive the current progress for this lesson? This will reset the progress tracking.')) {
				fetch(archiveUrl, {
					method: 'POST',
					headers: {
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
						'Accept': 'application/json',
					}
				})
					.then(response => response.json())
					.then(data => {
						if (data.success) {
							showToast(data.message || 'Progress archived successfully.', 'Success', 'success');
							// Reload the page to show the reset progress bar
							window.location.reload();
						} else {
							showToast(data.message || 'Failed to archive progress.', 'Error', 'error');
						}
					})
					.catch(error => {
						console.error('Error archiving progress:', error);
						showToast('An error occurred while archiving progress.', 'Error', 'error');
					});
			}
		});
	});
	
	
}); // End DOMContentLoaded
