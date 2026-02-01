/**
 * TXC Competition Page - Alpine.js component
 */
function txcCompetition(config) {
    return {
        id: config.id,
        maxPerUser: config.maxPerUser,
        remaining: config.remaining,
        canEnter: config.canEnter,
        isDrawn: config.isDrawn,
        drawDate: config.drawDate,
        isLoggedIn: config.isLoggedIn,
        quantity: 1,
        loading: false,
        message: '',
        success: false,
        showQuestion: false,

        enterCompetition() {
            if (!this.isLoggedIn) {
                window.location.href = txcPublic.loginUrl;
                return;
            }

            // First attempt to add to cart - server will check qualifying
            this.loading = true;
            this.message = '';

            fetch(txcPublic.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'txc_add_to_cart',
                    nonce: txcPublic.nonce,
                    competition_id: this.id,
                    quantity: this.quantity,
                }),
            })
            .then(r => r.json())
            .then(res => {
                this.loading = false;
                if (res.success) {
                    this.success = true;
                    this.message = res.data.message;
                    this.remaining = Math.max(0, this.remaining - this.quantity);
                    setTimeout(() => {
                        window.location.href = res.data.cart_url || txcPublic.cartUrl;
                    }, 1000);
                } else {
                    this.success = false;
                    if (res.data && res.data.require_question) {
                        this.showQuestion = true;
                        this.loadQuestion();
                    } else {
                        this.message = res.data ? res.data.message : 'Something went wrong.';
                    }
                }
            })
            .catch(() => {
                this.loading = false;
                this.message = 'Network error. Please try again.';
            });
        },

        loadQuestion() {
            // Dispatched to the qualifying component
            window.dispatchEvent(new CustomEvent('txc-load-question', {
                detail: { competitionId: this.id }
            }));
        },

        onQualified() {
            this.showQuestion = false;
            this.enterCompetition();
        },

        init() {
            window.addEventListener('txc-qualified', () => this.onQualified());
        }
    };
}

/**
 * Countdown timer component
 */
function txcCountdown(drawDate) {
    return {
        days: 0,
        hours: 0,
        minutes: 0,
        seconds: 0,
        display: '',
        expired: false,
        interval: null,

        start() {
            // Normalize date string to ISO 8601 UTC
            if (drawDate && typeof drawDate === 'string') {
                drawDate = drawDate.trim();
                if (!drawDate.includes('T')) {
                    drawDate = drawDate.replace(' ', 'T');
                }
                if (!/Z$/i.test(drawDate) && !/[+-]\d{2}:\d{2}$/.test(drawDate)) {
                    drawDate += 'Z';
                }
            }
            this.update();
            this.interval = setInterval(() => this.update(), 1000);
        },

        update() {
            const target = new Date(drawDate).getTime();
            if (isNaN(target)) {
                this.expired = true;
                this.display = 'Invalid date';
                if (this.interval) clearInterval(this.interval);
                return;
            }
            const now = Date.now();
            let diff = Math.max(0, Math.floor((target - now) / 1000));

            if (diff <= 0) {
                this.expired = true;
                this.display = 'Draw time reached';
                if (this.interval) clearInterval(this.interval);
                return;
            }

            this.days = Math.floor(diff / 86400);
            diff %= 86400;
            this.hours = Math.floor(diff / 3600);
            diff %= 3600;
            this.minutes = Math.floor(diff / 60);
            this.seconds = diff % 60;

            const parts = [];
            if (this.days > 0) parts.push(this.days + 'd');
            parts.push(String(this.hours).padStart(2, '0') + 'h');
            parts.push(String(this.minutes).padStart(2, '0') + 'm');
            parts.push(String(this.seconds).padStart(2, '0') + 's');
            this.display = parts.join(' ');
        },

        destroy() {
            if (this.interval) clearInterval(this.interval);
        }
    };
}
