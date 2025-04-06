document.addEventListener('DOMContentLoaded', () => {
	const freepikModalElement = document.getElementById('freepikSearchModal');
	const freepikModal = freepikModalElement ? new bootstrap.Modal(freepikModalElement) : null;
	const freepikModalQuestionIdInput = document.getElementById('freepikModalQuestionId');
	const freepikSearchQueryInput = document.getElementById('freepikSearchQuery');
	const freepikSearchExecuteBtn = document.getElementById('freepikSearchExecuteBtn');
	const freepikSearchResultsContainer = document.getElementById('freepikSearchResults');
	const freepikSearchError = document.getElementById('freepikSearchError');
	const freepikSearchPlaceholder = document.getElementById('freepikSearchPlaceholder');
	const freepikSearchLoading = document.getElementById('freepikSearchLoading');
	const freepikSearchNoResults = document.getElementById('freepikSearchNoResults');
	const freepikPaginationContainer = document.getElementById('freepikPaginationContainer');
	const freepikPaginationUl = document.getElementById('freepikPagination');
	
	
	// --- NEW: Freepik Modal Functions ---
	function resetFreepikModal() {
		if (freepikModalQuestionIdInput) freepikModalQuestionIdInput.value = '';
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
	
	async function performFreepikSearch(questionId, query, page = 1) {
		hideFreepikError();
		if (freepikSearchResultsContainer) freepikSearchResultsContainer.innerHTML = ''; // Clear previous results
		if (freepikSearchPlaceholder) freepikSearchPlaceholder.classList.add('d-none');
		if (freepikSearchLoading) freepikSearchLoading.classList.remove('d-none'); // Show loading
		if (freepikSearchNoResults) freepikSearchNoResults.classList.add('d-none');
		if (freepikPaginationContainer) freepikPaginationContainer.classList.add('d-none');
		showSpinner(freepikSearchExecuteBtn, true);
		
		const url = `/question/${questionId}/search-freepik`;
		
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
	
	
	async function selectFreepikImageAction(questionId, freepikId, description, imgUrl) {
		const errorAreaId = `q-image-error-${questionId}`;
		const successAreaId = `q-image-success-${questionId}`;
		const url = `/question/${questionId}/select-freepik`;
		
		hideError(errorAreaId);
		hideSuccess(successAreaId);
		
		console.log(`Selecting Freepik image ${freepikId} with url ${imgUrl} for question ${questionId}...`);
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
			updateQuestionImageDisplay(questionId, imgUrls, description, 'Image selected successfully!');
			// updateQuestionImageDisplay(questionId, result.image_urls, result.prompt, result.message || 'Image selected successfully!');
			freepikModal.hide(); // Close modal on success
			
		} catch (error) {
			console.error(`Error selecting Freepik image ${freepikId} for question ${questionId}:`, error);
			// Show error in the main question item area AND the modal
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
	
	document.body.addEventListener('click', async (event) => {

// --- NEW: Freepik Search Modal Trigger ---
		const searchFreepikBtn = event.target.closest('.search-freepik-btn');
		if (searchFreepikBtn) {
			resetFreepikModal(); // Clear previous results/errors
			
			const questionId = searchFreepikBtn.dataset.questionId;
			const keywordsInputId = searchFreepikBtn.dataset.keywordsInputId;
			const keywordsInput = document.getElementById(keywordsInputId);
			const currentPrompt = keywordsInput ? keywordsInput.value.trim() : '';
			
			// Set the question ID in the modal
			if (freepikModalQuestionIdInput) {
				freepikModalQuestionIdInput.value = questionId;
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
			const questionId = freepikModalQuestionIdInput.value;
			const query = freepikSearchQueryInput.value.trim();
			if (questionId && query) {
				performFreepikSearch(questionId, query, 1); // Start search on page 1
			} else {
				showFreepikError("Please enter a search term. questionId: " + questionId + " query: " + query);
			}
			return;
		}

// --- NEW: Freepik Image Selection (inside modal results) ---
		const selectFreepikImage = event.target.closest('.freepik-result-image');
		if (selectFreepikImage) {
			const questionId = freepikModalQuestionIdInput.value;
			const freepikId = selectFreepikImage.dataset.freepikId;
			const description = selectFreepikImage.dataset.description;
			const imageUrl = selectFreepikImage.src;
			
			if (questionId && freepikId) {
				// Add visual confirmation / loading state to the clicked image
				selectFreepikImage.classList.add('border', 'border-primary', 'border-3'); // Highlight selected
				const loadingDiv = document.createElement('div');
				loadingDiv.innerHTML = `<div class="spinner-border spinner-border-sm text-primary" role="status"></div> Selecting...`;
				loadingDiv.classList.add('position-absolute', 'top-50', 'start-50', 'translate-middle', 'bg-light', 'p-1', 'rounded', 'opacity-75');
				selectFreepikImage.parentElement.appendChild(loadingDiv); // Append loading to container
				
				// Disable further clicks in modal? Optional
				setFreepikModalInteractable(false);
				
				await selectFreepikImageAction(questionId, freepikId, description, imageUrl);
				
				// Re-enable modal interaction on completion (success or error handled in selectFreepikImageAction)
				setFreepikModalInteractable(true);
				
				
			}
			return;
		}

// --- NEW: Freepik Pagination Click ---
		const paginationLink = event.target.closest('.freepik-page-link');
		if (paginationLink && !paginationLink.parentElement.classList.contains('disabled') && !paginationLink.parentElement.classList.contains('active')) {
			event.preventDefault();
			const questionId = freepikModalQuestionIdInput.value;
			const query = freepikSearchQueryInput.value.trim();
			const page = parseInt(paginationLink.dataset.page);
			if (questionId && query && page) {
				performFreepikSearch(questionId, query, page);
			}
			return;
		}
		
		
		if (freepikModalElement) {
			freepikModalElement.addEventListener('hidden.bs.modal', () => {
				resetFreepikModal();
			});
		}
	});
	
	
});
