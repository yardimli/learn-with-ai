// JS for the content display page
document.addEventListener('DOMContentLoaded', () => {
	const startQuizButton = document.getElementById('startQuizButton');
	const startQuizSpinner = document.getElementById('startQuizSpinner');
	const startQuizButtonText = document.getElementById('startQuizButtonText');
	const startQuizMessage = document.getElementById('startQuizMessage');
	
	const initialVideoPlayer = document.getElementById('initialVideoPlayer');
	const initialVideoWrapper = document.getElementById('initialVideoWrapper'); // Get the wrapper
	const initialImageContainer = document.getElementById('initialImageContainer');
	const initialImage = document.getElementById('initialImage'); // Might need later
	const playIconOverlay = initialImageContainer?.querySelector('.play-icon-overlay'); // Find inside container
	
	const loadingOverlay = document.getElementById('loadingOverlay');
	const loadingMessageEl = document.getElementById('loadingMessage');
	
	let hasVideoPlayedOnce = false; // Track if video played
	
	function setLoading(isLoading, message = 'Generating...') {
		if (!loadingOverlay || !loadingMessageEl) return;
		loadingMessageEl.textContent = message;
		if (isLoading) {
			loadingOverlay.classList.remove('d-none');
		} else {
			loadingOverlay.classList.add('d-none');
		}
	}
	
	function toggleElement(element, show) {
		if (!element) return;
		element.classList.toggle('d-none', !show);
	}
	
	// Function to update UI based on video/image state
	function updateMediaDisplay(showVideo) {
		if (initialVideoWrapper && initialImageContainer) {
			toggleElement(initialVideoWrapper, showVideo);
			toggleElement(initialImageContainer, !showVideo);
			
			// Show/hide play icon overlay on the image
			if (initialImageContainer && playIconOverlay) {
				const videoIsAvailable = !!initialVideoPlayer?.src;
				initialImageContainer.classList.toggle('show-play', !showVideo && videoIsAvailable);
			}
		} else if (initialImageContainer) {
			// Only image exists, ensure it's shown
			toggleElement(initialImageContainer, true);
			if (playIconOverlay) playIconOverlay.classList.add('d-none'); // No video to play
		} else if (initialVideoWrapper) {
			// Only video exists (unlikely based on blade logic, but handle)
			toggleElement(initialVideoWrapper, showVideo);
		}
	}
	
	// --- Initial Setup ---
	const videoExists = !!initialVideoPlayer;
	hasVideoPlayedOnce = true; // Treat as "played" if no video
	if (startQuizButton) {
		if (videoExists) {
			startQuizButton.disabled = true; // Disable initially if video exists
			if (startQuizButtonText) startQuizButtonText.textContent = 'Watch Intro Video First';
		} else {
			startQuizButton.disabled = false; // Enable if no video
			hasVideoPlayedOnce = true; // Treat as "played" if no video
			if (startQuizButtonText) startQuizButtonText.textContent = 'Start Quiz';
		}
	}
	
	// Set initial media visibility (Blade handles initial d-none, JS confirms)
	updateMediaDisplay(videoExists);
	
	
	// --- Event Listeners ---
	
	if (startQuizButton && startQuizSpinner && startQuizMessage && startQuizButtonText) {
		startQuizButton.closest('form').addEventListener('submit', (event) => { // Add event param
			if(startQuizButton.disabled) {
				console.warn("Quiz start prevented, button should be disabled.");
				event.preventDefault(); // Prevent submission if button is somehow clicked while disabled
				return;
			}
			startQuizButton.disabled = true;
			startQuizSpinner.classList.remove('d-none');
			startQuizMessage.classList.remove('d-none');
			startQuizButtonText.textContent = 'Loading...';
			// Use the global loading overlay as well for consistency
			setLoading(true, 'Generating first quiz question...');
		});
	}
	
	
	if (initialVideoPlayer) {
		initialVideoPlayer.addEventListener('ended', () => {
			console.log('Intro video finished.');
			if (!hasVideoPlayedOnce) {
				hasVideoPlayedOnce = true;
				if (startQuizButton) {
					startQuizButton.disabled = false; // Enable button
					if (startQuizButtonText) startQuizButtonText.textContent = 'Start Quiz';
					// Optional: Add a visual cue
					startQuizButton.classList.add('pulsing-border');
					setTimeout(() => startQuizButton.classList.remove('pulsing-border'), 2000);
				}
			}
			updateMediaDisplay(false); // Show image
		});
		
		initialVideoPlayer.addEventListener('play', () => {
			console.log('Intro video playing.');
			updateMediaDisplay(true); // Ensure video is shown
		});
		
		initialVideoPlayer.addEventListener('error', () => {
			console.error('Failed to load intro video.');
			// Video failed, enable the quiz button as the condition can't be met
			hasVideoPlayedOnce = true; // Mark as "played" to skip requirement
			if (startQuizButton) {
				startQuizButton.disabled = false;
				if (startQuizButtonText) startQuizButtonText.textContent = 'Start Quiz (Video Failed)';
			}
			updateMediaDisplay(false); // Attempt to show image as fallback
		});
	}
	
	// Click listener for the image container to replay video
	if (initialImageContainer && initialVideoPlayer) {
		initialImageContainer.addEventListener('click', () => {
			// Check if video source exists before trying to replay
			if (initialVideoPlayer.src) {
				console.log('Replaying intro video.');
				updateMediaDisplay(true); // Show video
				initialVideoPlayer.currentTime = 0;
				initialVideoPlayer.play().catch(e => console.error("Video replay error:", e));
			} else {
				console.log("Video source not found, cannot replay.");
			}
		});
	}
	
	
	// Hide loading overlay if it was somehow shown on page load
	setLoading(false);
	
	// Final check on button state after setup
	if(startQuizButton && hasVideoPlayedOnce) {
		startQuizButton.disabled = false;
		if (startQuizButtonText && startQuizButtonText.textContent.includes('Watch')) {
			startQuizButtonText.textContent = 'Start Quiz'; // Ensure text is correct if somehow missed
		}
	}
	
});
