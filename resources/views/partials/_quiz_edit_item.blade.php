{{-- resources/views/partials/_quiz_edit_item.blade.php --}}
{{-- Expects $quiz object, or null if used as template --}}
<div class="quiz-item" data-quiz-id="{{ $quiz->id ?? 'TEMPLATE_QUIZ_ID' }}" id="quiz-item-{{ $quiz->id ?? 'TEMPLATE_QUIZ_ID' }}">
	@php
		// Use optional chaining and null coalescing for template safety
		$quizId = $quiz->id ?? 'TEMPLATE_QUIZ_ID';
		$subjectId = $quiz->subject_id ?? 'TEMPLATE_SUBJECT_ID';
		$partIndex = $quiz->lesson_part_index ?? 'TEMPLATE_PART_INDEX';
		$image = $quiz->generatedImage ?? null;
		$prompt = $quiz->image_prompt_idea ?? '';
		$questionText = $quiz->question_text ?? 'TEMPLATE QUESTION TEXT';
		$questionAudioUrl = $quiz->question_audio_url ?? null; // Accessor handles existence check
		$answers = $quiz->answers ?? [];

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
		<div class="me-auto small text-muted">Quiz ID: {{ $quizId }}</div> {{-- Show ID --}}
		<button class="btn btn-sm btn-outline-danger delete-quiz-btn" data-quiz-id="{{ $quizId }}" data-delete-url="{{ route('quiz.delete', ['quiz' => $quizId]) }}" title="Delete Quiz">
			<i class="fas fa-trash-alt"></i> Delete <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
		</button>
	</div>
	
	<div class="asset-container mb-3" id="q-image-container-{{ $quizId }}">
		<div class="d-flex align-items-start">
			{{-- Image Display Area --}}
			<div id="q-image-display-{{ $quizId }}" class="me-3 flex-shrink-0" style="width: 150px;">
				{{-- Fixed size container --}}
				@if($image && ($image->medium_url || $image->small_url))
					<a href="#" class="quiz-image-clickable" data-bs-toggle="modal" data-bs-target="#imageModal" data-image-url="{{ $image->original_url ?? '#' }}" data-image-alt="{{ $image->image_alt ?? $prompt ?? 'Quiz Image' }}" title="Click to enlarge">
						<img src="{{ $image->medium_url ?? $image->small_url }}" alt="{{ $image->image_alt ?? $prompt ?? 'Quiz Image' }}" class="img-thumbnail quiz-image-thumb" style="width: 100%; object-fit: cover;">
					</a>
				@else
					<span class="text-muted quiz-image-thumb d-flex align-items-center justify-content-center border rounded p-2 text-center" style="width: 100%; height: 100%; background: var(--bs-tertiary-bg);">
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
					<span id="q-audio-controls-{{ $quizId }}" class="ms-1">
                       {{-- ... (existing audio button/generation logic) ... --}}
						@if($questionAudioUrl)
							<button class="btn btn-sm btn-outline-primary btn-play-pause" data-audio-url="{{ $questionAudioUrl }}" data-error-area-id="q-audio-error-{{ $quizId }}" title="Play Question Audio">
                           <i class="fas fa-play"></i><i class="fas fa-pause"></i>
                       </button>
						@elseif($quiz)
							<button class="btn btn-sm btn-outline-secondary generate-asset-btn" data-url="{{ route('quiz.generate.audio.question', $quizId) }}" data-asset-type="question-audio" data-quiz-id="{{ $quizId }}" data-target-area-id="q-audio-controls-{{ $quizId }}" data-error-area-id="q-audio-error-{{ $quizId }}" title="Generate Question Audio">
                           <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                           <i class="fas fa-microphone-alt"></i> Gen
                       </button>
						@else
							<span class="badge bg-light text-dark ms-1" title="Audio not generated"><i class="fas fa-volume-mute"></i></span>
						@endif
                    </span>
					<div class="asset-generation-error text-danger small mt-1 d-inline-block" id="q-audio-error-{{ $quizId }}" style="display: none;"></div>
				</div>
				
				{{-- Image Prompt & Actions --}}
				<h6><i class="fas fa-image me-1 text-success"></i>Quiz Image</h6>
				<div class="mb-2">
					<label for="prompt-input-{{ $quizId }}" class="form-label visually-hidden">Image Prompt</label>
					<input type="text" class="form-control form-control-sm quiz-image-prompt-input" id="prompt-input-{{ $quizId }}" value="{{ $prompt }}" placeholder="Enter Image Prompt for AI generation">
				</div>
				
				{{-- Image Action Buttons (Dropdown) --}}
				@if($quiz) {{-- Only show actions if not template --}}
				<div class="btn-group btn-group-sm" role="group" aria-label="Image Actions">
					{{-- 1. Generate Button --}}
					<button type="button" class="btn btn-outline-primary regenerate-quiz-image-btn"
					        data-url="{{ route('quiz.generate.image', $quizId) }}"
					        data-quiz-id="{{ $quizId }}"
					        data-prompt-input-id="prompt-input-{{ $quizId }}"
					        data-target-area-id="q-image-display-{{ $quizId }}"
					        data-error-area-id="q-image-error-{{ $quizId }}"
					        title="Generate image using AI and the prompt above">
						<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
						<i class="fas fa-magic"></i> {{ $image && $image->source == 'llm' ? 'Regen' : 'Generate' }}
					</button>
					
					{{-- 2. Upload Button Trigger --}}
					<button type="button" class="btn btn-outline-secondary trigger-upload-btn"
					        data-quiz-id="{{ $quizId }}"
					        data-file-input-id="file-input-{{ $quizId }}"
					        title="Upload your own image">
						<i class="fas fa-upload"></i> Upload
					</button>
					{{-- Hidden File Input --}}
					<input type="file" class="d-none" id="file-input-{{ $quizId }}" data-quiz-id="{{ $quizId }}" accept="image/png, image/jpeg, image/gif, image/webp">
					
					
					{{-- 3. Search Button --}}
					<button type="button" class="btn btn-outline-info search-freepik-btn"
					        data-bs-toggle="modal" data-bs-target="#freepikSearchModal"
					        data-quiz-id="{{ $quizId }}"
					        data-prompt-input-id="prompt-input-{{ $quizId }}"
					        title="Search Freepik for an image">
						<i class="fas fa-search"></i> Search
					</button>
				</div>
				<div class="asset-generation-error text-danger small mt-1" id="q-image-error-{{ $quizId }}" style="display: none;"></div>
				<div class="asset-generation-success text-success small mt-1" id="q-image-success-{{ $quizId }}" style="display: none;"></div>
				@else
					<span class="text-muted small">Image actions available after creation.</span>
				@endif
			
			</div> {{-- /flex-grow-1 --}}
		</div> {{-- /d-flex --}}
	</div> {{-- /.asset-container image --}}
	
	
	{{-- Answer & Feedback Audio --}}
	<div class="asset-container mb-2" id="a-audio-container-{{ $quizId }}">
		<h6><i class="fas fa-comments me-2 text-warning"></i>Answer & Feedback Audio</h6>
		<div id="a-audio-status-{{ $quizId }}" class="d-inline-block me-2">
			@if(empty($answers))
				<span class="text-muted small">No answers found.</span>
			@elseif(!$answersNeedAudio)
				<span class="text-success small"><i class="fas fa-check-circle me-1"></i>Generated</span>
			@else
				<span class="text-muted small">Not generated</span>
			@endif
		</div>
		{{-- Generate button only if $quiz exists and audio needed --}}
		@if($quiz && !empty($answers) && $answersNeedAudio)
			<button class="btn btn-sm btn-outline-secondary generate-asset-btn"
			        data-url="{{ route('quiz.generate.audio.answers', $quizId) }}"
			        data-asset-type="answer-audio"
			        data-quiz-id="{{ $quizId }}"
			        data-target-area-id="a-audio-status-{{ $quizId }}" {{-- Target the status span --}}
			        data-error-area-id="a-audio-error-{{ $quizId }}"
			        title="Generate Audio for All Answers & Feedback">
				<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
				<i class="fas fa-microphone-alt"></i> Generate All
			</button>
		@endif
		<div class="asset-generation-error text-danger small d-inline-block ms-2" id="a-audio-error-{{ $quizId }}" style="display: none;"></div>
	</div>
	
	{{-- List Answers --}}
	@if(!empty($answers))
		<ul class="list-unstyled mt-2 ms-3 answer-list">
			@foreach($answers as $ansIndex => $answer)
				@php
					// Check individual audio existence for display
					$answerAudioUrl = $quiz ? $quiz->getAnswerAudioUrl($ansIndex) : null;
					$feedbackAudioUrl = $quiz ? $quiz->getFeedbackAudioUrl($ansIndex) : null;
				@endphp
				<li>
					<span class="answer-text-content">{{ $ansIndex + 1 }}. {{ $answer['text'] ?? 'N/A' }}</span>
					@if($answer['is_correct'] ?? false) <strong class="text-success">(Correct)</strong> @endif
					{{-- Answer Audio Play Button --}}
					<span id="ans-audio-controls-{{ $quizId }}-{{ $ansIndex }}" class="ms-1">
                     @if($answerAudioUrl)
							<button class="btn btn-sm btn-outline-primary btn-play-pause" data-audio-url="{{ $answerAudioUrl }}" data-error-area-id="a-audio-error-{{ $quizId }}" title="Play Answer Audio">
                            <i class="fas fa-play"></i><i class="fas fa-pause"></i>
                        </button>
						@elseif(!$answersNeedAudio && $quiz) {{-- Only show mute icon if overall generation happened but this specific one failed/is missing --}}
						<span class="badge bg-light text-dark ms-1" title="Answer audio not available"><i class="fas fa-volume-mute"></i></span>
						@endif
                </span>
					<br>
					<small class="text-muted feedback-text-content">Feedback: {{ $answer['feedback'] ?? 'N/A' }}</small>
					{{-- Feedback Audio Play Button --}}
					<span id="fb-audio-controls-{{ $quizId }}-{{ $ansIndex }}" class="ms-1">
                     @if($feedbackAudioUrl)
							<button class="btn btn-sm btn-outline-primary btn-play-pause" data-audio-url="{{ $feedbackAudioUrl }}" data-error-area-id="a-audio-error-{{ $quizId }}" title="Play Feedback Audio">
                            <i class="fas fa-play"></i><i class="fas fa-pause"></i>
                        </button>
						@elseif(!$answersNeedAudio && $quiz)
							<span class="badge bg-light text-dark ms-1" title="Feedback audio not available"><i class="fas fa-volume-mute"></i></span>
						@endif
                </span>
				</li>
			@endforeach
		</ul>
	@endif

</div> {{-- /.quiz-item --}}
