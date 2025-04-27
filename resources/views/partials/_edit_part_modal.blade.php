<div class="modal fade" id="editPartModal" tabindex="-1" aria-labelledby="editPartModalLabel" aria-hidden="true"
     data-bs-backdrop="static">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="editPartModalLabel">Edit Lesson Part</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<form id="editPartForm">
					<input type="hidden" id="editPartIndex" value="">
					<div class="mb-3">
						<label for="editPartTitle" class="form-label">Part Title</label>
						<input type="text" class="form-control" id="editPartTitle" required>
						<div class="invalid-feedback">Part title is required.</div>
					</div>
					<div class="mb-3">
						<label for="editPartText" class="form-label">Part Text</label>
						<textarea class="form-control" id="editPartText" rows="8" required></textarea>
						<div class="invalid-feedback">Part text is required (minimum 10 characters)</div>
					</div>
				</form>
				<div id="editPartError" class="alert alert-danger mt-3 d-none"></div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-primary" id="savePartBtn">
					<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
					Save Changes
				</button>
			</div>
		</div>
	</div>
</div>
