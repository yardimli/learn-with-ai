<div class="progress-container mb-4">
	{{-- Progress Bar --}}
	<div class="progress" role="progressbar" aria-label="Lesson Progress" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="height: 25px;">
		<div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%">0%</div>
	</div>
	{{-- Part Indicator --}}
	@if($totalParts > 0)
		<div id="partIndicatorContainer" class="part-indicator mt-2">
			@for ($i = 0; $i < $totalParts; $i++)
				{{-- Use unique IDs if needed, but class/data attribute is sufficient for JS --}}
				<span class="part-label" data-part-index="{{ $i }}">Part {{ $i + 1 }}</span>
			@endfor
		</div>
	@endif
</div>

{{-- Part Introduction Area --}}
<div id="partIntroArea" class="d-none">
	<h4 id="partIntroTitle" class="mb-3 text-center">Part Title</h4>
	
	<div class="row align-items-top">
		<div class="col-md-4 text-center mb-3 mb-md-0">
			<div id="introSentenceImageContainer" class="intro-image-container">
				<img id="introSentenceImage" src="{{ asset('images/placeholder_intro.png') }}"
				class="img-fluid rounded shadow-sm" alt="Sentence illustration" style="max-height: 250px; display: none;" > {{-- Initially hidden --}}
				<div id="introSentenceImagePlaceholder" class="text-muted small mt-2">
					(Image will appear here as audio plays)
				</div>
			</div>
			
			<div id="introPlaybackControls" class="text-center mb-3 mt-3 d-none">
				<button id="startOverIntroButton" class="btn btn-sm btn-outline-secondary" title="Restart Intro Audio & Images">
					<i class="fas fa-redo-alt me-1"></i> Start Over Intro
				</button>
			</div>
		
		</div>
		
		{{-- Right Column: Text & Controls --}}
		<div class="col-md-8">
			<div id="partIntroTextContainer" class="mb-3 lead" style="line-height: 1.8;">
				<p id="partIntroText" class="text-muted">Loading intro...</p>
			</div>
			
			
			<div class="text-center">
				<button id="startPartQuestionButton" class="btn btn-success btn-lg">
					Start Part Questions
					<i class="fas fa-arrow-right ms-2"></i>
				</button>
			</div>
		</div>
	</div> {{-- End Row --}}
</div>
