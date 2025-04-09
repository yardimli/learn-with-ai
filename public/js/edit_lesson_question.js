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

document.addEventListener('DOMContentLoaded', () => {
	
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
		
		
	});
	
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
	
});
