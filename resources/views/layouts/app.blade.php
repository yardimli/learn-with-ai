<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="csrf-token" content="{{ csrf_token() }}">
	
	<title>@yield('title', 'Learn with AI')</title>
	
	<link rel="preconnect" href="https://fonts.bunny.net">
	<link href="https://fonts.bunny.net/css?family=figtree:400,600&display=swap" rel="stylesheet"/>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"
	      integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
	      integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA=="
	      crossorigin="anonymous" referrerpolicy="no-referrer"/>
	{{-- Link the common CSS file --}}
	<link rel="stylesheet" href="{{ asset('css/app.css') }}">
	
	@stack('styles') {{-- For page-specific styles --}}
	
	<script>
		(function () {
			const darkModeEnabled = localStorage.getItem('darkModeEnabled') === 'true';
			if (darkModeEnabled) {
				document.documentElement.classList.add('dark-mode'); // Apply to <html>
			}
			// Ensure body exists before trying to add class (though applying to html is better for early application)
			// document.addEventListener('DOMContentLoaded', () => {
			//    if (darkModeEnabled) document.body.classList.add('dark-mode');
			// });
		})();
	</script>
	
	<style>
      /* Style for the switch container */
      .dark-mode-switch-container {
          position: fixed; /* Or absolute if you prefer relative to a parent */
          top: 1rem;
          right: 1rem;
          z-index: 1050; /* Ensure it's above most content */
          background: rgba(var(--bs-body-bg-rgb), 0.8); /* Semi-transparent background */
          padding: 0.4rem 0.6rem;
          border-radius: 50px; /* Rounded pill shape */
          backdrop-filter: blur(3px); /* Optional: blur background */
          display: flex;
          align-items: center;
      }

      .dark-mode-switch-container .form-check-label {
          margin-left: 0.5rem;
          cursor: pointer; /* Make label clickable */
      }

      .dark-mode-switch-container .form-switch .form-check-input {
          cursor: pointer;
          width: 2.5em; /* Slightly larger switch */
          height: 1.3em;
      }
	</style>

</head>
<body class="antialiased">

{{-- Dark Mode Switch --}}
<div class="dark-mode-switch-container shadow-sm">
	<div class="form-check form-switch">
		<input class="form-check-input" type="checkbox" role="switch" id="darkModeSwitch">
		<label class="form-check-label" for="darkModeSwitch">
			<i class="fas fa-moon" id="darkModeIconMoon"></i>
			<i class="fas fa-sun d-none" id="darkModeIconSun"></i>
		</label>
	</div>
</div>

<div class="container mt-4 mb-5">
	
	<!-- Loading Indicator -->
	<div id="loadingOverlay" class="loading-overlay d-none">
		<div class="spinner-border text-primary" role="status">
			<span class="visually-hidden">Loading...</span>
		</div>
		<span id="loadingMessage" class="ms-2 fs-5">Generating...</span>
	</div>
	
	<!-- Error Message Area -->
	<div id="errorMessageArea" class="alert alert-danger alert-dismissible fade show d-none" role="alert">
		<span id="errorMessageText"></span>
		<button type="button" class="btn-close" id="closeErrorButton" aria-label="Close"></button>
	</div>
	
	@yield('content')
	
	<!-- Hidden audio element (Maybe move to question page only if not needed globally) -->
	<audio id="feedbackAudioPlayer" style="display: none;"></audio>
	
	<!-- Hidden audio element for JS control -->
	<audio id="sharedAudioPlayer" style="display: none;"></audio>
	
	<!-- Generic Image Modal -->
	<div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-xl modal-dialog-centered"> {{-- Extra large and centered --}}
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="imageModalLabel">Image Preview</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body text-center"> {{-- Center the image --}}
					<img src="" id="imageModalDisplay" alt="Image Preview" class="img-fluid"
					     style="max-height: 80vh;"> {{-- Responsive, limit height --}}
				</div>
			</div>
		</div>
	</div>


</div> <!-- End Container -->

<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
	<div id="toast" class="toast hide" role="alert" aria-live="assertive" aria-atomic="true">
		<div class="toast-header">
			<strong class="me-auto" id="toastTitle">Notification</strong>
			<button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
		</div>
		<div class="toast-body" id="toastMessage"></div>
	</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
        crossorigin="anonymous"></script>
{{-- Common JS utilities if needed --}}
<script src="{{ asset('js/common.js') }}"></script>
@stack('scripts') {{-- For page-specific scripts --}}
</body>
</html>
