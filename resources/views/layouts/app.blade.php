<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="csrf-token" content="{{ csrf_token() }}">
	
	<title>@yield('title', 'Learn with AI')</title>
	
	<link rel="preconnect" href="https://fonts.bunny.net">
	<link href="https://fonts.bunny.net/css?family=figtree:400,600&display=swap" rel="stylesheet"/>
	<link href="/vendor/bootstrap5.3.5/css/bootstrap.min.css" rel="stylesheet" />
	<link rel="stylesheet" href="/vendor/fontawesome-free-6.7.2/css/all.min.css" />
	
	<link rel="stylesheet" href="{{ asset('css/base.css') }}">
	<link rel="stylesheet" href="{{ asset('css/components.css') }}">
	<link rel="stylesheet" href="{{ asset('css/lesson-interface.css') }}">
	<link rel="stylesheet" href="{{ asset('css/lesson-edit.css') }}">
	<link rel="stylesheet" href="{{ asset('css/progress-report.css') }}">
	<link rel="stylesheet" href="{{ asset('css/dark-mode.css') }}">
	
	@stack('styles')
	
	<script>
		(function () {
			const darkModeEnabled = localStorage.getItem('darkModeEnabled') === 'true';
			if (darkModeEnabled) {
				document.documentElement.classList.add('dark-mode');
			}
		})();
	</script>
</head>
<body class="antialiased">

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
	<audio id="feedbackAudioPlayer" style="display: none;" preload="auto"></audio>
	<audio id="sharedAudioPlayer" style="display: none;" preload="auto"></audio>
	<audio id="ttsAudioPlayer" style="display: none;" preload="auto"></audio>
	
	<!-- Generic Image Modal -->
	<div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-xl modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="imageModalLabel">Image Preview</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body text-center">
					<img src="" id="imageModalDisplay" alt="Image Preview" class="img-fluid"
					     style="max-height: 80vh;">
				</div>
			</div>
		</div>
	</div>


</div> <!-- End Container -->

<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1100">
	<div id="toast" class="toast hide" role="alert" aria-live="assertive" aria-atomic="true">
		<div class="toast-header">
			<strong class="me-auto" id="toastTitle">Notification</strong>
			<button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
		</div>
		<div class="toast-body" id="toastMessage"></div>
	</div>
</div>

<script src="/vendor/bootstrap5.3.5/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/common.js') }}"></script>
@stack('scripts')
</body>
</html>
