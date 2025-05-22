document.addEventListener('DOMContentLoaded', () => {
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
