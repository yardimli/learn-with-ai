document.addEventListener('DOMContentLoaded', () => {
	// --- Delete Lesson Button Handler ---
	document.querySelectorAll('.delete-lesson-btn').forEach(button => {
		button.addEventListener('click', function () {
			const lessonId = this.dataset.lessonId;
			const deleteUrl = this.dataset.deleteUrl;
			const lessonTitle = this.dataset.lessonTitle;
			
			if (confirm(`Are you sure you want to delete the lesson "${lessonTitle}" and all its associated questions and progress? This action cannot be undone.`)) {
				// Use Fetch API for AJAX delete
				fetch(deleteUrl, {
					method: 'DELETE',
					headers: {
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
						'Accept': 'application/json',
					}
				})
					.then(response => response.json())
					.then(data => {
						if (data.success) {
							showToast(data.message || 'Lesson deleted successfully.', 'Success', 'success');
							// Remove the list item from the DOM
							const listItem = this.closest('.list-group-item');
							if (listItem) {
								listItem.remove();
								// Optionally check if the parent accordion body/collapse is now empty and update counts/UI
							} else {
								window.location.reload(); // Fallback refresh
							}
						} else {
							showToast(data.message || 'Failed to delete lesson.', 'Error', 'error');
						}
					})
					.catch(error => {
						console.error('Error deleting lesson:', error);
						showToast('An error occurred while deleting the lesson.', 'Error', 'error');
					});
				
				// If using the hidden form as fallback (not recommended with the fetch approach)
				// const formId = `delete-form-${lessonId}`;
				// const form = document.getElementById(formId);
				// if (form) {
				//     form.submit();
				// }
			}
		});
	});
	
	// --- Archive Progress Button Handler ---
	document.querySelectorAll('.archive-progress-btn').forEach(button => {
		button.addEventListener('click', function () {
			const archiveUrl = this.dataset.archiveUrl;
			const lessonId = this.dataset.lessonId; // For potential UI updates
			
			if (confirm('Are you sure you want to archive the current progress for this lesson? This will reset the progress tracking.')) {
				fetch(archiveUrl, {
					method: 'POST',
					headers: {
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
						'Accept': 'application/json',
					}
				})
					.then(response => response.json())
					.then(data => {
						if (data.success) {
							showToast(data.message || 'Progress archived successfully.', 'Success', 'success');
							// Reload the page to show the reset progress bar
							window.location.reload();
						} else {
							showToast(data.message || 'Failed to archive progress.', 'Error', 'error');
						}
					})
					.catch(error => {
						console.error('Error archiving progress:', error);
						showToast('An error occurred while archiving progress.', 'Error', 'error');
					});
			}
		});
	});
	
	
	
}); // End DOMContentLoaded
