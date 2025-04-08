<div class="progress-container shadow-sm mb-4">
	<div class="progress" role="progressbar" aria-label="Lesson Progress" aria-valuenow="0" aria-valuemin="0"
	     aria-valuemax="100">
		<div class="progress-bar progress-bar-striped progress-bar-animated" id="progressBar" style="width: 0%">0%</div>
	</div>
	<div class="part-indicator" id="partIndicatorContainer">
		@isset($totalParts)
			@for ($i = 0; $i < $totalParts; $i++)
				<div class="part-label" id="partLabel_{{ $i }}" data-part-index="{{ $i }}">
					Part {{ $i + 1 }}
				</div>
			@endfor
		@else
			<span class="text-muted small">Progress unavailable</span>
		@endisset
	</div>
</div>

<div id="partIntroArea" class="d-none">
	<div class="row align-items-center">
		<div class="col-md-4 text-center mb-3 mb-md-0">
			<video id="partIntroVideo" controls preload="metadata"
			       class="d-none img-fluid rounded">
				Your browser does not support the video tag.
			</video>
			<div id="partIntroVideoPlaceholder" class="video-placeholder d-none">
				<i class="fas fa-photo-video fa-3x text-muted mb-2"></i>
				<span>(No video for this part)</span>
			</div>
		</div>
		<div class="col-md-8">
			<h4 id="partIntroTitle" class="mb-3"></h4>
			<p id="partIntroText" class="lead"></p>
		</div>
	</div>
	<hr class="my-4">
	<div class="text-center">
		<button id="startPartQuestionButton" class="btn btn-primary btn-lg">
			Start Part Question
		</button>
	</div>
</div>
