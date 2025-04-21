{{-- learn-with-ai/resources/views/weekly_plan.blade.php --}}
@extends('layouts.app')

@section('title', __('weekly_plan.configure_title'))

@push('styles')
	<style>
      .config-table th, .config-table td {
          vertical-align: middle;
          text-align: center;
          min-width: 160px; /* Ensure dropdowns fit */
      }

      .config-table .time-slot-header {
          font-weight: bold;
          width: 100px;
          min-width: 100px;
      }

      .date-selector-group label {
          min-width: 80px;
      }

      #planDisplayArea {
          margin-top: 2rem;
          padding-top: 1rem;
          border-top: 1px solid #dee2e6;
      }

      /* Add styles from the original weekly_plan.blade.php for the display table if needed */
      /* Import or copy relevant styles for .weekly-plan-table, .lesson-entry etc. */
      .weekly-plan-table th, .weekly-plan-table td {
          vertical-align: top;
          min-width: 150px;
          height: 100px;
          position: relative;
      }

      .weekly-plan-table .time-slot-header {
          font-weight: bold;
          vertical-align: middle;
          text-align: center;
          width: 100px;
          min-width: 100px;
      }

      .lesson-entry {
          font-size: 0.9rem;
          padding: 8px;
          border: 1px solid #e0e0e0;
          border-radius: 5px;
          background-color: #fdfdfd;
          height: 100%;
          display: flex;
          flex-direction: column;
          justify-content: space-between;
          box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
          overflow: hidden;
      }

      .lesson-title {
          font-weight: bold;
          margin-bottom: 5px;
          overflow: hidden;
          text-overflow: ellipsis;
          display: -webkit-box;
          -webkit-line-clamp: 2;
          -webkit-box-orient: vertical;
      }

      .lesson-title a {
          color: var(--bs-link-color);
          text-decoration: none;
          display: block;
      }

      .lesson-title a:hover {
          text-decoration: underline;
      }

      .lesson-category, .activity-type {
          font-size: 0.8rem;
          color: #6c757d;
          margin-top: auto;
          flex-shrink: 0;
          text-align: right;
          overflow: hidden;
          text-overflow: ellipsis;
          white-space: nowrap;
      }

      .activity-type {
          font-style: italic;
      }

      /* Style for PE/Review/Empty */
      .week-header {
          background-color: #e9ecef;
          padding: 0.75rem 1.25rem;
          margin-bottom: 1rem;
          border: 1px solid #dee2e6;
          border-radius: 0.25rem;
      }

      .empty-slot-placeholder {
          font-size: 0.85rem;
          color: #6c757d;
          display: flex;
          align-items: center;
          justify-content: center;
          height: 100%;
          text-align: center;
          padding: 5px;
      }

      /* Dark Mode Adjustments */
      .dark-mode .lesson-entry {
          background-color: var(--bs-gray-700);
          border-color: var(--bs-gray-600);
          box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
      }

      .dark-mode .lesson-title a {
          color: var(--bs-link-color-dark, #9cc0ff);
      }

      .dark-mode .lesson-category, .dark-mode .activity-type {
          color: var(--bs-gray-500);
      }

      .dark-mode .week-header {
          background-color: var(--bs-gray-800);
          border-color: var(--bs-gray-700);
          color: var(--bs-light);
      }

      .dark-mode .table {
          --bs-table-color: var(--bs-light);
          --bs-table-bg: var(--bs-gray-800);
          --bs-table-border-color: var(--bs-gray-700);
          --bs-table-striped-bg: var(--bs-gray-750);
          --bs-table-hover-bg: var(--bs-gray-600);
      }

      .dark-mode .table-light {
          --bs-table-color: var(--bs-light);
          --bs-table-bg: var(--bs-gray-900);
          --bs-table-border-color: var(--bs-gray-700);
      }

      .dark-mode .empty-slot-placeholder {
          color: var(--bs-gray-500);
      }

      /* Print Styles (Copy from original if needed, adjust for potential AJAX loading) */
      @media print {
          /* Hide config section, buttons, etc. */
          #configSection, .btn, .language-selector, label[for="languageSelector"], body > .navbar, body > .dark-mode-switch-container, #loadingOverlay, #errorMessageArea, #toast {
              display: none !important;
          }

          /* Ensure plan display area takes full width */
          #planDisplayArea {
              margin-top: 0 !important;
              padding-top: 0 !important;
              border-top: none !important;
          }

          .container {
              max-width: none !important;
              width: 100% !important;
              padding: 0 !important;
              margin: 0 !important;
          }

          /* Include other print styles from original weekly_plan.blade.php */
          .weekly-plan-week-container {
              page-break-inside: avoid;
              margin-bottom: 0 !important;
              padding-top: 10px;
          }

          .page-break-after-pair {
              page-break-after: always;
          }

          .weekly-plan-table tr, .weekly-plan-table td, .lesson-entry {
              page-break-inside: avoid;
          }

          .table-responsive {
              overflow-x: visible !important;
              margin-bottom: 10px !important;
          }

          .weekly-plan-table {
              width: 100% !important;
              font-size: 9pt;
              border-collapse: collapse !important;
              table-layout: fixed;
          }

          .weekly-plan-table th, .weekly-plan-table td {
              border: 1px solid #aaa !important;
              padding: 4px;
              height: auto;
              min-width: 0;
              word-wrap: break-word;
              vertical-align: top;
          }

          .weekly-plan-table thead th {
              background-color: #eee !important;
              color: #000 !important;
              font-weight: bold;
          }

          .time-slot-header {
              width: 8% !important;
              font-weight: bold;
              text-align: center;
              vertical-align: middle;
          }

          .weekly-plan-table th.text-center {
              width: 15.3% !important;
              text-align: center;
          }

          .lesson-entry {
              border: 1px solid #ddd !important;
              padding: 3px;
              font-size: 8pt;
              box-shadow: none !important;
              background-color: #fff !important;
              color: #000 !important;
              height: auto;
              min-height: 40px;
          }

          .lesson-title a {
              color: #000 !important;
              text-decoration: none !important;
              font-weight: bold;
              font-size: 8pt;
              -webkit-line-clamp: 4;
          }

          .lesson-category, .activity-type {
              color: #555 !important;
              font-size: 7pt;
              text-align: right;
              margin-top: 1px;
          }

          .empty-slot-placeholder {
              color: #999 !important;
              font-size: 8pt;
              height: 40px;
          }

          .week-header {
              background-color: #ddd !important;
              color: #000 !important;
              border: 1px solid #bbb !important;
              padding: 5px 10px;
              margin-bottom: 5px !important;
              border-radius: 0 !important;
              page-break-before: avoid;
              page-break-after: avoid;
          }

          .week-header h4 {
              font-size: 12pt;
              margin-bottom: 0;
          }

          .week-header i {
              display: none;
          }

          body.dark-mode, .dark-mode .table, .dark-mode .lesson-entry, .dark-mode .week-header {
              background-color: #fff !important;
              color: #000 !important;
          }

          .dark-mode .table {
              --bs-table-color: #000;
              --bs-table-bg: #fff;
              --bs-table-border-color: #aaa;
              --bs-table-striped-bg: #f9f9f9;
              --bs-table-hover-bg: #f5f5f5;
          }

          .dark-mode .table-light th {
              background-color: #eee !important;
              color: #000 !important;
              border-color: #aaa !important;
          }

          .dark-mode .lesson-title a {
              color: #000 !important;
          }

          .dark-mode .lesson-category, .dark-mode .activity-type {
              color: #555 !important;
          }

          .dark-mode .empty-slot-placeholder {
              color: #999 !important;
          }
      }
	
	</style>
