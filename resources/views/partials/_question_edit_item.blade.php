{{-- resources/views/partials/_question_edit_item.blade.php --}}
{{-- Expects $question object, or null if used as template --}}
<div class="question-item" data-question-id="{{ $question->id ?? 'TEMPLATE_QUESTION_ID' }}" id="question-item-{{ $question->id ?? 'TEMPLATE_QUESTION_ID' }}">
	@php
		// Use optional chaining and null coalescing for template safety
		$questionId = $question->id ?? 'TEMPLATE_QUESTION_ID';
		$subjectId = $question->subject_id ?? 'TEMPLATE_SUBJECT_ID';
		$partIndex = $question->lesson_part_index ?? 'TEMPLATE_PART_INDEX';
		$image = $question->generatedImage ?? null;
		$prompt = $question->image_prompt_idea ?? '';
		$image_search_keywords = $question->image_search_keywords ?? '';
		$questionText = $question->question_text ?? 'TEMPLATE QUESTION TEXT';
		$questionAudioUrl = $question->question_audio_url ?? null; // Accessor handles existence check
		$answers = $question->answers ?? [];

		// Check if answer/feedback audio needs generation (check first answer)
		$answersNeedAudio = true; // Default to needing it if no answers or no audio path
		if (!empty($answers)) {
				$firstAnswer = $answers[0];
				// Check if BOTH paths/URLs exist and are non-empty for the first answer
				if ((!empty($firstAnswer['answer_audio_path']) || !empty($firstAnswer['answer_audio_url'])) &&
						(!empty($firstAnswer['feedback_audio_path']) || !empty($firstAnswer['feedback_audio_url'])) ) {
						 // We could add a file existence check here, but it's slow. Rely on generation logic.
						 $answersNeedAudio = false;
				}
		}
	@endphp
	
	<div class="d-flex justify-content-end mb-2">
		<div class="me-auto small text-muted">Question ID: {{ $questionId }}</div> {{-- Show ID --}}
		<button class="btn btn-sm btn-outline-danger delete-question-btn" data-question-id="{{ $questionId }}" data-delete-url="{{ route('question.delete', ['question' => $questionId]) }}" title="Delete Question">
			<i class="fas fa-trash-alt"></i> Delete <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
		</button>
	</div>
	
	<div class="asset-container mb-3" id="q-image-container-{{ $questionId }}">
		<div class="d-flex align-items-start">
			{{-- Image Display Area --}}
			<div id="q-image-display-{{ $questionId }}" class="me-3 flex-shrink-0" style="width: 150px;">
				{{-- Fixed size container --}}
				@if($image && ($image->medium_url || $image->small_url))
					<a href="#" class="question-image-clickable" data-bs-toggle="modal" data-bs-target="#imageModal" data-image-url="{{ $image->original_url ?? '#' }}" data-image-alt="{{ $image->image_alt ?? $prompt ?? 'Question Image' }}" title="Click to enlarge">
						<img src="{{ $image->medium_url ?? $image->small_url }}" alt="{{ $image->image_alt ?? $prompt ?? 'Question Image' }}" class="img-thumbnail question-image-thumb" style="width: 100%; object-fit: cover;">
					</a>
				@else
					<span class="text-muted question-image-thumb d-flex align-items-center justify-content-center border rounded p-2 text-center" style="width: 100%; height: 100%; background: var(--bs-tertiary-bg);">
                         {{ empty($prompt) ? 'No Prompt or Image' : 'No Image Generated' }}
                    </span>
				@endif
			</div>
			
			{{-- Question Text, Audio, Image Prompt/Actions --}}
			<div class="flex-grow-1">
				{{-- Question Text & Audio --}}
				<div class="question-line mb-2">
					<strong>Q: {{ $questionText }}</strong>
					{{-- Question Audio Play/Generate --}}
					<span id="q-audio-controls-{{ $questionId }}" class="ms-1">
                       {{-- ... (existing audio button/generation logic) ... --}}
						@if($questionAudioUrl)
							<button class="btn btn-sm btn-outline-primary btn-play-pause" data-audio-url="{{ $questionAudioUrl }}" data-error-area-id="q-audio-error-{{ $questionId }}" title="Play Question Audio">
                           <i class="fas fa-play"></i><i class="fas fa-pause"></i>
                       </button>
						@elseif($question)
							<button class="btn btn-sm btn-outline-secondary generate-asset-btn" data-url="{{ route('question.generate.audio.question', $questionId) }}" data-asset-type="question-audio" data-question-id="{{ $questionId }}" data-target-area-id="q-audio-controls-{{ $questionId }}" data-error-area-id="q-audio-error-{{ $questionId }}" title="Generate Question Audio">
                           <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                           <i class="fas fa-microphone-alt"></i> Gen
                       </button>
						@else
							<span class="badge bg-light text-dark ms-1" title="Audio not generated"><i class="fas fa-volume-mute"></i></span>
						@endif
                    </span>
					<div class="asset-generation-error text-danger small mt-1 d-inline-block" id="q-audio-error-{{ $questionId }}" style="display: none;"></div>
				</div>
				
				{{-- Image Prompt & Actions --}}
				<h6><i class="fas fa-image me-1 text-success"></i>Question Image</h6>
				<div class="mb-2">
					<label for="prompt-input-{{ $questionId }}" class="form-label visually-hidden">Image Prompt</label>
					<input type="text" class="form-control form-control-sm question-image-prompt-input" id="prompt-input-{{ $questionId }}" value="{{ $prompt }}" placeholder="Enter Image Prompt for AI generation">
					
					<input type="hidden" class="question-image-search-keywords" id="keywords-input-{{ $questionId }}" value="{{ $image_search_keywords }}">
				</div>
				
				{{-- Image Action Buttons (Dropdown) --}}
				@if($question) {{-- Only show actions if not template --}}
				<div class="btn-group btn-group-sm" role="group" aria-label="Image Actions">
					{{-- 1. Generate Button --}}
					<button type="button" class="btn btn-outline-primary regenerate-question-image-btn"
					        data-url="{{ route('question.generate.image', $questionId) }}"
					        data-question-id="{{ $questionId }}"
					        data-prompt-input-id="prompt-input-{{ $questionId }}"
					        data-target-area-id="q-image-display-{{ $questionId }}"
					        data-error-area-id="q-image-error-{{ $questionId }}"
					        title="Generate image using AI and the prompt above">
						<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
						<i class="fas fa-magic"></i> {{ $image && $image->source == 'llm' ? 'Regen' : 'Generate' }}
					</button>
					
					{{-- 2. Upload Button Trigger --}}
					<button type="button" class="btn btn-outline-secondary trigger-upload-btn"
					        data-question-id="{{ $questionId }}"
					        data-file-input-id="file-input-{{ $questionId }}"
					        title="Upload your own image">
						<i class="fas fa-upload"></i> Upload
					</button>
					{{-- Hidden File Input --}}
					<input type="file" class="d-none" id="file-input-{{ $questionId }}" data-question-id="{{ $questionId }}" accept="image/png, image/jpeg, image/gif, image/webp">
					
					
					{{-- 3. Search Button --}}
					<button type="button" class="btn btn-outline-info search-freepik-btn"
					        data-bs-toggle="modal" data-bs-target="#freepikSearchModal"
					        data-question-id="{{ $questionId }}"
					        data-prompt-input-id="prompt-input-{{ $questionId }}"
					        data-keywords-input-id="keywords-input-{{ $questionId }}"
					        title="Search Freepik for an image">
						<i class="fas fa-search"></i> Search
					</button>
				</div>
				<div class="asset-generation-error text-danger small mt-1" id="q-image-error-{{ $questionId }}" style="display: none;"></div>
				<div class="asset-generation-success text-success small mt-1" id="q-image-success-{{ $questionId }}" style="display: none;"></div>
				@else
					<span class="text-muted small">Image actions available after creation.</span>
				@endif
			
			</div> {{-- /flex-grow-1 --}}
		</div> {{-- /d-flex --}}
	</div> {{-- /.asset-container image --}}
	
	
	{{-- Answer & Feedback Audio --}}
	<div class="asset-container mb-2" id="a-audio-container-{{ $questionId }}">
		<h6><i class="fas fa-comments me-2 text-warning"></i>Answer & Feedback Audio</h6>
		<div id="a-audio-status-{{ $questionId }}" class="d-inline-block me-2">
			@if(empty($answers))
				<span class="text-muted small">No answers found.</span>
			@elseif(!$answersNeedAudio)
				<span class="text-success small"><i class="fas fa-check-circle me-1"></i>Generated</span>
			@else
				<span class="text-muted small">Not generated</span>
			@endif
		</div>
		{{-- Generate button only if $question exists and audio needed --}}
		@if($question && !empty($answers) && $answersNeedAudio)
			<button class="btn btn-sm btn-outline-secondary generate-asset-btn"
			        data-url="{{ route('question.generate.audio.answers', $questionId) }}"
			        data-asset-type="answer-audio"
			        data-question-id="{{ $questionId }}"
			        data-target-area-id="a-audio-status-{{ $questionId }}" {{-- Target the status span --}}
			        data-error-area-id="a-audio-error-{{ $questionId }}"
			        title="Generate Audio for All Answers & Feedback">
				<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
				<i class="fas fa-microphone-alt"></i> Generate All
			</button>
		@endif
		<div class="asset-generation-error text-danger small d-inline-block ms-2" id="a-audio-error-{{ $questionId }}" style="display: none;"></div>
	</div>
	
	{{-- List Answers --}}
	@if(!empty($answers))
		<ul class="list-unstyled mt-2 ms-3 answer-list">
			@foreach($answers as $ansIndex => $answer)
				@php
					// Check individual audio existence for display
					$answerAudioUrl = $question ? $question->getAnswerAudioUrl($ansIndex) : null;
					$feedbackAudioUrl = $question ? $question->getFeedbackAudioUrl($ansIndex) : null;
				@endphp
				<li>
					<span class="answer-text-content">{{ $ansIndex + 1 }}. {{ $answer['text'] ?? 'N/A' }}</span>
					@if($answer['is_correct'] ?? false) <strong class="text-success">(Correct)</strong> @endif
					{{-- Answer Audio Play Button --}}
					<span id="ans-audio-controls-{{ $questionId }}-{{ $ansIndex }}" class="ms-1">
                     @if($answerAudioUrl)
							<button class="btn btn-sm btn-outline-primary btn-play-pause" data-audio-url="{{ $answerAudioUrl }}" data-error-area-id="a-audio-error-{{ $questionId }}" title="Play Answer Audio">
                            <i class="fas fa-play"></i><i class="fas fa-pause"></i>
                        </button>
						@elseif(!$answersNeedAudio && $question) {{-- Only show mute icon if overall generation happened but this specific one failed/is missing --}}
						<span class="badge bg-light text-dark ms-1" title="Answer audio not available"><i class="fas fa-volume-mute"></i></span>
						@endif
                </span>
					<br>
					<small class="text-muted feedback-text-content">Feedback: {{ $answer['feedback'] ?? 'N/A' }}</small>
					{{-- Feedback Audio Play Button --}}
					<span id="fb-audio-controls-{{ $questionId }}-{{ $ansIndex }}" class="ms-1">
                     @if($feedbackAudioUrl)
							<button class="btn btn-sm btn-outline-primary btn-play-pause" data-audio-url="{{ $feedbackAudioUrl }}" data-error-area-id="a-audio-error-{{ $questionId }}" title="Play Feedback Audio">
                            <i class="fas fa-play"></i><i class="fas fa-pause"></i>
                        </button>
						@elseif(!$answersNeedAudio && $question)
							<span class="badge bg-light text-dark ms-1" title="Feedback audio not available"><i class="fas fa-volume-mute"></i></span>
						@endif
                </span>
				</li>
			@endforeach
		</ul>
	@endif

</div> {{-- /.question-item --}}
