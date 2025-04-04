@extends('layouts.app')

@section('title', 'Edit Lesson Assets: ' . $subject->title)

@push('styles')
	<style>
      .content-card, .quiz-item-card { /* Use a common class for styling */
          background-color: var(--bs-body-bg); /* Use CSS var for dark mode */
          border: 1px solid var(--bs-border-color);
          padding: 1.5rem;
          margin-bottom: 1.5rem;
          border-radius: 0.375rem; /* bs default */
          box-shadow: 0 1px 3px rgba(0,0,0,.1);
      }

      .asset-container {
          border: 1px solid var(--bs-border-color-translucent);
          padding: 1rem;
          margin-bottom: 1rem;
          border-radius: 0.25rem;
          background-color: var(--bs-secondary-bg); /* Use CSS var */
      }

      .asset-container h6 {
          margin-bottom: 0.75rem;
          border-bottom: 1px solid var(--bs-border-color);
          padding-bottom: 0.5rem;
      }

      .asset-container .btn-sm { /* Ensure buttons are visible */
          margin-top: 0rem;
          margin-left: 0.5rem;
          vertical-align: middle;
      }

      .asset-status {
          font-size: 0.9em;
          margin-left: 0.5rem;
          display: inline-block; /* To allow spinner placement */
          vertical-align: middle;
          min-width: 80px; /* Give status some space */
          text-align: left;
      }
      .asset-status .spinner-border-sm {
          width: 1rem;
          height: 1rem;
          vertical-align: text-bottom;
          margin-right: 0.3rem;
      }
      .asset-status .text-success,
      .asset-status .text-danger {
          font-weight: bold;
      }


      .quiz-difficulty-group {
          margin-bottom: 1.5rem;
          padding-left: 1rem;
          border-left: 3px solid var(--bs-tertiary-bg); /* Use CSS var */
      }

      .quiz-item { /* Individual quiz box */
          /* border-bottom: 1px dashed var(--bs-border-color); */
          padding: 1rem;
          margin-bottom: 1rem;
          border: 1px solid var(--bs-border-color);
          border-radius: 0.25rem;
          background-color: var(--bs-body-bg); /* Slight contrast if needed */
      }

      .quiz-item:last-child {
          /* border-bottom: none; */
          margin-bottom: 0;
          /* padding-bottom: 0; */
      }
      .quiz-item p strong { /* Question text */
          display: block;
          margin-bottom: 0.75rem;
          font-size: 1.1em;
      }

      .generated-video-container {
          background-color: var(--bs-secondary-bg); /* Use CSS var */
      }

      .generated-video {
          max-width: 100%; /* Responsive video */
          max-height: 400px; /* Limit video preview height */
          background-color: #000; /* Black background for video player */
          border-radius: 0.25rem;
      }

      .quiz-image-thumb {
          max-width: 150px;
          max-height: 150px;
          object-fit: cover;
          margin-right: 0.5rem;
      }

      audio {
          max-width: 250px; /* Prevent audio players getting too wide */
          height: 35px; /* Consistent height */
          vertical-align: middle;
      }

      .answer-list li {
          margin-bottom: 0.75rem;
          padding-bottom: 0.75rem;
          border-bottom: 1px solid var(--bs-tertiary-bg);
      }
      .answer-list li:last-child {
          border-bottom: none;
          margin-bottom: 0;
          padding-bottom: 0;
      }

      .answer-audio-status {
          font-size: 0.85em;
          margin-left: 0.5rem;
      }
	
	</style>
@endpush

