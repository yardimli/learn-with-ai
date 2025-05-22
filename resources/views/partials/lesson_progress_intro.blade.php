<div class="progress-container mb-4">
	{{-- Progress Bar --}}
	<div class="progress" role="progressbar" aria-label="Lesson Progress" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="height: 25px;">
		<div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%">0%</div>
	</div>
</div>

{{-- Introduction Area --}}
<div id="IntroArea" class="d-none">
	<h4 id="IntroTitle" class="mb-3 text-center">Intro Title</h4>
	
	{{-- Video Area --}}
	<div id="introVideoArea" class="text-center mb-3 d-none">
		<video id="introVideoPlayer" width="100%" style="max-width: 1024px; border-radius: 0.25rem; box-shadow: 0 .125rem .25rem rgba(0,0,0,.075)!important;" controls>
			<source src="" type="video/mp4">
			Your browser does not support the video tag.
		</video>
	</div>
	
	{{-- Sentences/Image Area --}}
	<div id="introSentencesArea" class="d-none">
		<div class="row align-items-top">
			<div class="col-md-4 text-center mb-3 mb-md-0">
				<div id="introSentenceImageContainer" class="intro-image-container">
					<img id="introSentenceImage" src="{{ asset('images/placeholder_intro.png') }}" class="img-fluid rounded shadow-sm" alt="Sentence illustration" style="max-height: 250px; display: none;" >
					<div id="introSentenceImagePlaceholder" class="text-muted small mt-2">
						(Image will appear here as audio plays)
					</div>
				</div>
				<div id="introPlaybackControls" class="text-center mb-3 mt-3 d-none"> {{-- Shown only if sentences are active --}}
					<button id="startOverIntroButton" class="btn btn-sm btn-outline-secondary" title="Restart Intro Audio & Images">
						<i class="fas fa-redo-alt me-1"></i> Start Over Intro
					</button>
				</div>
			</div>
			{{-- Right Column: Text for Sentences --}}
			<div class="col-md-8">
				<div id="IntroTextContainer" class="mb-3 lead" style="line-height: 1.8;">
					{{-- Sentences will be populated here by JS --}}
				</div>
			</div>
		</div>
	</div>
	
	{{-- Full Text Display Area (when no video and no sentences) --}}
	<div id="introFullTextDisplayArea" class="mb-3 lead d-none" style="line-height: 1.8;">
		<p id="introFullTextContent" class="text-muted"></p>
	</div>
	
	{{-- Start Questions Button (common to all intro types) --}}
	<div class="text-center mt-4">
		<button id="startLessonButton" class="btn btn-success btn-lg">
			Start Questions <i class="fas fa-arrow-right ms-2"></i>
		</button>
	</div>
</div>
