// Basic JS for the subject input page
document.addEventListener('DOMContentLoaded', () => {
	const subjectForm = document.getElementById('subjectForm');
	const startLearningButton = document.getElementById('startLearningButton');
	const startLearningSpinner = document.getElementById('startLearningSpinner');
	const subjectInput = document.getElementById('subjectInput');
	const loadingOverlay = document.getElementById('loadingOverlay'); // Get common elements
	const loadingMessageEl = document.getElementById('loadingMessage');
	
	function setLoading(isLoading, message = 'Generating...') {
		if (!loadingOverlay || !loadingMessageEl) return;
		loadingMessageEl.textContent = message;
		if (isLoading) {
			loadingOverlay.classList.remove('d-none');
		} else {
			loadingOverlay.classList.add('d-none');
		}
	}
	
	if (subjectForm && startLearningButton && startLearningSpinner && subjectInput) {
		// Disable button initially if input is empty
		startLearningButton.disabled = !subjectInput.value.trim();
		
		subjectInput.addEventListener('input', () => {
			startLearningButton.disabled = !subjectInput.value.trim();
		});
		
		subjectForm.addEventListener('submit', () => {
			// Show spinner and loading message on submit
			startLearningButton.disabled = true;
			startLearningSpinner.classList.remove('d-none');
			setLoading(true, 'Generating initial content...'); // Use common overlay
			
			// The form submission itself handles the navigation
		});
	}
	
	// Hide loading overlay if it was somehow shown on page load (e.g., back button)
	setLoading(false);
});