@section('content')
	<div class="d-flex justify-content-between align-items-center mb-3">
		<a href="{{ route('home') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back to Home</a>
		{{-- Maybe add a "View Live Lesson" button here later --}}
		<a href="{{ route('content.show', ['subject' => $subject->session_id]) }}" class="btn btn-outline-success"><i class="fas fa-eye"></i> View Live Lesson</a>
	</div>
	
	<div class="content-card mb-4">
		<h1 class="mb-1">Edit Lesson Assets: {{ $subject->title }}</h1>
		<p class="text-muted mb-3">Subject: {{ $subject->name }} (ID: {{ $subject->id }}, Session: {{ $subject->session_id }})</p>
		<p><small>Use the buttons below to generate missing video, audio, or images for this lesson. Generated assets will appear automatically.</small></p>
	</div>
	
	
	{{-- Lesson Parts and Quizzes --}}
	@if (!empty($subject->lesson_parts))
		@foreach($subject->lesson_parts as $partIndex => $part)
			<div class="content-card mb-4">
				<h3 class="mb-3">Lesson Part {{ $partIndex + 1 }}: {{ $part['title'] }}</h3>
				<p>{{ $part['text'] }}</p>
				
				<div class="asset-container mb-3 generated-video-container">
					<h6><i class="fas fa-film me-2 text-primary"></i>Part Video</h6>
					{{-- Check if video_url exists within the part array --}}
					@if(isset($part['video_url']) && !empty($part['video_url']))
						<div class="mb-2 text-center" id="video-display-{{ $partIndex }}">
							<video controls preload="metadata" src="{{ Storage::url($part['video_path']) /* Ensure URL is correct */ }}" class="generated-video">
								Your browser does not support the video tag.
							</video>
							<p><small class="text-muted d-block mt-1">Video generated. Path: {{ $part['video_path'] ?? 'N/A' }}</small></p>
							{{-- Optional: Add Regenerate/Delete Button --}}
							{{-- <button class="btn btn-sm btn-outline-warning generate-part-video-btn" ...>Regenerate</button> --}}
						</div>
						<div class="video-placeholder mt-3" id="video-placeholder-{{ $partIndex }}" style="display: none;"></div>
						<div class="text-center" id="video-button-area-{{ $partIndex }}" style="display: none;">
							{{-- Button is hidden initially if video exists, shown by JS if regeneration is added --}}
							<button class="btn btn-outline-info generate-part-video-btn"
							        data-subject-id="{{ $subject->session_id }}"
							        data-part-index="{{ $partIndex }}"
							        data-generate-url="{{ route('lesson.part.generate.video', ['subject' => $subject->session_id, 'partIndex' => $partIndex]) }}">
								<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
								<i class="fas fa-video me-1"></i> Generate Video
							</button>
							<small class="text-muted d-block mt-1">Generates a short talking head video based on this part's text.</small>
							<div class="asset-generation-error text-danger small mt-1" id="video-error-{{ $partIndex }}" style="display: none;"></div>
						</div>
					
					@else
						{{-- Display the Generate button only if video doesn't exist --}}
						<div class="mb-2 text-center" id="video-button-area-{{ $partIndex }}">
							<button class="btn btn-outline-info generate-part-video-btn"
							        data-subject-id="{{ $subject->session_id }}"
							        data-part-index="{{ $partIndex }}"
							        data-generate-url="{{ route('lesson.part.generate.video', ['subject' => $subject->session_id, 'partIndex' => $partIndex]) }}">
								<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
								<i class="fas fa-video me-1"></i> Generate Video
							</button>
							<small class="text-muted d-block mt-1">Generates a short talking head video based on this part's text.</small>
							<div class="asset-generation-error text-danger small mt-1" id="video-error-{{ $partIndex }}" style="display: none;"></div>
						</div>
						{{-- Placeholder where the video will be inserted by JS --}}
						<div class="video-placeholder mt-3" id="video-placeholder-{{ $partIndex }}" style="display: none;">
							<!-- Video tag will be inserted here by JS -->
						</div>
						<div class="mb-2 text-center" id="video-display-{{ $partIndex }}" style="display: none;">
							<!-- Video display area if generated -->
						</div>
					@endif
				</div>
				
				<h4 class="mt-4">Quizzes for this Part</h4>
				@if(isset($groupedQuizzes[$partIndex]) && !empty($groupedQuizzes[$partIndex]))
					@foreach(['easy', 'medium', 'hard'] as $difficulty)
						@if(isset($groupedQuizzes[$partIndex][$difficulty]) && count($groupedQuizzes[$partIndex][$difficulty]) > 0)
							<div class="quiz-difficulty-group mt-3">
								<h5>{{ ucfirst($difficulty) }} Quizzes</h5>
								@foreach($groupedQuizzes[$partIndex][$difficulty] as $quiz)
									<div class="quiz-item" data-quiz-id="{{ $quiz->id }}">
										<p><strong>Q: {{ $quiz->question_text }}</strong></p>
										
										{{-- Quiz Question Audio --}}
										<div class="asset-container mb-3" id="q-audio-container-{{ $quiz->id }}">
											<h6><i class="fas fa-volume-up me-2 text-info"></i>Question Audio</h6>
											<div id="q-audio-display-{{ $quiz->id }}">
												@if($quiz->question_audio_url)
													<audio controls controlsList="nodownload noremoteplayback">
														<source src="{{ $quiz->question_audio_url }}" type="audio/mpeg">
														Your browser doesn't support audio.
													</audio>
												@else
													<span class="text-muted">Not generated</span>
												@endif
											</div>
											<div id="q-audio-button-area-{{ $quiz->id }}" class="{{ $quiz->question_audio_url ? 'd-none' : '' }}">
												<button class="btn btn-sm btn-outline-secondary generate-asset-btn"
												        data-url="{{ route('quiz.generate.audio.question', $quiz->id) }}"
												        data-asset-type="question-audio"
												        data-quiz-id="{{ $quiz->id }}"
												        data-target-area-id="q-audio-display-{{ $quiz->id }}"
												        data-button-area-id="q-audio-button-area-{{ $quiz->id }}"
												        data-error-area-id="q-audio-error-{{ $quiz->id }}">
													<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
													<i class="fas fa-microphone-alt"></i> Generate
												</button>
											</div>
											<div class="asset-generation-error text-danger small mt-1" id="q-audio-error-{{ $quiz->id }}" style="display: none;"></div>
										</div>
										
										
										{{-- Quiz Answer Audio (Single button for all answers) --}}
										@php
											$answersNeedAudio = true; // Default to needing audio
											if (!empty($quiz->answers) && is_array($quiz->answers)) {
													// Check first answer for URL - assumes if one has it, all should
													if(isset($quiz->answers[0]['answer_audio_url']) && !empty($quiz->answers[0]['answer_audio_url'])) {
															$answersNeedAudio = false;
													}
											} else {
													$answersNeedAudio = false; // No answers to generate for
											}
										@endphp
										<div class="asset-container mb-3" id="a-audio-container-{{ $quiz->id }}">
											<h6><i class="fas fa-comments me-2 text-warning"></i>Answer & Feedback Audio</h6>
											<div id="a-audio-status-{{ $quiz->id }}">
												@if(!$answersNeedAudio && !empty($quiz->answers))
													<span class="text-success"><i class="fas fa-check-circle me-1"></i>Generated</span>
													{{-- Optionally list links or players for each answer/feedback audio here if needed, using $quiz->getAnswerAudioUrl($index), etc --}}
												@elseif(empty($quiz->answers))
													<span class="text-muted">No answers for this quiz.</span>
												@else
													<span class="text-muted">Not generated</span>
												@endif
											</div>
											<div id="a-audio-button-area-{{ $quiz->id }}" class="{{ !$answersNeedAudio ? 'd-none' : '' }}">
												@if(!empty($quiz->answers))
													<button class="btn btn-sm btn-outline-secondary generate-asset-btn"
													        data-url="{{ route('quiz.generate.audio.answers', $quiz->id) }}"
													        data-asset-type="answer-audio"
													        data-quiz-id="{{ $quiz->id }}"
													        data-target-area-id="a-audio-status-{{ $quiz->id }}"
													        data-button-area-id="a-audio-button-area-{{ $quiz->id }}"
													        data-error-area-id="a-audio-error-{{ $quiz->id }}">
														<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
														<i class="fas fa-microphone-alt"></i> Generate All
													</button>
												@endif
											</div>
											<div class="asset-generation-error text-danger small mt-1" id="a-audio-error-{{ $quiz->id }}" style="display: none;"></div>
										</div>
										
										
										{{-- Quiz Image --}}
										<div class="asset-container mb-3" id="q-image-container-{{ $quiz->id }}">
											<h6><i class="fas fa-image me-2 text-success"></i>Quiz Image</h6>
											<div id="q-image-display-{{ $quiz->id }}" class="mb-2">
												@if($quiz->generatedImage && $quiz->generatedImage->medium_url)
													<a href="{{ $quiz->generatedImage->original_url }}" target="_blank" title="View full size">
														<img src="{{ $quiz->generatedImage->medium_url }}" alt="{{ $quiz->generatedImage->image_alt }}" class="img-thumbnail quiz-image-thumb">
													</a>
													<p><small class="text-muted d-block mt-1">Prompt: {{ $quiz->image_prompt_idea ?: 'N/A' }}</small></p>
												@elseif(empty($quiz->image_prompt_idea))
													<span class="text-muted">No image prompt provided.</span>
												@else
													<span class="text-muted">Not generated. Prompt: {{ $quiz->image_prompt_idea }}</span>
												@endif
											</div>
											<div id="q-image-button-area-{{ $quiz->id }}" class="{{ ($quiz->generatedImage || empty($quiz->image_prompt_idea)) ? 'd-none' : '' }}">
												@if(!empty($quiz->image_prompt_idea))
													<button class="btn btn-sm btn-outline-secondary generate-asset-btn"
													        data-url="{{ route('quiz.generate.image', $quiz->id) }}"
													        data-asset-type="quiz-image"
													        data-quiz-id="{{ $quiz->id }}"
													        data-target-area-id="q-image-display-{{ $quiz->id }}"
													        data-button-area-id="q-image-button-area-{{ $quiz->id }}"
													        data-error-area-id="q-image-error-{{ $quiz->id }}">
														<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
														<i class="fas fa-image"></i> Generate
													</button>
												@endif
											</div>
											<div class="asset-generation-error text-danger small mt-1" id="q-image-error-{{ $quiz->id }}" style="display: none;"></div>
										</div>
										
										
										{{-- List Answers (Read Only with Audio Previews if available) --}}
										@if(!empty($quiz->answers))
											<details class="mt-3">
												<summary>View Answers & Feedback</summary>
												<ul class="list-unstyled mt-2 ms-3 answer-list">
													@foreach($quiz->answers as $ansIndex => $answer)
														<li>
															{{ $ansIndex + 1 }}. {{ $answer['text'] }}
															@if($answer['is_correct']) <strong class="text-success">(Correct)</strong> @endif
															
															{{-- Answer Audio Player --}}
															@if(isset($answer['answer_audio_url']) && $answer['answer_audio_url'])
																<audio controls controlsList="nodownload noremoteplayback" class="d-block mt-1" style="height: 25px;">
																	<source src="{{ $answer['answer_audio_url'] }}" type="audio/mpeg">
																</audio>
															@elseif(!$answersNeedAudio) {{-- Show missing only if generation was attempted --}}
															<small class="text-warning d-block mt-1">(Answer audio missing)</small>
															@endif
															
															<br>
															<small class="text-muted">Feedback: {{ $answer['feedback'] }}</small>
															
															{{-- Feedback Audio Player --}}
															@if(isset($answer['feedback_audio_url']) && $answer['feedback_audio_url'])
																<audio controls controlsList="nodownload noremoteplayback" class="d-block mt-1" style="height: 25px;">
																	<source src="{{ $answer['feedback_audio_url'] }}" type="audio/mpeg">
																</audio>
															@elseif(!$answersNeedAudio) {{-- Show missing only if generation was attempted --}}
															<small class="text-warning d-block mt-1">(Feedback audio missing)</small>
															@endif
														</li>
													@endforeach
												</ul>
											</details>
										@endif
									
									</div> {{-- /.quiz-item --}}
								@endforeach
							</div> {{-- /.quiz-difficulty-group --}}
						@endif
					@endforeach
				@else
					<p class="text-muted">No quizzes found for this lesson part.</p>
				@endif
			</div> {{-- /.content-card --}}
		@endforeach
	@else
		<div class="alert alert-warning">Lesson part data is missing or invalid for this subject. Cannot display edit options.</div>
	@endif
@endsection

@push('scripts')
	{{-- Make sure common.js is loaded if it contains shared functions like setLoading --}}
	{{-- <script src="{{ asset('js/common.js') }}"></script> --}}
	<script>
		document.addEventListener('DOMContentLoaded', () => {
			
			// --- Helper Functions ---
			function showSpinner(button, show = true) {
				if (!button) return;
				const spinner = button.querySelector('.spinner-border');
				if (spinner) {
					spinner.classList.toggle('d-none', !show);
				}
				button.disabled = show;
			}
			
			function showError(elementId, message) {
				const errorEl = document.getElementById(elementId);
				if (errorEl) {
					errorEl.textContent = message || 'An unknown error occurred.';
					errorEl.style.display = 'block';
				}
			}
			
			function hideError(elementId) {
				const errorEl = document.getElementById(elementId);
				if (errorEl) {
					errorEl.style.display = 'none';
					errorEl.textContent = '';
				}
			}
			
			// --- Asset Display Updaters ---
			function updateVideoDisplay(partIndex, videoUrl, videoPath) {
				const placeholder = document.getElementById(`video-placeholder-${partIndex}`);
				const displayArea = document.getElementById(`video-display-${partIndex}`);
				const buttonArea = document.getElementById(`video-button-area-${partIndex}`);
				
				if (!displayArea) return;
				displayArea.innerHTML = ''; // Clear previous content
				
				const video = document.createElement('video');
				video.src = videoUrl; // Use the direct URL from response
				video.controls = true;
				video.preload = 'metadata';
				video.classList.add('generated-video');
				
				const pathText = document.createElement('p');
				pathText.innerHTML = `<small class="text-muted d-block mt-1">Video generated. Path: ${videoPath || 'N/A'}</small>`;
				
				displayArea.appendChild(video);
				displayArea.appendChild(pathText);
				displayArea.style.display = 'block';
				
				if (placeholder) placeholder.style.display = 'none'; // Hide placeholder
				if (buttonArea) buttonArea.style.display = 'none'; // Hide generate button
				
			}
			
			function updateQuestionAudioDisplay(quizId, audioUrl) {
				const displayArea = document.getElementById(`q-audio-display-${quizId}`);
				const buttonArea = document.getElementById(`q-audio-button-area-${quizId}`);
				if (!displayArea) return;
				
				displayArea.innerHTML = ''; // Clear 'Not generated' text
				const audio = document.createElement('audio');
				audio.src = audioUrl;
				audio.controls = true;
				audio.controlsList = "nodownload noremoteplayback";
				displayArea.appendChild(audio);
				
				if (buttonArea) buttonArea.style.display = 'none'; // Hide button
			}
			
			function updateAnswerAudioStatus(quizId, success = true) {
				const statusArea = document.getElementById(`a-audio-status-${quizId}`);
				const buttonArea = document.getElementById(`a-audio-button-area-${quizId}`);
				if (!statusArea) return;
				
				if (success) {
					statusArea.innerHTML = '<span class="text-success"><i class="fas fa-check-circle me-1"></i>Generated</span>';
					// We might need to reload the page or make another AJAX call
					// to get the individual audio URLs for the answer list <details> section
					// For now, just show success status. Consider adding a "refresh answers" link.
					// statusArea.innerHTML += ' <a href="javascript:location.reload();">(Refresh to view players)</a>';
					// Or better: Trigger a specific AJAX reload for the answers section if needed.
					
				} else {
					statusArea.innerHTML = '<span class="text-danger"><i class="fas fa-times-circle me-1"></i>Failed</span>';
				}
				
				if (buttonArea && success) buttonArea.style.display = 'none'; // Hide button on success
			}
			
			
			function updateQuizImageDisplay(quizId, imageUrls, prompt) {
				const displayArea = document.getElementById(`q-image-display-${quizId}`);
				const buttonArea = document.getElementById(`q-image-button-area-${quizId}`);
				if (!displayArea) return;
				
				displayArea.innerHTML = ''; // Clear previous content
				
				const link = document.createElement('a');
				link.href = imageUrls.original || '#';
				link.target = '_blank';
				link.title = 'View full size';
				
				const img = document.createElement('img');
				img.src = imageUrls.medium || imageUrls.small || imageUrls.original; // Fallback size
				img.alt = `Generated image for prompt: ${prompt || 'Quiz Image'}`;
				img.classList.add('img-thumbnail', 'quiz-image-thumb');
				link.appendChild(img);
				
				const promptText = document.createElement('p');
				promptText.innerHTML = `<small class="text-muted d-block mt-1">Prompt: ${prompt || 'N/A'}</small>`;
				
				displayArea.appendChild(link);
				displayArea.appendChild(promptText);
				
				if (buttonArea) buttonArea.style.display = 'none'; // Hide button
			}
			
			// --- Event Listeners ---
			
			// 1. Generate Video for Specific Part Button
			document.querySelectorAll('.generate-part-video-btn').forEach(button => {
				button.addEventListener('click', async (event) => {
					const btn = event.currentTarget;
					const partIndex = btn.dataset.partIndex;
					const url = btn.dataset.generateUrl;
					const errorElId = `video-error-${partIndex}`;
					
					hideError(errorElId);
					showSpinner(btn, true);
					
					try {
						const response = await fetch(url, {
							method: 'POST',
							headers: {
								'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
								'Accept': 'application/json',
							}
						});
						
						const result = await response.json();
						
						if (!response.ok || !result.success) {
							// Handle potential 409 Conflict (already exists) gracefully
							if (response.status === 409 || result.message?.includes('already exists')) {
								console.warn(`Video for part ${partIndex} already exists or generation triggered elsewhere.`);
								// Optionally update UI if necessary, maybe fetch the existing URL again
								if(result.video_url) {
									updateVideoDisplay(partIndex, result.video_url, result.video_path);
								} else {
									// Maybe just hide button?
									btn.closest('.video-button-area').style.display = 'none';
								}
								
							} else {
								throw new Error(result.message || `HTTP error ${response.status}`);
							}
						} else {
							// Success
							updateVideoDisplay(partIndex, result.video_url, result.video_path);
						}
						
					} catch (error) {
						console.error(`Error generating video for part ${partIndex}:`, error);
						showError(errorElId, `Failed: ${error.message}`);
					} finally {
						showSpinner(btn, false); // Ensure spinner is hidden and button enabled on error
						// We don't re-enable button on success as it should be hidden
					}
				});
			});
			
			// 2. Generate Quiz Assets (Audio/Image) Button (Common Listener)
			document.querySelectorAll('.generate-asset-btn').forEach(button => {
				button.addEventListener('click', async (event) => {
					const btn = event.currentTarget;
					const url = btn.dataset.url;
					const assetType = btn.dataset.assetType;
					const quizId = btn.dataset.quizId;
					const targetAreaId = btn.dataset.targetAreaId;
					const buttonAreaId = btn.dataset.buttonAreaId;
					const errorAreaId = btn.dataset.errorAreaId;
					
					hideError(errorAreaId);
					showSpinner(btn, true);
					
					try {
						const response = await fetch(url, {
							method: 'POST',
							headers: {
								'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
								'Accept': 'application/json',
							}
						});
						
						const result = await response.json();
						
						if (!response.ok || !result.success) {
							// Handle conflicts (already exists)
							if (response.status === 409 || result.message?.includes('already exists')) {
								console.warn(`${assetType} for quiz ${quizId} already exists.`);
								// Update UI based on type if URL/data is returned
								if (assetType === 'question-audio' && result.audio_url) {
									updateQuestionAudioDisplay(quizId, result.audio_url);
								} else if (assetType === 'answer-audio') {
									updateAnswerAudioStatus(quizId, true); // Mark as success
								} else if (assetType === 'quiz-image' && result.image_urls) {
									// Need prompt from somewhere if not returned - maybe fetch quiz data again?
									// For now, just use a generic alt text if prompt isn't handy
									const prompt = document.querySelector(`#q-image-container-${quizId} small`)?.textContent.replace('Prompt: ','') || 'Quiz Image';
									updateQuizImageDisplay(quizId, result.image_urls, prompt);
								} else {
									// Just hide button if data not available
									const buttonArea = document.getElementById(buttonAreaId);
									if(buttonArea) buttonArea.style.display = 'none';
								}
								
							} else {
								throw new Error(result.message || `HTTP error ${response.status}`);
							}
						} else {
							// --- Success ---
							if (assetType === 'question-audio' && result.audio_url) {
								updateQuestionAudioDisplay(quizId, result.audio_url);
							} else if (assetType === 'answer-audio') {
								updateAnswerAudioStatus(quizId, true);
								// Consider reloading the answer list details here if players are needed immediately
								// For simplicity, we currently don't. User might need to refresh.
							} else if (assetType === 'quiz-image' && result.image_urls) {
								const prompt = document.querySelector(`#q-image-container-${quizId} small`)?.textContent.replace('Prompt: ','') || 'Quiz Image';
								updateQuizImageDisplay(quizId, result.image_urls, prompt);
							}
						}
						
					} catch (error) {
						console.error(`Error generating ${assetType} for quiz ${quizId}:`, error);
						showError(errorAreaId, `Failed: ${error.message}`);
						showSpinner(btn, false); // Re-enable button on error
					} finally {
						// Spinner is hidden within the success/error branches for button hiding logic
						// showSpinner(btn, false); // Might re-enable button wrongly if it was hidden on success
					}
				});
			});
			
			
		}); // End DOMContentLoaded
	</script>
@endpush
