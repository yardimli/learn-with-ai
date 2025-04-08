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

function populateEditTextsModal(questionId) {
	// Find the question item in the DOM
	const questionItem = document.getElementById(`question-item-${questionId}`);
	if (!questionItem) {
		console.error(`Question item with ID ${questionId} not found.`);
		return;
	}
	
	// Store the question ID in the modal
	document.getElementById('editQuestionId').value = questionId;
	
	// Set modal title
	document.getElementById('editTextsModalLabel').textContent = `Edit Question ID: ${questionId}`;
	
	// Get the question text and populate the textarea
	const questionTextEl = questionItem.querySelector('.question-line strong');
	const questionText = questionTextEl ? questionTextEl.textContent.replace(/^Q:\s*/, '') : '';
	document.getElementById('editQuestionText').value = questionText;
	
	// Get answer elements and their data
	const answerItems = questionItem.querySelectorAll('.answer-list li');
	const answersContainer = document.getElementById('editAnswersContainer');
	answersContainer.innerHTML = ''; // Clear previous answers
	
	// For each answer found in the question item
	answerItems.forEach((item, index) => {
		// Extract text and feedback from DOM
		const answerTextEl = item.querySelector('.answer-text-content');
		// Remove index number and trailing colon from answer text
		const rawText = answerTextEl ? answerTextEl.textContent : '';
		const answerText = rawText.replace(/^\d+\.\s*/, '');
		
		const feedbackTextEl = item.querySelector('.feedback-text-content');
		const rawFeedback = feedbackTextEl ? feedbackTextEl.textContent : '';
		const feedbackText = rawFeedback.replace(/^Feedback:\s*/, '');
		
		// Determine if this is the correct answer
		const isCorrect = item.innerHTML.includes('(Correct)');
		
		// Create form group for this answer
		const answerFormGroup = document.createElement('div');
		answerFormGroup.className = 'card mb-3';
		answerFormGroup.innerHTML = `
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Answer ${index + 1}</span>
                <div class="form-check">
                    <input class="form-check-input answer-correct-checkbox" type="checkbox"
                        id="editAnswerCorrect${index}" ${isCorrect ? 'checked' : ''}>
                    <label class="form-check-label" for="editAnswerCorrect${index}">
                        Correct Answer
                    </label>
                </div>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="editAnswerText${index}" class="form-label">Answer Text</label>
                    <input type="text" class="form-control answer-text-input"
                        id="editAnswerText${index}" value="${escapeHtml(answerText)}" required>
                    <div class="invalid-feedback">Answer text is required</div>
                </div>
                <div class="mb-0">
                    <label for="editFeedbackText${index}" class="form-label">Feedback</label>
                    <textarea class="form-control answer-feedback-input"
                        id="editFeedbackText${index}" rows="2" required>${escapeHtml(feedbackText)}</textarea>
                    <div class="invalid-feedback">Feedback is required</div>
                </div>
            </div>
        `;
		
		answersContainer.appendChild(answerFormGroup);
	});
	
	// If no answers were found (unlikely), add placeholders
	if (answerItems.length === 0) {
		for (let i = 0; i < 4; i++) {
			const placeholderGroup = document.createElement('div');
			placeholderGroup.className = 'card mb-3';
			placeholderGroup.innerHTML = `
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Answer ${i + 1}</span>
                    <div class="form-check">
                        <input class="form-check-input answer-correct-checkbox" type="checkbox"
                            id="editAnswerCorrect${i}" ${i === 0 ? 'checked' : ''}>
                        <label class="form-check-label" for="editAnswerCorrect${i}">
                            Correct Answer
                        </label>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="editAnswerText${i}" class="form-label">Answer Text</label>
                        <input type="text" class="form-control answer-text-input"
                            id="editAnswerText${i}" value="" required>
                        <div class="invalid-feedback">Answer text is required</div>
                    </div>
                    <div class="mb-0">
                        <label for="editFeedbackText${i}" class="form-label">Feedback</label>
                        <textarea class="form-control answer-feedback-input"
                            id="editFeedbackText${i}" rows="2" required></textarea>
                        <div class="invalid-feedback">Feedback is required</div>
                    </div>
                </div>
            `;
			
			answersContainer.appendChild(placeholderGroup);
		}
	}
	
	// Set up the radio button behavior for correct answers
	setupCorrectAnswerCheckboxes();
}

