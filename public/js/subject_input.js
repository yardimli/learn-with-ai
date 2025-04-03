// learn-with-ai/public/js/subject_input.js

document.addEventListener('DOMContentLoaded', () => {
	const subjectForm = document.getElementById('subjectForm');
	const startLearningButton = document.getElementById('startLearningButton');
	const startLearningSpinner = document.getElementById('startLearningSpinner'); // Original button spinner
	const subjectInput = document.getElementById('subjectInput');
	const llmSelect = document.getElementById('llmSelect');
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
	
	const form = document.getElementById("form");
	const submitter = document.querySelector("button[value=save]");

	
	
	// const formData = new FormData(subjectForm, startLearningButton);
	// const subject = formData.get('subject');
	// const llm = formData.get('llm');
	// console.log("Form Data:", formData);
	// console.log("Subject:", subject);
	// console.log("LLM:", llm);
	
	
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
	
	// Enable/disable form controls
	function setFormEnabled(enabled) {
		startLearningButton.disabled = !enabled;
		subjectInput.disabled = !enabled;
		llmSelect.disabled = !enabled;
		if (enabled) {
			startLearningSpinner.classList.add('d-none');
		} else {
			startLearningSpinner.classList.remove('d-none');
		}
	}
	
	// --- Event Listeners ---
	
	if (subjectForm && startLearningButton && subjectInput && previewModal) {
		// Initial button state
		startLearningButton.disabled = !subjectInput.value.trim();
		subjectInput.addEventListener('input', () => {
			startLearningButton.disabled = !subjectInput.value.trim();
		});

		
		// Intercept form submission for AJAX preview
		subjectForm.addEventListener('submit', async (event) => {
			event.preventDefault(); // Stop normal form submission

			const formData = new FormData(subjectForm);
			const subject = formData.get('subject');
			const llm = formData.get('llm');
			
			hideMainError();
			setFormEnabled(false);
			setLoading(true, 'Generating lesson preview...'); // Show full page loader initially
			
			try {
				const response = await fetch(subjectForm.getAttribute('action'), { // Action is now plan.preview
					method: 'POST',
					headers: {
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
						'Accept': 'application/json',
						'Content-Type': 'application/json' // Send JSON
					},
					body: JSON.stringify({ subject, llm }) // Send data as JSON
				});
				
				setLoading(false); // Hide full page loader once preview response starts
				
				const result = await response.json();
				
				if (!response.ok || !result.success) {
					throw new Error(result.message || `HTTP error! status: ${response.status}`);
				}
				
				// Success: Populate and show modal
				currentPlanData = result.plan; // Store the plan
				populateModal(currentPlanData, subject, llm);
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
			modalLoadingIndicator.classList.remove('d-none');
			setLoading(true, 'Creating lesson and generating assets...'); // Show full page loader again
			
			const subjectName = subjectInput.value; // Get original subject name
			const llmUsed = llmSelect.value || document.querySelector('#llmSelect option[value=""]').textContent.match(/\((.*)\)/)[1]; // Get selected or default LLM ID
			
			
			try {
				// Use the new create route
				const createUrl = document.getElementById('createLessonUrl').value; // Get URL from hidden input
				const response = await fetch(createUrl, {
					method: 'POST',
					headers: {
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
						'Accept': 'application/json',
						'Content-Type': 'application/json'
					},
					body: JSON.stringify({
						subject_name: subjectName,
						llm_used: llmUsed,
						plan: currentPlanData
					}) // Send the whole plan
				});
				
				const result = await response.json();
				setLoading(false); // Hide full page loader
				modalLoadingIndicator.classList.add('d-none');
				
				
				if (!response.ok || !result.success) {
					throw new Error(result.message || `Failed to create lesson. Status: ${response.status}`);
				}
				
				// Success: Redirect to the content page
				if (result.redirectUrl) {
					window.location.href = result.redirectUrl;
				} else {
					// Fallback if redirect URL isn't provided
					showMainError('Lesson created, but redirect failed. Please go to the home page.');
					previewModal.hide();
					setFormEnabled(true); // Re-enable form
				}
				
			} catch (error) {
				console.error("Error creating lesson:", error);
				// Show error inside modal or on main page? Let's use main page error
				previewModal.hide(); // Hide modal on error
				showMainError(`Failed to create lesson: ${error.message}`);
				setFormEnabled(true); // Re-enable form
				// Re-enable modal buttons in case they try again later? Maybe not needed if form is re-enabled.
				// confirmPreviewButton.disabled = false;
				// cancelPreviewButton.disabled = false;
				modalLoadingIndicator.classList.add('d-none');
				setLoading(false);
			}
		});
		
	} else {
		console.warn("Required form or modal elements not found on this page.");
	}
	
	// Function to populate the modal content
	function populateModal(plan, subjectName, llmName) {
		if (!previewModalBody || !plan) return;
		
		previewModalLabel.textContent = `Preview: ${plan.main_title || subjectName}`;
		
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
		
		content += `<h6>Quizzes Included:</h6>`;
		content += `<ul>`;
		content += `<li>5 Easy Questions</li>`;
		content += `<li>5 Medium Questions</li>`;
		content += `<li>5 Hard Questions</li>`;
		content += `</ul>`;
		content += `<p><small class="text-muted">(Quiz details will be available after creation)</small></p>`;
		
		// Optional: Display first easy question as example?
		// if (plan.quizzes?.easy?.[0]) {
		//     const q1 = plan.quizzes.easy[0];
		//     content += `<hr><h6>Example Easy Question:</h6>`;
		//     content += `<p><em>${q1.question}</em></p>`;
		//     content += `<ul>`;
		//     q1.answers.forEach(a => {
		//         content += `<li>${a.text} ${a.is_correct ? '<strong class="text-success">(Correct)</strong>' : ''}</li>`;
		//     });
		//     content += `</ul>`;
		// }
		
		previewModalBody.innerHTML = content;
	}
	
	// Hide loading overlay if it was somehow shown on page load
	setLoading(false);
});
