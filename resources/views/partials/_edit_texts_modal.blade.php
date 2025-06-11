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

<div class="modal fade" id="questionGenerationModal" tabindex="-1" aria-labelledby="questionGenerationModalLabel"
     aria-hidden="true" data-bs-backdrop="static">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="questionGenerationModalLabel">Generate <span id="questionGenDifficulty"
				                                                                         class="text-capitalize"></span>
					Questions</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<form id="questionGenerationForm">
					<input type="hidden" id="questionGenUrl" value="">
					<div class="mb-3">
						<label for="questionGenInstructions" class="form-label">Question Generation Instructions</label>
						<textarea class="form-control" id="questionGenInstructions" rows="4"
						          placeholder="e.g., Create questions for a 10th-grade biology class. Focus on practical application rather than rote memorization. Keep the language clear and concise."></textarea>
						<small class="form-text text-muted">These instructions will be saved to your profile and used for future
							question generation.</small>
					</div>
				</form>
				<div id="questionGenError" class="alert alert-danger mt-3 d-none"></div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-primary" id="confirmQuestionGenerationBtn">
					<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
					Generate Questions
				</button>
			</div>
		</div>
	</div>
</div>
