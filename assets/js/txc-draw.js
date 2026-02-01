/**
 * TXC Draw Animation - Alpine.js component
 */
function txcDraw(config) {
    return {
        competitionId: config.competitionId || 0,
        isAdmin: config.isAdmin || false,
        status: 'idle', // idle, countdown, rolling, result
        countdownValue: 5,
        currentRoll: null,
        rolls: [],
        winnerTicket: null,
        seedHash: '',
        animating: false,
        displayNumber: '?',
        rollIndex: 0,

        /**
         * Start the draw animation with pre-loaded data.
         */
        startAnimation(drawData) {
            this.rolls = drawData.rolls || [];
            this.winnerTicket = drawData.winning_ticket || null;
            this.seedHash = drawData.seed_hash || '';
            this.status = 'countdown';
            this.countdownValue = 5;
            this.rollIndex = 0;

            this.runCountdown();
        },

        runCountdown() {
            const interval = setInterval(() => {
                this.countdownValue--;
                if (this.countdownValue <= 0) {
                    clearInterval(interval);
                    this.processNextRoll();
                }
            }, 1000);
        },

        processNextRoll() {
            if (this.rollIndex >= this.rolls.length) {
                this.status = 'result';
                return;
            }

            const roll = this.rolls[this.rollIndex];
            this.currentRoll = roll;
            this.status = 'rolling';
            this.animating = true;

            // Slot machine animation - rapidly cycle numbers
            let cycles = 0;
            const maxCycles = 20;
            const animInterval = setInterval(() => {
                this.displayNumber = Math.floor(Math.random() * 9999) + 1;
                cycles++;
                if (cycles >= maxCycles) {
                    clearInterval(animInterval);
                    this.displayNumber = roll.ticket;
                    this.animating = false;

                    if (roll.result === 'rejected') {
                        // Show rejected state, then move to next roll after delay
                        setTimeout(() => {
                            this.rollIndex++;
                            this.processNextRoll();
                        }, 5000);
                    } else if (roll.result === 'winner') {
                        // Winner!
                        this.status = 'result';
                    }
                }
            }, 80);
        },

        /**
         * Admin: trigger the draw via AJAX.
         */
        triggerDraw() {
            this.status = 'countdown';
            this.countdownValue = 5;

            fetch(txcAdmin ? txcAdmin.ajaxUrl : txcPublic.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'txc_manual_draw',
                    nonce: txcAdmin ? txcAdmin.nonce : txcPublic.nonce,
                    competition_id: this.competitionId,
                }),
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    this.startAnimation(res.data);
                } else {
                    this.status = 'idle';
                    alert(res.data ? res.data.message : 'Draw failed.');
                }
            })
            .catch(() => {
                this.status = 'idle';
                alert('Network error during draw.');
            });

            this.runCountdown();
        },

        get isRejected() {
            return this.currentRoll && this.currentRoll.result === 'rejected';
        },

        get isWinner() {
            return this.status === 'result' && this.winnerTicket !== null;
        },

        get rollsCompleted() {
            return this.rolls.slice(0, this.rollIndex + 1);
        }
    };
}

