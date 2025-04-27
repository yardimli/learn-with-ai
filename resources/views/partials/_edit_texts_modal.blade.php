<div class="modal fade" id="editTextsModal" tabindex="-1" aria-labelledby="editTextsModalLabel" aria-hidden="true"
     data-bs-backdrop="static">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="editTextsModalLabel">Edit Question Texts</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<form id="editTextsForm">
					<input type="hidden" id="editQuestionId" value="">
					
					<div class="mb-3">
						<label for="editQuestionText" class="form-label">Question Text</label>
						<textarea class="form-control" id="editQuestionText" rows="3" required></textarea>
						<div class="invalid-feedback">Question text is required (minimum 5 characters)</div>
					</div>
					
					<div id="editAnswersContainer">
						<!-- Answers will be dynamically populated by JS -->
					</div>
				</form>
				
				<div id="editTextsError" class="alert alert-danger mt-3 d-none"></div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-primary" id="saveTextsBtn">
					<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
					Save Changes
				</button>
			</div>
		</div>
	</div>
</div>
