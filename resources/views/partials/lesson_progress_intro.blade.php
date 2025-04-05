@push('styles')
	<style>
      /* Progress Bar */
      .progress-container {
          margin-bottom: 1.5rem;
          padding: 0.5rem;
          background-color: var(--bs-tertiary-bg);
          border-radius: 0.375rem; /* Match Bootstrap's default */
      }
      .progress {
          height: 25px; /* Make progress bar thicker */
          font-size: 0.85rem; /* Adjust font size inside */
      }
      .progress-bar {
          transition: width 0.6s ease; /* Smooth transition */
      }
      .part-indicator {
          display: flex;
          justify-content: space-around;
          margin-top: 0.5rem;
          font-size: 0.9em;
      }
      .part-label {
          text-align: center;
          flex: 1; /* Distribute space */
          cursor: default; /* No clicking for now */
          padding: 0.2rem;
          border-radius: 0.25rem;
          transition: background-color 0.3s ease;
      }
      .part-label.active {
          font-weight: bold;
          background-color: rgba(var(--bs-primary-rgb), 0.2); /* Highlight active part */
      }
      .part-label.completed {
          color: var(--bs-secondary); /* Gray out completed */
          text-decoration: line-through;
      }


      /* Part Intro Area */
      #partIntroArea {
          background-color: var(--bs-light);
          border: 1px solid var(--bs-border-color);
          border-radius: 0.375rem;
          padding: 1.5rem;
          margin-bottom: 1.5rem;
          transition: opacity 0.5s ease-in-out;
      }
      .dark-mode #partIntroArea {
          background-color: var(--bs-secondary-bg);
          border-color: var(--bs-border-color);
      }
      #partIntroArea.d-none { /* Ensure smooth fade out */
          opacity: 0;
      }
      #partIntroVideo {
          max-width: 100%;
          max-height: 300px; /* Limit video height */
          border-radius: 0.25rem;
      }
	</style>
@endpush

{{-- 1. Progress Bar and Part Indicators --}}
<div class="progress-container shadow-sm mb-4"> {{-- Added mb-4 for spacing --}}
	<div class="progress" role="progressbar" aria-label="Lesson Progress" aria-valuenow="0" aria-valuemin="0"
	     aria-valuemax="100">
		<div class="progress-bar progress-bar-striped progress-bar-animated" id="progressBar" style="width: 0%">0%</div>
	</div>
	<div class="part-indicator" id="partIndicatorContainer">
		{{-- Loop needs totalParts passed from parent --}}
		@isset($totalParts)
			@for ($i = 0; $i < $totalParts; $i++)
				<span class="part-label" id="partLabel_{{ $i }}">Part {{ $i + 1 }}</span>
			@endfor
		@else
			<span class="text-muted small">Progress unavailable</span>
		@endisset
	</div>
</div>

{{-- 2. Part Introduction Area (Video & Text) - Initially hidden/shown by JS --}}
<div id="partIntroArea" class="d-none"> {{-- Add mb-4 here too if desired --}}
	<h4 id="partIntroTitle" class="mb-3"></h4>
	<div class="row align-items-center">
		<div class="col-md-5 text-center mb-3 mb-md-0">
			{{-- Consider adding a wrapper for better loading state handling if needed --}}
			<video id="partIntroVideo" controls preload="metadata"
			       class="d-none img-fluid rounded"> {{-- Added img-fluid and rounded --}}
				Your browser does not support the video tag.
			</video>
			<div id="partIntroVideoPlaceholder" class="video-placeholder d-none"> {{-- Use existing style --}}
				<i class="fas fa-photo-video fa-3x text-muted mb-2"></i>
				<span>(No video for this part)</span>
			</div>
		</div>
		<div class="col-md-7">
			<p id="partIntroText" class="lead"></p> {{-- Used lead class for slightly larger text --}}
		</div>
	</div>
	<hr class="my-4"> {{-- Added margin --}}
	<div class="text-center">
		<button id="startPartQuizButton" class="btn btn-primary btn-lg"> {{-- Larger button --}}
			Start Part {{-- Number added by JS --}} Quiz
		</button>
	</div>
</div>
