document.addEventListener('DOMContentLoaded', () => {
	// --- Settings Selectors ---
	preferredLlmSelect = document.getElementById('preferredLlmSelect');
	ttsEngineSelect = document.getElementById('ttsEngineSelect');
	ttsVoiceSelect = document.getElementById('ttsVoiceSelect');
	ttsLanguageCodeSelect = document.getElementById('ttsLanguageCodeSelect');
	
	editMainCategorySelect = document.getElementById('editMainCategorySelect');
	editSubCategorySelect = document.getElementById('editSubCategorySelect');
	
	editLanguageSelect = document.getElementById('editLanguageSelect');
	updateSettingsBtn = document.getElementById('updateLessonSettingsBtn');
	
	// --- Data ---
	let allCategoriesData = {};
	try {
		// Use the data attribute passed from Blade
		allCategoriesData = JSON.parse(lessonSettingsCard.dataset.categories || '{}');
	} catch (e) {
		console.error("Error parsing categories data:", e);
		showToast('Error loading category data.', 'Error', 'error');
	}


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
	iif (preferredLlmSelect) {
		// Load available LLMs via AJAX
		fetch(llmsListUrl) // Use variable defined in script tag
			.then(response => response.json())
			.then(data => {
				if (data.llms && Array.isArray(data.llms)) {
					const currentLlmValue = preferredLlmSelect.value; // Get the value set by Blade
					let currentLlmExists = false;
					
					// Check if current value exists in the available LLMs
					for (const llm of data.llms) {
						if (llm.id === currentLlmValue) {
							currentLlmExists = true;
							break;
						}
					}
					
					// Clear existing options
					preferredLlmSelect.innerHTML = '';
					
					// Add all available LLMs to dropdown
					data.llms.forEach(llm => {
						const option = document.createElement('option');
						option.value = llm.id;
						option.textContent = llm.name;
						preferredLlmSelect.appendChild(option);
					});
					
					// Set the selected option
					if (currentLlmExists) {
						// If the stored LLM exists, select it
						preferredLlmSelect.value = currentLlmValue;
					} else if (data.llms.length > 0) {
						// If stored LLM doesn't exist, select the first available one
						// (which is typically the default)
						preferredLlmSelect.value = data.llms[0].id;
						console.warn(`Selected LLM '${currentLlmValue}' not found in available models. Defaulting to '${data.llms[0].id}'.`);
						showToast(`Selected AI model was no longer available. Defaulted to ${data.llms[0].name}.`, 'Note', 'info');
					}
				}
			})
			.catch(error => {
				console.error('Error loading LLMs list:', error);
				showToast('Error loading AI models.', 'Error', 'error');
			});
	}
	
	// --- Save Lesson Settings Button ---
	if (updateSettingsBtn) {
		updateSettingsBtn.addEventListener('click', function () {
			const selectedLlm = preferredLlmSelect.value;
			const selectedEngine = ttsEngineSelect.value;
			const selectedVoice = ttsVoiceSelect.value;
			const selectedLangCode = ttsLanguageCodeSelect.value;
			const selectedMainCategory = editMainCategorySelect.value;    // <-- Get Main Category ID
			const selectedSubCategory = editSubCategorySelect.value;    // <-- Get Category ID
			const selectedLanguage = editLanguageSelect.value;    // <-- Get Lesson Language
			
			const userTitleInput = document.getElementById('editUserTitle');
			const subjectInput = document.getElementById('editSubject');
			const notesInput = document.getElementById('editNotes');
			const monthInput = document.getElementById('editMonth');
			const yearInput = document.getElementById('editYear');
			const weekInput = document.getElementById('editWeek');
			
			if (!selectedMainCategory || !selectedLanguage) {
				showToast('Please select a sub-category and language.', 'Missing Selection', 'warning');
				return;
			}
			
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
					tts_language_code: selectedLangCode,
					main_category_id: selectedMainCategory,
					sub_category_id: selectedSubCategory,
					language: selectedLanguage,
					
					user_title: userTitleInput ? userTitleInput.value : null,
					subject: subjectInput ? subjectInput.value : null,
					notes: notesInput ? notesInput.value : null,
					month: monthInput ? (monthInput.value || null) : null,
					year: yearInput ? (yearInput.value || null) : null,
					week: weekInput ? (weekInput.value || null) : null,
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
	
	function populateSubCategories() {
		const selectedMainId = editMainCategorySelect.value;
		// Clear existing options (keep the first '-- None --' option)
		while (editSubCategorySelect.options.length > 1) {
			editSubCategorySelect.remove(1);
		}
		
		if (selectedMainId && allCategoriesData[selectedMainId] && allCategoriesData[selectedMainId].subCategories) {
			editSubCategorySelect.disabled = false;
			const subCats = allCategoriesData[selectedMainId].subCategories;
			// Check if subCats is an object (from mapWithKeys) or array
			const subCatKeys = Object.keys(subCats);
			
			if (subCatKeys.length > 0) {
				subCatKeys.forEach(subId => {
					const subCat = subCats[subId];
					const option = new Option(subCat.name, subCat.id);
					editSubCategorySelect.add(option);
				});
			} else {
				// Optionally add a disabled message if no sub-categories exist
				// const option = new Option("No sub-categories", "");
				// option.disabled = true;
				// editSubCategorySelect.add(option);
			}
			
			// Try to re-select the initial sub-category if it belongs to this main category
			if (initialSelectedSubCategoryId && subCats[initialSelectedSubCategoryId]) {
				editSubCategorySelect.value = initialSelectedSubCategoryId;
			} else {
				editSubCategorySelect.value = ""; // Default to '-- None --'
			}
			
		} else {
			editSubCategorySelect.disabled = true;
			editSubCategorySelect.value = ""; // Reset to '-- None --'
		}
	}
	
	if (editMainCategorySelect) {
		editMainCategorySelect.addEventListener('change', populateSubCategories);
		populateSubCategories();
	}
	
	
});
