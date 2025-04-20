let lessonForm = null;
let createButton = null;
let createSpinner = null;
let lessonSubject = null;
let ttsEngineSelect = null;
let ttsVoiceSelect = null;
let errorMessageArea = null;
let errorMessageText = null;

// Category Selection Logic
let categorySelectionMode = null;
let mainCategoryArea = null;
let subCategoryArea = null;
let mainCategorySelect = null;
let subCategorySelect = null;


function updateCategoryAreas() {
	const mode = categorySelectionMode.value;
	
	// Reset and hide all areas first
	mainCategoryArea.classList.add('d-none');
	subCategoryArea.classList.add('d-none');
	mainCategorySelect.disabled = true;
	subCategorySelect.disabled = true;
	
	// Enable appropriate areas based on selection mode
	if (mode === 'main_only') {
		mainCategoryArea.classList.remove('d-none');
		mainCategorySelect.disabled = false;
		
		// Clear sub-category selection
		subCategorySelect.innerHTML = '<option value="" selected disabled>Select a sub-category</option>';
	} else if (mode === 'both') {
		mainCategoryArea.classList.remove('d-none');
		subCategoryArea.classList.remove('d-none');
		mainCategorySelect.disabled = false;
		subCategorySelect.disabled = false;
		
		// Populate sub-categories if main is selected
		if (mainCategorySelect.value) {
			populateSubCategories();
		}
	}
}

function populateSubCategories() {
	const mainCategoryId = mainCategorySelect.value;
	if (!mainCategoryId) return;
	
	// Clear existing options
	subCategorySelect.innerHTML = '<option value="" selected disabled>Select a sub-category</option>';
	
	// Find the main category data element with matching ID
	const mainCategoryData = document.querySelector(`.main-category-data[data-main-id="${mainCategoryId}"]`);
	if (!mainCategoryData) return;
	
	// Get all sub-categories for this main category
	const subCategoryElements = mainCategoryData.querySelectorAll('.sub-category-data');
	
	// Create an option for each sub-category
	subCategoryElements.forEach(subElement => {
		const subId = subElement.dataset.subId;
		const subName = subElement.dataset.subName;
		
		const option = document.createElement('option');
		option.value = subId;
		option.textContent = subName;
		subCategorySelect.appendChild(option);
	});
}

document.addEventListener('DOMContentLoaded', () => {
	 lessonForm = document.getElementById('lessonForm');
	 createButton = document.getElementById('createBasicLessonButton');
	 createSpinner = document.getElementById('createBasicLessonSpinner');
	 lessonSubject = document.getElementById('lessonSubject');
	 ttsEngineSelect = document.getElementById('ttsEngineSelect');
	 ttsVoiceSelect = document.getElementById('ttsVoiceSelect');
	 errorMessageArea = document.getElementById('errorMessageArea');
	 errorMessageText = document.getElementById('errorMessageText');
	
	// Category Selection Logic
	 categorySelectionMode = document.getElementById('categorySelectionMode');
	 mainCategoryArea = document.getElementById('mainCategoryArea');
	 subCategoryArea = document.getElementById('subCategoryArea');
	 mainCategorySelect = document.getElementById('mainCategorySelect');
	 subCategorySelect = document.getElementById('subCategorySelect');
	
	
	// Initial button state
	createButton.disabled = !lessonSubject.value.trim();
	
	lessonSubject.addEventListener('input', () => {
		createButton.disabled = !lessonSubject.value.trim();
	});
	
	// Voice Selector Logic
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
						firstVisibleOption = options[0];
					}
				}
			});
			
			const selectedOption = ttsVoiceSelect.options[ttsVoiceSelect.selectedIndex];
			if (selectedOption && selectedOption.parentElement.style.display === 'none' && firstVisibleOption) {
				firstVisibleOption.selected = true;
			} else if (!selectedOption && firstVisibleOption) {
				firstVisibleOption.selected = true;
			}
		}
		
		ttsEngineSelect.addEventListener('change', updateVoiceOptions);
		updateVoiceOptions();
	}
	
	if (categorySelectionMode) {
		categorySelectionMode.addEventListener('change', updateCategoryAreas);
		
		// Initial state
		updateCategoryAreas();
		
		// Main category change - populate sub-categories
		if (mainCategorySelect) {
			mainCategorySelect.addEventListener('change', populateSubCategories);
		}
	}
	
	// Form Submission
	lessonForm.addEventListener('submit', async (event) => {
		event.preventDefault();
		
		createButton.disabled = true;
		createSpinner.classList.remove('d-none');
		
		const formData = new FormData(lessonForm);
		
		try {
			const response = await fetch(lessonForm.getAttribute('action'), {
				method: 'POST',
				headers: {
					'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
					'Accept': 'application/json',
					'Content-Type': 'application/json'
				},
				body: JSON.stringify(Object.fromEntries(formData))
			});
			
			const result = await response.json();
			
			if (!response.ok || !result.success) {
				throw new Error(result.message || `HTTP error! status: ${response.status}`);
			}
			
			// Success: Redirect to lesson list
			if (result.redirectUrl) {
				window.location.href = result.redirectUrl;
			} else {
				showToast('Lesson created! You can now generate content.', 'Success', 'success');
				// Fallback if no redirect URL
				window.location.href = '{{ route("lessons.list") }}';
			}
			
		} catch (error) {
			console.error("Error creating lesson:", error);
			showToast(`Failed to create lesson: ${error.message}`, 'Error', 'error');
			createButton.disabled = false;
			createSpinner.classList.add('d-none');
		}
	});
});
