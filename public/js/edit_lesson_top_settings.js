document.addEventListener('DOMContentLoaded', () => {
	// --- Settings Selectors ---
	preferredLlmSelect = document.getElementById('preferredLlmSelect');
	ttsEngineSelect = document.getElementById('ttsEngineSelect');
	ttsVoiceSelect = document.getElementById('ttsVoiceSelect');
	ttsLanguageCodeSelect = document.getElementById('ttsLanguageCodeSelect');
	editSubCategorySelect = document.getElementById('editSubCategorySelect');
	editLanguageSelect = document.getElementById('editLanguageSelect');
	updateSettingsBtn = document.getElementById('updateLessonSettingsBtn');

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
	if (preferredLlmSelect) {
		// Load available LLMs via AJAX
		fetch(llmsListUrl) // Use variable defined in script tag
			.then(response => response.json())
			.then(data => {
				if (data.llms && Array.isArray(data.llms)) {
					const currentLlmValue = preferredLlmSelect.value; // Get the value set by Blade
					
					// Clear existing options except the first one (which shows current)
					while (preferredLlmSelect.options.length > 1) {
						preferredLlmSelect.remove(1);
					}
					
					// Rebuild options list
					data.llms.forEach(llm => {
						// Don't add the 'current' one again if it's in the list
						if (llm.id !== currentLlmValue) {
							const option = document.createElement('option');
							option.value = llm.id;
							option.textContent = `${llm.name}`; // Simpler text
							preferredLlmSelect.appendChild(option);
						}
					});
					
					// Ensure the first option text reflects the name correctly
					const currentOption = preferredLlmSelect.options[0];
					const matchingLlm = data.llms.find(llm => llm.id === currentLlmValue);
					if (currentOption && matchingLlm) {
						currentOption.textContent = `${matchingLlm.name}`; // Update display name
					} else if (currentOption) {
						currentOption.textContent = currentLlmValue; // Fallback to ID if name not found
					}
					
				}
			})
			.catch(error => {
				console.error('Error loading LLMs list:', error);
				// Optionally show an error to the user
			});
	}
	
	// --- Save Lesson Settings Button ---
	if (updateSettingsBtn) {
		updateSettingsBtn.addEventListener('click', function () {
			const selectedLlm = preferredLlmSelect.value;
			const selectedEngine = ttsEngineSelect.value;
			const selectedVoice = ttsVoiceSelect.value;
			const selectedLangCode = ttsLanguageCodeSelect.value;
			const selectedSubCategory = editSubCategorySelect.value;    // <-- Get Category ID
			const selectedLanguage = editLanguageSelect.value;    // <-- Get Lesson Language
			
			if (!selectedSubCategory || !selectedLanguage) {
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
					sub_category_id: selectedSubCategory,
					language: selectedLanguage
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
	
});
