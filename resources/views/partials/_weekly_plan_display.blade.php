{{-- resources/views/partials/_weekly_plan_display.blade.php --}}
{{-- This view renders the actual populated plan table(s) --}}

@if (empty($populatedPlan))
	<div class="alert alert-info text-center" role="alert">
		@lang('weekly_plan.no_lessons_for_period')
	</div>
@else
	@php $weekCounter = 0; @endphp
	@foreach ($populatedPlan as $year => $months)
		@foreach ($months as $month => $weeks)
			@foreach ($weeks as $week => $weekData) {{-- $week is now 1, 2, 3, or 4 --}}
			@php
				$monthName = $monthNames[$month] ?? 'Unknown Month';
				// Generate week label like "Week 1", "Week 2", etc.
				$weekLabel = __('weekly_plan.week_header_numbered', ['week' => $week]);
				$weekCounter++;
			@endphp
			{{-- Add print page break logic if needed --}}
			<div class="weekly-plan-week-container @if($weekCounter > 1 && $weekCounter % 2 != 0) page-break-before @endif @if($weekCounter % 2 == 0) page-break-after-pair @endif">
				<div class="week-header">
					<h4 class="mb-0">
						<i class="fas fa-calendar-alt me-2"></i> {{-- Changed icon slightly --}}
						{{ $year }} - {{ $monthName }} - {{ $weekLabel }}
					</h4>
				</div>
				<div class="table-responsive mb-4"> {{-- Reduced margin for print --}}
					<table class="table table-bordered table-striped weekly-plan-table">
						<thead class="table-light">
						<tr>
							<th class="time-slot-header" style="width:10%;">@lang('weekly_plan.time')</th>
							@foreach ($internalDays as $dayKey)
								<th class="text-center" style="width:15%;">{{ $displayDays[$dayKey] }}</th>
							@endforeach
						</tr>
						</thead>
						<tbody>
						@foreach ($internalTimeSlots as $slotKey)
							<tr>
								<td class="time-slot-header">{{ $displayTimeSlots[$slotKey] }}</td>
								@foreach ($internalDays as $dayKey)
									<td>
										@php
											// Access lesson data using internal keys
											$slotContent = $weekData['lessons'][$dayKey][$slotKey] ?? ['type' => 'empty', 'title' => __('weekly_plan.empty_slot')];
										@endphp
										
										@if ($slotContent instanceof \App\Models\Lesson)
											@php $lesson = $slotContent; @endphp
											<div class="lesson-entry">
												<div class="lesson-title">
													<a href="{{ route('lesson.edit', $lesson->session_id) }}" title="{{ $lesson->title ?: $lesson->notes }}">
														{{ $lesson->title ?: ($lesson->notes ?: __('weekly_plan.untitled_lesson')) }}
													</a>
												</div>
												<div class="lesson-category" title="@lang('weekly_plan.category_label'): {{ $lesson->mainCategory->name ?? __('weekly_plan.no_category') }}">
													{{ $lesson->mainCategory->name ?? __('weekly_plan.no_category') }}
												</div>
												{{-- Optional: Display Y-M-W for debugging/clarity --}}
												{{-- <div class="lesson-debug-info" style="font-size: 0.7rem; color: #aaa;">
														{{ $lesson->year }}-{{ $lesson->month }}-W{{ $lesson->week }}
												</div> --}}
											</div>
										@elseif (is_array($slotContent) && isset($slotContent['type']))
											{{-- Handle PE, Review, Empty, Missing --}}
											<div class="empty-slot-placeholder"> {{-- Re-use placeholder style --}}
												@if ($slotContent['type'] === 'missing')
													<span class="text-warning" title="@lang('weekly_plan.no_lesson_tooltip')">
                                                                <i class="fas fa-exclamation-triangle me-1"></i>{{ $slotContent['title'] }}
                                                            </span>
												@elseif ($slotContent['type'] === 'pe' || $slotContent['type'] === 'review')
													<span class="activity-type">
                                                                <i class="fas {{ $slotContent['type'] === 'pe' ? 'fa-dumbbell' : 'fa-book-open' }} me-1"></i>{{ $slotContent['title'] }}
                                                            </span>
												@else
													{{-- Empty slot --}}
													{{ $slotContent['title'] }}
												@endif
											</div>
										@else
											{{-- Fallback for unexpected data --}}
											<div class="empty-slot-placeholder">
												@lang('weekly_plan.empty_slot')
											</div>
										@endif
									</td>
								@endforeach
							</tr>
						@endforeach
						</tbody>
					</table>
				</div>
			</div> {{-- End weekly-plan-week-container --}}
			@endforeach {{-- End Week Loop --}}
		@endforeach {{-- End Month Loop --}}
	@endforeach {{-- End Year Loop --}}
@endif
