<div class="modal fade" id="freepikSearchModal" tabindex="-1" aria-labelledby="freepikSearchModalLabel"
     aria-hidden="true" data-bs-backdrop="static">
	<div class="modal-dialog modal-xl modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="freepikSearchModalLabel">Search Freepik for Question Image</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<input type="hidden" id="freepikModalQuestionId" value="{{$lesson->id}}">
				<input type="hidden" id="freepikModalPartIndex" value="">
				<input type="hidden" id="freepikModalSentenceIndex" value="">
				<input type="hidden" id="freepikModalContext" value="question">
				<div class="input-group mb-3">
					<input type="text" id="freepikSearchQuery" class="form-control"
					       placeholder="Enter search term (e.g., 'science experiment', 'cat studying')">
					<button class="btn btn-primary" type="button" id="freepikSearchExecuteBtn">
						<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
						<i class="fas fa-search"></i> Search
					</button>
				</div>
				<div id="freepikSearchError" class="alert alert-danger d-none" role="alert"></div>
				
				<div id="freepikSearchResults" class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3"
				     style="min-height: 200px;">
					<div class="col-12 text-center text-muted d-none" id="freepikSearchPlaceholder">
						Enter a search term above to find images.
					</div>
					<div class="col-12 text-center d-none" id="freepikSearchLoading">
						<div class="spinner-border text-primary" role="status"></div>
						<p>Loading images...</p>
					</div>
					<div class="col-12 text-center text-muted d-none" id="freepikSearchNoResults">
						No images found for that search term.
					</div>
				</div>
				
				<nav aria-label="Freepik Search Pagination" class="mt-3 d-none" id="freepikPaginationContainer">
					<ul class="pagination justify-content-center" id="freepikPagination">
					</ul>
				</nav>
			
			</div>
			<div class="modal-footer">
				<small class="text-muted me-auto">Image search powered by Freepik. Ensure compliance with Freepik's
					terms.</small>
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>
