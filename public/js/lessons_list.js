console.log("Lessons List JS loaded.");

const GENERATING_ALL_FLAG = 'learnwithai_generating_all';
const CURRENT_GENERATING_TITLE = 'learnwithai_current_generating_title';

document.addEventListener('DOMContentLoaded', () => {
	const generateContentModal = document.getElementById('generateContentModal');
	const lessonIdInput = document.getElementById('lessonIdForGeneration');
	const lessonTitleDisplay = document.getElementById('lessonTitleDisplay');
	const lessonSubjectTextarea = document.getElementById('lessonSubjectDisplay');
	const lessonNotesDisplay = document.getElementById('lessonNotesDisplay');
	const additionalInstructionsTextarea = document.getElementById('additionalInstructionsTextarea');
	const aiModelSelect = document.getElementById('aiModelSelect');
	const lessonPartsCountSelect = document.getElementById('lessonPartsCountSelect');
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
	
	const addVideoModal = document.getElementById('addVideoModal');
	const addVideoForm = document.getElementById('addVideoForm');
	const lessonIdForVideoInput = document.getElementById('lessonIdForVideo');
	const lessonTitleForVideoSpan = document.getElementById('lessonTitleForVideo');
	const youtubeVideoIdInput = document.getElementById('youtubeVideoIdInput');
	const submitVideoButton = document.getElementById('submitVideoButton');
	const submitVideoSpinner = document.getElementById('submitVideoSpinner');
	const addVideoError = document.getElementById('addVideoError');
	const addVideoProgress = document.getElementById('addVideoProgress'); // Progress indicator
	
	const generateAllButton = document.getElementById('generateAllButton');
	const generateAllText = document.getElementById('generateAllText');
	const generateAllSpinner = document.getElementById('generateAllSpinner');
	
	let currentGeneratedPlan = null; // Store the previewed plan
	let currentSuggestedMainCategory = null;
	let currentSuggestedSubCategory = null;
	let isAutoDetectingCategory = true; // Track state for apply action - reflects if auto-detect *was used* for preview
	let applyButtonObserver = null; // To watch for the apply button
	
	function updateGenerateAllButtonState(isGenerating, message = "Generate All Pending") {
		if (isGenerating) {
			generateAllButton.disabled = true;
			generateAllText.textContent = message;
			generateAllSpinner.classList.remove('d-none');
			// Change icon or text to indicate "Stop"
			generateAllButton.querySelector('i').classList.remove('fa-robot');
			generateAllButton.querySelector('i').classList.add('fa-stop-circle');
			generateAllButton.title = "Stop automatic generation";
		} else {
			generateAllButton.disabled = false;
			generateAllText.textContent = "Generate All Pending";
			generateAllSpinner.classList.add('d-none');
			localStorage.removeItem(GENERATING_ALL_FLAG);
			localStorage.removeItem(CURRENT_GENERATING_TITLE);
			// Restore icon
			generateAllButton.querySelector('i').classList.remove('fa-stop-circle');
			generateAllButton.querySelector('i').classList.add('fa-robot');
			generateAllButton.title = "Automatically generate content for all pending lessons";
		}
	}
	
	function stopGenerateAll(reason = "Stopped.") {
		console.log("Stopping Generate All:", reason);
		localStorage.removeItem(GENERATING_ALL_FLAG);
		localStorage.removeItem(CURRENT_GENERATING_TITLE);
		updateGenerateAllButtonState(false);
		showToast(reason, "Auto Generation", "warning");
	}
	
	function startOrContinueGenerateAll() {
		if (localStorage.getItem(GENERATING_ALL_FLAG) !== 'true') {
			console.log("Generate All not active.");
			return; // Not in auto-generation mode
		}
		
		const pendingButtons = document.querySelectorAll('.generate-ai-content-btn');
		
		if (pendingButtons.length === 0) {
			console.log("No more pending lessons found.");
			stopGenerateAll("All pending lessons have been processed!");
			return;
		}
		
		const nextButton = pendingButtons[0]; // Process the first one found
		const lessonTitle = nextButton.dataset.userTitle || 'Untitled Lesson';
		localStorage.setItem(CURRENT_GENERATING_TITLE, lessonTitle); // Store title for modal check
		
		console.log(`Auto-generating next lesson: ${lessonTitle}`);
		updateGenerateAllButtonState(true, `Generating: ${lessonTitle}...`);
		
		// Short delay before clicking to allow UI update
		setTimeout(() => {
			nextButton.click(); // Trigger the modal for this lesson
		}, 100); // 100ms delay
	}
	
	// --- Event Listener for the "Generate All" Button ---
	if (generateAllButton) {
		generateAllButton.addEventListener('click', () => {
			if (localStorage.getItem(GENERATING_ALL_FLAG) === 'true') {
				// Button is currently in "Stop" mode
				stopGenerateAll("Manual stop requested.");
			} else {
				// Start the process
				const pendingButtons = document.querySelectorAll('.generate-ai-content-btn');
				if (pendingButtons.length === 0) {
					showToast("No lessons need content generation.", "Info", "info");
					return;
				}
				if (confirm(`Start generating content for ${pendingButtons.length} lesson(s)? The page will reload after each lesson.`)) {
					console.log("Starting Generate All process...");
					localStorage.setItem(GENERATING_ALL_FLAG, 'true');
					updateGenerateAllButtonState(true, "Starting...");
					startOrContinueGenerateAll(); // Kick off the first one
				}
			}
		});
	}
	
	// --- Modal Setup ---
	if (generateContentModal) {
		generateContentModal.addEventListener('show.bs.modal', async (event) => {
			const button = event.relatedTarget;
			const lessonId = button.dataset.lessonId;
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
			additionalInstructionsTextarea.value = '';
			
			// Populate basic fields
			lessonIdInput.value = lessonId;
			lessonTitleDisplay.value = userTitle || '';
			lessonSubjectTextarea.value = lessonSubject;
			lessonNotesDisplay.value = notes || '';
			currentSubCategoryIdInput.value = subCategoryId || ''; // Store original sub-category ID
			currentSelectedMainCategoryIdInput.value = selectedMainCategoryId || ''; // Store original main category ID
			lessonPartsCountSelect.value = '3';
			
			try {
				const response = await fetch('/user/llm-instructions', { // Use the new route
					method: 'GET',
					headers: {
						'Accept': 'application/json',
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
					}
				});
				if (!response.ok) {
					throw new Error(`HTTP error ${response.status}`);
				}
				const result = await response.json();
				if (result.success && result.instructions) {
					additionalInstructionsTextarea.value = result.instructions;
				}
			} catch (error) {
				console.error('Error fetching user instructions:', error);
				// Optionally show a small error message near the textarea
				// additionalInstructionsTextarea.placeholder = 'Could not load saved instructions.';
			}
			
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
			
			// --- Auto-Generation Check ---
			// Check if this modal was opened by the auto-generator
			const isAutoGeneratingThis = localStorage.getItem(GENERATING_ALL_FLAG) === 'true' &&
				localStorage.getItem(CURRENT_GENERATING_TITLE) === userTitle;
			
			if (isAutoGeneratingThis) {
				console.log(`Modal opened for auto-generation: ${userTitle}`);
				// Disable cancel/back buttons during auto-gen step
				cancelGenerationButton.disabled = true;
				backToOptionsButton.disabled = true;
				// Automatically click "Generate Preview" after a short delay
				setTimeout(() => {
					if (generatePreviewButton && !generatePreviewButton.disabled) {
						console.log("Auto-clicking Generate Preview...");
						generatePreviewButton.click();
					}
				}, 500); // Delay to ensure modal is fully rendered
			} else {
				// Enable buttons if opened manually
				cancelGenerationButton.disabled = false;
				backToOptionsButton.disabled = false;
			}
			
		});
		
		
		generateContentModal.addEventListener('hidden.bs.modal', () => {
			// Clear fields on close to prevent stale data
			lessonIdInput.value = '';
			lessonTitleDisplay.value = '';
			lessonSubjectTextarea.value = '';
			lessonNotesDisplay.value = '';
			additionalInstructionsTextarea.value = '';
			currentSubCategoryIdInput.value = '';
			currentSelectedMainCategoryIdInput.value = '';
			
			// Stop observer if it's running
			if (applyButtonObserver) {
				applyButtonObserver.disconnect();
				applyButtonObserver = null;
			}
			// Re-enable buttons potentially disabled by auto-gen
			cancelGenerationButton.disabled = false;
			backToOptionsButton.disabled = false;
			generatePreviewButton.disabled = false;
			applyGenerationButton.disabled = false;
			
		});
	}
	
	// --- Generate Preview Button ---
	if (generatePreviewButton) {
		generatePreviewButton.addEventListener('click', async () => {
			const lessonId = lessonIdInput.value;
			const userTitle = lessonTitleDisplay.value;
			const subject = lessonSubjectTextarea.value;
			const notes = lessonNotesDisplay.value;
			const additionalInstructions = additionalInstructionsTextarea.value;
			const llm = aiModelSelect.value;
			const partsCount = lessonPartsCountSelect.value;
			const autoDetect = isAutoDetectingCategory;
			
			if (!lessonId || !subject || !userTitle || !llm) {
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
			
			// --- Auto-Gen: Start watching for Apply button ---
			const isAutoGeneratingThis = localStorage.getItem(GENERATING_ALL_FLAG) === 'true' &&
				localStorage.getItem(CURRENT_GENERATING_TITLE) === userTitle;
			if (isAutoGeneratingThis) {
				console.log("Preview started for auto-gen. Watching for Apply button...");
				watchForApplyButton(userTitle); // Start observer/poller
			}
			// --- End Auto-Gen ---
			
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
						parts_count: parseInt(partsCount, 10),
						additional_instructions: additionalInstructions,
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
				
				// --- Auto-Gen: Stop if preview failed ---
				if (isAutoGeneratingThis) {
					console.error("Auto-generation stopped due to preview error.");
					stopGenerateAll(`Preview failed for '${userTitle}': ${error.message}`);
					// Ensure observer stops if it was started
					if (applyButtonObserver) {
						applyButtonObserver.disconnect();
						applyButtonObserver = null;
					}
				}
				// --- End Auto-Gen ---
				
			} finally {
				// generatePreviewButton.disabled = false; // Keep disabled until back/cancel
				generatePreviewSpinner.classList.add('d-none');
				
				// Re-enable back/cancel if NOT auto-generating
				if (!isAutoGeneratingThis) {
					backToOptionsButton.disabled = false;
					cancelGenerationButton.disabled = false;
				}
			}
		});
	}
	
	// --- Function to Watch for Apply Button (using MutationObserver) ---
	function watchForApplyButton(expectedTitle) {
		if (applyButtonObserver) {
			applyButtonObserver.disconnect(); // Disconnect previous observer if any
		}
		
		const targetNode = applyGenerationButton;
		if (!targetNode) return;
		
		const config = { attributes: true, attributeFilter: ['class'] };
		
		const callback = function(mutationsList, observer) {
			for(const mutation of mutationsList) {
				if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
					const isVisible = !targetNode.classList.contains('d-none');
					const previewSpinnerHidden = generatePreviewSpinner.classList.contains('d-none');
					const errorMessageHidden = generationErrorMessage.classList.contains('d-none');
					const isAutoGeneratingNow = localStorage.getItem(GENERATING_ALL_FLAG) === 'true' &&
						localStorage.getItem(CURRENT_GENERATING_TITLE) === expectedTitle;
					
					
					if (isVisible && previewSpinnerHidden && errorMessageHidden && isAutoGeneratingNow) {
						console.log("Apply button is ready for auto-click.");
						observer.disconnect(); // Stop observing
						applyButtonObserver = null;
						
						// Auto-click Apply after a short delay
						setTimeout(() => {
							if (applyGenerationButton && !applyGenerationButton.disabled) {
								console.log("Auto-clicking Apply Content...");
								applyGenerationButton.click();
							}
						}, 300); // Short delay before clicking apply
						break; // Exit loop once handled
					} else if (!errorMessageHidden && isAutoGeneratingNow) {
						console.error("Error detected during preview generation. Stopping auto-gen.");
						observer.disconnect();
						applyButtonObserver = null;
						stopGenerateAll(`Preview failed for '${expectedTitle}'. Check modal error.`);
						break;
					}
				}
			}
		};
		
		applyButtonObserver = new MutationObserver(callback);
		applyButtonObserver.observe(targetNode, config);
		console.log("Observer started for Apply button visibility.");
		
		// Failsafe timeout: If apply button doesn't appear after a long time, stop.
		setTimeout(() => {
			if (applyButtonObserver) {
				const isAutoGeneratingNow = localStorage.getItem(GENERATING_ALL_FLAG) === 'true' &&
					localStorage.getItem(CURRENT_GENERATING_TITLE) === expectedTitle;
				if (isAutoGeneratingNow) {
					console.warn("Apply button timeout reached. Stopping auto-gen.");
					applyButtonObserver.disconnect();
					applyButtonObserver = null;
					stopGenerateAll(`Timeout waiting for preview/apply button for '${expectedTitle}'.`);
				}
			}
		}, 180000); // 3 minutes timeout
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
			
			if (applyButtonObserver) {
				applyButtonObserver.disconnect();
				applyButtonObserver = null;
			}
			
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
			const lessonId = lessonIdInput.value;
			
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
			
			const userTitle = lessonTitleDisplay.value; // Get title for potential error message
			const isAutoGeneratingThis = localStorage.getItem(GENERATING_ALL_FLAG) === 'true' &&
				localStorage.getItem(CURRENT_GENERATING_TITLE) === userTitle;
			
			try {
				const payload = {
					plan: currentGeneratedPlan,
					category_input: categoryInput, // 'auto' or the original sub_category_id
					// Only include suggested names if categoryInput is 'auto'
					suggested_main_category: categoryInput === 'auto' ? currentSuggestedMainCategory : null,
					suggested_sub_category: categoryInput === 'auto' ? currentSuggestedSubCategory : null,
					// No need to send original IDs here; backend uses category_input to decide how to proceed
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
				
				// --- Auto-Gen: Stop if apply failed ---
				if (isAutoGeneratingThis) {
					console.error("Auto-generation stopped due to apply error.");
					stopGenerateAll(`Applying content failed for '${userTitle}': ${error.message}`);
				} else {
					// Re-enable buttons only if NOT auto-generating
					cancelGenerationButton.disabled = false;
					backToOptionsButton.disabled = false;
				}
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
			const lessonId = this.dataset.lessonId;
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
				// const formId = `delete-form-${lessonId}`;
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
			const lessonId = this.dataset.lessonId; // For potential UI updates
			
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
	
	
	document.querySelectorAll('.add-video-btn').forEach(button => {
		button.addEventListener('click', function() {
			const lessonId = this.dataset.lessonId;
			const lessonTitle = this.dataset.lessonTitle;
			
			// Populate modal
			lessonIdForVideoInput.value = lessonId;
			lessonTitleForVideoSpan.textContent = lessonTitle;
			
			// Reset modal state
			youtubeVideoIdInput.value = '';
			addVideoError.classList.add('d-none');
			addVideoError.textContent = '';
			addVideoProgress.classList.add('d-none'); // Hide progress
			submitVideoButton.disabled = false;
			submitVideoSpinner.classList.add('d-none');
		});
	});
	
	// --- Listener for Add Video Modal Form Submission ---
	if (addVideoForm) {
		addVideoForm.addEventListener('submit', async (event) => {
			event.preventDefault(); // Prevent default HTML form submission
			
			const lessonId = lessonIdForVideoInput.value;
			let videoId = youtubeVideoIdInput.value.trim();
			
			// Basic validation & attempt to extract ID from URL
			if (!videoId) {
				addVideoError.textContent = 'Please enter a YouTube Video ID or URL.';
				addVideoError.classList.remove('d-none');
				return;
			}
			
			// Try to extract video ID from various YouTube URL formats
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
			
			// If no match from URL patterns, assume the input *is* the ID (basic check)
			if (!extractedId && /^[a-zA-Z0-9_-]{11}$/.test(videoId)) {
				extractedId = videoId;
			}
			
			if (!extractedId) {
				addVideoError.textContent = 'Invalid YouTube Video ID or URL format.';
				addVideoError.classList.remove('d-none');
				youtubeVideoIdInput.focus();
				return;
			}
			
			// Update the input field with the extracted ID for clarity
			youtubeVideoIdInput.value = extractedId;
			videoId = extractedId; // Use the extracted ID
			
			// --- Start processing ---
			submitVideoButton.disabled = true;
			submitVideoSpinner.classList.remove('d-none');
			addVideoError.classList.add('d-none');
			addVideoError.textContent = '';
			addVideoProgress.classList.remove('d-none'); // Show progress indicator
			
			try {
				const response = await fetch(`/lesson/${lessonId}/add-youtube`, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
						'Accept': 'application/json',
					},
					body: JSON.stringify({
						youtube_video_id: videoId
					}),
				});
				
				const result = await response.json();
				
				if (!response.ok || !result.success) {
					throw new Error(result.message || `HTTP error ${response.status}`);
				}
				
				// Success
				showToast(`Video '${result.video_title || ''}' added successfully!`, 'Success', 'success');
				const modalInstance = bootstrap.Modal.getInstance(addVideoModal);
				if (modalInstance) {
					modalInstance.hide();
				}
				// Optional: Update the specific lesson item in the list dynamically
				// Or just reload the page for simplicity
				window.location.reload();
				
			} catch (error) {
				console.error('Error adding YouTube video:', error);
				addVideoError.textContent = `Error: ${error.message}`;
				addVideoError.classList.remove('d-none');
			} finally {
				// Always re-enable button and hide spinners/progress on completion or error
				submitVideoButton.disabled = false;
				submitVideoSpinner.classList.add('d-none');
				addVideoProgress.classList.add('d-none'); // Hide progress
			}
		});
	}
	
	// --- Initial Check on Page Load ---
	// If the flag is set, continue the process
	if (localStorage.getItem(GENERATING_ALL_FLAG) === 'true') {
		console.log("Page loaded, continuing Generate All process...");
		// Use a small delay to ensure the DOM is fully ready and painted
		setTimeout(startOrContinueGenerateAll, 200);
	} else {
		// Ensure button is in the default state if flag is not set
		updateGenerateAllButtonState(false);
	}
	
}); // End DOMContentLoaded