// Helper function to ensure only one checkbox is checked
function setupCorrectAnswerCheckboxes() {
	const checkboxes = document.querySelectorAll('.answer-correct-checkbox');
	
	checkboxes.forEach(checkbox => {
		checkbox.addEventListener('change', function () {
			if (this.checked) {
				// Uncheck all other checkboxes
				checkboxes.forEach(cb => {
					if (cb !== this) {
						cb.checked = false;
					}
				});
			} else {
				// Prevent unchecking if this is the only one checked
				const anyOtherChecked = Array.from(checkboxes).some(cb => cb !== this && cb.checked);
				if (!anyOtherChecked) {
					this.checked = true; // Force it to stay checked
				}
			}
		});
	});
}

// Helper function to escape HTML special characters
function escapeHtml(text) {
	const div = document.createElement('div');
	div.textContent = text;
	return div.innerHTML;
}

// Function to validate the form before submission
function validateEditTextsForm() {
	let isValid = true;
	
	// Validate question text
	const questionText = document.getElementById('editQuestionText');
	if (!questionText.value || questionText.value.trim().length < 5) {
		questionText.classList.add('is-invalid');
		isValid = false;
	} else {
		questionText.classList.remove('is-invalid');
	}
	
	// Validate each answer
	const answerTextInputs = document.querySelectorAll('.answer-text-input');
	answerTextInputs.forEach(input => {
		if (!input.value || input.value.trim().length < 1) {
			input.classList.add('is-invalid');
			isValid = false;
		} else {
			input.classList.remove('is-invalid');
		}
	});
	
	// Validate each feedback
	const feedbackInputs = document.querySelectorAll('.answer-feedback-input');
	feedbackInputs.forEach(input => {
		if (!input.value || input.value.trim().length < 1) {
			input.classList.add('is-invalid');
			isValid = false;
		} else {
			input.classList.remove('is-invalid');
		}
	});
	
	// Check if at least one answer is marked as correct
	const anyCorrect = Array.from(document.querySelectorAll('.answer-correct-checkbox')).some(cb => cb.checked);
	if (!anyCorrect) {
		document.getElementById('editTextsError').textContent = 'At least one answer must be marked as correct.';
		document.getElementById('editTextsError').classList.remove('d-none');
		isValid = false;
	}
	
	return isValid;
}

