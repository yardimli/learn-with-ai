<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}"> <!-- CSRF Token for AJAX -->
    
    <title>Learn with AI</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,600&display=swap" rel="stylesheet" />
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    
    <!-- Custom Styles (Optional) -->
    <style>
        body { font-family: 'Figtree', sans-serif; background-color: #f8f9fa; }
        .container { max-width: 800px; }
        .content-card, .quiz-card { background-color: #ffffff; border-radius: 0.5rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .loading-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-color: rgba(255, 255, 255, 0.8);
            display: flex; justify-content: center; align-items: center;
            z-index: 1050; /* Ensure it's above other content */
        }
        .video-placeholder {
            background-color: #e9ecef;
            min-height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            border-radius: 0.25rem;
        }
        .answer-btn { transition: background-color 0.3s ease; }
        .answer-btn.correct { background-color: #d1e7dd !important; border-color: #badbcc !important; color: #0f5132 !important; }
        .answer-btn.incorrect { background-color: #f8d7da !important; border-color: #f5c2c7 !important; color: #842029 !important; }
        .answer-btn.selected { border-width: 2px; }
        .answer-btn:disabled { cursor: not-allowed; }
        .feedback-section { border-left: 4px solid #0d6efd; padding-left: 15px; margin-top: 15px; }
    </style>
    
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

</head>
<body class="antialiased">

<div class="container mt-4 mb-5" x-data="appState()" x-init="init()">
    
    <h1 class="text-center mb-4">Learn Something New with AI</h1>
    
    <!-- Loading Indicator -->
    <div x-show="isLoading" class="loading-overlay" style="display: none;">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <span class="ms-2 fs-5" x-text="loadingMessage">Generating...</span>
    </div>
    
    <!-- Error Message Area -->
    <div x-show="errorMessage" class="alert alert-danger alert-dismissible fade show" role="alert" style="display: none;">
        <span x-text="errorMessage"></span>
        <button type="button" class="btn-close" @click="errorMessage = null" aria-label="Close"></button>
    </div>
    
    <!-- === Subject Input Section === -->
    <div x-show="currentState === 'initial'" class="card p-4 content-card">
        <form @submit.prevent="submitSubject">
            <div class="mb-3">
                <label for="subjectInput" class="form-label fs-5">Enter a Subject:</label>
                <input type="text" class="form-control form-control-lg" id="subjectInput" x-model="subject" placeholder="e.g., Quantum Physics, Photosynthesis, The Roman Empire" required>
            </div>

            <div class="mb-3">
                    <label for="llmSelect" class="form-label">Choose AI Model (Optional):</label>
                    <select class="form-select" id="llmSelect" x-model="selectedLlm">
                        <option value="">Use Default</option>
                        <?php foreach ($llms ?? [] as $llm): ?>
                        <option value="<?= htmlspecialchars($llm['id']) ?>"><?= htmlspecialchars($llm['name']) ?></option>
                        <?php endforeach; ?>
              </select>
					</div>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-lg" :disabled="isLoading || !subject.trim()">
                    <span x-show="isLoading" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                    Start Learning
                </button>
            </div>
        </form>
    </div>
    
    <!-- === Content Display Section === -->
    <div x-show="currentState === 'content_ready' || currentState === 'quiz_ready'" class="mb-4 content-card p-4" style="display: none;">
        <h2 x-text="content.title" class="text-center mb-3"></h2>
        
        <!-- Image -->
        <img x-show="content.imageUrl" :src="content.imageUrl" class="img-fluid rounded mb-3 mx-auto d-block" style="max-height: 300px;" alt="AI Generated Image" >
        <div x-show="!content.imageUrl" class="text-center text-muted mb-3">(No image generated)</div>
        
        <!-- Initial Video -->
        <div class="mb-3">
            <video controls width="100%" :src="content.initialVideoUrl" class="rounded" style="display: none;"></video>
        </div>
        
        <!-- Main Text -->
        <p class="lead" x-html="content.mainText"></p> <!-- Using x-html assuming text is safe or sanitized -->
        
        <hr>
        
        <div x-show="currentState === 'content_ready'">
            <p class="text-center">Generating the first quiz question...</p>
            <div class="d-flex justify-content-center">
                <div class="spinner-border text-secondary" role="status">
                    <span class="visually-hidden">Loading Quiz...</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- === Quiz Section === -->
    <div x-show="currentState === 'quiz_ready'" class="quiz-card p-4 mb-4" style="display: none;">
        <h3 class="text-center mb-3">Quiz Time!</h3>
        
        <!-- Question Video -->
        <div class="mb-3">
            <div x-show="quiz.questionVideoStatus === 'pending' || quiz.questionVideoStatus === 'processing'" class="video-placeholder">
                <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                Generating question video... <span x-text="quiz.questionVideoStatus"></span>
            </div>
            <div x-show="quiz.questionVideoStatus === 'failed'" class="video-placeholder text-danger">
                Question video generation failed.
            </div>
            <video x-show="quiz.questionVideoStatus === 'completed' && quiz.questionVideoUrl" controls width="100%" :src="quiz.questionVideoUrl" class="rounded" style="display: none;"></video>
        </div>
        
        
        <!-- Question Text -->
        <p class="fs-5 text-center mb-4" x-text="quiz.questionText"></p>
        
        <!-- Answer Buttons -->
        <div class="d-grid gap-3">
            <template x-for="(answer, index) in quiz.answers" :key="index">
                <button type="button"
                        class="btn btn-outline-primary btn-lg answer-btn"
                        @click="submitAnswer(index)"
                        :disabled="quiz.answered !== null"
                        :class="{
                                'selected': quiz.selectedIndex === index,
                                'correct': quiz.answered !== null && index === quiz.correctIndex,
                                'incorrect': quiz.answered !== null && quiz.selectedIndex === index && index !== quiz.correctIndex
                            }">
                    <span x-text="answer.text"></span>
                </button>
            </template>
        </div>
        
        <!-- Feedback Section -->
        <div x-show="quiz.answered !== null" class="mt-4 feedback-section" style="display: none;">
            <h4 x-text="quiz.answered === 'correct' ? 'Correct!' : 'Not Quite!'" :class="quiz.answered === 'correct' ? 'text-success' : 'text-danger'"></h4>
            <p x-text="quiz.feedbackText"></p>
            <button x-show="quiz.feedbackAudioUrl" @click="playAudio(quiz.feedbackAudioUrl)" class="btn btn-sm btn-secondary">
                Play Feedback Audio
            </button>
            <hr>
            <button @click="generateNextQuiz()" class="btn btn-info w-100" :disabled="isLoading">
                <span x-show="isLoading && loadingMessage.includes('quiz')" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                Next Question
            </button>
        </div>
        <!-- Hidden audio element -->
        <audio id="feedbackAudioPlayer" style="display: none;"></audio>
    </div>
    
    
    <!-- === Start Over Button === -->
    <div class="text-center mt-4">
        <button @click="resetApp" class="btn btn-outline-secondary">
            Start New Subject
        </button>
    </div>
</div>

<!-- Bootstrap JS Bundle (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>

<!-- Alpine.js Data and Methods -->
<script>
    function appState() {
        return {
            // State Variables
            currentState: 'initial', // 'initial', 'generating_content', 'content_ready', 'generating_quiz', 'quiz_ready', 'showing_feedback'
            isLoading: false,
            loadingMessage: '',
            errorMessage: null,
            subject: '',
            selectedLlm: '', // Optional LLM choice
            subjectId: null,
            content: {
                title: '',
                mainText: '',
                imageUrl: null,
                initialVideoUrl: null,
            },
            quiz: {
                quizId: null,
                questionText: '',
                answers: [], // { text: '', feedback_audio_url: '' }
                questionVideoUrl: null,
                selectedIndex: null,
                answered: null, // null, 'correct', 'incorrect'
                correctIndex: null,
                feedbackText: '',
                feedbackAudioUrl: null,
            },
            
            // Initialization
            init() {
                console.log('App initialized');
                // Any setup needed on load
            },
            
            // Methods
            resetApp() {
                console.log('Resetting application state');
                this.currentState = 'initial';
                this.isLoading = false;
                this.loadingMessage = '';
                this.errorMessage = null;
                this.subject = '';
                this.subjectId = null;
                this.content = { title: '', mainText: '', imageUrl: null, initialVideoUrl: null };
                this.quiz = { quizId: null, questionText: '', answers: [], questionVideoUrl: null, selectedIndex: null, answered: null, correctIndex: null, feedbackText: '', feedbackAudioUrl: null };
            },
            
            async submitSubject() {
                if (!this.subject.trim()) return;
                this.isLoading = true;
                this.errorMessage = null;
                this.loadingMessage = 'Generating initial content...';
                this.currentState = 'generating_content'; // Visual state change
                
                console.log('Submitting subject:', this.subject);
                
                try {
                    const response = await fetch('{{ route("content.generate") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            subject: this.subject,
                            llm: this.selectedLlm // Send selected LLM if implemented
                        })
                    });
                    
                    const data = await response.json();
                    
                    if (!response.ok || !data.success) {
                        throw new Error(data.message || `HTTP error! status: ${response.status}`);
                    }
                    
                    console.log('Initial content received:', data);
                    this.subjectId = data.subject_id;
                    this.content.title = data.title;
                    this.content.mainText = data.main_text; // Consider sanitizing if needed
                    this.content.imageUrl = data.image_url;
                    this.content.initialVideoUrl = data.initial_video_url;
                    
                    this.currentState = 'content_ready';
                    this.isLoading = false; // Content is ready, now generate quiz
                    
                    // Automatically trigger first quiz generation
                    this.generateNextQuiz();
                    
                } catch (error) {
                    console.error('Error generating initial content:', error);
                    this.errorMessage = `Failed to generate content: ${error.message}`;
                    this.isLoading = false;
                    this.currentState = 'initial'; // Revert state
                }
            },
            
            async generateNextQuiz() {
                if (!this.subjectId) return;
                this.isLoading = true;
                this.errorMessage = null;
                this.loadingMessage = 'Generating quiz question...';
                this.currentState = 'generating_quiz'; // Visual state update
                this.quiz = { ...this.quiz, selectedIndex: null, answered: null, correctIndex: null, feedbackText: '', feedbackAudioUrl: null };
                
                console.log('Requesting next quiz for subject:', this.subjectId);
                
                try {
                    const response = await fetch('{{ route("quiz.generate") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            subject_id: this.subjectId,
                            llm: this.selectedLlm // Pass LLM choice if needed
                        })
                    });
                    
                    const data = await response.json();
                    
                    if (!response.ok || !data.success) {
                        throw new Error(data.message || `HTTP error! status: ${response.status}`);
                    }
                    
                    console.log('Quiz data received:', data);
                    this.quiz.quizId = data.quiz_id;
                    this.quiz.questionText = data.question_text;
                    this.quiz.answers = data.answers; // Expecting [{text: '', feedback_audio_url: ''}]
                    this.quiz.questionVideoUrl = data.question_video_url;
                    
                    this.currentState = 'quiz_ready';
                    this.isLoading = false;
                    
                } catch (error) {
                    console.error('Error generating quiz:', error);
                    this.errorMessage = `Failed to generate quiz: ${error.message}`;
                    this.isLoading = false;
                    // Decide fallback state, maybe back to content or show error persist
                    this.currentState = 'content_ready'; // Fallback to showing content
                }
            },
            
            async submitAnswer(index) {
                if (this.quiz.answered !== null) return; // Prevent re-submission
                
                this.quiz.selectedIndex = index;
                this.isLoading = true; // Briefly indicate processing
                this.errorMessage = null;
                this.loadingMessage = 'Checking answer...';
                
                console.log('Submitting answer index:', index, 'for quiz:', this.quiz.quizId);
                
                try {
                    const response = await fetch('{{ route("answer.submit") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            quiz_id: this.quiz.quizId,
                            selected_index: index
                        })
                    });
                    
                    const data = await response.json();
                    
                    if (!response.ok || !data.success) {
                        // Handle specific errors like 'already answered'
                        if (response.status === 409) {
                            this.errorMessage = data.message || 'Quiz already answered.';
                            // Optionally show previous state? For now, just show error.
                        } else {
                            throw new Error(data.message || `HTTP error! status: ${response.status}`);
                        }
                    } else {
                        console.log('Answer feedback received:', data);
                        this.quiz.answered = data.was_correct ? 'correct' : 'incorrect';
                        this.quiz.correctIndex = data.correct_index;
                        this.quiz.feedbackText = data.feedback_text;
                        this.quiz.feedbackAudioUrl = data.feedback_audio_url;
                        
                        // Play audio automatically if URL exists
                        if (this.quiz.feedbackAudioUrl) {
                            this.playAudio(this.quiz.feedbackAudioUrl);
                        }
                        // currentState doesn't need to change, just quiz.answered
                    }
                    
                } catch (error) {
                    console.error('Error submitting answer:', error);
                    this.errorMessage = `Failed to submit answer: ${error.message}`;
                    // Reset selected index if submission failed?
                    // this.quiz.selectedIndex = null;
                } finally {
                    this.isLoading = false;
                    this.loadingMessage = '';
                }
            },
            
            playAudio(url) {
                if (!url) return;
                const player = document.getElementById('feedbackAudioPlayer');
                if (player) {
                    player.src = url;
                    player.play().catch(e => console.error("Audio playback error:", e));
                }
            }
        }
    }
</script>

</body>
</html>
