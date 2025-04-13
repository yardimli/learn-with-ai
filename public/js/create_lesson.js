// learn-with-ai/public/js/create_lesson.js

document.addEventListener('DOMContentLoaded', () => {
	const lessonForm = document.getElementById('lessonForm');
	const startLearningButton = document.getElementById('startLearningButton');
	const startLearningSpinner = document.getElementById('startLearningSpinner'); // Original button spinner
	const lessonInput = document.getElementById('lessonInput');
	
	const preferredLlmSelect = document.getElementById('preferredLlmSelect');
	const categorySelect = document.getElementById('categorySelect');         // New
	const languageSelect = document.getElementById('languageSelect');         // New
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
	const modalCategorySuggestionArea = document.getElementById('modalCategorySuggestionArea'); // New
	const suggestedCategoryText = document.getElementById('suggestedCategoryText');             // New
	
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
		categorySelect.disabled = !enabled;         // Disable/enable category
		languageSelect.disabled = !enabled;         // Disable/enable language
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
			
			// Gather data for preview
			const lesson = lessonInput.value;
			const llm = preferredLlmSelect.value;
			const category_id = categorySelect.value; // Get category ('auto' or ID)
			const language = languageSelect.value;   // Get language
			
			if (!lesson || !llm || !category_id || !language) {
				showMainError("Please enter a lesson subject and select all options (AI model, category, language).");
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
					body: JSON.stringify({ lesson, llm, category_id, language })
				});
				
				setLoading(false); // Hide full page loader once preview response starts
				const result = await response.json();
				
				if (!response.ok || !result.success) {
					throw new Error(result.message || `HTTP error! status: ${response.status}`);
				}
				
				// Success: Store data and populate modal
				currentPlanData = result.plan;
				currentCategoryInput = result.category_input;       // Store original input
				currentLanguageSelected = result.language_selected; // Store selected language
				currentSuggestedCategory = result.suggested_category_name; // Store AI suggestion
				
				populateModal(currentPlanData, lesson, llm); // Populate basic structure
				
				// Show suggested category if 'auto' was selected and suggestion exists
				if (currentCategoryInput === 'auto' && currentSuggestedCategory) {
					suggestedCategoryText.textContent = currentSuggestedCategory;
					modalCategorySuggestionArea.classList.remove('d-none');
				} else {
					modalCategorySuggestionArea.classList.add('d-none'); // Hide if not auto or no suggestion
				}
				
				
				confirmPreviewButton.disabled = false;
				modalLoadingIndicator.classList.add('d-none');
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
			setFormEnabled(true);
			currentPlanData = null; // Clear stored data
			currentCategoryInput = null;
			currentLanguageSelected = null;
			currentSuggestedCategory = null;
			confirmPreviewButton.disabled = true;
			modalLoadingIndicator.classList.add('d-none');
			modalCategorySuggestionArea.classList.add('d-none'); // Hide suggestion area
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
						language: currentLanguageSelected,         // Send saved language
						category_input: currentCategoryInput,       // Send original category input ('auto' or ID)
						suggested_category_name: currentSuggestedCategory, // Send AI suggestion (null if not 'auto')
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
				modalLoadingIndicator.classList.add('d-none');
				// Reset modal state on error close
				currentPlanData = null;
				currentCategoryInput = null;
				currentLanguageSelected = null;
				currentSuggestedCategory = null;
				modalCategorySuggestionArea.classList.add('d-none');
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
	
	// Hide loading overlay if it was somehow shown on page load
	setLoading(false);
});
