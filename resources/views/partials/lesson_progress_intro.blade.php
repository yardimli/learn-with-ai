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
	<h4 id="partIntroTitle" class="mb-3">Part Title</h4>
	
	{{-- Container for sentence spans (replaces video) --}}
	<div id="partIntroTextContainer" class="mb-3 lead" style="line-height: 1.8;"> {{-- Increased line height --}}
		{{-- Sentences will be loaded here by JS --}}
		<p id="partIntroText" class="text-muted">Loading intro...</p> {{-- Placeholder --}}
	</div>
	
	{{-- Button to start questions for this part --}}
	<div class="text-center">
		<button id="startPartQuestionButton" class="btn btn-success btn-lg">
			Start Questions for this Part <i class="fas fa-arrow-right ms-2"></i>
		</button>
	</div>
</div>
