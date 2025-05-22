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
						<label for="editContentText" class="form-label">Content Text</label>
						<textarea class="form-control" id="editContentText" rows="8"></textarea>
						<div class="invalid-feedback">Either Content text or Video Transcription is required.</div>
					</div>
					
					<div class="mb-3 d-none" id="videoTranscriptionGroup">
						<label for="editContentVideoTranscription" class="form-label">Video Transcription</label>
						<textarea class="form-control" id="editContentVideoTranscription" rows="5" placeholder="Edit video transcription here..."></textarea>
						<small class="form-text text-muted">This will update the subtitles text associated with the lesson's YouTube video.</small>
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

<div class="modal fade" id="addVideoModal" tabindex="-1" aria-labelledby="addVideoModalLabel" aria-hidden="true" data-bs-backdrop="static">
	<div class="modal-dialog">
		<div class="modal-content">
			<form id="addVideoForm">
				<div class="modal-header">
					<h5 class="modal-title" id="addVideoModalLabel">Add YouTube Video to Lesson</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<input type="hidden" id="lessonIdForVideo" name="lesson_id" value="">
					<p>Lesson: <strong id="lessonTitleForVideo"></strong></p>
					<div class="mb-3">
						<label for="youtubeVideoIdInputModal" class="form-label">YouTube Video ID or URL:</label>
						<input type="text" class="form-control" id="youtubeVideoIdInputModal" name="youtube_video_id"
						       placeholder="e.g., 6dWBGfH55RM or https://www.youtube.com/watch?v=6dWBGfH55RM" required>
						<small class="form-text text-muted">Enter the unique ID from the YouTube video URL.</small>
					</div>
					<div id="addVideoError" class="alert alert-danger d-none mt-3"></div>
					<div id="addVideoProgress" class="text-primary d-none mt-3">
						<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
						Processing... This might take a minute or two depending on video size.
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary" id="submitVideoButton">
						<span id="submitVideoSpinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
						<i class="fas fa-download"></i> Add Video & Captions
					</button>
				</div>
			</form>
		</div>
	</div>
</div>


