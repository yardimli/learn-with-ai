<div class="modal fade" id="editContentModal" tabindex="-1" aria-labelledby="editContentModalLabel" aria-hidden="true"
     data-bs-backdrop="static">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="editContentModalLabel">Edit Lesson Content</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<form id="editContentForm">
					<div class="mb-3">
						<label for="editContentTitle" class="form-label">Content Title</label> {{-- Was editPartTitle --}}
						<input type="text" class="form-control" id="editContentTitle" required>
						<div class="invalid-feedback">Content title is required.</div>
					</div>
					<div class="mb-3">
						<label for="editContentText" class="form-label">Content Text</label>
						<textarea class="form-control" id="editContentText" rows="8" required></textarea>
						<div class="invalid-feedback">Content text is required (minimum 10 characters)</div>
					</div>
				</form>
				<div id="editContentError" class="alert alert-danger mt-3 d-none"></div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-primary" id="saveContentBtn"> {{-- Was savePartBtn --}}
					<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
					Save Changes
				</button>
			</div>
		</div>
	</div>
</div>