@endpush

@section('content')
	<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
		<h1>@lang('weekly_plan.configure_title')</h1>
		{{-- Language Selector Dropdown --}}
		<div class="d-flex align-items-center mt-2 mt-md-0">
			<label for="languageSelector" class="form-label me-2 mb-0">@lang('weekly_plan.select_language'):</label>
			<select id="languageSelector" class="form-select form-select-sm language-selector">
				<option value="en" {{ $currentLocale == 'en' ? 'selected' : '' }}>@lang('weekly_plan.english')</option>
				<option
					value="zh-TW" {{ $currentLocale == 'zh-TW' ? 'selected' : '' }}>@lang('weekly_plan.traditional_chinese')</option>
				<option value="tr" {{ $currentLocale == 'tr' ? 'selected' : '' }}>@lang('weekly_plan.turkish')</option>
			</select>
			<a href="{{ route('lessons.list') }}" class="btn btn-outline-secondary ms-3">
				<i class="fas fa-arrow-left"></i> @lang('weekly_plan.back_button')
			</a>
		</div>
	</div>
	
	@include('partials.session_messages')
	
	<div id="configSection">
		{{-- Date Range Selection --}}
		<div class="card mb-4 shadow-sm">
			<div class="card-header">@lang('weekly_plan.select_date_range')</div>
			<div class="card-body">
				<div class="row g-3 align-items-end">
					<div class="col-md-3 col-sm-6">
						<label for="start_month" class="form-label">@lang('weekly_plan.start_month')</label>
						<select id="start_month" class="form-select">
							@foreach($months as $num => $name)
								<option value="{{ $num }}">{{ $name }}</option>
							@endforeach
						</select>
					</div>
					<div class="col-md-3 col-sm-6">
						<label for="start_year" class="form-label">@lang('weekly_plan.start_year')</label>
						<select id="start_year" class="form-select">
							@foreach($years as $year)
								<option value="{{ $year }}" {{ $year == now()->year ? 'selected' : '' }}>{{ $year }}</option>
							@endforeach
						</select>
					</div>
					<div class="col-md-3 col-sm-6">
						<label for="end_month" class="form-label">@lang('weekly_plan.end_month')</label>
						<select id="end_month" class="form-select">
							@foreach($months as $num => $name)
								<option value="{{ $num }}" {{ $num == now()->month ? 'selected' : '' }}>{{ $name }}</option>
							@endforeach
						</select>
					</div>
					<div class="col-md-3 col-sm-6">
						<label for="end_year" class="form-label">@lang('weekly_plan.end_year')</label>
						<select id="end_year" class="form-select">
							@foreach($years as $year)
								<option value="{{ $year }}" {{ $year == now()->year ? 'selected' : '' }}>{{ $year }}</option>
							@endforeach
						</select>
					</div>
				</div>
			</div>
		</div>
		
		{{-- Template Configuration Grid --}}
		<div class="card mb-4 shadow-sm">
			<div class="card-header">@lang('weekly_plan.configure_template_header')</div>
			<div class="card-body">
				<div class="table-responsive">
					<table class="table table-bordered config-table">
						<thead>
						<tr>
							<th class="time-slot-header">@lang('weekly_plan.time')</th>
							@foreach ($internalDays as $dayKey)
								<th>{{ $displayDays[$dayKey] }}</th>
							@endforeach
						</tr>
						</thead>
						<tbody>
						@foreach ($internalTimeSlots as $slotKey)
							<tr>
								<td class="time-slot-header">{{ $displayTimeSlots[$slotKey] }}</td>
								@foreach ($internalDays as $dayKey)
									<td>
										<select class="form-select slot-select" data-day="{{ $dayKey }}" data-slot="{{ $slotKey }}"
										        id="slot-{{ $dayKey }}-{{ $slotKey }}">
											<option value="empty">@lang('weekly_plan.empty_slot_option')</option>
											<option value="pe">@lang('weekly_plan.physical_education')</option>
											<option value="review">@lang('weekly_plan.review')</option>
											<option disabled>--- @lang('weekly_plan.categories') ---</option>
											@foreach ($mainCategories as $category)
												<option value="cat_{{ $category->id }}">{{ $category->name }}</option>
											@endforeach
										</select>
									</td>
								@endforeach
							</tr>
						@endforeach
						</tbody>
					</table>
				</div>
			</div>
		</div>
		
		{{-- Action Buttons --}}
		<div class="text-center mb-4">
			<button type="button" class="btn btn-secondary me-2" id="saveTemplateBtn">
				<i class="fas fa-save me-1"></i> @lang('weekly_plan.save_template_button')
			</button>
			<button type="button" class="btn btn-primary" id="loadPlanBtn">
				<i class="fas fa-calendar-alt me-1"></i> @lang('weekly_plan.load_plan_button')
			</button>
			<button type="button" class="btn btn-info ms-2" onclick="window.print();">
				<i class="fas fa-print me-1"></i> @lang('weekly_plan.print_plan_button')
			</button>
		</div>
	</div>
	
	{{-- Area to display the loaded plan --}}
	<div id="planDisplayArea">
		{{-- Populated plan will be loaded here via AJAX --}}
		<p class="text-center text-muted">@lang('weekly_plan.configure_and_load_message')</p>
	</div>