function updateQuestionDisplay(questionId, newQuestionText, newAnswers) {
	const questionItem = document.getElementById(`question-item-${questionId}`);
	if (!questionItem) return;
	
	// Update question text
	const questionTextEl = questionItem.querySelector('.question-line strong');
	if (questionTextEl) {
		questionTextEl.textContent = `Q: ${newQuestionText}`;
	}
	
	// Update answers list
	const answerList = questionItem.querySelector('.answer-list');
	if (answerList) {
		// Clear existing list items
		answerList.innerHTML = '';
		
		// Add updated answers
		newAnswers.forEach((answer, index) => {
			const li = document.createElement('li');
			
			// Answer text with correct indicator if applicable
			const answerTextSpan = document.createElement('span');
			answerTextSpan.className = 'answer-text-content';
			answerTextSpan.textContent = `${index + 1}. ${answer.text}`;
			li.appendChild(answerTextSpan);
			
			// Add "Correct" label if this is the correct answer
			if (answer.is_correct) {
				const correctLabel = document.createElement('strong');
				correctLabel.className = 'text-success';
				correctLabel.textContent = ' (Correct)';
				li.appendChild(correctLabel);
			}
			
			// Audio control placeholders (preserve existing ones)
			const ansControlsId = `ans-audio-controls-${questionId}-${index}`;
			const existingAnsControls = questionItem.querySelector(`#${ansControlsId}`);
			const ansControlsSpan = document.createElement('span');
			ansControlsSpan.id = ansControlsId;
			ansControlsSpan.className = 'ms-1';
			if (existingAnsControls) {
				ansControlsSpan.innerHTML = existingAnsControls.innerHTML;
			}
			li.appendChild(ansControlsSpan);
			
			// Line break before feedback
			li.appendChild(document.createElement('br'));
			
			// Feedback text
			const feedbackSpan = document.createElement('small');
			feedbackSpan.className = 'text-muted feedback-text-content';
			feedbackSpan.textContent = `Feedback: ${answer.feedback}`;
			li.appendChild(feedbackSpan);
			
			// Feedback audio controls (preserve existing ones)
			const fbControlsId = `fb-audio-controls-${questionId}-${index}`;
			const existingFbControls = questionItem.querySelector(`#${fbControlsId}`);
			const fbControlsSpan = document.createElement('span');
			fbControlsSpan.id = fbControlsId;
			fbControlsSpan.className = 'ms-1';
			if (existingFbControls) {
				fbControlsSpan.innerHTML = existingFbControls.innerHTML;
			}
			li.appendChild(fbControlsSpan);
			
			answerList.appendChild(li);
		});
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
	// --- Settings Selectors ---
	const preferredLlmSelect = document.getElementById('preferredLlmSelect');
	const ttsEngineSelect = document.getElementById('ttsEngineSelect');
	const ttsVoiceSelect = document.getElementById('ttsVoiceSelect');
	const ttsLanguageCodeSelect = document.getElementById('ttsLanguageCodeSelect');
	const updateSettingsBtn = document.getElementById('updateLessonSettingsBtn');

// --- Voice Selector Logic ---
	if (ttsEngineSelect && ttsVoiceSelect) {
		function updateVoiceOptions() {
			const engine = ttsEngineSelect.value;
			const optgroups = ttsVoiceSelect.querySelectorAll('optgroup');
			let firstVisibleOption = null;
			let currentSelectedOption = ttsVoiceSelect.options[ttsVoiceSelect.selectedIndex];
			
			optgroups.forEach(group => {
				const isVisible = (engine === 'google' && group.label === 'Google Voices') ||
					(engine === 'openai' && group.label === 'OpenAI Voices');
				group.style.display = isVisible ? '' : 'none';
				
				if (isVisible) {
					const options = group.querySelectorAll('option');
					if (options.length > 0 && !firstVisibleOption) {
						firstVisibleOption = options[0]; // Find the first option in the now visible group
					}
				}
			});
			
			// If the currently selected option is now hidden, select the first available visible option
			if (currentSelectedOption && currentSelectedOption.parentElement.style.display === 'none' && firstVisibleOption) {
				firstVisibleOption.selected = true;
			}
		}
		
		ttsEngineSelect.addEventListener('change', updateVoiceOptions);
		// Initial call on page load to ensure correct voices shown/selected
		updateVoiceOptions();
	}
	
	// --- LLM Selector Logic ---
	if (preferredLlmSelect) {
		// Load available LLMs via AJAX
		fetch(llmsListUrl) // Use variable defined in script tag
			.then(response => response.json())
			.then(data => {
				if (data.llms && Array.isArray(data.llms)) {
					const currentLlmValue = preferredLlmSelect.value; // Get the value set by Blade
					
					// Clear existing options except the first one (which shows current)
					while (preferredLlmSelect.options.length > 1) {
						preferredLlmSelect.remove(1);
					}
					
					// Rebuild options list
					data.llms.forEach(llm => {
						// Don't add the 'current' one again if it's in the list
						if (llm.id !== currentLlmValue) {
							const option = document.createElement('option');
							option.value = llm.id;
							option.textContent = `${llm.name}`; // Simpler text
							preferredLlmSelect.appendChild(option);
						}
					});
					
					// Ensure the first option text reflects the name correctly
					const currentOption = preferredLlmSelect.options[0];
					const matchingLlm = data.llms.find(llm => llm.id === currentLlmValue);
					if (currentOption && matchingLlm) {
						currentOption.textContent = `${matchingLlm.name}`; // Update display name
					} else if (currentOption){
						currentOption.textContent = currentLlmValue; // Fallback to ID if name not found
					}
					
				}
			})
			.catch(error => {
				console.error('Error loading LLMs list:', error);
				// Optionally show an error to the user
			});
	}
	
	// --- Save Lesson Settings Button ---
	if (updateSettingsBtn) {
		updateSettingsBtn.addEventListener('click', function () {
			const selectedLlm = preferredLlmSelect.value;
			const selectedEngine = ttsEngineSelect.value;
			const selectedVoice = ttsVoiceSelect.value;
			const selectedLang = ttsLanguageCodeSelect.value;
			
			// Add spinner to button
			showSpinner(this, true);
			
			fetch(updateSettingsUrl, { // Use variable defined in script tag
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
					'Accept': 'application/json',
				},
				body: JSON.stringify({
					preferred_llm: selectedLlm,
					tts_engine: selectedEngine,
					tts_voice: selectedVoice,
					tts_language_code: selectedLang,
					// No lesson_id needed here as it's in the URL
				})
			})
				.then(response => response.json())
				.then(data => {
					if (data.success) {
						showToast('Lesson settings updated successfully!', 'Settings Saved', 'success');
						// Update the 'Current' display text for LLM selector if needed
						const currentLlmOption = preferredLlmSelect.options[0];
						if (currentLlmOption.value === selectedLlm) {
							const selectedLlmText = preferredLlmSelect.options[preferredLlmSelect.selectedIndex].text;
							currentLlmOption.textContent = `${selectedLlmText}`;
						} else {
							// Find the newly selected option and update the first option
							for (let i = 0; i < preferredLlmSelect.options.length; i++) {
								if (preferredLlmSelect.options[i].value === selectedLlm) {
									preferredLlmSelect.options[0].value = selectedLlm;
									preferredLlmSelect.options[0].textContent = preferredLlmSelect.options[i].textContent;
									preferredLlmSelect.selectedIndex = 0; // Select the updated first option
									break;
								}
							}
						}
						
					} else {
						showToast(data.message || 'Failed to update lesson settings.', 'Error', 'error');
					}
				})
				.catch(error => {
					console.error('Error saving lesson settings:', error);
					showToast('An error occurred while saving settings.', 'Error', 'error');
				})
				.finally(() => {
					// Restore button
					showSpinner(this, false);
					// Ensure the icon is visible if text was removed
					if (!this.querySelector('i')) {
						this.innerHTML = '<i class="fas fa-save me-1"></i>Save';
					}
				});
		});
	}
	
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
		
		const editTextsBtn = event.target.closest('.edit-question-texts-btn');
		if (editTextsBtn) {
			const questionId = editTextsBtn.dataset.questionId;
			// Show loading indicator
			document.getElementById('saveTextsBtn').querySelector('.spinner-border').classList.add('d-none');
			// Clear previous error message
			document.getElementById('editTextsError').classList.add('d-none');
			// Populate the modal with question data
			populateEditTextsModal(questionId);
		}
		
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
	
	document.getElementById('saveTextsBtn').addEventListener('click', async function () {
		// Hide any previous error message
		document.getElementById('editTextsError').classList.add('d-none');
		
		// Validate the form
		if (!validateEditTextsForm()) {
			return;
		}
		
		// Get question ID and action URL
		const questionId = document.getElementById('editQuestionId').value;
		const editBtn = document.querySelector(`.edit-question-texts-btn[data-question-id="${questionId}"]`);
		const url = editBtn.dataset.editUrl;
		
		// Show spinner
		const saveBtn = this;
		saveBtn.querySelector('.spinner-border').classList.remove('d-none');
		saveBtn.disabled = true;
		
		// Gather form data
		const questionText = document.getElementById('editQuestionText').value;
		const answers = [];
		
		// Get all answer input groups and their data
		const answerCards = document.querySelectorAll('#editAnswersContainer .card');
		answerCards.forEach((card, index) => {
			const isCorrect = card.querySelector(`.answer-correct-checkbox`).checked;
			const text = card.querySelector(`.answer-text-input`).value;
			const feedback = card.querySelector(`.answer-feedback-input`).value;
			
			answers.push({
				text: text,
				is_correct: isCorrect,
				feedback: feedback
			});
		});
		
		try {
			const response = await fetch(url, {
				method: 'POST',
				headers: {
					'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
					'Content-Type': 'application/json',
					'Accept': 'application/json'
				},
				body: JSON.stringify({
					question_text: questionText,
					answers: answers
				})
			});
			
			const result = await response.json();
			
			if (!response.ok || !result.success) {
				throw new Error(result.message || `HTTP error ${response.status}`);
			}
			
			// Update the question display with new data
			updateQuestionDisplay(questionId, result.question.question_text, result.question.answers);
			
			// Close the modal
			const modal = bootstrap.Modal.getInstance(document.getElementById('editTextsModal'));
			modal.hide();
			
			// Show success message (optional)
			showToast('Question texts updated successfully', 'success');
		} catch (error) {
			console.error(`Error updating question texts for Question ${questionId}:`, error);
			document.getElementById('editTextsError').textContent = `Failed to update: ${error.message}`;
			document.getElementById('editTextsError').classList.remove('d-none');
		} finally {
			// Hide spinner and re-enable button
			saveBtn.querySelector('.spinner-border').classList.add('d-none');
			saveBtn.disabled = false;
		}
	});
	
	
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
