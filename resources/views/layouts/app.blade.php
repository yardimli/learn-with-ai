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


<nav class="navbar navbar-expand-md shadow-sm mb-4">
	<div class="container">
		<a class="navbar-brand" href="{{ route('lessons.list') }}">
			{{ config('app.name', 'Learn with AI') }}
		</a>
		<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="{{ __('Toggle navigation') }}">
			<span class="navbar-toggler-icon"></span>
		</button>
		
		<div class="collapse navbar-collapse" id="navbarSupportedContent">
			<!-- Left Side Of Navbar -->
			<ul class="navbar-nav me-auto">
				@auth {{-- Show links only if user is logged in --}}
				<li class="nav-item">
					<a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('create-lesson') }}">Create Lesson</a>
				</li>
				<li class="nav-item">
					<a class="nav-link {{ request()->routeIs('lessons.list') ? 'active' : '' }}" href="{{ route('lessons.list') }}">My Lessons</a>
				</li>
				<li class="nav-item">
					<a class="nav-link {{ request()->routeIs('weekly.plan.configure') ? 'active' : '' }}" href="{{ route('weekly.plan.configure') }}">Weekly Plan</a>
				</li>
				<li class="nav-item dropdown">
					<a class="nav-link dropdown-toggle {{ request()->routeIs('category_management.*') ? 'active' : '' }}" href="#" id="categoryDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
						Manage Categories
					</a>
					<ul class="dropdown-menu" aria-labelledby="categoryDropdown">
						<li><a class="dropdown-item" href="{{ route('category_management.main.index') }}">Main Categories</a></li>
						<li><a class="dropdown-item" href="{{ route('category_management.sub.index') }}">Sub Categories</a></li>
					</ul>
				</li>
				@endauth
			</ul>
			
			<!-- Right Side Of Navbar -->
			<ul class="navbar-nav ms-auto">
				<!-- Authentication Links -->
				@guest
					@if (Route::has('login'))
						<li class="nav-item">
							<a class="nav-link" href="{{ route('login') }}">{{ __('Login') }}</a>
						</li>
					@endif
					
					@if (Route::has('register'))
						<li class="nav-item">
							<a class="nav-link" href="{{ route('register') }}">{{ __('Register') }}</a>
						</li>
					@endif
				@else
					<li class="nav-item dropdown">
						<a id="navbarDropdown" class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>
							{{ Auth::user()->name }}
						</a>
						
						<div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
							<a class="dropdown-item" href="{{ route('logout') }}"
							   onclick="event.preventDefault();
                                                 document.getElementById('logout-form').submit();">
								{{ __('Logout') }}
							</a>
							
							<form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
								@csrf
							</form>
						</div>
					</li>
				@endguest
			</ul>
		</div>
	</div>
</nav>
{{-- End Navbar --}}


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
