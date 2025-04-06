document.addEventListener('DOMContentLoaded', () => {
		// --- Shared Audio Player (Keep as is) ---
		const sharedAudioPlayer = document.getElementById('sharedAudioPlayer');
		let currentlyPlayingButton = null;
		// ... (audio player event listeners: ended, pause, error - unchanged) ...
		if (sharedAudioPlayer) {
			sharedAudioPlayer.addEventListener('ended', () => {
				resetPlayButton(currentlyPlayingButton);
				currentlyPlayingButton = null;
			});
			sharedAudioPlayer.addEventListener('pause', () => {
				if (!sharedAudioPlayer.ended && currentlyPlayingButton) {
					resetPlayButton(currentlyPlayingButton);
					currentlyPlayingButton = null;
				}
			});
			sharedAudioPlayer.addEventListener('error', (e) => {
				console.error("Audio Player Error:", e);
				const errorAreaId = currentlyPlayingButton.dataset.errorAreaId;
				if (errorAreaId) showError(errorAreaId, 'Audio playback error.');
				resetPlayButton(currentlyPlayingButton);
				currentlyPlayingButton = null;
			});
		}
		
		const freepikModalElement = document.getElementById('freepikSearchModal');
		const freepikModal = freepikModalElement ? new bootstrap.Modal(freepikModalElement) : null;
		const freepikModalQuizIdInput = document.getElementById('freepikModalQuizId');
		const freepikSearchQueryInput = document.getElementById('freepikSearchQuery');
		const freepikSearchExecuteBtn = document.getElementById('freepikSearchExecuteBtn');
		const freepikSearchResultsContainer = document.getElementById('freepikSearchResults');
		const freepikSearchError = document.getElementById('freepikSearchError');
		const freepikSearchPlaceholder = document.getElementById('freepikSearchPlaceholder');
		const freepikSearchLoading = document.getElementById('freepikSearchLoading');
		const freepikSearchNoResults = document.getElementById('freepikSearchNoResults');
		const freepikPaginationContainer = document.getElementById('freepikPaginationContainer');
		const freepikPaginationUl = document.getElementById('freepikPagination');
		
		// --- NEW: Upload Function ---
		async function uploadQuizImage(quizId, file, errorAreaId, successAreaId) {
			const formData = new FormData();
			formData.append('quiz_image', file);
			
			const url = `/quiz/${quizId}/upload-image`; // Use the new route
			
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
				updateQuizImageDisplay(quizId, result.image_urls, result.prompt, result.message || 'Image uploaded successfully!');
				
			} catch (error) {
				console.error(`Error uploading image for quiz ${quizId}:`, error);
				showError(errorAreaId, `Upload Failed: ${error.message}`);
				// Optionally hide success message if shown previously
				hideSuccess(successAreaId);
			}
		}

// --- NEW: Freepik Modal Functions ---
		function resetFreepikModal() {
			//if (freepikModalQuizIdInput) freepikModalQuizIdInput.value = '';
			if (freepikSearchQueryInput) freepikSearchQueryInput.value = '';
			if (freepikSearchResultsContainer) freepikSearchResultsContainer.innerHTML = ''; // Clear results
			if (freepikSearchError) freepikSearchError.classList.add('d-none');
			if (freepikSearchLoading) freepikSearchLoading.classList.add('d-none');
			if (freepikSearchNoResults) freepikSearchNoResults.classList.add('d-none');
			if (freepikPaginationContainer) freepikPaginationContainer.classList.add('d-none');
			if (freepikPaginationUl) freepikPaginationUl.innerHTML = '';
			if (freepikSearchPlaceholder) freepikSearchPlaceholder.classList.remove('d-none'); // Show placeholder
			setFreepikModalInteractable(true); // Ensure modal is interactable
		}
		
		function setFreepikModalInteractable(enabled = true) {
			if (freepikSearchQueryInput) freepikSearchQueryInput.disabled = !enabled;
			if (freepikSearchExecuteBtn) freepikSearchExecuteBtn.disabled = !enabled;
			// Disable clicking on results while selecting
			if (freepikSearchResultsContainer) {
				freepikSearchResultsContainer.style.pointerEvents = enabled ? 'auto' : 'none';
			}
		}
		
		
		function showFreepikError(message) {
			if (freepikSearchError) {
				freepikSearchError.textContent = message || 'An error occurred.';
				freepikSearchError.classList.remove('d-none');
			}
		}
		
		function hideFreepikError() {
			if (freepikSearchError) {
				freepikSearchError.classList.add('d-none');
			}
		}
		
		async function performFreepikSearch(quizId, query, page = 1) {
			hideFreepikError();
			if (freepikSearchResultsContainer) freepikSearchResultsContainer.innerHTML = ''; // Clear previous results
			if (freepikSearchPlaceholder) freepikSearchPlaceholder.classList.add('d-none');
			if (freepikSearchLoading) freepikSearchLoading.classList.remove('d-none'); // Show loading
			if (freepikSearchNoResults) freepikSearchNoResults.classList.add('d-none');
			if (freepikPaginationContainer) freepikPaginationContainer.classList.add('d-none');
			showSpinner(freepikSearchExecuteBtn, true);
			
			const url = `/quiz/${quizId}/search-freepik`;
			
			try {
				const response = await fetch(url, {
					method: 'POST',
					headers: {
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
						'Accept': 'application/json',
						'Content-Type': 'application/json'
					},
					body: JSON.stringify({query: query, page: page})
				});
				const result = await response.json();
				if (freepikSearchLoading) freepikSearchLoading.classList.add('d-none'); // Hide loading
				
				if (!response.ok || !result.success) {
					throw new Error(result.message || `Search failed. Status: ${response.status}`);
				}
				
				// Display results
				displayFreepikResults(result.results || []);
				displayFreepikPagination(result.pagination || null, query);
				
				
			} catch (error) {
				if (freepikSearchLoading) freepikSearchLoading.classList.add('d-none');
				console.error("Freepik search error:", error);
				showFreepikError(`Search Failed: ${error.message}`);
			} finally {
				showSpinner(freepikSearchExecuteBtn, false);
			}
		}
		
		function displayFreepikResults(results) {
			if (!freepikSearchResultsContainer) return;
			freepikSearchResultsContainer.innerHTML = ''; // Clear again just in case
			
			if (results.length === 0) {
				if (freepikSearchNoResults) freepikSearchNoResults.classList.remove('d-none');
				return;
			}
			if (freepikSearchNoResults) freepikSearchNoResults.classList.add('d-none');
			
			
			results.forEach(item => {
				const col = document.createElement('div');
				col.className = 'col';
				col.innerHTML = `
             <div class="card h-100">
                 <img src="${item.preview_url}"
                      class="card-img-top freepik-result-image"
                      alt="${item.description}"
                      title="Select: ${item.description}"
                      data-freepik-id="${item.id}"
                      data-description="${item.description}"
                      style="cursor: pointer; aspect-ratio: 1 / 1; object-fit: contain;"
                      >
                 <div class="card-body p-1">
                     <p class="card-text small text-muted">${item.description}</p>
                 </div>
             </div>
         `;
				freepikSearchResultsContainer.appendChild(col);
			});
		}
		
		// Basic Pagination Rendering
		function displayFreepikPagination(pagination, query) {
			if (!pagination || !freepikPaginationUl || !freepikPaginationContainer || pagination.total_pages <= 1) {
				if (freepikPaginationContainer) freepikPaginationContainer.classList.add('d-none');
				if (freepikPaginationUl) freepikPaginationUl.innerHTML = '';
				return;
			}
			
			freepikPaginationUl.innerHTML = ''; // Clear existing
			const currentPage = pagination.current_page;
			const totalPages = pagination.total_pages;
			
			// Max number of page links to show (e.g., Prev, 1, ..., 4, 5, 6, ..., 10, Next)
			const maxPagesToShow = 7;
			let startPage, endPage;
			
			if (totalPages <= maxPagesToShow) {
				// Less pages than max shown, display all
				startPage = 1;
				endPage = totalPages;
			} else {
				// More pages than max shown, calculate range
				const maxPagesBeforeCurrent = Math.floor((maxPagesToShow - 3) / 2); // -3 for Prev, Next, current
				const maxPagesAfterCurrent = Math.ceil((maxPagesToShow - 3) / 2);
				
				if (currentPage <= maxPagesBeforeCurrent + 1) { // +1 for first page
					startPage = 1;
					endPage = maxPagesToShow - 2; // -2 for Prev/Next
				} else if (currentPage + maxPagesAfterCurrent >= totalPages) {
					startPage = totalPages - (maxPagesToShow - 3); // -3 for Prev/Next/Last
					endPage = totalPages;
				} else {
					startPage = currentPage - maxPagesBeforeCurrent;
					endPage = currentPage + maxPagesAfterCurrent;
				}
			}
			
			
			// Previous Button
			const prevLi = document.createElement('li');
			prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
			prevLi.innerHTML = `<a class="page-link freepik-page-link" href="#" data-page="${currentPage - 1}" data-query="${query}" aria-label="Previous"><span aria-hidden="true">«</span></a>`;
			freepikPaginationUl.appendChild(prevLi);
			
			// First Page and Ellipsis (if needed)
			if (startPage > 1) {
				const firstLi = document.createElement('li');
				firstLi.className = 'page-item';
				firstLi.innerHTML = `<a class="page-link freepik-page-link" href="#" data-page="1" data-query="${query}">1</a>`;
				freepikPaginationUl.appendChild(firstLi);
				if (startPage > 2) {
					const ellipsisLi = document.createElement('li');
					ellipsisLi.className = 'page-item disabled';
					ellipsisLi.innerHTML = `<li class="page-item disabled"><span class="page-link">...</span></li>`;
					freepikPaginationUl.appendChild(ellipsisLi);
				}
			}
			
			// Page Number Links
			for (let i = startPage; i <= endPage; i++) {
				const pageLi = document.createElement('li');
				pageLi.className = `page-item ${i === currentPage ? 'active' : ''}`;
				pageLi.innerHTML = `<a class="page-link freepik-page-link" href="#" data-page="${i}" data-query="${query}">${i}</a>`;
				freepikPaginationUl.appendChild(pageLi);
			}
			
			// Last Page and Ellipsis (if needed)
			if (endPage < totalPages) {
				if (endPage < totalPages - 1) {
					const ellipsisLi = document.createElement('li');
					ellipsisLi.className = 'page-item';
					ellipsisLi.innerHTML = `<span class="page-link">...</span></li>`;
					freepikPaginationUl.appendChild(ellipsisLi);
				}
				const lastLi = document.createElement('li');
				lastLi.className = 'page-item';
				lastLi.innerHTML = `<a href="#" class="page-link freepik-page-link" data-page="${totalPages}" data-query = "${query}">${totalPages}</a>`;
				freepikPaginationUl.appendChild(lastLi);
			}
			
			// Next Button
			const nextLi = document.createElement('li');
			nextLi.className = `page-item ${currentPage === totalPages ? 'disabled' : ''}`;
			nextLi.innerHTML = `<a href="#" class="page-link freepik-page-link"  data-page="${currentPage + 1}" data-query="${query}" aria-label = "Next"><span aria-hidden = "true" >»</span></a>`;
			freepikPaginationUl.appendChild(nextLi);
			
			freepikPaginationContainer.classList.remove('d-none'); // Show pagination
		}
		
		
		async function selectFreepikImageAction(quizId, freepikId, description, imgUrl) {
			const errorAreaId = `q-image-error-${quizId}`;
			const successAreaId = `q-image-success-${quizId}`;
			const url = `/quiz/${quizId}/select-freepik`;
			
			hideError(errorAreaId);
			hideSuccess(successAreaId);
			
			console.log(`Selecting Freepik image ${freepikId} with url ${imgUrl} for quiz ${quizId}...`);
			let imgUrls = {
				'medium': imgUrl,
			}
			
			try {
				const response = await fetch(url, {
					method: 'POST',
					headers: {
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
						'Accept': 'application/json',
						'Content-Type': 'application/json'
					},
					body: JSON.stringify({
						freepik_id: freepikId,
						description: description,
						download_token_or_url: imgUrl
					})
				});
				const result = await response.json();
				
				if (!response.ok || !result.success) {
					throw new Error(result.message || `Image selection failed. Status: ${response.status}`);
				}
				
				// Success
				updateQuizImageDisplay(quizId, imgUrls, description, 'Image selected successfully!');
				// updateQuizImageDisplay(quizId, result.image_urls, result.prompt, result.message || 'Image selected successfully!');
				freepikModal.hide(); // Close modal on success
				
			} catch (error) {
				console.error(`Error selecting Freepik image ${freepikId} for quiz ${quizId}:`, error);
				// Show error in the main quiz item area AND the modal
				showError(errorAreaId, `Selection Failed: ${error.message}`);
				showFreepikError(`Selection Failed: ${error.message}`); // Show error in modal too
				// Remove loading indicator from image
				const selectedImg = freepikSearchResultsContainer.querySelector(`.freepik-result-image[data-freepik-id="${freepikId}"]`);
				if (selectedImg && selectedImg.parentElement.lastChild.nodeName !== 'IMG') { // Basic check for loading div
					selectedImg.parentElement.lastChild.remove();
					selectedImg.classList.remove('border', 'border-primary', 'border-3'); // Remove highlight
				}
			}
			
		}
		
		
		function playAudio(button, audioUrl) {
			if (!sharedAudioPlayer || !button || !audioUrl) return;
			
			if (currentlyPlayingButton && currentlyPlayingButton !== button) {
				sharedAudioPlayer.pause();
				resetPlayButton(currentlyPlayingButton);
			}
			
			if (currentlyPlayingButton === button) {
				sharedAudioPlayer.pause(); // Will trigger pause listener to reset state
			} else {
				sharedAudioPlayer.src = audioUrl;
				sharedAudioPlayer.play().then(() => {
					button.classList.add('playing');
					currentlyPlayingButton = button;
					if (button.dataset.errorAreaId) hideError(button.dataset.errorAreaId);
				}).catch(error => {
					console.error("Error playing audio:", error);
					if (button.dataset.errorAreaId) showError(button.dataset.errorAreaId, 'Playback failed.');
					resetPlayButton(button); // Reset button state on play error
					currentlyPlayingButton = null;
				});
			}
		}
		
		function resetPlayButton(button) {
			if (button) {
				button.classList.remove('playing');
			}
		}
		
		// --- Image Modal (Keep as is) ---
		const imageModal = document.getElementById('imageModal');
		// ... (image modal event listeners: show.bs.modal, hidden.bs.modal - unchanged) ...
		if (imageModal) {
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
		}
		
		// --- Helper Functions (Refined showSpinner, showError, hideError) ---
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
		
		
		// --- Asset Display Updaters (Modified/Simplified where needed) ---
		
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
		
		function updateQuestionAudioDisplay(quizId, audioUrl) {
			const controlsArea = document.getElementById(`q-audio-controls-${quizId}`);
			const errorAreaId = `q-audio-error-${quizId}`;
			if (!controlsArea) return;
			
			hideError(errorAreaId);
			controlsArea.innerHTML = `
            <button class="btn btn-sm btn-outline-primary btn-play-pause" data-audio-url="${audioUrl}" data-error-area-id="${errorAreaId}" title="Play Question Audio">
                <i class="fas fa-play"></i><i class="fas fa-pause"></i>
            </button>`;
			// Ensure any associated generate button is removed (might be handled by caller)
			const genButton = controlsArea.closest('.quiz-item').querySelector(`.generate-asset-btn[data-quiz-id="${quizId}"][data-asset-type="question-audio"]`);
			genButton.remove();
		}
		
		function updateAnswerAudioStatus(quizId, success = true, answersData = null) {
			const statusArea = document.getElementById(`a-audio-status-${quizId}`);
			const buttonContainer = document.getElementById(`a-audio-container-${quizId}`);
			const errorAreaId = `a-audio-error-${quizId}`;
			hideError(errorAreaId);
			
			if (!statusArea || !buttonContainer) return;
			
			const generateButton = buttonContainer.querySelector(`.generate-asset-btn[data-quiz-id="${quizId}"][data-asset-type="answer-audio"]`);
			
			if (success) {
				statusArea.innerHTML = '<span class="text-success small"><i class="fas fa-check-circle me-1"></i>Generated</span>';
				generateButton.remove(); // Remove the 'Generate All' button
				
				// Update individual answer/feedback play buttons if data provided
				if (answersData && Array.isArray(answersData)) {
					const answerList = document.querySelector(`#quiz-item-${quizId} .answer-list`);
					if (answerList) {
						answersData.forEach((answer, index) => {
							const ansControls = answerList.querySelector(`#ans-audio-controls-${quizId}-${index}`);
							const fbControls = answerList.querySelector(`#fb-audio-controls-${quizId}-${index}`);
							
							if (ansControls && answer.answer_audio_url) {
								ansControls.innerHTML = `<button class="btn btn-sm btn-outline-primary btn-play-pause" data-audio-url="${answer.answer_audio_url}" data-error-area-id="${errorAreaId}" title="Play Answer Audio"><i class="fas fa-play"></i><i class="fas fa-pause"></i></button>`;
							} else if (ansControls) {
								ansControls.innerHTML = `<span class="badge bg-light text-dark ms-1" title="Answer audio not available"><i class="fas fa-volume-mute"></i></span>`; // Show mute if failed/missing
							}
							
							if (fbControls && answer.feedback_audio_url) {
								fbControls.innerHTML = `<button class="btn btn-sm btn-outline-primary btn-play-pause" data-audio-url="${answer.feedback_audio_url}" data-error-area-id="${errorAreaId}" title="Play Feedback Audio"><i class="fas fa-play"></i><i class="fas fa-pause"></i></button>`;
							} else if (fbControls) {
								fbControls.innerHTML = `<span class="badge bg-light text-dark ms-1" title="Feedback audio not available"><i class="fas fa-volume-mute"></i></span>`; // Show mute if failed/missing
							}
						});
					}
				} else {
					// If no data, just show status and maybe suggest refresh?
					// statusArea.innerHTML += ' <a href="javascript:location.reload();" class="small">(Refresh page to view players)</a>';
					console.warn(`Answer audio generated for quiz ${quizId}, but no answer data returned to update players.`);
				}
			} else {
				statusArea.innerHTML = '<span class="text-danger small"><i class="fas fa-times-circle me-1"></i>Failed</span>';
				// Re-enable button on failure
				if (generateButton) showSpinner(generateButton, false);
			}
		}
		
		function updateQuizImageDisplay(quizId, imageUrls, prompt, successMessage = null) {
			const displayArea = document.getElementById(`q-image-display-${quizId}`);
			const buttonContainer = document.getElementById(`q-image-container-${quizId}`); // Container of image+prompt+buttons
			const errorAreaId = `q-image-error-${quizId}`;
			const successAreaId = `q-image-success-${quizId}`; // ID for success message
			
			hideError(errorAreaId); // Hide previous errors
			hideSuccess(successAreaId); // Hide previous success messages
			
			if (!displayArea || !buttonContainer || !imageUrls) {
				console.error("Missing elements or image URLs for quiz image update:", quizId);
				showError(errorAreaId, "Internal error updating image display.");
				return;
			}
			
			const altText = `Quiz Image: ${prompt || 'User provided image'}`;
			// Prefer medium, then small, then original for display
			const displayUrl = imageUrls.medium || imageUrls.small || imageUrls.original;
			
			if (!displayUrl) {
				displayArea.innerHTML = `<span class="text-danger quiz-image-thumb d-flex align-items-center justify-content-center border rounded p-2 text-center" style="width: 100%; height: 100%;">Error loading image URL</span>`;
				showError(errorAreaId, "Generated/uploaded image URL is missing."); // Show error
				return;
			}
			
			// Update Image Display
			displayArea.innerHTML = `
        <a href="#" class="quiz-image-clickable" data-bs-toggle="modal" data-bs-target="#imageModal" data-image-url="${imageUrls.original || '#'}" data-image-alt="${altText}" title="Click to enlarge">
            <img src="${displayUrl}" alt="${altText}" class="img-thumbnail quiz-image-thumb" style="width: 100%; height: 100%; object-fit: contain;">
        </a>`;
			
			// Update Prompt Input (only if prompt is provided, might be null for uploads)
			const promptInput = document.getElementById(`prompt-input-${quizId}`);
			if (promptInput) {
				promptInput.value = prompt ?? ''; // Set to empty string if prompt is null
			}
			
			// Update AI Generate Button Text (might now be just 'Generate' if user uploaded)
			const regenButton = buttonContainer.querySelector('.regenerate-quiz-image-btn');
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
		
		
		// --- Event Listeners ---
		
		// Use event delegation for dynamically added elements
		document.body.addEventListener('click', async (event) => {
			
			// 1. Play/Pause Button Click
			const playPauseButton = event.target.closest('.btn-play-pause');
			if (playPauseButton) {
				const audioUrl = playPauseButton.dataset.audioUrl;
				playAudio(playPauseButton, audioUrl);
				return; // Stop processing further listeners
			}
			
			// 2. Generate Part Video Button Click
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
			
			// 3. Generate Single Asset Button Click (Question Audio, Answer Audio)
			const generateAssetBtn = event.target.closest('.generate-asset-btn');
			if (generateAssetBtn) {
				const btn = generateAssetBtn;
				const url = btn.dataset.url;
				const assetType = btn.dataset.assetType; // 'question-audio', 'answer-audio'
				const quizId = btn.dataset.quizId;
				const targetAreaId = btn.dataset.targetAreaId;
				const errorAreaId = btn.dataset.errorAreaId;
				
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
						// Handle conflict/already exists specifically
						if (response.status === 200 && result.message.includes('already exists')) { // Backend returns 200
							console.warn(`${assetType} for quiz ${quizId} already exists.`);
							if (assetType === 'question-audio' && result.audio_url) {
								updateQuestionAudioDisplay(quizId, result.audio_url); // Update UI to show player
							} else if (assetType === 'answer-audio') {
								updateAnswerAudioStatus(quizId, true, result.answers); // Mark as success, update players if data provided
							} else {
								btn.remove(); // Fallback: remove the generate button
							}
							showSpinner(btn, false); // Ensure spinner is off
						} else {
							throw new Error(result.message || `HTTP error ${response.status}`);
						}
					} else {
						// --- Success ---
						if (assetType === 'question-audio' && result.audio_url) {
							updateQuestionAudioDisplay(quizId, result.audio_url);
							// Button is removed/replaced by updateQuestionAudioDisplay
						} else if (assetType === 'answer-audio') {
							updateAnswerAudioStatus(quizId, true, result.answers); // Pass returned answer data
							// Button is removed by updateAnswerAudioStatus
						}
					}
				} catch (error) {
					console.error(`Error generating ${assetType} for quiz ${quizId}:`, error);
					showError(errorAreaId, `Failed: ${error.message}`);
					showSpinner(btn, false); // Re-enable button on error
				}
				return; // Stop processing further listeners
			}
			
			// 4. Regenerate/Generate Quiz Image Button Click
			const regenImageBtn = event.target.closest('.regenerate-quiz-image-btn');
			if (regenImageBtn) {
				const btn = regenImageBtn;
				const url = btn.dataset.url;
				const quizId = btn.dataset.quizId;
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
							console.warn(`Image for quiz ${quizId} already exists.`);
							if (result.image_urls && result.prompt) {
								updateQuizImageDisplay(quizId, result.image_urls, result.prompt); // Update UI anyway
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
						updateQuizImageDisplay(quizId, result.image_urls, result.prompt); // Use prompt from result
					}
				} catch (error) {
					console.error(`Error generating/regenerating image for quiz ${quizId}:`, error);
					showError(errorAreaId, `Failed: ${error.message}`);
					showSpinner(btn, false); // Ensure button enabled on error
				}
				return; // Stop processing further listeners
			}
			
			
			// 5. NEW: Add Quiz Batch Button Click
			const addQuizBatchBtn = event.target.closest('.add-quiz-batch-btn');
			if (addQuizBatchBtn) {
				const btn = addQuizBatchBtn;
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
					
					// Success: Render the new quizzes
					if (result.quizzes && Array.isArray(result.quizzes)) {
						
						//show alert with success message when press ok refresh page
						const modal = document.createElement('div');
						modal.style.position = 'fixed';
						modal.style.top = '0';
						modal.style.left = '0';
						modal.style.width = '100%';
						modal.style.height = '100%';
						modal.style.backgroundColor = 'rgba(0,0,0,0.5)';
						modal.style.display = 'flex';
						modal.style.justifyContent = 'center';
						modal.style.alignItems = 'center';
						modal.style.zIndex = '9999';
						
						// Create the modal content
						const modalContent = document.createElement('div');
						modalContent.style.backgroundColor = 'white';
						modalContent.style.padding = '20px';
						modalContent.style.borderRadius = '5px';
						modalContent.style.textAlign = 'center';
						modalContent.style.maxWidth = '400px';
						
						// Add message and button
						modalContent.innerHTML = `
        <h3>Success!</h3>
        <p>Operation completed successfully.</p>
        <button id="okButton" style="padding: 8px 16px; background-color: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer;">OK</button>
    `;
						
						modal.appendChild(modalContent);
						document.body.appendChild(modal);
						
						// Add event listener to button
						document.getElementById('okButton').addEventListener('click', function () {
							window.location.reload();
						});
						
					} else {
						console.warn("Quiz generation successful, but no quiz data returned.");
					}
					
				} catch (error) {
					console.error(`Error generating ${difficulty} quizzes for part ${partIndex}:`, error);
					showError(errorAreaId, `Failed: ${error.message}`);
				} finally {
					showSpinner(btn, false);
				}
				return; // Stop processing
			}
			
			// 6. NEW: Delete Quiz Button Click
			const deleteQuizBtn = event.target.closest('.delete-quiz-btn');
			if (deleteQuizBtn) {
				const btn = deleteQuizBtn;
				const quizId = btn.dataset.quizId;
				const url = btn.dataset.deleteUrl;
				const quizItemElement = document.getElementById(`quiz-item-${quizId}`);
				
				if (!quizId || !url || !quizItemElement) {
					console.error("Missing data for quiz deletion.", {quizId, url, quizItemElement});
					showError(btn.parentElement, "Cannot delete quiz: internal error."); // Show error near button
					return;
				}
				
				// Confirmation
				if (!confirm(`Are you sure you want to delete this quiz? This action cannot be undone.`)) {
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
					const listContainer = quizItemElement.parentElement;
					quizItemElement.remove();
					
					// Update count badge
					const badge = listContainer.closest('.quiz-difficulty-group').querySelector('.badge');
					if (badge) {
						const currentCount = parseInt(badge.textContent) || 0;
						const newCount = Math.max(0, currentCount - 1); // Ensure count doesn't go below 0
						badge.textContent = newCount;
						// Show placeholder if list becomes empty
						if (newCount === 0 && listContainer) {
							const difficulty = listContainer.id.split('-')[1]; // Extract difficulty from ID like 'quiz-list-easy-0'
							listContainer.innerHTML = `<p class="placeholder-text">No ${difficulty} quizzes created yet for this part.</p>`;
						}
					}
					
					
				} catch (error) {
					console.error(`Error deleting quiz ${quizId}:`, error);
					showError(btn.parentElement, `Failed: ${error.message}`); // Show error near button
					showSpinner(btn, false); // Hide spinner on error
				}
				// No finally needed for spinner here, element is removed on success
				return; // Stop processing
			}
			
			
			// --- NEW: Image Upload Trigger ---
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
			
			// --- NEW: Freepik Search Modal Trigger ---
			const searchFreepikBtn = event.target.closest('.search-freepik-btn');
			if (searchFreepikBtn) {
				resetFreepikModal(); // Clear previous results/errors
				
				const quizId = searchFreepikBtn.dataset.quizId;
				const promptInputId = searchFreepikBtn.dataset.promptInputId;
				const promptInput = document.getElementById(promptInputId);
				const currentPrompt = promptInput ? promptInput.value.trim() : '';
				
				// Set the quiz ID in the modal
				if (freepikModalQuizIdInput) {
					freepikModalQuizIdInput.value = quizId;
				}
				// Pre-fill search query from prompt? Optional UX improvement
				if (freepikSearchQueryInput && currentPrompt) {
					freepikSearchQueryInput.value = currentPrompt;
				}
				// Modal is opened via data-bs-toggle/target attributes on the button
				return;
			}
			
			// --- NEW: Freepik Search Execute Button (inside modal) ---
			if (freepikSearchExecuteBtn && freepikSearchExecuteBtn.contains(event.target)) {
				const quizId = freepikModalQuizIdInput.value;
				const query = freepikSearchQueryInput.value.trim();
				if (quizId && query) {
					performFreepikSearch(quizId, query, 1); // Start search on page 1
				} else {
					showFreepikError("Please enter a search term. quizId: " + quizId + " query: " + query);
				}
				return;
			}
			
			// --- NEW: Freepik Image Selection (inside modal results) ---
			const selectFreepikImage = event.target.closest('.freepik-result-image');
			if (selectFreepikImage) {
				const quizId = freepikModalQuizIdInput.value;
				const freepikId = selectFreepikImage.dataset.freepikId;
				const description = selectFreepikImage.dataset.description;
				const imageUrl = selectFreepikImage.src;
				
				if (quizId && freepikId) {
					// Add visual confirmation / loading state to the clicked image
					selectFreepikImage.classList.add('border', 'border-primary', 'border-3'); // Highlight selected
					const loadingDiv = document.createElement('div');
					loadingDiv.innerHTML = `<div class="spinner-border spinner-border-sm text-primary" role="status"></div> Selecting...`;
					loadingDiv.classList.add('position-absolute', 'top-50', 'start-50', 'translate-middle', 'bg-light', 'p-1', 'rounded', 'opacity-75');
					selectFreepikImage.parentElement.appendChild(loadingDiv); // Append loading to container
					
					// Disable further clicks in modal? Optional
					setFreepikModalInteractable(false);
					
					await selectFreepikImageAction(quizId, freepikId, description, imageUrl);
					
					// Re-enable modal interaction on completion (success or error handled in selectFreepikImageAction)
					setFreepikModalInteractable(true);
					
					
				}
				return;
			}
			
			// --- NEW: Freepik Pagination Click ---
			const paginationLink = event.target.closest('.freepik-page-link');
			if (paginationLink && !paginationLink.parentElement.classList.contains('disabled') && !paginationLink.parentElement.classList.contains('active')) {
				event.preventDefault();
				const quizId = freepikModalQuizIdInput.value;
				const query = freepikSearchQueryInput.value.trim();
				const page = parseInt(paginationLink.dataset.page);
				if (quizId && query && page) {
					performFreepikSearch(quizId, query, page);
				}
				return;
			}
			
		}); // End of delegated event listener
		
		
		document.body.addEventListener('change', async (event) => {
			const fileInput = event.target.closest('input[type="file"]');
			// Check if it's one of our specific quiz image inputs
			if (fileInput && fileInput.id.startsWith('file-input-')) {
				const quizId = fileInput.dataset.quizId;
				const errorAreaId = `q-image-error-${quizId}`;
				const successAreaId = `q-image-success-${quizId}`;
				
				if (fileInput.files.length > 0) {
					const file = fileInput.files[0];
					hideError(errorAreaId);
					hideSuccess(successAreaId);
					
					// Show temporary loading state near the button group maybe?
					const buttonGroup = fileInput.closest('.quiz-item').querySelector('.btn-group[aria-label="Image Actions"]');
					let tempSpinner;
					if (buttonGroup) {
						tempSpinner = document.createElement('span');
						tempSpinner.innerHTML = `<span class="spinner-border spinner-border-sm ms-2" role="status" aria-hidden="true"></span> Uploading...`;
						buttonGroup.parentNode.insertBefore(tempSpinner, buttonGroup.nextSibling);
					}
					
					
					await uploadQuizImage(quizId, file, errorAreaId, successAreaId);
					
					if (tempSpinner) tempSpinner.remove(); // Remove temporary spinner
					fileInput.value = ''; // Reset file input
				}
			}
		});
		
		
		// --- NEW: Freepik Modal Reset on Hide ---
		if (freepikModalElement) {
			freepikModalElement.addEventListener('hidden.bs.modal', () => {
				resetFreepikModal();
			});
		}
		
	}
)
; // End DOMContentLoaded