@endsection

@push('scripts')
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			const saveTemplateBtn = document.getElementById('saveTemplateBtn');
			const loadPlanBtn = document.getElementById('loadPlanBtn');
			const planDisplayArea = document.getElementById('planDisplayArea');
			const languageSelector = document.getElementById('languageSelector');
			const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
			const userId = {{ Auth::id() }}; // Get user ID for LocalStorage key
			const storageKey = `weeklyPlanConfig_${userId}`;
			
			// --- Language Selector ---
			if (languageSelector) {
				languageSelector.addEventListener('change', function () {
					const selectedLang = this.value;
					const currentUrl = new URL(window.location.href);
					currentUrl.searchParams.set('lang', selectedLang);
					window.location.href = currentUrl.toString();
				});
			}
			
			// --- Template Saving ---
			saveTemplateBtn.addEventListener('click', function () {
				const template = {};
				const slotSelects = document.querySelectorAll('.slot-select');
				slotSelects.forEach(select => {
					const day = select.dataset.day;
					const slot = select.dataset.slot;
					if (!template[day]) {
						template[day] = {};
					}
					template[day][slot] = select.value;
				});
				
				const dateRange = {
					start_month: document.getElementById('start_month').value,
					start_year: document.getElementById('start_year').value,
					end_month: document.getElementById('end_month').value,
					end_year: document.getElementById('end_year').value,
				};
				
				const config = {template, dateRange};
				
				try {
					localStorage.setItem(storageKey, JSON.stringify(config));
					// Use the common toast function if available
					if (typeof showToast === 'function') {
						showToast('Success', 'Template saved successfully!', 'success');
					} else {
						alert('Template saved successfully!'); // Fallback
					}
					console.log('Template saved:', config);
				} catch (e) {
					console.error('Error saving template to LocalStorage:', e);
					if (typeof showToast === 'function') {
						showToast('Error', 'Could not save template. LocalStorage might be full or disabled.', 'danger');
					} else {
						alert('Error saving template.');
					}
				}
			});
			
			function showLoading(show, message) {
				const loadingOverlay = document.getElementById('loadingOverlay');
				const loadingMessage = document.getElementById('loadingMessage');
				if (show) {
					loadingOverlay.classList.remove('d-none');
					loadingMessage.textContent = message || 'Loading...';
				} else {
					loadingOverlay.classList.add('d-none');
				}
			}
			
			// --- Template Loading (from LocalStorage on page load) ---
			function loadTemplateFromStorage() {
				const savedConfig = localStorage.getItem(storageKey);
				if (savedConfig) {
					try {
						const config = JSON.parse(savedConfig);
						console.log('Loading template from storage:', config);
						
						// Populate date range
						if (config.dateRange) {
							document.getElementById('start_month').value = config.dateRange.start_month || '{{ now()->month }}';
							document.getElementById('start_year').value = config.dateRange.start_year || '{{ now()->year }}';
							document.getElementById('end_month').value = config.dateRange.end_month || '{{ now()->month }}';
							document.getElementById('end_year').value = config.dateRange.end_year || '{{ now()->year }}';
						}
						
						// Populate template grid
						if (config.template) {
							const slotSelects = document.querySelectorAll('.slot-select');
							slotSelects.forEach(select => {
								const day = select.dataset.day;
								const slot = select.dataset.slot;
								if (config.template[day] && config.template[day][slot]) {
									select.value = config.template[day][slot];
								} else {
									select.value = 'empty'; // Default if not found
								}
							});
						}
						// Optionally auto-load the plan if a template exists
						// loadPopulatedPlan();
						
					} catch (e) {
						console.error('Error parsing saved template:', e);
						localStorage.removeItem(storageKey); // Remove corrupted data
					}
				} else {
					console.log('No saved template found in LocalStorage.');
					// Set default dates if no template saved
					document.getElementById('start_month').value = '{{ now()->month }}';
					document.getElementById('start_year').value = '{{ now()->year }}';
					document.getElementById('end_month').value = '{{ now()->month }}';
					document.getElementById('end_year').value = '{{ now()->year }}';
				}
			}
			
			// --- Load Populated Plan (AJAX) ---
			function loadPopulatedPlan() {
				const template = {};
				const slotSelects = document.querySelectorAll('.slot-select');
				slotSelects.forEach(select => {
					const day = select.dataset.day;
					const slot = select.dataset.slot;
					if (!template[day]) {
						template[day] = {};
					}
					template[day][slot] = select.value;
				});
				
				const data = {
					template: template,
					start_month: document.getElementById('start_month').value,
					start_year: document.getElementById('start_year').value,
					end_month: document.getElementById('end_month').value,
					end_year: document.getElementById('end_year').value,
				};
				
				// Show loading indicator (using common.js function)
				showLoading(true, 'Loading plan...');
				planDisplayArea.innerHTML = '<p class="text-center text-muted">Loading...</p>'; // Placeholder
				
				fetch('{{ route("weekly.plan.load") }}', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-CSRF-TOKEN': csrfToken,
						'Accept': 'application/json'
					},
					body: JSON.stringify(data)
				})
					.then(response => {
						if (!response.ok) {
							return response.json().then(err => {
								throw new Error(err.message || `HTTP error! status: ${response.status}`)
							});
						}
						return response.json();
					})
					.then(result => {
						if (result.html) {
							planDisplayArea.innerHTML = result.html;
							// Re-apply dark mode if necessary after AJAX load
							if (document.documentElement.classList.contains('dark-mode')) {
								applyDarkModeStyles(planDisplayArea); // You might need a helper for this
							}
						} else if (result.error) {
							throw new Error(result.error);
						} else {
							planDisplayArea.innerHTML = '<div class="alert alert-warning">Received unexpected data from server.</div>';
						}
					})
					.catch(error => {
						console.error('Error loading plan:', error);
						planDisplayArea.innerHTML = `<div class="alert alert-danger">Error loading plan: ${error.message}</div>`;
						// Use common error display if available
						if (typeof showErrorMessage === 'function') {
							showErrorMessage(`Error loading plan: ${error.message}`);
						}
					})
					.finally(() => {
						// Hide loading indicator
						showLoading(false);
					});
			}
			
			// Helper to apply dark mode to dynamically loaded content (basic example)
			function applyDarkModeStyles(container) {
				container.querySelectorAll('.table').forEach(el => el.classList.add('table-dark')); // Example
				// Add more specific rules as needed based on your CSS
			}
			
			
			loadPlanBtn.addEventListener('click', loadPopulatedPlan);
			
			// Load saved template when the page loads
			loadTemplateFromStorage();
			
		});
	</script>
@endpush
