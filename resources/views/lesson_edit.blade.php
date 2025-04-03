@extends('layouts.app')

@section('title', 'Edit Lesson Assets: ' . $subject->title)

@push('styles')
	<style>
      .asset-container {
          border: 1px solid #eee;
          padding: 1rem;
          margin-bottom: 1rem;
          border-radius: 0.25rem;
          background-color: #f8f9fa; /* Light background */
      }

      .asset-container .btn {
          margin-top: 0.5rem;
      }

      .asset-status {
          font-size: 0.9em;
          margin-left: 0.5rem;
      }

      .asset-status .spinner-border-sm {
          width: 1rem;
          height: 1rem;
          vertical-align: text-bottom;
      }

      .quiz-difficulty-group {
          margin-bottom: 1.5rem;
          padding-left: 1rem;
          border-left: 3px solid #dee2e6;
      }

      .quiz-item {
          border-bottom: 1px dashed #ccc;
          padding-bottom: 1rem;
          margin-bottom: 1rem;
      }

      .quiz-item:last-child {
          border-bottom: none;
          margin-bottom: 0;
          padding-bottom: 0;
      }

      .generated-video-container {
          background-color: #f8f9fa; /* Light background */
      }

      .generated-video {
          max-height: 300px; /* Limit video preview height */
          background-color: #f8f9fa; /* Light background for video */
      }

      /* Dark mode adjustments */
      .dark-mode .asset-container {
          background-color: #343a40; /* Darker background */
          border: 1px solid #495057;
      }

      .dark-mode .quiz-difficulty-group {
          border-left-color: #495057;
      }

      .dark-mode .quiz-item {
          border-bottom-color: #495057;
      }

      .dark-mode .generated-video-container {
          background-color: #343a40; /* Darker background */
      }

      .dark-mode .generated-video {
          background-color: #495057; /* Darker background for video */
      }
	
	
	</style>
@endpush