<div class="modal fade" id="generateContentModal" tabindex="-1" aria-labelledby="generateContentModalLabel"
     aria-hidden="true" data-bs-backdrop="static">
	<div class="modal-dialog modal-lg modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="generateContentModalLabel">Generate Lesson Content</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<div id="generationOptionsArea">
					<input type="hidden" id="lessonIdForGeneration" value="">
					<input type="hidden" id="currentSubCategoryId" value="">
					<input type="hidden" id="currentSelectedMainCategoryId" value="">
					<input type="hidden" id="generationSourceInput" name="generation_source" value="subject">
					<input type="hidden" id="videoSubtitlesBase64" value="">
					
					
					<div id="generationSourceGroup" class="mb-3 border rounded p-2 d-none">
						<label class="form-label fw-bold">Generation Source:</label>
						<div class="form-check">
							<input class="form-check-input" type="radio" name="generationSource" id="sourceSubject" value="subject"
							       checked>
							<label class="form-check-label" for="sourceSubject">
								Use Subject & Notes
							</label>
						</div>
						<div class="form-check">
							<input class="form-check-input" type="radio" name="generationSource" id="sourceVideo" value="video">
							<label class="form-check-label" for="sourceVideo">
								Use Video Subtitles
							</label>
						</div>
					</div>
					
					<div id="videoSubtitlesDisplayArea" class="mb-3 d-none">
						<label for="videoSubtitlesTextarea" class="form-label">Video Subtitles (for context):</label>
						<textarea class="form-control" id="videoSubtitlesTextarea" rows="6" readonly
						          style="font-size: 0.8rem; background-color: var(--bs-secondary-bg);"></textarea>
					</div>
					
					<div class="mb-3">
						<label for="lessonTitleDisplay" class="form-label">Lesson Title:</label>
						<input type="text" class="form-control" id="lessonTitleDisplay">
					</div>
					<div class="mb-3">
						<label for="lessonSubjectDisplay" class="form-label">Lesson Subject:</label>
						<textarea type="text" class="form-control" id="lessonSubjectDisplay"></textarea>
					</div>
					<div class="mb-3">
						<label for="lessonNotesDisplay" class="form-label">Additional Notes:</label>
						<textarea class="form-control" id="lessonNotesDisplay" rows="2"></textarea>
					</div>
					<div class="mb-3">
						<label for="additionalInstructionsTextarea" class="form-label">Additional Instructions (for AI):</label>
						<textarea class="form-control" id="additionalInstructionsTextarea" rows="3"
						          placeholder="e.g., Focus on practical examples, keep the tone informal..."></textarea>
						<small class="form-text text-muted">These instructions will be added to the prompt sent to the AI for
							content generation. They are saved to your profile when you generate a preview.</small>
					</div>
					
					<div id="existingCategoryDisplayArea" class="mb-3 p-2 border rounded d-none">
						<p class="mb-1"><strong>Current Category:</strong></p>
						<p class="mb-0">
							Main: <span id="existingMainCategoryName" class="fw-bold"></span><br>
							Sub: <span id="existingSubCategoryName" class="fw-bold"></span>
						</p>
						<small class="text-muted" id="existingCategoryNote">Content will be generated for this category.</small>
					</div>
					
					<div id="autoDetectCheckboxArea" class="mb-3 form-check">
						<input type="checkbox" class="form-check-input" id="autoDetectCategoryCheck" checked>
						<label class="form-check-label" for="autoDetectCategoryCheck">
							Auto-detect category based on content
						</label>
					</div>
					
					<div class="mb-3">
						<label for="aiModelSelect" class="form-label">AI Model for Generation:</label>
						<select class="form-select" id="aiModelSelect" required>
							@php $defaultLlmIdModal = env('DEFAULT_LLM', ''); @endphp
							@forelse ($llms ?? [] as $llm_modal_option)
								<option
									value="{{ $llm_modal_option['id'] }}" {{ $llm_modal_option['id'] === $defaultLlmIdModal ? 'selected' : '' }}>
									{{ $llm_modal_option['name'] }}
								</option>
							@empty
								<option value="" disabled>No AI models available</option>
							@endforelse
						</select>
					</div>
				</div>
				<div id="previewContentArea" class="d-none">
					<div id="lessonPreviewBody">
						{{-- Preview content will be loaded here by JS --}}
						<div class="text-center">
							<div class="spinner-border text-primary" role="status">
								<span class="visually-hidden">Loading preview...</span>
							</div>
							<p class="mt-2">Generating preview...</p>
						</div>
					</div>
					<div id="modalCategorySuggestionArea" class="mt-3 p-2 border rounded d-none">
						<h6>AI Suggested Category:</h6>
						<p class="mb-1">
							Main: <span id="suggestedMainCategoryText" class="badge bg-danger text-dark"></span> <br>
							Sub: <span id="suggestedSubCategoryText" class="badge bg-light text-dark"></span>
						</p>
						<small class="text-muted">These categories will be created if they don't exist.</small>
					</div>
				</div>
			</div>
			<div class="modal-footer">
				<div id="generationErrorMessage" class="text-danger me-auto d-none"></div>
				<span id="applyGenerationSpinner" class="me-auto d-none">
                        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Applying Content...
                    </span>
				<button id="generatePreviewButton" class="btn btn-primary">
						<span id="generatePreviewSpinner" class="spinner-border spinner-border-sm d-none" role="status"
						      aria-hidden="true"></span>
					Generate Content Preview
				</button>
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="cancelGenerationButton">
					Cancel
				</button>
				<button type="button" class="btn btn-primary d-none" id="backToOptionsButton">
					<i class="fas fa-arrow-left"></i> Back to Options
				</button>
				<button type="button" class="btn btn-success d-none" id="applyGenerationButton">
					Apply Content <i class="fas fa-check"></i>
				</button>
			</div>
		</div>
	</div>
</div>


