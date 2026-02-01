/**
 * TXC Qualifying Question - Alpine.js component
 */
function txcQualifying() {
    return {
        competitionId: 0,
        questionId: 0,
        questionText: '',
        options: {},
        selectedAnswer: '',
        answered: false,
        loading: false,
        resultMessage: '',
        resultCorrect: false,
        timeLeft: 30,
        timerActive: false,
        timerInterval: null,
        cooldownActive: false,
        cooldownSeconds: 0,
        cooldownInterval: null,
        cooldownDisplay: '',

        init() {
            window.addEventListener('txc-load-question', (e) => {
                this.competitionId = e.detail.competitionId;
                this.fetchQuestion();
            });
        },

        fetchQuestion() {
            this.loading = true;
            this.resetState();

            fetch(txcPublic.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'txc_get_question',
                    nonce: txcPublic.nonce,
                    competition_id: this.competitionId,
                }),
            })
            .then(r => r.json())
            .then(res => {
                this.loading = false;
                if (res.success) {
                    if (res.data.already_qualified) {
                        window.dispatchEvent(new Event('txc-qualified'));
                        return;
                    }
                    this.questionId = res.data.question_id;
                    this.questionText = res.data.question;
                    this.options = res.data.options;
                    this.startTimer(res.data.time_limit || 30);
                } else {
                    if (res.data && res.data.cooldown_seconds) {
                        this.startCooldown(res.data.cooldown_seconds);
                    }
                    this.resultMessage = res.data ? res.data.message : 'Error loading question.';
                    this.resultCorrect = false;
                }
            })
            .catch(() => {
                this.loading = false;
                this.resultMessage = 'Network error. Please try again.';
            });
        },

        selectAnswer(key) {
            if (!this.answered) {
                this.selectedAnswer = key;
            }
        },

        submitAnswer() {
            if (!this.selectedAnswer || this.answered || this.loading) return;

            this.loading = true;
            this.stopTimer();

            fetch(txcPublic.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'txc_submit_answer',
                    nonce: txcPublic.nonce,
                    competition_id: this.competitionId,
                    question_id: this.questionId,
                    answer: this.selectedAnswer,
                }),
            })
            .then(r => r.json())
            .then(res => {
                this.loading = false;
                this.answered = true;

                if (res.success) {
                    this.resultCorrect = true;
                    this.resultMessage = res.data.message;
                    setTimeout(() => {
                        window.dispatchEvent(new Event('txc-qualified'));
                    }, 1500);
                } else {
                    this.resultCorrect = false;
                    this.resultMessage = res.data ? res.data.message : 'Incorrect.';

                    if (res.data && res.data.cooldown_seconds) {
                        this.startCooldown(res.data.cooldown_seconds);
                    } else if (res.data && res.data.attempts_left > 0) {
                        setTimeout(() => {
                            this.fetchQuestion();
                        }, 2000);
                    }
                }
            })
            .catch(() => {
                this.loading = false;
                this.resultMessage = 'Network error. Please try again.';
            });
        },

        startTimer(seconds) {
            this.timeLeft = seconds;
            this.timerActive = true;
            this.timerInterval = setInterval(() => {
                this.timeLeft--;
                if (this.timeLeft <= 0) {
                    this.stopTimer();
                    this.resultMessage = 'Time expired! Please try again.';
                    this.resultCorrect = false;
                    this.answered = true;
                    setTimeout(() => this.fetchQuestion(), 2000);
                }
            }, 1000);
        },

        stopTimer() {
            this.timerActive = false;
            if (this.timerInterval) {
                clearInterval(this.timerInterval);
                this.timerInterval = null;
            }
        },

        startCooldown(seconds) {
            this.cooldownActive = true;
            this.cooldownSeconds = seconds;
            this.updateCooldownDisplay();

            this.cooldownInterval = setInterval(() => {
                this.cooldownSeconds--;
                if (this.cooldownSeconds <= 0) {
                    this.cooldownActive = false;
                    clearInterval(this.cooldownInterval);
                    this.cooldownInterval = null;
                    this.fetchQuestion();
                } else {
                    this.updateCooldownDisplay();
                }
            }, 1000);
        },

        updateCooldownDisplay() {
            const mins = Math.floor(this.cooldownSeconds / 60);
            const secs = this.cooldownSeconds % 60;
            this.cooldownDisplay = String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
        },

        resetState() {
            this.questionId = 0;
            this.questionText = '';
            this.options = {};
            this.selectedAnswer = '';
            this.answered = false;
            this.resultMessage = '';
            this.resultCorrect = false;
            this.stopTimer();
        }
    };
}