@section('content')
	<a href="{{ route('home') }}" class="btn btn-outline-secondary mb-3"><i class="fas fa-arrow-left"></i> Back to Lessons</a>
	<h1 class="mb-4">Edit Lesson Assets: {{ $subject->title }}</h1>
	<p class="text-muted">Subject: {{ $subject->name }} (ID: {{ $subject->id }}, Session: {{ $subject->session_id }})</p>
	<p><small>Use the buttons below to generate missing video, audio, or images for this lesson.</small></p>
	
	<hr>
	
	{{-- Lesson Parts and Quizzes --}}
	@if (!empty($subject->lesson_parts))
		@foreach($subject->lesson_parts as $partIndex => $part)
			<div class="content-card mb-4">
				<h3 class="mb-3">Lesson Part {{ $partIndex + 1 }}: {{ $part['title'] }}</h3>
				<p>{{ $part['text'] }}</p>
				
				<div class="border rounded p-3 mb-3 generated-video-container">
					<h6>Part Video</h6>
					{{-- Check if video_url exists within the part array --}}
					@if(isset($part['video_url']) && !empty($part['video_url']))
						<div class="mb-2 text-center">
							<video controls preload="metadata" src="{{ $part['video_url'] }}"
							       class="rounded generated-video">
								Your browser does not support the video tag.
							</video>
							<p><small class="text-muted">Video generated. Path: {{ $part['video_path'] ?? 'N/A' }}</small></p>
							{{-- Optional: Add Regenerate/Delete Button --}}
							{{-- <button class="btn btn-sm btn-outline-warning generate-part-video-btn" ...>Regenerate</button> --}}
						</div>
					@else
						{{-- Display the Generate button only if video doesn't exist --}}
						<div class="mb-2 text-center">
							<button class="btn btn-outline-info generate-part-video-btn"
							        data-subject-id="{{ $subject->session_id }}"
							        data-part-index="{{ $partIndex }}"
							        data-generate-url="{{ route('lesson.part.generate.video', ['subject' => $subject->session_id, 'partIndex' => $partIndex]) }}">
								<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
								<i class="fas fa-video me-1"></i> Generate Video for Part {{ $partIndex + 1 }}
							</button>
							{{-- Placeholder where the video will be inserted by JS --}}
							<div class="video-placeholder mt-3" id="video-placeholder-{{ $partIndex }}" style="display: none;">
								<!-- Video tag will be inserted here by JS -->
							</div>
							<small class="text-muted d-block mt-1">Generates a short talking head video based on this part's
								text.</small>
							<div class="asset-generation-error text-danger small mt-1" id="video-error-{{ $partIndex }}"
							     style="display: none;"></div>
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
										<div class="mb-2" id="q-audio-area-{{ $quiz->id }}">
											<strong>Question Audio:</strong>
											@if($quiz->question_audio_url)
												<audio controls controlsList="nodownload noremoteplayback"
												       style="vertical-align: middle; height: 30px; margin-left: 5px;">
													<source src="{{ $quiz->question_audio_url }}" type="audio/mpeg">
													Your browser doesn't support audio.
												</audio>
											@else
												<button class="btn btn-sm btn-secondary generate-asset-btn ms-2"
												        data-url="{{ route('quiz.generate.audio.question', $quiz->id) }}"
												        data-asset-type="question-audio"
												        data-target-area="#q-audio-area-{{ $quiz->id }}">
													<i class="fas fa-volume-up"></i> Generate
												</button>
												<span class="asset-status ms-2"></span>
											@endif
										</div>
										
										{{-- Quiz Answer Audio --}}
										@php
											$answersNeedAudio = false;
											if (!empty($quiz->answers) && is_array($quiz->answers) && (empty($quiz->answers[0]['audio_path']) && empty($quiz->answers[0]['audio_url']))) {
													$answersNeedAudio = true;
											}
										@endphp
										<div class="mb-2" id="a-audio-area-{{ $quiz->id }}">
											<strong>Answer Audio:</strong>
											@if(!$answersNeedAudio && !empty($quiz->answers))
												<span class="text-success ms-2">Generated</span>
												{{-- Optionally list links or players for each answer audio here if needed --}}
											@else
												<button class="btn btn-sm btn-secondary generate-asset-btn ms-2"
												        data-url="{{ route('quiz.generate.audio.answers', $quiz->id) }}"
												        data-asset-type="answer-audio"
												        data-target-area="#a-audio-area-{{ $quiz->id }}">
													<i class="fas fa-microphone-alt"></i> Generate for All Answers
												</button>
												<span class="asset-status ms-2"></span>
											@endif
										</div>
										
										{{-- Quiz Image --}}
										<div class="mb-2" id="q-image-area-{{ $quiz->id }}">
											<strong>Image:</strong>
											@if($quiz->generatedImage && $quiz->generatedImage->image_medium_url)
												<div class="mt-2">
													<img src="{{ $quiz->generatedImage->image_medium_url }}"
													     alt="{{ $quiz->generatedImage->image_alt ?? 'Quiz image' }}" class="img-thumbnail"
													     style="max-width: 200px; max-height: 200px;">
													<p><small class="text-muted">Prompt: {{ $quiz->image_prompt_idea ?: 'N/A' }}</small></p>
												</div>
											@elseif(!empty($quiz->image_prompt_idea))
												<p><small class="text-muted">Prompt: {{ $quiz->image_prompt_idea }}</small></p>
												<button class="btn btn-sm btn-secondary generate-asset-btn ms-2"
												        data-url="{{ route('quiz.generate.image', $quiz->id) }}"
												        data-asset-type="quiz-image"
												        data-target-area="#q-image-area-{{ $quiz->id }}">
													<i class="fas fa-image"></i> Generate
												</button>
												<span class="asset-status ms-2"></span>
											@else
												<span class="text-muted ms-2">No image prompt provided.</span>
											@endif
										</div>
										
										{{-- List Answers (Read Only) --}}
										<ul class="list-unstyled mt-2">
											@foreach($quiz->answers as $ansIndex => $answer)
												<li>
													{{ $ansIndex + 1 }}. {{ $answer['text'] }}
													@if($answer['is_correct'])
														<strong class="text-success">(Correct)</strong>
													@endif
													<br><small class="text-muted">Feedback: {{ $answer['feedback'] }}</small>
												</li>
											@endforeach
										</ul>
									
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
		<p class="text-danger">Lesson part data is missing or invalid.</p>
	@endif

@endsection

@push('scripts')
	{{-- Make sure common.js is loaded if it contains shared functions like setLoading --}}
	{{-- <script src="{{ asset('js/common.js') }}"></script> --}}
	<script>
		document.addEventListener('DOMContentLoaded', () => {
			
			// --- Helper Functions ---
			function showSpinner(button, show = true) {
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
			
			function createVideoElement(url, targetElementId) {
				const placeholder = document.getElementById(targetElementId);
				if (!placeholder) return;
				
				placeholder.innerHTML = ''; // Clear placeholder
				
				const video = document.createElement('video');
				video.src = url;
				video.controls = true;
				video.preload = 'metadata';
				video.classList.add('rounded', 'generated-video', 'mb-2');
				
				placeholder.appendChild(video);
				placeholder.style.display = 'block'; // Show the placeholder div
			}
			
			function createAudioPlayer(url, targetElementId) {
				const placeholder = document.getElementById(targetElementId);
				if (!placeholder) return;
				placeholder.innerHTML = ''; // Clear if regenerating
				
				const audio = document.createElement('audio');
				audio.src = url;
				audio.controls = true;
				audio.controlsList = "nodownload noremoteplayback";
				audio.preload = "none";
				audio.style.height = "25px";
				
				placeholder.appendChild(audio);
				placeholder.style.display = 'inline-block'; // Adjust display
			}
			
			function createImageElement(smallUrl, originalUrl, targetElementId) {
				const placeholder = document.getElementById(targetElementId);
				if (!placeholder) return;
				placeholder.innerHTML = ''; // Clear if regenerating
				
				const link = document.createElement('a');
				link.href = originalUrl;
				link.target = '_blank';
				
				const img = document.createElement('img');
				img.src = smallUrl;
				img.alt = 'Generated Quiz Image';
				img.classList.add('img-thumbnail', 'quiz-image-thumb', 'mb-1');
				
				link.appendChild(img);
				placeholder.appendChild(link);
				
				// Add checkmark icon
				const icon = document.createElement('i');
				icon.classList.add('fas', 'fa-check-circle', 'asset-generated', 'asset-status-icon', 'd-block', 'mx-auto');
				icon.title = 'Image Generated';
				placeholder.appendChild(icon);
				
				placeholder.style.display = 'block';
			}
			
			
			// --- Event Listeners ---
			
			// Generate Video for Specific Part Button
			document.querySelectorAll('.generate-part-video-btn').forEach(button => {
				button.addEventListener('click', async (event) => {
					const btn = event.currentTarget;
					const partIndex = btn.dataset.partIndex;
					const url = btn.dataset.generateUrl;
					const errorElId = `video-error-${partIndex}`;
					const placeholderId = `video-placeholder-${partIndex}`;
					
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
							throw new Error(result.message || `HTTP error ${response.status}`);
						}
						
						// Success
						createVideoElement(result.video_url, placeholderId);
						btn.style.display = 'none'; // Hide button after success
						
					} catch (error) {
						console.error(`Error generating video for part ${partIndex}:`, error);
						showError(errorElId, `Failed: ${error.message}`);
						showSpinner(btn, false); // Re-enable button on error
					}
				});
			});
			
			// Generate Quiz Audio (Question or Answers) Button
			document.querySelectorAll('.generate-quiz-audio-btn').forEach(button => {
				button.addEventListener('click', async (event) => {
					const btn = event.currentTarget;
					const quizId = btn.dataset.quizId;
					const type = btn.dataset.type; // 'question' or 'answers'
					const url = btn.dataset.url;
					const errorElId = (type === 'question' ? `q-audio-error-${quizId}` : `a-audio-error-${quizId}`);
					const placeholderId = (type === 'question' ? `q-audio-player-${quizId}` : null); // Only question has a direct player placeholder here
					
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
							throw new Error(result.message || `HTTP error ${response.status}`);
						}
						
						// Success
						if (type === 'question' && result.audio_url && placeholderId) {
							createAudioPlayer(result.audio_url, placeholderId);
							// Update status icon
							const icon = btn.closest('li').querySelector(`i.asset-pending[title*='Audio Pending']`); // More specific selector if needed
							if (icon) {
								icon.classList.remove('fa-clock', 'asset-pending');
								icon.classList.add('fa-check-circle', 'asset-generated');
								icon.title = 'Audio Generated';
							}
							btn.style.display = 'none'; // Hide button
						} else if (type === 'answers') {
							// Update status icon for answers audio
							const icon = btn.closest('li').querySelector(`i.asset-pending[title*='Answer Audio Pending']`);
							if (icon) {
								icon.classList.remove('fa-clock', 'asset-pending');
								icon.classList.add('fa-check-circle', 'asset-generated');
								icon.title = 'Answer Audio Generated';
								// Optionally add the "Audio generated" text
								const smallText = document.createElement('small');
								smallText.classList.add('ms-2');
								smallText.textContent = '(Audio generated for answers/feedback)';
								icon.parentNode.appendChild(smallText); // Append after the icon
							}
							btn.style.display = 'none'; // Hide button
						}
						
					} catch (error) {
						console.error(`Error generating ${type} audio for quiz ${quizId}:`, error);
						showError(errorElId, `Failed: ${error.message}`);
						showSpinner(btn, false); // Re-enable on error
					}
				});
			});
			
			
			// Generate Quiz Image Button
			document.querySelectorAll('.generate-quiz-image-btn').forEach(button => {
				button.addEventListener('click', async (event) => {
					const btn = event.currentTarget;
					const quizId = btn.dataset.quizId;
					const url = btn.dataset.url;
					const errorElId = `image-error-${quizId}`;
					const placeholderId = `image-placeholder-${quizId}`;
					
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
							throw new Error(result.message || `HTTP error ${response.status}`);
						}
						
						// Success
						if (result.image_urls && result.image_urls.small && result.image_urls.original) {
							createImageElement(result.image_urls.small, result.image_urls.original, placeholderId);
							// Hide the original pending icon if it exists
							const icon = btn.closest('div').querySelector('i.asset-missing');
							if(icon) icon.style.display = 'none';
							btn.style.display = 'none'; // Hide button
						} else {
							throw new Error("Image URLs missing in response.");
						}
						
					} catch (error) {
						console.error(`Error generating image for quiz ${quizId}:`, error);
						showError(errorElId, `Failed: ${error.message}`);
						showSpinner(btn, false); // Re-enable on error
					}
					
				});
			});
			
			
		}); // End DOMContentLoaded
	</script>
@endpush
