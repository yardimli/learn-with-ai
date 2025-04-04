@extends('layouts.app')
@section('title', 'Edit Lesson Assets: ' . $subject->title)
@push('styles')
	<style>
      audio {
          max-width: 250px;
          height: 35px;
          vertical-align: middle;
      }

      .answer-list li {
          margin-bottom: 0.5rem; /* Reduced */
          padding-bottom: 0.5rem; /* Reduced */
          border-bottom: 1px solid var(--bs-tertiary-bg);
          font-size: 0.95em; /* Slightly smaller answer text */
      }

      .answer-list li:last-child {
          border-bottom: none;
          margin-bottom: 0;
          padding-bottom: 0;
      }

      .answer-text-content, .feedback-text-content {
          display: inline; /* Allow button next to text */
      }

      .asset-container h6 {
          font-size: 1em; /* Slightly smaller asset titles */
      }

      .quiz-item p strong { /* Question text */
          display: inline; /* Allow button next to text */
          margin-bottom: 0; /* Reset margin */
          font-size: 1.05em; /* Adjust size */
      }

      .quiz-item .question-line {
          margin-bottom: 0.75rem; /* Space below question */
      }
	</style>
@endpush

@section('content')
	<div class="d-flex justify-content-between align-items-center mb-3">
		<a href="{{ route('home') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back to Home</a>
		<a href="{{ route('content.show', ['subject' => $subject->session_id]) }}" class="btn btn-outline-success"><i
				class="fas fa-eye"></i> View Live Lesson</a>
	</div>
	
	<div class="content-card mb-4">
		<h1 class="mb-1">Edit Lesson Assets: {{ $subject->title }}</h1>
		<p class="text-muted mb-3">Subject: {{ $subject->name }} (ID: {{ $subject->id }},
			Session: {{ $subject->session_id }})</p>
		<p><small>Use the buttons below to generate missing assets or regenerate images. Click audio icons (<i
					class="fas fa-play text-primary"></i>) to listen. Click images to enlarge.</small></p>
	</div>
	
	{{-- Lesson Parts and Quizzes --}}
	@if (!empty($subject->lesson_parts))
		@foreach($subject->lesson_parts as $partIndex => $part)
			<div class="content-card mb-4">
				<h3 class="mb-3">Lesson Part {{ $partIndex + 1 }}: {{ $part['title'] }}</h3>
				<p>{{ $part['text'] }}</p>
				
				<div class="asset-container mb-3 generated-video-container">
					<h6><i class="fas fa-film me-2 text-primary"></i>Part Video</h6>
					@if(isset($part['video_url']) && !empty($part['video_url']))
						<div class="mb-2 text-center" id="video-display-{{ $partIndex }}">
							<video controls preload="metadata"
							       src="{{ Storage::disk('public')->url($part['video_path']) /* Get URL from storage */ }}"
							       class="generated-video">
								Your browser does not support the video tag.
							</video>
							<p><small class="text-muted d-block mt-1">Video generated.
									Path: {{ $part['video_path'] ?? 'N/A' }}</small></p>
						</div>
						<div class="video-placeholder mt-3" id="video-placeholder-{{ $partIndex }}" style="display: none;"></div>
						<div class="text-center" id="video-button-area-{{ $partIndex }}" style="display: none;">
							{{-- Button initially hidden if video exists --}}
							<button class="btn btn-outline-info generate-part-video-btn" data-subject-id="{{ $subject->session_id }}"
							        data-part-index="{{ $partIndex }}"
							        data-generate-url="{{ route('lesson.part.generate.video', ['subject' => $subject->session_id, 'partIndex' => $partIndex]) }}">
								<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
								<i class="fas fa-video me-1"></i> Regenerate Video
							</button>
							<div class="asset-generation-error text-danger small mt-1" id="video-error-{{ $partIndex }}"
							     style="display: none;"></div>
						</div>
					@else
						<div class="mb-2 text-center" id="video-button-area-{{ $partIndex }}">
							<button class="btn btn-outline-info generate-part-video-btn" data-subject-id="{{ $subject->session_id }}"
							        data-part-index="{{ $partIndex }}"
							        data-generate-url="{{ route('lesson.part.generate.video', ['subject' => $subject->session_id, 'partIndex' => $partIndex]) }}">
								<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
								<i class="fas fa-video me-1"></i> Generate Video
							</button>
							<small class="text-muted d-block mt-1">Generates a short talking head video based on this part's
								text.</small>
							<div class="asset-generation-error text-danger small mt-1" id="video-error-{{ $partIndex }}"
							     style="display: none;"></div>
						</div>
						<div class="video-placeholder mt-3" id="video-placeholder-{{ $partIndex }}" style="display: none;"></div>
						<div class="mb-2 text-center" id="video-display-{{ $partIndex }}" style="display: none;"></div>
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
										
										{{-- Quiz Image & Prompt --}}
										<div class="asset-container my-2" id="q-image-container-{{ $quiz->id }}">
											<div class="d-flex align-items-start">
												<div id="q-image-display-{{ $quiz->id }}" class="me-3">
													@if($quiz->generatedImage && $quiz->generatedImage->medium_url)
														<a href="#" class="quiz-image-clickable" data-bs-toggle="modal" data-bs-target="#imageModal"
														   data-image-url="{{ $quiz->generatedImage->original_url }}"
														   data-image-alt="{{ $quiz->generatedImage->image_alt }}" title="Click to enlarge">
															<img src="{{ $quiz->generatedImage->medium_url }}"
															     alt="{{ $quiz->generatedImage->image_alt }}" class="img-thumbnail quiz-image-thumb">
														</a>
													@elseif(empty($quiz->image_prompt_idea))
														<span
															class="text-muted quiz-image-thumb d-inline-block border rounded p-2 text-center align-middle"
															style="line-height: 130px; width: 150px; height: 150px; background: var(--bs-tertiary-bg);">No Prompt</span>
													@else
														<span
															class="text-muted quiz-image-thumb d-inline-block border rounded p-2 text-center align-middle"
															style="line-height: 130px; width: 150px; height: 150px; background: var(--bs-tertiary-bg);">No Image</span>
													@endif
												</div>
												<div class="flex-grow-1">
													<div class="question-line">
														<strong>Q: {{ $quiz->question_text }}</strong>
														{{-- Question Audio Play/Generate --}}
														<span id="q-audio-controls-{{ $quiz->id }}">
                                                @if($quiz->question_audio_url)
																<button class="btn btn-sm btn-outline-primary btn-play-pause"
																        data-audio-url="{{ $quiz->question_audio_url }}" title="Play Question Audio">
                                                        <i class="fas fa-play"></i><i class="fas fa-pause"></i>
                                                    </button>
															@else
																<button class="btn btn-sm btn-outline-secondary generate-asset-btn"
																        data-url="{{ route('quiz.generate.audio.question', $quiz->id) }}"
																        data-asset-type="question-audio" data-quiz-id="{{ $quiz->id }}"
																        data-target-area-id="q-audio-controls-{{ $quiz->id }}" {{-- Target the span --}}
																        data-button-area-id="q-audio-controls-{{ $quiz->id }}" {{-- Not really needed now --}}
																        data-error-area-id="q-audio-error-{{ $quiz->id }}"
																        title="Generate Question Audio">
                                                        <span class="spinner-border spinner-border-sm d-none"
                                                              role="status" aria-hidden="true"></span>
                                                        <i class="fas fa-microphone-alt"></i> Gen
                                                    </button>
															@endif
                                            </span>
														<div class="asset-generation-error text-danger small mt-1 d-inline-block"
														     id="q-audio-error-{{ $quiz->id }}" style="display: none;"></div>
													</div>
													
													<h6><i class="fas fa-image me-1 text-success"></i>Quiz Image</h6>
													@if(!empty($quiz->image_prompt_idea) || $quiz->generatedImage)
														<div class="quiz-image-prompt-group mb-2">
															<input type="text" class="form-control form-control-sm quiz-image-prompt-input"
															       id="prompt-input-{{ $quiz->id }}"
															       value="{{ $quiz->image_prompt_idea }}"
															       placeholder="Image Prompt"
																{{-- Disable if no prompt initially and no image? Maybe not needed --}}
															>
															<button class="btn btn-sm btn-outline-primary regenerate-quiz-image-btn"
															        data-url="{{ route('quiz.generate.image', $quiz->id) }}"
															        data-quiz-id="{{ $quiz->id }}"
															        data-prompt-input-id="prompt-input-{{ $quiz->id }}"
															        data-target-area-id="q-image-display-{{ $quiz->id }}"
															        data-error-area-id="q-image-error-{{ $quiz->id }}"
															        title="Generate or Regenerate Image">
																<span class="spinner-border spinner-border-sm d-none" role="status"
																      aria-hidden="true"></span>
																<i class="fas fa-sync-alt"></i> {{ $quiz->generatedImage ? 'Regen' : 'Generate' }}
															</button>
														</div>
														<div class="asset-generation-error text-danger small" id="q-image-error-{{ $quiz->id }}"
														     style="display: none;"></div>
													@else
														<span class="text-muted small">No image prompt provided.</span>
													@endif
												</div>
											</div>
										</div>
										
										
										{{-- Answer & Feedback Audio (Single Generate Button) --}}
										@php
											$answersNeedAudio = false; // Assume they don't need unless proven otherwise
											$answersExist = !empty($quiz->answers) && is_array($quiz->answers);
											if ($answersExist) {
													// Check first answer for *either* audio URL missing
													if (empty($quiz->answers[0]['answer_audio_url']) || empty($quiz->answers[0]['feedback_audio_url'])) {
															$answersNeedAudio = true;
													}
											}
										@endphp
										<div class="asset-container mb-2" id="a-audio-container-{{ $quiz->id }}">
											<h6><i class="fas fa-comments me-2 text-warning"></i>Answer & Feedback Audio</h6>
											<div id="a-audio-status-{{ $quiz->id }}" class="d-inline-block me-2">
												@if(!$answersExist)
													<span class="text-muted small">No answers found.</span>
												@elseif(!$answersNeedAudio)
													<span class="text-success small"><i class="fas fa-check-circle me-1"></i>Generated</span>
												@else
													<span class="text-muted small">Not generated</span>
												@endif
											</div>
											@if($answersExist && $answersNeedAudio)
												<button class="btn btn-sm btn-outline-secondary generate-asset-btn"
												        data-url="{{ route('quiz.generate.audio.answers', $quiz->id) }}"
												        data-asset-type="answer-audio" data-quiz-id="{{ $quiz->id }}"
												        data-target-area-id="a-audio-status-{{ $quiz->id }}"
												        data-button-area-id="a-audio-container-{{ $quiz->id }}"
												        {{-- Hide button inside container? --}}
												        data-error-area-id="a-audio-error-{{ $quiz->id }}"
												        title="Generate Audio for All Answers & Feedback">
													<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
													<i class="fas fa-microphone-alt"></i> Generate All
												</button>
											@endif
											<div class="asset-generation-error text-danger small d-inline-block ms-2"
											     id="a-audio-error-{{ $quiz->id }}" style="display: none;"></div>
										</div>
										
										{{-- List Answers (Always Visible) --}}
										@if($answersExist)
											<ul class="list-unstyled mt-2 ms-3 answer-list">
												@foreach($quiz->answers as $ansIndex => $answer)
													<li>
														<span class="answer-text-content">{{ $ansIndex + 1 }}. {{ $answer['text'] }}</span>
														@if($answer['is_correct'])
															<strong class="text-success">(Correct)</strong>
														@endif
														{{-- Answer Audio Play Button --}}
														<span id="ans-audio-controls-{{ $quiz->id }}-{{ $ansIndex }}">
                                                             @if(isset($answer['answer_audio_url']) && $answer['answer_audio_url'])
																<button class="btn btn-sm btn-outline-primary btn-play-pause"
																        data-audio-url="{{ $answer['answer_audio_url'] }}" title="Play Answer Audio">
                                                                    <i class="fas fa-play"></i><i
																		class="fas fa-pause"></i>
                                                                </button>
															@elseif(!$answersNeedAudio)
																<span class="badge bg-light text-dark ms-1"
																      title="Audio not generated or generation failed"><i
																		class="fas fa-volume-mute"></i></span>
															@endif
															{{-- Error placeholder can go here if needed --}}
                                                        </span>
														<br>
														<small class="text-muted feedback-text-content">Feedback: {{ $answer['feedback'] }}</small>
														{{-- Feedback Audio Play Button --}}
														<span id="fb-audio-controls-{{ $quiz->id }}-{{ $ansIndex }}">
                                                            @if(isset($answer['feedback_audio_url']) && $answer['feedback_audio_url'])
																<button class="btn btn-sm btn-outline-primary btn-play-pause"
																        data-audio-url="{{ $answer['feedback_audio_url'] }}"
																        title="Play Feedback Audio">
                                                                    <i class="fas fa-play"></i><i
																		class="fas fa-pause"></i>
                                                                </button>
															@elseif(!$answersNeedAudio)
																<span class="badge bg-light text-dark ms-1"
																      title="Audio not generated or generation failed"><i
																		class="fas fa-volume-mute"></i></span>
															@endif
															{{-- Error placeholder can go here if needed --}}
                                                        </span>
													</li>
												@endforeach
											</ul>
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
		<div class="alert alert-warning">Lesson part data is missing or invalid for this subject. Cannot display edit
			options.
		</div>
	@endif
@endsection

@push('scripts')
	<script src="{{ asset('js/lesson_edit.js') }}"></script>
@endpush
