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

	const freepikModalContextInput = document.getElementById('freepikModalContext');
	const freepikModalPartIndexInput = document.getElementById('freepikModalPartIndex');
	const freepikModalSentenceIndexInput = document.getElementById('freepikModalSentenceIndex');
	
	
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
		
		if (freepikModalContextInput) freepikModalContextInput.value = 'question'; // Default context
		if (freepikModalPartIndexInput) freepikModalPartIndexInput.value = '';
		if (freepikModalSentenceIndexInput) freepikModalSentenceIndexInput.value = '';
		
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
	
	async function performFreepikSearch(context, query, page = 1, questionId = null, partIndex = null, sentenceIndex = null) {
		hideFreepikError();
		if (freepikSearchResultsContainer) freepikSearchResultsContainer.innerHTML = ''; // Clear previous results
		if (freepikSearchPlaceholder) freepikSearchPlaceholder.classList.add('d-none');
		if (freepikSearchLoading) freepikSearchLoading.classList.remove('d-none'); // Show loading
		if (freepikSearchNoResults) freepikSearchNoResults.classList.add('d-none');
		if (freepikPaginationContainer) freepikPaginationContainer.classList.add('d-none');
		showSpinner(freepikSearchExecuteBtn, true);
		
		let url;
		if (context === 'sentence' && lessonId && partIndex !== null && sentenceIndex !== null) { // lessonId from global scope
			url = `/lesson/${lessonId}/part/${partIndex}/sentence/${sentenceIndex}/search-freepik`;
		} else if (context === 'question' && questionId) {
			url = `/question/${questionId}/search-freepik`; // Existing URL
		} else {
			showFreepikError('Invalid context for search.');
			showSpinner(freepikSearchExecuteBtn, false);
			if (freepikSearchLoading) freepikSearchLoading.classList.add('d-none');
			return;
		}
		
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
			displayFreepikPagination(result.pagination || null, query, context, questionId, partIndex, sentenceIndex);
			
			
		} catch (error) {
			if (freepikSearchLoading) freepikSearchLoading.classList.add('d-none');
			console.error("Freepik search error:", error);
			showFreepikError(`Search Failed: ${error.message}`);
		} finally {
			showSpinner(freepikSearchExecuteBtn, false);
			if (freepikSearchLoading) freepikSearchLoading.classList.add('d-none');
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
	function displayFreepikPagination(pagination, query, context, questionId, partIndex, sentenceIndex) {
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
		
		// Helper to create link attributes
		const createPageLinkAttributes = (pageNumber) => {
			let attrs = `href="#" class="page-link freepik-page-link" data-page="${pageNumber}" data-query="${escapeHtml(query)}" data-context="${context}"`;
			if (context === 'question' && questionId) attrs += ` data-question-id="${questionId}"`;
			if (context === 'sentence' && partIndex !== null && sentenceIndex !== null) {
				attrs += ` data-part-index="${partIndex}" data-sentence-index="${sentenceIndex}"`;
			}
			return attrs;
		};
		
		// Previous Button
		const prevLi = document.createElement('li');
		prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
		prevLi.innerHTML = `<a ${createPageLinkAttributes(currentPage - 1)} aria-label="Previous"><span aria-hidden="true">«</span></a>`;
		freepikPaginationUl.appendChild(prevLi);
		
		// First Page and Ellipsis (if needed)
		if (startPage > 1) {
			const firstLi = document.createElement('li');
			firstLi.className = 'page-item';
			firstLi.innerHTML = `<a ${createPageLinkAttributes(1)}>1</a>`;
			freepikPaginationUl.appendChild(firstLi);
			// ... ellipsis ...
		}
		
		// Page Number Links
		for (let i = startPage; i <= endPage; i++) {
			const pageLi = document.createElement('li');
			pageLi.className = `page-item ${i === currentPage ? 'active' : ''}`;
			pageLi.innerHTML = `<a ${createPageLinkAttributes(i)}>${i}</a>`;
			freepikPaginationUl.appendChild(pageLi);
		}
		
		// Last Page and Ellipsis (if needed)
		if (endPage < totalPages) {
			// ... ellipsis ...
			const lastLi = document.createElement('li');
			lastLi.className = 'page-item';
			lastLi.innerHTML = `<a ${createPageLinkAttributes(totalPages)}>${totalPages}</a>`;
			freepikPaginationUl.appendChild(lastLi);
		}
		
		// Next Button
		const nextLi = document.createElement('li');
		nextLi.className = `page-item ${currentPage === totalPages ? 'disabled' : ''}`;
		nextLi.innerHTML = `<a ${createPageLinkAttributes(currentPage + 1)} aria-label="Next"><span aria-hidden="true">»</span></a>`;
		freepikPaginationUl.appendChild(nextLi);
		
		freepikPaginationContainer.classList.remove('d-none'); // Show pagination
	}
	
	
	async function selectFreepikImageAction(context, freepikId, description, imgUrl, questionId = null, partIndex = null, sentenceIndex = null) {
		let url, errorAreaId, successAreaId;
		
		if (context === 'sentence' && lessonId && partIndex !== null && sentenceIndex !== null) {
			url = `/lesson/${lessonId}/part/${partIndex}/sentence/${sentenceIndex}/select-freepik`;
			errorAreaId = `sent-image-error-p${partIndex}-s${sentenceIndex}`;
			successAreaId = `sent-image-success-p${partIndex}-s${sentenceIndex}`;
		} else if (context === 'question' && questionId) {
			url = `/question/${questionId}/select-freepik`;
			errorAreaId = `q-image-error-${questionId}`;
			successAreaId = `q-image-success-${questionId}`;
		} else {
			showFreepikError('Invalid context for image selection.');
			return;
		}
		
		hideError(errorAreaId); // Hide error in the main page area
		// hideSuccess(successAreaId); // Hide success in the main page area
		
		console.log(`Selecting Freepik image ${freepikId} for ${context}...`);
		
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
			
			// Success: Update the correct display
			if (context === 'sentence') {
				updateSentenceImageDisplay(partIndex, sentenceIndex, result.image_urls, result.prompt, result.image_id, 'Freepik image selected!');
			} else {
				updateQuestionImageDisplay(questionId, result.image_urls, result.prompt, 'Freepik image selected!');
			}
			freepikModal.hide(); // Close modal on success
			
		} catch (error) {
			console.error(`Error selecting Freepik image ${freepikId} for ${context}:`, error);
			showError(errorAreaId, `Selection Failed: ${error.message}`); // Show error on main page
			showFreepikError(`Selection Failed: ${error.message}`); // Show error in modal too
			// Remove loading indicator from image in modal
			// ... (existing loading removal logic) ...
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
		
		// --- Search Freepik for SENTENCE ---
		const searchFreepikSentenceBtn = event.target.closest('.search-freepik-sentence-btn');
		if (searchFreepikSentenceBtn) {
			resetFreepikModal(); // Clear previous search
			const sentenceItem = searchFreepikSentenceBtn.closest('.sentence-item');
			const partIndex = sentenceItem.dataset.partIndex;
			const sentenceIndex = sentenceItem.dataset.sentenceIndex;
			const keywordsInputId = searchFreepikSentenceBtn.dataset.keywordsInputId;
			const keywordsInput = document.getElementById(keywordsInputId);
			const currentKeywords = keywordsInput ? keywordsInput.value.trim() : '';
			
			// Set context and indices in the modal
			document.getElementById('freepikModalContext').value = 'sentence';
			document.getElementById('freepikModalPartIndex').value = partIndex;
			document.getElementById('freepikModalSentenceIndex').value = sentenceIndex;
			document.getElementById('freepikModalQuestionId').value = ''; // Clear question ID
			
			// Pre-fill search query
			if (freepikSearchQueryInput && currentKeywords) {
				freepikSearchQueryInput.value = currentKeywords;
			}
			
			// Modal is opened via data-bs-toggle/target attributes
			return;
		}

// --- NEW: Freepik Search Execute Button (inside modal) ---
		if (freepikSearchExecuteBtn && freepikSearchExecuteBtn.contains(event.target)) {
			const context = freepikModalContextInput.value;
			const query = freepikSearchQueryInput.value.trim();
			const questionId = freepikModalQuestionIdInput.value; // Might be empty
			const partIndex = freepikModalPartIndexInput.value; // Might be empty
			const sentenceIndex = freepikModalSentenceIndexInput.value; // Might be empty
			
			if (query && context) {
				performFreepikSearch(context, query, 1, questionId || null, partIndex || null, sentenceIndex || null);
			} else {
				showFreepikError("Please enter a search term.");
			}
			return;
		}

// --- NEW: Freepik Image Selection (inside modal results) ---
		const selectFreepikImage = event.target.closest('.freepik-result-image');
		if (selectFreepikImage) {
			const context = freepikModalContextInput.value;
			const freepikId = selectFreepikImage.dataset.freepikId;
			const description = selectFreepikImage.dataset.description;
			const imageUrl = selectFreepikImage.src;
			const questionId = freepikModalQuestionIdInput.value || null;
			const partIndex = freepikModalPartIndexInput.value || null;
			const sentenceIndex = freepikModalSentenceIndexInput.value || null;
			
			if (freepikId && context) {
				// Add visual confirmation / loading state to the clicked image
				selectFreepikImage.classList.add('border', 'border-primary', 'border-3'); // Highlight selected
				const loadingDiv = document.createElement('div');
				loadingDiv.innerHTML = `<div class="spinner-border spinner-border-sm text-primary" role="status"></div> Selecting...`;
				loadingDiv.classList.add('position-absolute', 'top-50', 'start-50', 'translate-middle', 'bg-light', 'p-1', 'rounded', 'opacity-75');
				selectFreepikImage.parentElement.appendChild(loadingDiv); // Append loading to container
				
				// Disable further clicks in modal? Optional
				setFreepikModalInteractable(false);
				
				await selectFreepikImageAction(context, freepikId, description, imageUrl, questionId, partIndex, sentenceIndex);
				
				// Re-enable modal interaction on completion (success or error handled in selectFreepikImageAction)
				setFreepikModalInteractable(true);
				
				
			}
			return;
		}

// --- NEW: Freepik Pagination Click ---
		const paginationLink = event.target.closest('.freepik-page-link');
		if (paginationLink && !paginationLink.parentElement.classList.contains('disabled') && !paginationLink.parentElement.classList.contains('active')) {
			event.preventDefault();
			const context = paginationLink.dataset.context;
			const query = paginationLink.dataset.query;
			const page = parseInt(paginationLink.dataset.page);
			const questionId = paginationLink.dataset.questionId || null;
			const partIndex = paginationLink.dataset.partIndex || null;
			const sentenceIndex = paginationLink.dataset.sentenceIndex || null;
			
			
			if (context && query && page) {
				performFreepikSearch(context, query, page, questionId, partIndex, sentenceIndex);
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
