document.addEventListener('DOMContentLoaded', () => {
	console.log('Interactive Quiz JS Loaded');
	
	// --- State Variables ---
	const subjectSessionId = document.getElementById('subjectSessionId')?.value;
	const subjectId = document.getElementById('subjectId')?.value;
	const totalParts = window.totalLessonParts || 0;
	const difficulties = ['easy', 'medium', 'hard'];
	
	let currentState = window.quizInitialState || null; // { partIndex, difficulty, correctCounts, status, requiredCorrect, currentPartIntroText, currentPartVideoUrl }
	let currentPartQuizzes = []; // <-- NEW: Stores quizzes for the current part/difficulty
	let currentQuizIndex = -1; // <-- NEW: Index for currentPartQuizzes
	let currentQuiz = null; // The specific quiz object being displayed
	
	let selectedIndex = null; // User's selection on the current quiz
	let feedbackData = null; // { was_correct, correct_index, feedback_text, feedback_audio_url, level_advanced, lesson_completed }
	let isLoading = false; // For AJAX requests
	let interactionsDisabled = false; // For audio playback, loading etc.
	let isPartIntroVisible = false; // Track if intro or quiz area is shown
	let hasIntroVideoPlayed = false; // Track if intro video played (per part)
	
	// TTS Playback State
	let playbackQueue = [];
	let currentPlaybackIndex = -1;
	let isAutoPlaying = false;
	let currentHighlightElement = null;
	
	// --- DOM Element References ---
	// ... (Keep all existing DOM element references)
	const loadingOverlay = document.getElementById('loadingOverlay');
	const loadingMessageEl = document.getElementById('loadingMessage');
	const errorMessageArea = document.getElementById('errorMessageArea');
	const errorMessageText = document.getElementById('errorMessageText');
	const closeErrorButton = document.getElementById('closeErrorButton');
	const progressBar = document.getElementById('progressBar');
	const partIndicatorContainer = document.getElementById('partIndicatorContainer');
	const partIntroArea = document.getElementById('partIntroArea');
	const partIntroTitle = document.getElementById('partIntroTitle');
	const partIntroVideo = document.getElementById('partIntroVideo');
	const partIntroVideoPlaceholder = document.getElementById('partIntroVideoPlaceholder');
	const partIntroText = document.getElementById('partIntroText');
	const startPartQuizButton = document.getElementById('startPartQuizButton');
	const quizArea = document.getElementById('quizArea');
	const questionDifficulty = document.getElementById('questionDifficulty');
	const questionTextElement = document.getElementById('questionTextElement');
	const questionImageElement = document.getElementById('questionImageElement');
	const noImagePlaceholder = document.getElementById('noImagePlaceholder');
	const quizAnswersContainer = document.getElementById('quizAnswersContainer');
	const feedbackSection = document.getElementById('feedbackSection');
	const feedbackHeading = document.getElementById('feedbackHeading');
	const feedbackTextEl = document.getElementById('feedbackText');
	const playFeedbackButton = document.getElementById('playFeedbackButton');
	const feedbackIncorrectMessage = document.getElementById('feedbackIncorrectMessage');
	const feedbackThresholdMessage = document.getElementById('feedbackThresholdMessage');
	const remainingCorrectCount = document.getElementById('remainingCorrectCount');
	const feedbackListenMessage = document.getElementById('feedbackListenMessage');
	const nextQuestionButton = document.getElementById('nextQuestionButton');
	const nextQuestionSpinner = document.getElementById('nextQuestionSpinner');
	const completionMessage = document.getElementById('completionMessage');
	const ttsAudioPlayer = document.getElementById('ttsAudioPlayer');
	const feedbackAudioPlayer = document.getElementById('feedbackAudioPlayer');
	
	
	// --- Helper Functions ---
	// ... (Keep setLoadingState, setErrorState, setInteractionsDisabled, toggleElement, highlightElement, removeHighlight, capitalizeFirstLetter) ...
	function setLoadingState(loading, message = 'Loading...') {
		isLoading = loading;
		// Also disable interactions when loading, re-enable takes care of isAutoPlaying check
		setInteractionsDisabled(loading || isAutoPlaying);
		if (loadingOverlay && loadingMessageEl) {
			loadingMessageEl.textContent = message;
			loadingOverlay.classList.toggle('d-none', !loading);
		}
		// Disable next button specifically during loading
		if (nextQuestionButton) nextQuestionButton.disabled = loading || interactionsDisabled;
		if (nextQuestionSpinner) nextQuestionSpinner.classList.toggle('d-none', !loading);
		
		// Disable start part button during loading
		if (startPartQuizButton) startPartQuizButton.disabled = loading || interactionsDisabled;
		
		// No general updateUI call here to prevent flicker, specific updates done where needed
	}
	
	function setErrorState(message) {
		if (errorMessageArea && errorMessageText) {
			errorMessageText.textContent = message || '';
			errorMessageArea.classList.toggle('d-none', !message);
		}
	}
	
	function setInteractionsDisabled(disabled) {
		const changed = interactionsDisabled !== disabled;
		interactionsDisabled = disabled;
		// console.log(`Interactions Disabled: ${interactionsDisabled} (Req: ${disabled}, Load: ${isLoading}, AutoPlay: ${isAutoPlaying})`);
		if (changed) {
			// Update button states etc. based on this change
			updateButtonStates();
		}
	}
	
	function updateButtonStates() {
		// Update Answer Buttons enabled/disabled state
		quizAnswersContainer?.querySelectorAll('.answer-btn').forEach(button => {
			// Disable if interactions generally disabled OR feedback is shown
			button.disabled = interactionsDisabled || feedbackData != null;
		});
		
		// Update Next Question Button state
		if (nextQuestionButton) {
			// Show if feedback is visible AND not currently loading
			const showNextButton = feedbackData != null && !isLoading;
			toggleElement(nextQuestionButton, showNextButton);
			// Disable if interactions are off OR loading
			nextQuestionButton.disabled = interactionsDisabled || isLoading;
		}
		
		// Update Play Feedback Button state
		if (playFeedbackButton) {
			toggleElement(playFeedbackButton, !!feedbackData?.feedback_audio_url);
			// Disable ONLY if interactions are disabled (e.g. audio playing/loading)
			playFeedbackButton.disabled = interactionsDisabled;
		}
		
		// Update Start Part Button state
		if (startPartQuizButton) {
			startPartQuizButton.disabled = interactionsDisabled || isLoading;
		}
	}
	
	
	function toggleElement(element, show) {
		if (!element) return;
		element.classList.toggle('d-none', !show);
	}
	
	function highlightElement(element, shouldHighlight) {
		if (!element) return;
		element.classList.toggle('reading-highlight', shouldHighlight); // Use CSS class
		if (shouldHighlight) {
			currentHighlightElement = element; // Track highlighted element
		} else if (currentHighlightElement === element) {
			currentHighlightElement = null;
		}
	}
	
	function removeHighlight() {
		if (currentHighlightElement) {
			highlightElement(currentHighlightElement, false);
		}
		document.querySelectorAll('.reading-highlight').forEach(el => el.classList.remove('reading-highlight'));
		currentHighlightElement = null;
	}
	
	function capitalizeFirstLetter(string) {
		if (!string) return '';
		return string.charAt(0).toUpperCase() + string.slice(1);
	}
	
	
	// --- TTS Playback Functions ---
	// ... (Keep buildPlaybackQueue, startPlaybackSequence, stopPlaybackSequence, playNextInSequence, handleTtsAudioEnded, handleTtsAudioError) ...
	function buildPlaybackQueue(quizData) {
		playbackQueue = [];
		currentPlaybackIndex = -1;
		if (!quizData) return;
		
		if (quizData.question_audio_url && questionTextElement) {
			playbackQueue.push({ element: questionTextElement, url: quizData.question_audio_url });
		}
		quizData.answers?.forEach((answer, index) => {
			const answerButton = document.getElementById(`answerBtn_${index}`);
			if (answer.answer_audio_url && answerButton) {
				playbackQueue.push({ element: answerButton, url: answer.answer_audio_url });
			}
		});
		// console.log("Playback queue built:", playbackQueue);
	}
	
	function startPlaybackSequence() {
		if (playbackQueue.length === 0) {
			console.log("Playback queue empty, enabling interactions.");
			setInteractionsDisabled(false);
			return;
		}
		stopPlaybackSequence();
		// console.log("Starting playback sequence...");
		isAutoPlaying = true;
		currentPlaybackIndex = 0;
		setInteractionsDisabled(true);
		playNextInSequence();
	}
	
	function stopPlaybackSequence(reEnableInteractions = false) {
		if (!isAutoPlaying && ttsAudioPlayer?.paused) return;
		// console.log("Stopping playback sequence.");
		isAutoPlaying = false;
		if (ttsAudioPlayer) {
			ttsAudioPlayer.pause();
			ttsAudioPlayer.currentTime = 0;
		}
		removeHighlight();
		if (reEnableInteractions) {
			setInteractionsDisabled(false);
		}
	}
	
	function playNextInSequence() {
		removeHighlight();
		if (!isAutoPlaying || currentPlaybackIndex < 0 || currentPlaybackIndex >= playbackQueue.length) {
			// console.log("Playback sequence finished or stopped.");
			isAutoPlaying = false;
			setInteractionsDisabled(false); // Enable interactions after sequence naturally ends
			return;
		}
		const item = playbackQueue[currentPlaybackIndex];
		if (!item || !item.element || !item.url) {
			console.warn("Skipping invalid item in playback queue:", item);
			currentPlaybackIndex++;
			if (isAutoPlaying) setTimeout(playNextInSequence, 50);
			else setInteractionsDisabled(false);
			return;
		}
		// console.log(`Playing item ${currentPlaybackIndex} (${item.element.id || item.element.tagName}):`, item.url);
		highlightElement(item.element, true);
		if (ttsAudioPlayer) {
			setTimeout(() => {
				if (!isAutoPlaying) return;
				ttsAudioPlayer.src = item.url;
				ttsAudioPlayer.play().catch(error => {
					console.error(`Error playing TTS audio for index ${currentPlaybackIndex} (${item.url}):`, error);
					stopPlaybackSequence();
					setErrorState("An error occurred during audio playback.");
					setInteractionsDisabled(false);
				});
			}, 300);
		} else {
			console.error("TTS Audio Player not found!");
			stopPlaybackSequence();
			setInteractionsDisabled(false);
		}
	}
	
	function handleTtsAudioEnded() {
		if (!isAutoPlaying) return;
		currentPlaybackIndex++;
		playNextInSequence();
	}
	
	function handleTtsAudioError(event) {
		console.error("TTS Audio Player Error:", event);
		if (isAutoPlaying) {
			stopPlaybackSequence();
			setErrorState("An error occurred during audio playback.");
			setInteractionsDisabled(false);
		}
	}
	
	
	// --- Feedback Audio ---
	// ... (Keep playFeedbackAudio, handleFeedbackAudioEnd) ...
	function playFeedbackAudio() {
		if (!feedbackData?.feedback_audio_url || !feedbackAudioPlayer) {
			checkStateAndTransition(); // No audio, proceed to state check
			return;
		}
		// Interactions should already be disabled here by submitAnswer's callback
		feedbackAudioPlayer.src = feedbackData.feedback_audio_url;
		feedbackAudioPlayer.play().catch(e => {
			console.error("Feedback audio playback error:", e);
			handleFeedbackAudioEnd(); // Treat error same as end
		});
	}
	
	function handleFeedbackAudioEnd() {
		// console.log("Feedback audio finished or failed.");
		checkStateAndTransition(); // Check state after audio finishes
	}
	
	
	// --- NEW: Function to load all questions for a given part/difficulty ---
	function loadQuestionsForLevel(partIndex, difficulty) {
		if (isLoading) return;
		setLoadingState(true, `Loading ${difficulty} questions for Part ${partIndex + 1}...`);
		setErrorState(null);
		feedbackData = null; // Clear feedback
		currentPartQuizzes = []; // Clear old quizzes
		currentQuizIndex = -1;
		currentQuiz = null;
		
		fetch(`/lesson/${subjectSessionId}/part-questions`, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
				'Accept': 'application/json',
			},
			body: JSON.stringify({ partIndex, difficulty })
		})
			.then(response => {
				if (!response.ok) {
					return response.json().then(err => { throw new Error(err.message || `HTTP error ${response.status}`) });
				}
				return response.json();
			})
			.then(data => {
				if (!data.success) {
					throw new Error(data.message || 'Failed to fetch questions for this part.');
				}
				
				if (!data.quizzes || data.quizzes.length === 0) {
					console.warn(`No quizzes returned for Part ${partIndex}, Difficulty ${difficulty}.`);
					// Maybe show a message and potentially try to advance state?
					// For now, treat as error or show message and halt.
					throw new Error(`No questions found for Part ${partIndex + 1} (${difficulty}). Cannot proceed.`);
				}
				
				console.log(`Loaded ${data.quizzes.length} quizzes for Part ${partIndex}, Diff ${difficulty}`);
				currentPartQuizzes = data.quizzes;
				currentQuizIndex = 0;
				
				// Hide intro (if visible), show quiz area
				showQuizScreen(); // Ensures quiz area is visible
				
				// Display the first question from the newly loaded set
				displayQuizAtIndex(currentQuizIndex); // This also handles TTS start
				
				setLoadingState(false); // Loading complete
			})
			.catch(error => {
				console.error('Error loading part questions:', error);
				setErrorState(`Error: ${error.message}`);
				setLoadingState(false); // Stop loading on error
				// Decide what to do - maybe show intro screen again? Or just the error.
				toggleElement(quizArea, false); // Hide quiz area on error
				toggleElement(partIntroArea, false); // Hide intro too
			});
	}
	
	// --- NEW: Function to display a specific quiz from the local array ---
	function displayQuizAtIndex(index) {
		if (index < 0 || index >= currentPartQuizzes.length) {
			console.error(`Invalid quiz index requested: ${index}`);
			// Handle this - maybe trigger state check?
			checkStateAndTransition();
			return;
		}
		
		currentQuizIndex = index;
		currentQuiz = currentPartQuizzes[index];
		selectedIndex = null; // Reset selection
		feedbackData = null; // Clear feedback
		
		console.log(`Displaying quiz index ${index} (ID: ${currentQuiz.id})`);
		
		// Update UI Elements
		updateUIForQuiz(); // Separate function for clarity
		
		// Build and start TTS playback sequence
		// Disable interactions *before* starting TTS
		setInteractionsDisabled(true); // Disable interactions initially
		buildPlaybackQueue(currentQuiz);
		startPlaybackSequence(); // This will re-enable interactions when done or if queue is empty
	}
	
	// --- NEW: Function to update UI specifically for the current quiz ---
	function updateUIForQuiz() {
		if (!currentQuiz) {
			console.error("updateUIForQuiz called but currentQuiz is null");
			return; // Or hide quiz area
		}
		
		if (questionDifficulty) {
			questionDifficulty.textContent = `Part ${currentQuiz.lesson_part_index + 1} - ${capitalizeFirstLetter(currentQuiz.difficulty_level)}`;
		}
		if (questionTextElement) {
			questionTextElement.textContent = currentQuiz.question_text;
		}
		
		// Image Display
		if (questionImageElement && noImagePlaceholder) {
			if (currentQuiz.image_url) {
				questionImageElement.src = currentQuiz.image_url;
				toggleElement(questionImageElement, true);
				toggleElement(noImagePlaceholder, false);
			} else {
				toggleElement(questionImageElement, false);
				toggleElement(noImagePlaceholder, true);
			}
		}
		
		// Answer Buttons
		quizAnswersContainer.innerHTML = ''; // Clear old buttons
		currentQuiz.answers?.forEach((answer, idx) => {
			const button = document.createElement('button');
			button.type = 'button';
			button.id = `answerBtn_${idx}`;
			button.classList.add('btn', 'btn-outline-primary', 'btn-lg', 'answer-btn', 'w-100', 'mb-2');
			button.dataset.index = idx;
			button.textContent = answer.text;
			button.disabled = interactionsDisabled; // Initial state based on current interaction status
			quizAnswersContainer.appendChild(button);
		});
		
		// Reset & Hide Feedback Section initially
		toggleElement(feedbackSection, false);
		feedbackData = null; // Ensure feedback is cleared
		
		// Reset button styles (remove correct/incorrect/selected) - done implicitly by rebuilding
		updateButtonStates(); // Ensure correct enabled/disabled state initially
	}
	
	// --- MODIFIED: Central function to check state and decide next step ---
	function checkStateAndTransition() {
		console.log("Checking state and transitioning. Current State:", currentState);
		// This function runs after feedback is processed (audio ended/skipped)
		// or when moving from the last question of a local set.
		
		const newState = currentState; // Use the state updated by submitAnswer response
		
		// Get details of the level we *just finished* displaying/answering
		const previousQuizLevelPart = currentQuiz?.lesson_part_index ?? -1;
		const previousQuizLevelDiff = currentQuiz?.difficulty_level ?? null;
		
		const isCompleted = newState.status === 'completed';
		const levelOrPartChangedInState = (newState.partIndex !== previousQuizLevelPart || newState.difficulty !== previousQuizLevelDiff);
		const hasNextInLocalList = currentPartQuizzes.length > 0 && (currentQuizIndex + 1) < currentPartQuizzes.length;
		
		console.log(`CheckState: Completed: ${isCompleted}, Level/Part Changed: ${levelOrPartChangedInState}, HasNextLocal: ${hasNextInLocalList}, PrevPart: ${previousQuizLevelPart}, NewPart: ${newState.partIndex}, PrevDiff: ${previousQuizLevelDiff}, NewDiff: ${newState.difficulty}`);
		
		// Clear feedback ONLY if moving to a new level/part, completing, OR if there are no more questions locally
		// Keep feedback visible if we are just enabling the 'Next Question' button for the current list.
		if (isCompleted || levelOrPartChangedInState || !hasNextInLocalList) {
			console.log("Clearing feedback data because state changed, lesson complete, or no more local questions.");
			feedbackData = null; // Clear feedback data before potential transition
			toggleElement(feedbackSection, false); // Hide feedback section
		} else {
			console.log("Keeping feedback visible as there are more questions locally and state didn't change.");
		}
		
		if (isCompleted) {
			console.log("Transition: Lesson Completed");
			showCompletionScreen();
			setInteractionsDisabled(false);
		} else if (levelOrPartChangedInState) {
			console.log("Transition: State indicates level/part change");
			// We need to load the next level/part set
			if (newState.partIndex !== previousQuizLevelPart && previousQuizLevelPart !== -1) {
				// Part index changed (and it wasn't the initial load)
				console.log(`Transition: Moving to Part ${newState.partIndex} Intro`);
				// Update intro text/video URLs in state for the new part
				// Fetch these from backend? No, state should have been updated in submitAnswer if needed.
				// Let's assume currentState *already* has the correct intro text/video for the new part
				// If not, we'd need another AJAX call here or include it in submitAnswer response.
				// For now, rely on currentState passed from submitAnswer having the new part's intro details.
				// We might need to fetch subject again or pass it with state? Let's assume state has it.
				// **Correction**: Controller doesn't add new part intro in submitAnswer. Let's add it to showPartIntro.
				showPartIntro(newState.partIndex); // This function will fetch intro details based on the state.
			} else {
				// Only difficulty changed within the same part
				console.log(`Transition: Loading next difficulty '${newState.difficulty}' for Part ${newState.partIndex}`);
				loadQuestionsForLevel(newState.partIndex, newState.difficulty); // Load the new set immediately
			}
		} else if (hasNextInLocalList) {
			console.log("Transition: More questions available in the current local list. Enabling Next button.");
			// Still questions left in the *current* loaded set.
			// Enable interaction and the 'Next Question' button should be visible/enabled via updateUI/updateButtonStates.
			setInteractionsDisabled(false); // Enable interaction
			updateUI(); // Refresh UI state (especially Next button)
		} else {
			// No more local questions, state didn't change (e.g., threshold not met but answered all once)
			console.warn("Transition: No more local questions and state hasn't advanced. What now?");
			// This scenario needs clearer definition. Loop back? Show message?
			// For now: Show a message, ensure interactions are enabled.
			setErrorState("You've completed the available questions for this section, but haven't met the requirement to advance yet. Please review the material or contact support if you believe this is an error."); // More specific message needed maybe
			toggleElement(nextQuestionButton, false); // Hide next button in this ambiguous state
			setInteractionsDisabled(false); // Ensure user isn't stuck
			updateProgressBar(); // Update progress bar based on final state
		}
	}
	
	// --- Core Logic ---
	// ** fetchNextQuestion is REMOVED **
	
	function submitAnswer(index) {
		if (isLoading || interactionsDisabled || feedbackData != null) {
			return;
		}
		stopPlaybackSequence(true); // Stop TTS, allow interaction temporarily
		selectedIndex = index;
		setLoadingState(true, 'Checking answer...');
		setErrorState(null);
		
		// Update button visually immediately (optional, but good UX)
		quizAnswersContainer?.querySelectorAll('.answer-btn').forEach(btn => {
			btn.classList.remove('selected');
			if (parseInt(btn.dataset.index) === index) {
				btn.classList.add('selected');
			}
			btn.disabled = true; // Disable all buttons after selection
		});
		
		fetch(`/quiz/${currentQuiz.id}/submit`, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
				'Accept': 'application/json',
			},
			body: JSON.stringify({ selected_index: index })
		})
			.then(response => {
				const status = response.status;
				return response.json().then(data => ({ status, data }));
			})
			.then(({ status, data }) => {
				setLoadingState(false); // Stop loading indicator *before* feedback audio
				
				if (!data.success) {
					let errorMsg = data.message || `HTTP error! status: ${status}`;
					throw new Error(errorMsg);
				}
				
				console.log('Answer feedback received:', data);
				
				// Store feedback and new state
				feedbackData = {
					was_correct: data.was_correct,
					correct_index: data.correct_index,
					feedback_text: data.feedback_text,
					feedback_audio_url: data.feedback_audio_url,
					level_advanced: data.level_advanced, // We still use this to *inform* the transition logic
					lesson_completed: data.lesson_completed
				};
				currentState = data.newState; // Update state based on backend calculation
				
				setInteractionsDisabled(true); // Disable interactions while showing feedback/playing audio
				updateUI(); // Update UI immediately to show feedback text/styles
				
				// Play feedback audio if available, otherwise check state immediately
				if (feedbackData.feedback_audio_url && feedbackAudioPlayer) {
					playFeedbackAudio(); // Calls checkStateAndTransition on end/error
				} else {
					// No feedback audio, check state/transition immediately after a short delay for user to read feedback
					setTimeout(checkStateAndTransition, 500); // 500ms delay
				}
			})
			.catch(error => {
				console.error('Error submitting answer:', error);
				setErrorState(`Failed to submit answer: ${error.message}`);
				selectedIndex = null;
				feedbackData = null;
				// Re-enable buttons if submit failed?
				quizAnswersContainer?.querySelectorAll('.answer-btn').forEach(btn => {
					btn.disabled = false;
					btn.classList.remove('selected');
				});
				setLoadingState(false); // Stop loading, re-enables interactions implicitly if needed
				updateUI(); // Refresh UI state
			});
	}
	
	// --- UI Update Functions ---
	function updateProgressBar() {
		if (!progressBar || !partIndicatorContainer || !currentState) return;
		const currentPart = currentState.partIndex;
		const currentDifficulty = currentState.difficulty || 'easy';
		const currentDifficultyIndex = difficulties.indexOf(currentDifficulty);
		const correctInCurrentDiff = currentState.correctCounts?.[currentDifficulty] ?? 0;
		const required = currentState.requiredCorrect ?? 2;
		
		const partsCompleted = currentPart;
		const difficultiesCompletedInPart = currentDifficultyIndex < 0 ? 0 : currentDifficultyIndex;
		const progressInCurrentDifficulty = required > 0 ? Math.min(1, correctInCurrentDiff / required) : 1;
		const totalDifficultySteps = totalParts * difficulties.length;
		const stepsCompleted = (partsCompleted * difficulties.length) + difficultiesCompletedInPart;
		
		let overallProgress = 0;
		if (currentState.status === 'completed') {
			overallProgress = 100;
		} else if (totalDifficultySteps > 0) {
			overallProgress = Math.round(((stepsCompleted + progressInCurrentDifficulty) / totalDifficultySteps) * 100);
		}
		overallProgress = Math.min(100, Math.max(0, overallProgress));
		
		progressBar.style.width = `${overallProgress}%`;
		progressBar.textContent = `${overallProgress}%`;
		progressBar.setAttribute('aria-valuenow', overallProgress);
		
		for (let i = 0; i < totalParts; i++) {
			const label = document.getElementById(`partLabel_${i}`);
			if (label) {
				label.classList.remove('active', 'completed');
				if (i < currentPart) {
					label.classList.add('completed');
				} else if (i === currentPart && currentState.status !== 'completed') {
					label.classList.add('active');
				}
				if (currentState.status === 'completed') {
					label.classList.add('completed'); // Mark all parts complete
					label.classList.remove('active');
				}
			}
		}
	}
	
	function showPartIntro(partIndexToShow) {
		console.log(`Showing intro for part ${partIndexToShow}`);
		stopPlaybackSequence(true); // Stop quiz audio and enable interactions
		feedbackData = null; // Clear any lingering feedback
		isPartIntroVisible = true;
		hasIntroVideoPlayed = false; // Reset video played flag
		currentPartQuizzes = []; // Clear quizzes from previous part
		currentQuizIndex = -1;
		currentQuiz = null;
		
		// Hide Quiz Area, Show Intro Area
		toggleElement(quizArea, false);
		toggleElement(completionMessage, false);
		toggleElement(partIntroArea, true);
		
		// --- Fetch Intro Content dynamically ---
		// We need the Subject model's lesson_parts here.
		// Option 1: Pass full subject data initially (can be large)
		// Option 2: Make a small AJAX call to get part details (better)
		// Option 3: Assume `currentState` might hold it (less reliable if state only has counts)
		// Let's choose Option 2 (or enhance `currentState` structure if preferred).
		// For simplicity here, we'll assume `currentState` magically gets the text/video URL
		// from the backend during state calculation/submit response.
		// If not, an AJAX call here is needed.
		
		// Assuming currentState has the data needed (modify backend if needed)
		const introText = currentState.currentPartIntroText;
		const introVideoUrl = currentState.currentPartVideoUrl;
		
		// Populate Intro Content
		const partNumber = partIndexToShow + 1;
		partIntroTitle.textContent = `Part ${partNumber}: Introduction`;
		partIntroText.textContent = introText || "Loading introduction..."; // Add loading state?
		startPartQuizButton.textContent = `Start Part ${partNumber} Quiz`;
		startPartQuizButton.disabled = false; // Should be enabled by default
		
		// Handle Video
		if (introVideoUrl) {
			partIntroVideo.src = introVideoUrl;
			toggleElement(partIntroVideo, true);
			toggleElement(partIntroVideoPlaceholder, false);
		} else {
			toggleElement(partIntroVideo, false);
			toggleElement(partIntroVideoPlaceholder, true);
			hasIntroVideoPlayed = true; // No video, treat as played
		}
		
		updateProgressBar(); // Update progress bar for the new part
		setInteractionsDisabled(false); // Ensure interactions are enabled for intro screen
		updateButtonStates(); // Update button enabled/disabled
	}
	
	function showQuizScreen() {
		// console.log("Showing quiz screen for Part:", currentState.partIndex);
		isPartIntroVisible = false;
		feedbackData = null; // Ensure feedback is cleared
		
		// Hide Intro Area, Show Quiz Area
		toggleElement(partIntroArea, false);
		toggleElement(completionMessage, false);
		toggleElement(quizArea, true);
		quizArea.dataset.currentPartIndex = currentState.partIndex; // Store current part index
		
		// Initial UI render for the quiz is handled by displayQuizAtIndex
		// Interactions are handled by displayQuizAtIndex/startPlaybackSequence
	}
	
	function showCompletionScreen() {
		console.log("Showing completion screen");
		stopPlaybackSequence(true); // Stop any audio
		isPartIntroVisible = false;
		feedbackData = null;
		currentPartQuizzes = [];
		currentQuizIndex = -1;
		currentQuiz = null;
		
		toggleElement(partIntroArea, false);
		toggleElement(quizArea, false);
		toggleElement(completionMessage, true);
		updateProgressBar(); // Ensure progress bar shows 100%
		setInteractionsDisabled(false); // Ensure interactions enabled on final screen
		updateButtonStates();
	}
	
	// --- Combined UI Update Logic ---
	function updateUI() {
		// console.log("Updating UI. Interactions Disabled:", interactionsDisabled, "Feedback:", !!feedbackData, "Loading:", isLoading);
		updateProgressBar();
		
		// If intro is visible, only update its buttons and return
		if (isPartIntroVisible) {
			updateButtonStates();
			return;
		}
		
		// Handle completion state (redundant check, but safe)
		if (currentState.status === 'completed') {
			if (!completionMessage.classList.contains('d-none')) return; // Already visible
			showCompletionScreen();
			return;
		}
		
		// If quiz area should be visible but no current quiz (e.g., during load), do nothing yet
		if (quizArea.classList.contains('d-none') || !currentQuiz) {
			// Don't update quiz elements if area is hidden or quiz not loaded
			updateButtonStates(); // Still update button states generally
			return;
		}
		
		// --- Update Quiz Specific Elements (delegated if needed) ---
		// updateUIForQuiz() handles question text, image, answers if currentQuiz is set
		
		// --- Update Feedback Section ---
		const showFeedback = feedbackData != null;
		toggleElement(feedbackSection, showFeedback);
		
		if (showFeedback) {
			const isCorrect = feedbackData.was_correct;
			feedbackHeading.textContent = isCorrect ? 'Correct!' : 'Not Quite!';
			feedbackHeading.className = isCorrect ? 'text-success mb-2' : 'text-danger mb-2';
			feedbackTextEl.textContent = feedbackData.feedback_text;
			
			// Update answer button styles based on feedback
			quizAnswersContainer?.querySelectorAll('.answer-btn').forEach(button => {
				const btnIndex = parseInt(button.dataset.index);
				button.classList.remove('selected', 'correct', 'incorrect', 'btn-outline-primary', 'btn-primary'); // Reset styles first
				
				// Always highlight the actual correct answer green
				if (btnIndex === feedbackData.correct_index) {
					button.classList.add('correct', 'btn-success'); // Use solid success color
				}
				// Highlight the user's *selected* answer
				else if (btnIndex === selectedIndex) {
					button.classList.add('selected'); // Mark as selected
					if (!isCorrect) {
						// If selection was wrong, mark it red
						button.classList.add('incorrect', 'btn-danger'); // Use solid danger color
					} else {
						// Selected and correct - already handled by correct check above
						// Maybe add a slightly different style? Or keep it just green.
					}
				}
				// For other non-selected, non-correct answers
				else {
					button.classList.add('btn-outline-secondary'); // Muted outline
				}
				
				button.disabled = true; // Keep buttons disabled while feedback is shown
			});
			
			
			// Show feedback messages based on state
			const required = currentState.requiredCorrect;
			const correctCount = currentState.correctCounts?.[currentState.difficulty] ?? 0;
			const remaining = Math.max(0, required - correctCount);
			
			// Show Incorrect message ONLY if incorrect AND next button will be shown (i.e. not auto-advancing)
			const willShowNextButton = !interactionsDisabled && !isLoading && !feedbackData.lesson_completed && !(feedbackData.level_advanced && feedbackData.was_correct); // Heuristic
			toggleElement(feedbackIncorrectMessage, !isCorrect && willShowNextButton);
			
			// Show Threshold message if correct BUT not advanced AND next button will be shown
			toggleElement(feedbackThresholdMessage, isCorrect && !feedbackData.level_advanced && willShowNextButton);
			if(remainingCorrectCount) remainingCorrectCount.textContent = remaining > 1 ? `${remaining} more` : `${remaining} more`;
			
			// Show Listen message ONLY if feedback audio is currently playing (interactionsDisabled + audio URL)
			toggleElement(feedbackListenMessage, interactionsDisabled && !!feedbackData.feedback_audio_url);
			
		} else {
			// Ensure feedback messages are hidden when no feedback data
			toggleElement(feedbackIncorrectMessage, false);
			toggleElement(feedbackThresholdMessage, false);
			toggleElement(feedbackListenMessage, false);
		}
		
		// Ensure button states (enabled/disabled/visibility) are correct
		updateButtonStates();
	} // End updateUI
	
	
	// --- Event Listeners ---
	function setupEventListeners() {
		if (closeErrorButton) {
			closeErrorButton.addEventListener('click', () => setErrorState(null));
		}
		
		if (quizAnswersContainer) {
			quizAnswersContainer.addEventListener('click', (event) => {
				const targetButton = event.target.closest('.answer-btn');
				if (targetButton && !targetButton.disabled) {
					submitAnswer(parseInt(targetButton.dataset.index, 10));
				}
			});
		}
		
		if (nextQuestionButton) {
			nextQuestionButton.addEventListener('click', () => {
				if (!isLoading && !interactionsDisabled) {
					console.log("Next Question button clicked.");
					
					console.log("Hiding feedback section on Next Question click.");
					feedbackData = null; // Clear data
					toggleElement(feedbackSection, false); // Hide section visually
					
					// Logic: If this button is clicked, it means we need to move
					// to the next question *in the current local list*.
					const nextIndex = currentQuizIndex + 1;
					if (nextIndex < currentPartQuizzes.length) {
						displayQuizAtIndex(nextIndex);
					} else {
						// This *shouldn't* happen if checkStateAndTransition works correctly
						// after the last answer submission.
						console.warn("Next button clicked, but no more local quizzes. Re-checking state.");
						checkStateAndTransition(); // Re-run transition logic as a fallback
					}
				}
			});
		}
		
		if (playFeedbackButton && feedbackAudioPlayer) {
			playFeedbackButton.addEventListener('click', () => {
				if (!interactionsDisabled) {
					setInteractionsDisabled(true); // Disable while playing
					updateUI(); // Reflect disabled state
					playFeedbackAudio();
				}
			});
			feedbackAudioPlayer.addEventListener('ended', handleFeedbackAudioEnd);
			feedbackAudioPlayer.addEventListener('error', handleFeedbackAudioEnd); // Treat error same as end
		}
		
		if (ttsAudioPlayer) {
			ttsAudioPlayer.addEventListener('ended', handleTtsAudioEnded);
			ttsAudioPlayer.addEventListener('error', handleTtsAudioError);
			// Add pause handling if needed
		}
		
		if (startPartQuizButton) {
			startPartQuizButton.addEventListener('click', () => {
				if (!isLoading && !interactionsDisabled) {
					console.log("Start Part Quiz button clicked for Part:", currentState.partIndex, "Difficulty:", currentState.difficulty);
					if (currentState.partIndex === null || currentState.difficulty === null) {
						setErrorState("Cannot start quiz: Invalid state (part or difficulty missing).");
						return;
					}
					// Load questions for the current state's part/difficulty
					loadQuestionsForLevel(currentState.partIndex, currentState.difficulty);
				}
			});
		}
		
		if (partIntroVideo) {
			partIntroVideo.addEventListener('play', () => hasIntroVideoPlayed = true);
			// Add logic here if startPartQuizButton should be disabled until video plays
		}
	}
	
	// --- Initialization ---
	function initQuizInterface() {
		console.log("Initializing Interactive Quiz...");
		console.log("Initial State:", currentState);
		// console.log("Initial Quiz:", currentQuiz); // Should be null now
		console.log("Total Parts:", totalParts);
		
		setLoadingState(true, 'Initializing...');
		
		if (!currentState || !subjectSessionId) {
			setErrorState("Failed to load initial quiz state. Please try refreshing the page.");
			setLoadingState(false);
			return;
		}
		
		setupEventListeners();
		
		// Determine initial view: Completion or Intro
		if (currentState.status === 'completed') {
			showCompletionScreen();
		} else if (currentState.partIndex >= 0 && currentState.partIndex < totalParts) {
			// Always show the intro for the current part first upon initial load or refresh
			showPartIntro(currentState.partIndex);
		} else {
			// Should not happen with valid state calculation, indicates an error
			setErrorState("Invalid starting state detected (Part index out of bounds). Please try refreshing.");
			toggleElement(partIntroArea, false); // Hide potentially broken intro
		}
		
		setLoadingState(false); // Done initializing
		console.log("Interactive Quiz Initialized.");
	}
	
	// Start the interface
	initQuizInterface();
});
