@extends('layouts.app')

@section('title', 'Progress Report: ' . $lesson->title)

@section('content')
	<div class="d-flex justify-content-between align-items-center mb-3">
		<h1 class="h2">{{ $lesson->title }} - Progress Report</h1>
		<div>
			<a href="{{ route('question.interface', ['lesson' => $lesson->session_id]) }}" class="btn btn-success me-2">
				<i class="fas fa-play"></i> {{ $currentProgress['score'] > 0 || empty($archivedProgressSets) ? 'Continue Lesson' : 'Start Lesson Again' }}
			</a>
			<a href="{{ route('home') }}" class="btn btn-secondary">
				<i class="fas fa-list"></i> All Lessons
			</a>
		</div>
	</div>
	
	<div class="row">
		<div class="col-md-6 mb-4">
			<div class="content-card h-100">
				<h5><i class="fas fa-tasks me-2 text-primary"></i>Current Progress</h5>
				@if ($currentProgress['total_questions'] > 0)
					@php
						$currentPercentage = round(($currentProgress['score'] / $currentProgress['total_questions']) * 100);
					@endphp
					<p>You have answered <strong>{{ $currentProgress['score'] }}</strong> out of <strong>{{ $currentProgress['total_questions'] }}</strong> questions correctly on the first attempt without any errors in the current session.</p>
					<div class="progress mb-3" role="progressbar" aria-label="Current Progress" aria-valuenow="{{ $currentPercentage }}" aria-valuemin="0" aria-valuemax="100">
						<div class="progress-bar bg-primary" style="width: {{ $currentPercentage }}%;">
							{{ $currentPercentage }}%
						</div>
					</div>
					<small class="text-muted">This score reflects questions answered right on the very first try *since the last archive*.</small>
				@else
					@if (App\Models\UserAnswer::where('lesson_id', $lesson->id)->exists())
						<p class="text-muted">You have started the current session, but haven't scored any points yet (remember, only first correct answers count).</p>
					@else
						<p class="text-muted">No progress recorded yet for the current session. Click "Start Lesson Again" to begin!</p>
					@endif
				@endif
			</div>
		</div>
		
		<div class="col-md-6 mb-4">
			<div class="content-card h-100">
				<h5><i class="fas fa-archive me-2 text-warning"></i>Archived Progress History</h5>
				@if (!empty($archivedProgressSets))
					<p><small>Scores reflect performance on first attempts without errors for each archived session.</small></p>
					@foreach ($archivedProgressSets as $archiveSet)
						<div class="archive-entry">
							<p class="mb-1">
								<strong>Archived on:</strong> {{ $archiveSet['date']->format('M d, Y H:i') }}
							</p>
							<p>
								Scored <strong>{{ $archiveSet['score'] }} / {{ $archiveSet['total_questions'] }}</strong> correctly.
							</p>
							<div class="progress mb-3" role="progressbar" aria-label="Archived Progress on {{ $archiveSet['date']->format('Y-m-d') }}" aria-valuenow="{{ $archiveSet['percentage'] }}" aria-valuemin="0" aria-valuemax="100">
								<div class="progress-bar bg-warning text-dark" style="width: {{ $archiveSet['percentage'] }}%;">
									{{ $archiveSet['percentage'] }}%
								</div>
							</div>
						</div>
					@endforeach
				@else
					<p class="text-muted">No archived progress history found for this lesson.</p>
					<p><small>Complete a lesson session and click 'Archive' on the main lesson list to save your score here.</small></p>
				@endif
			</div>
		</div>
	</div>

@endsection

@push('scripts')
@endpush
