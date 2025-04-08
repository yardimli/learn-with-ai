// learn-with-ai/public/js/create_lesson.js

document.addEventListener('DOMContentLoaded', () => {
	const lessonForm = document.getElementById('lessonForm');
	const startLearningButton = document.getElementById('startLearningButton');
	const startLearningSpinner = document.getElementById('startLearningSpinner'); // Original button spinner
	const lessonInput = document.getElementById('lessonInput');
	
	const preferredLlmSelect = document.getElementById('preferredLlmSelect');
	const ttsEngineSelect = document.getElementById('ttsEngineSelect');
	const ttsVoiceSelect = document.getElementById('ttsVoiceSelect');
	const ttsLanguageCodeSelect = document.getElementById('ttsLanguageCodeSelect');
	
	const loadingOverlay = document.getElementById('loadingOverlay'); // Full page overlay
	const loadingMessageEl = document.getElementById('loadingMessage');
	const errorMessageArea = document.getElementById('errorMessageArea'); // General error display
	const errorMessageText = document.getElementById('errorMessageText');
	const closeErrorButton = document.getElementById('closeErrorButton');
	
	// Modal elements
	const previewModalElement = document.getElementById('lessonPreviewModal');
	const previewModal = previewModalElement ? new bootstrap.Modal(previewModalElement) : null;
	const previewModalBody = document.getElementById('lessonPreviewBody');
	const previewModalLabel = document.getElementById('lessonPreviewModalLabel');
	const confirmPreviewButton = document.getElementById('confirmPreviewButton');
	const cancelPreviewButton = document.getElementById('cancelPreviewButton');
	const modalLoadingIndicator = document.getElementById('modalLoadingIndicator'); // Spinner in modal footer
	
	let currentPlanData = null; // Store the received plan data
	
	// --- Utility Functions ---
	function setLoading(isLoading, message = 'Generating...') {
		if (!loadingOverlay || !loadingMessageEl) return;
		loadingMessageEl.textContent = message;
		loadingOverlay.classList.toggle('d-none', !isLoading);
	}
	
	function showMainError(message) {
		if (!errorMessageArea || !errorMessageText) return;
		errorMessageText.textContent = message || 'An unknown error occurred.';
		errorMessageArea.classList.remove('d-none');
	}
	
	function hideMainError() {
		if (!errorMessageArea) return;
		errorMessageArea.classList.add('d-none');
	}
	
	if (closeErrorButton) {
		closeErrorButton.addEventListener('click', hideMainError);
	}
	
	function setFormEnabled(enabled) {
		startLearningButton.disabled = !enabled || !lessonInput.value.trim(); // Also check lesson input value
		lessonInput.disabled = !enabled;
		preferredLlmSelect.disabled = !enabled;
		ttsEngineSelect.disabled = !enabled;
		ttsVoiceSelect.disabled = !enabled;
		ttsLanguageCodeSelect.disabled = !enabled;
		
		if (enabled) {
			startLearningSpinner.classList.add('d-none');
		} else {
			startLearningSpinner.classList.remove('d-none');
		}
	}
	
	// --- Voice Selector Logic ---
	if (ttsEngineSelect && ttsVoiceSelect) {
		function updateVoiceOptions() {
			const engine = ttsEngineSelect.value;
			const optgroups = ttsVoiceSelect.querySelectorAll('optgroup');
			let firstVisibleOption = null;
			
			optgroups.forEach(group => {
				const isVisible = (engine === 'google' && group.label === 'Google Voices') ||
					(engine === 'openai' && group.label === 'OpenAI Voices');
				group.style.display = isVisible ? '' : 'none';
				
				if (isVisible) {
					const options = group.querySelectorAll('option');
					if (options.length > 0 && !firstVisibleOption) {
						firstVisibleOption = options[0]; // Find the first option in the visible group
					}
				}
			});
			
			// If the currently selected option is hidden, select the first visible option
			const selectedOption = ttsVoiceSelect.options[ttsVoiceSelect.selectedIndex];
			if (selectedOption && selectedOption.parentElement.style.display === 'none' && firstVisibleOption) {
				firstVisibleOption.selected = true;
			} else if (!selectedOption && firstVisibleOption){ // If nothing selected initially
				firstVisibleOption.selected = true;
			}
		}
		
		ttsEngineSelect.addEventListener('change', updateVoiceOptions);
		// Initial call to set voices based on default engine
		updateVoiceOptions();
	}
	
	
	// --- Event Listeners ---
	
	if (lessonForm && startLearningButton && lessonInput && previewModal) {
		// Initial button state
		startLearningButton.disabled = !lessonInput.value.trim();
		lessonInput.addEventListener('input', () => {
			startLearningButton.disabled = !lessonInput.value.trim();
		});
		
		
		// Intercept form submission for AJAX preview
		lessonForm.addEventListener('submit', async (event) => {
			event.preventDefault(); // Stop normal form submission
			
			// Gather only needed data for preview
			const lesson = lessonInput.value;
			const llm = preferredLlmSelect.value; // Use the preferred LLM for generation
			
			if (!lesson || !llm) {
				showMainError("Please enter a lesson subject and select an AI model.");
				return;
			}
			
			hideMainError();
			setFormEnabled(false);
			setLoading(true, 'Generating lesson preview...'); // Show full page loader initially
			
			try {
				const response = await fetch(lessonForm.getAttribute('action'), { // Action is plan.preview
					method: 'POST',
					headers: {
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
						'Accept': 'application/json',
						'Content-Type': 'application/json'
					},
					body: JSON.stringify({ lesson, llm }) // Send only lesson and llm for preview
				});
				
				setLoading(false); // Hide full page loader once preview response starts
				const result = await response.json();
				
				if (!response.ok || !result.success) {
					throw new Error(result.message || `HTTP error! status: ${response.status}`);
				}
				
				// Success: Populate and show modal
				currentPlanData = result.plan; // Store the plan
				populateModal(currentPlanData, lesson, llm);
				confirmPreviewButton.disabled = false; // Enable confirm button
				modalLoadingIndicator.classList.add('d-none'); // Hide modal spinner
				previewModal.show();
				
			} catch (error) {
				console.error("Error fetching lesson preview:", error);
				showMainError(`Failed to generate preview: ${error.message}`);
				setFormEnabled(true); // Re-enable form on error
				setLoading(false);
			}
		});
		
		// Modal Cancel Button
		cancelPreviewButton.addEventListener('click', () => {
			setFormEnabled(true); // Re-enable the main form
			currentPlanData = null; // Clear stored plan
			confirmPreviewButton.disabled = true;
			modalLoadingIndicator.classList.add('d-none');
		});
		
		// Modal Confirm Button
		confirmPreviewButton.addEventListener('click', async () => {
			if (!currentPlanData) return;
			
			// Disable modal buttons, show modal spinner
			confirmPreviewButton.disabled = true;
			cancelPreviewButton.disabled = true;
			modalLoadingIndicator.textContent = 'Creating Lesson Structure...';
			modalLoadingIndicator.classList.remove('d-none');
			
			// Gather ALL data for final creation
			const lessonName = lessonInput.value;
			const preferredLlm = preferredLlmSelect.value;
			const ttsEngine = ttsEngineSelect.value;
			const ttsVoice = ttsVoiceSelect.value;
			const ttsLanguageCode = ttsLanguageCodeSelect.value;
			
			try {
				// Use the new create route
				const createUrl = document.getElementById('saveStructureUrl').value;
				const response = await fetch(createUrl, {
					method: 'POST',
					headers: {
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
						'Accept': 'application/json',
						'Content-Type': 'application/json'
					},
					body: JSON.stringify({
						lesson_name: lessonName,
						preferred_llm: preferredLlm, // Renamed field
						tts_engine: ttsEngine,
						tts_voice: ttsVoice,
						tts_language_code: ttsLanguageCode,
						plan: currentPlanData // Send the generated structure plan
					})
				});
				
				const result = await response.json();
				modalLoadingIndicator.classList.add('d-none');
				
				if (!response.ok || !result.success) {
					throw new Error(result.message || `Failed to create lesson. Status: ${response.status}`);
				}
				
				// Success: Redirect to the edit screen
				if (result.redirectUrl) {
					window.location.href = result.redirectUrl;
				} else {
					showMainError('Lesson created, but redirect failed. Please go to the home page.');
					previewModal.hide();
					setFormEnabled(true);
				}
				
			} catch (error) {
				console.error("Error creating lesson:", error);
				previewModal.hide();
				showMainError(`Failed to create lesson: ${error.message}`);
				setFormEnabled(true);
				// Don't re-enable modal buttons automatically, let user retry from main form
				modalLoadingIndicator.classList.add('d-none');
			}
		});
		
	} else {
		console.warn("Required form or modal elements not found on this page.");
	}
	
	// Function to populate the modal content
	function populateModal(plan, lessonName, llmName) {
		if (!previewModalBody || !plan) return;
		
		previewModalLabel.textContent = `Preview: ${plan.main_title || lessonName}`;
		
		let content = `<h5>${plan.main_title}</h5>`;
		content += `<p><small class="text-muted">Image Idea: ${plan.image_prompt_idea}</small></p>`;
		content += `<hr>`;
		
		content += `<h6>Lesson Content:</h6>`;
		content += `<dl class="row">`;
		plan.lesson_parts.forEach((part, index) => {
			content += `<dt class="col-sm-3">Part ${index + 1}: ${part.title}</dt>`;
			content += `<dd class="col-sm-9">${part.text}</dd>`;
		});
		content += `</dl><hr>`;
		
		
		previewModalBody.innerHTML = content;
	}
	
	document.querySelectorAll('.archive-progress-btn').forEach(button => {
		button.addEventListener('click', async (event) => {
			const lessonId = event.currentTarget.dataset.lessonSessionId;
			const archiveUrl = event.currentTarget.dataset.archiveUrl;
			
			if (!lessonId || !archiveUrl) {
				showToast('Error: Could not find lesson information for archiving.', 'Error', 'error');
				return;
			}
			
			if (!confirm('Are you sure you want to archive the current progress for this lesson? This will allow you to retake it from the beginning, but your previous answers will be saved.')) {
				return;
			}
			
			// Simple loading indicator on the button
			const originalHtml = button.innerHTML;
			button.disabled = true;
			button.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Archiving...`;
			
			try {
				const response = await fetch(archiveUrl, {
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
				
				showToast(result.message || 'Progress archived successfully!', 'Success', 'success');
				// Optional: You might want to visually update the state or refresh part of the page
				// For now, just show a toast. The user can then click 'View' to start fresh.
				
			} catch (error) {
				console.error('Error archiving progress:', error);
				showToast(`Archiving failed: ${error.message}`, 'Error', 'error');
			} finally {
				// Restore button state
				button.disabled = false;
				button.innerHTML = originalHtml;
			}
		});
	});
	
	// Hide loading overlay if it was somehow shown on page load
	setLoading(false);
});
