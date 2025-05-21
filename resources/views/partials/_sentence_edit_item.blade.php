@php
	// Default values if $sentence is null (for template)
	$sentenceText = $sentence['text'] ?? 'SENTENCE_TEXT_PLACEHOLDER';
	$audioUrl = $sentence['audio_url'] ?? null;
	$promptIdea = $sentence['image_prompt_idea'] ?? '';
	$searchKeywords = $sentence['image_search_keywords'] ?? '';
	$imageId = $sentence['generated_image_id'] ?? null;
	$imageUrl = null; // Will be fetched by JS if imageId exists

	// Generate unique IDs based on indices
	$sentenceIdBase = "s{$sentenceIndex}";
	$audioControlsId = "sent-audio-controls-{$sentenceIdBase}";
	$audioErrorId = "sent-audio-error-{$sentenceIdBase}";
	$imageDisplayId = "sent-image-display-{$sentenceIdBase}";
	$imageErrorId = "sent-image-error-{$sentenceIdBase}";
	$imageSuccessId = "sent-image-success-{$sentenceIdBase}";
	$imagePromptInputId = "sent-prompt-input-{$sentenceIdBase}";
	$imageKeywordsInputId = "sent-keywords-input-{$sentenceIdBase}"; // Maybe hidden
	$imageActionsId = "sent-image-actions-{$sentenceIdBase}";
	$fileInputId = "sent-file-input-{$sentenceIdBase}";
	$itemContainerId = "sentence-item-{$sentenceIdBase}";

	// URLs for actions (replace placeholders if using template)
	 $lessonId = $lesson->id ?? 'LESSON_ID_PLACEHOLDER';
	 $generateImageUrl = $sentence ? route('sentence.generate.image', ['lesson' => $lessonId, 'sentenceIndex' => $sentenceIndex]) : '#';
	 $uploadImageUrl = $sentence ? route('sentence.image.upload', ['lesson' => $lessonId, 'sentenceIndex' => $sentenceIndex]) : '#';
	 $searchFreepikUrl = $sentence ? route('sentence.image.search_freepik', ['lesson' => $lessonId, 'sentenceIndex' => $sentenceIndex]) : '#';

@endphp

<div class="sentence-item d-flex align-items-start border-bottom py-2" id="{{ $itemContainerId }}" data-sentence-index="{{ $sentenceIndex }}" data-image-id="{{ $imageId ?? '' }}">
	{{-- Sentence Text & Audio --}}
	<div class="flex-grow-1 me-3">
		<p class="mb-1 sentence-text">{{ $sentenceText }}</p>
		<div class="d-flex align-items-center">
			{{-- Image Generation Buttons (Moved here) --}}
			<div class="btn-group btn-group-sm me-2" role="group" aria-label="Sentence Image Actions" id="{{ $imageActionsId }}">
				<button class="btn btn-outline-primary generate-sentence-image-btn"
				        title="Generate AI image using the suggested prompt below"
				        data-prompt-input-id="{{ $imagePromptInputId }}"
				        data-url="{{ $generateImageUrl }}"
				        data-error-area-id="{{ $imageErrorId }}">
					<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
					<i class="fas fa-magic"></i> Generate
				</button>
				<button class="btn btn-outline-info search-freepik-sentence-btn"
				        title="Search Freepik using keywords"
				        data-keywords-input-id="{{ $imageKeywordsInputId }}"
				        data-freepik-search-url="{{ $searchFreepikUrl }}"
				        data-bs-toggle="modal" data-bs-target="#freepikSearchModal">
					<i class="fas fa-search"></i> Stock Photo</button>
				<button class="btn btn-outline-secondary trigger-sentence-upload-btn"
				        title="Upload image"
				        data-file-input-id="{{ $fileInputId }}">
					<i class="fas fa-upload"></i> Upload
				</button>
				{{-- Hidden file input --}}
				<input type="file" accept="image/*" class="d-none sentence-image-file-input" id="{{ $fileInputId }}">
			</div>
			
			{{-- Audio Controls --}}
			<span id="{{ $audioControlsId }}" class="me-2 sentence-audio-controls" style="min-width: 50px;">
                @if($audioUrl)
					<button class="btn btn-sm btn-outline-primary btn-play-pause" data-audio-url="{{ $audioUrl }}" data-error-area-id="{{ $audioErrorId }}" title="Play Sentence Audio">
                        <i class="fas fa-play"></i><i class="fas fa-pause"></i>
                        <span class="audio-duration ms-1"></span>
                    </button>
				@else
					<span class="badge bg-light text-dark" title="Audio not generated"><i class="fas fa-volume-mute"></i></span>
				@endif
            </span>
			<span id="{{ $audioErrorId }}" class="text-danger small sentence-audio-error"></span>
			
			{{-- Error/Success Messages for Image --}}
			<div class="p-1" style="font-size: 0.75em;">
				<span id="{{ $imageErrorId }}" class="text-danger sentence-image-error"></span>
				<span id="{{ $imageSuccessId }}" class="text-success sentence-image-success"></span>
			</div>
		
		</div>
		
		{{-- Hidden inputs for prompts/keywords --}}
		<input type="hidden" id="{{ $imagePromptInputId }}" value="{{ $promptIdea }}" class="sentence-prompt-idea">
		<input type="hidden" id="{{ $imageKeywordsInputId }}" value="{{ $searchKeywords }}" class="sentence-search-keywords">
	</div>
	
	{{-- Image Area --}}
	<div class="flex-shrink-0" style="width: 60px;"> {{-- Fixed width for image area --}}
		<div id="{{ $imageDisplayId }}" class="w-100 h-100 d-flex align-items-center justify-content-center  sentence-image-display" style="height: 60px;">
			<i class="fas fa-image text-muted fa-lg"></i>
		</div>
		
	</div>
	
</div>
