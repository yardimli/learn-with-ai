console.log("Common JS loaded.");

document.addEventListener('DOMContentLoaded', () => {
	const darkModeSwitch = document.getElementById('darkModeSwitch');
	const htmlElement = document.documentElement; // Target <html> for the class
	const moonIcon = document.getElementById('darkModeIconMoon');
	const sunIcon = document.getElementById('darkModeIconSun');
	
	if (!darkModeSwitch || !htmlElement || !moonIcon || !sunIcon) {
		console.error("Dark mode switch elements not found!");
		return;
	}
	
	// Function to update icon visibility
	const updateIcons = (isDarkMode) => {
		moonIcon.classList.toggle('d-none', isDarkMode);
		sunIcon.classList.toggle('d-none', !isDarkMode);
	};
	
	// Set initial switch state and icons based on localStorage/class on <html>
	const isCurrentlyDark = htmlElement.classList.contains('dark-mode');
	darkModeSwitch.checked = isCurrentlyDark;
	updateIcons(isCurrentlyDark);
	
	// Add event listener
	darkModeSwitch.addEventListener('change', (event) => {
		const isDarkMode = event.target.checked;
		htmlElement.classList.toggle('dark-mode', isDarkMode);
		localStorage.setItem('darkModeEnabled', isDarkMode);
		updateIcons(isDarkMode);
	});
	
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
	
});


function showSpinner(element, show = true) {
	if (!element) return;
	const spinner = element.querySelector('.spinner-border');
	if (spinner) spinner.classList.toggle('d-none', !show);
	// Disable button/input associated with the spinner container
	if (element.tagName === 'BUTTON' || element.tagName === 'INPUT') {
		element.disabled = show;
	} else {
		// If it's a container, try to find a button inside
		const button = element.querySelector('button');
		if (button) button.disabled = show;
	}
}

function showError(elementOrId, message) {
	const errorEl = (typeof elementOrId === 'string') ? document.getElementById(elementOrId) : elementOrId;
	if (errorEl) {
		errorEl.textContent = message || 'An unknown error occurred.';
		errorEl.style.display = 'inline-block'; // Or 'block' if preferred
	} else {
		console.warn(`Error element not found: ${elementOrId}`);
		// Fallback to general alert if exists
		const mainErrorArea = document.getElementById('errorMessageArea');
		const mainErrorText = document.getElementById('errorMessageText');
		if (mainErrorArea && mainErrorText) {
			mainErrorText.textContent = `Error: ${message || 'An unknown error occurred.'}`;
			mainErrorArea.classList.remove('d-none');
		}
	}
}

function hideError(elementOrId) {
	const errorEl = (typeof elementOrId === 'string') ? document.getElementById(elementOrId) : elementOrId;
	if (errorEl && errorEl.style.display !== 'none') {
		errorEl.style.display = 'none';
		errorEl.textContent = '';
	}
}

function showSuccess(elementOrId, message, autoHideDelay = 3000) {
	const successEl = (typeof elementOrId === 'string') ? document.getElementById(elementOrId) : elementOrId;
	if (successEl) {
		successEl.textContent = message || 'Operation successful.';
		successEl.style.display = 'block'; // Or 'inline-block'
		if (autoHideDelay > 0) {
			setTimeout(() => hideSuccess(successEl), autoHideDelay);
		}
	}
}

function hideSuccess(elementOrId) {
	const successEl = (typeof elementOrId === 'string') ? document.getElementById(elementOrId) : elementOrId;
	if (successEl && successEl.style.display !== 'none') {
		successEl.style.display = 'none';
		successEl.textContent = '';
	}
}

// Show a toast notification
function showToast(message, title = 'Notification', type = 'info') {
	const toast = document.getElementById('toast');
	const toastTitle = document.getElementById('toastTitle');
	const toastMessage = document.getElementById('toastMessage');
	
	if (!toast || !toastTitle || !toastMessage) return;
	
	// Set toast content
	toastTitle.textContent = title;
	toastMessage.textContent = message;
	
	// Set toast type (via Bootstrap classes)
	toast.className = 'toast';
	if (type === 'success') {
		toast.classList.add('bg-success', 'text-white');
	} else if (type === 'error') {
		toast.classList.add('bg-danger', 'text-white');
	} else if (type === 'warning') {
		toast.classList.add('bg-warning');
	} else {
		toast.classList.add('bg-info', 'text-white');
	}
	
	// Create and show toast
	const bsToast = new bootstrap.Toast(toast);
	bsToast.show();
}

function escapeHtml(text) {
	const div = document.createElement('div');
	div.textContent = text;
	return div.innerHTML;
}
