/**
 * Stellar Dominion - Main JavaScript File
 *
 * This file contains the shared JavaScript logic for the game pages,
 * including timers, icon initialization, and form helpers.
 */
document.addEventListener('DOMContentLoaded', () => {
    // Initialize Lucide icons on the page
    lucide.createIcons();

    // --- Next Turn Timer ---
    const timerDisplay = document.getElementById('next-turn-timer');
    if (timerDisplay) {
        let totalSeconds = parseInt(timerDisplay.dataset.secondsUntilNextTurn) || 0;
        const interval = setInterval(() => {
            if (totalSeconds <= 0) {
                timerDisplay.textContent = "Processing...";
                clearInterval(interval);
                setTimeout(() => {
                    window.location.href = window.location.pathname + '?t=' + new Date().getTime();
                }, 1500); 
                return;
            }
            totalSeconds--;
            const minutes = Math.floor(totalSeconds / 60);
            const seconds = totalSeconds % 60;
            timerDisplay.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        }, 1000);
    }

    // --- Dominion Time Clock ---
    const timeDisplay = document.getElementById('dominion-time');
    if (timeDisplay) {
        const initialHours = parseInt(timeDisplay.dataset.hours) || 0;
        const initialMinutes = parseInt(timeDisplay.dataset.minutes) || 0;
        const initialSeconds = parseInt(timeDisplay.dataset.seconds) || 0;
        let serverTime = new Date();
        serverTime.setUTCHours(initialHours, initialMinutes, initialSeconds);
        setInterval(() => {
            serverTime.setSeconds(serverTime.getSeconds() + 1);
            const hours = String(serverTime.getUTCHours()).padStart(2, '0');
            const minutes = String(serverTime.getUTCMinutes()).padStart(2, '0');
            const seconds = String(serverTime.getUTCSeconds()).padStart(2, '0');
            timeDisplay.textContent = `${hours}:${minutes}:${seconds}`;
        }, 1000);
    }

    // --- Point Allocation Form Helper ---
    const availablePointsEl = document.getElementById('available-points');
    const totalSpentEl = document.getElementById('total-spent');
    const pointInputs = document.querySelectorAll('.point-input');
    if (availablePointsEl && totalSpentEl && pointInputs.length > 0) {
        function updateTotal() {
            let total = 0;
            pointInputs.forEach(input => {
                total += parseInt(input.value) || 0;
            });
            totalSpentEl.textContent = total;
            if (total > parseInt(availablePointsEl.textContent)) {
                totalSpentEl.classList.add('text-red-500');
            } else {
                totalSpentEl.classList.remove('text-red-500');
            }
        }
        pointInputs.forEach(input => input.addEventListener('input', updateTotal));
    }

    // --- A.I. Advisor Text Rotator ---
    const advisorTextEl = document.getElementById('advisor-text');
    if (advisorTextEl) {
        const adviceList = JSON.parse(advisorTextEl.dataset.advice || '[]');
        let currentAdviceIndex = 0;

        if (adviceList.length > 1) {
            setInterval(() => {
                currentAdviceIndex = (currentAdviceIndex + 1) % adviceList.length;
                
                // Fade out the text
                advisorTextEl.style.opacity = 0;

                // After the fade-out, change the text and fade it back in
                setTimeout(() => {
                    advisorTextEl.textContent = adviceList[currentAdviceIndex];
                    advisorTextEl.style.opacity = 1;
                }, 500); // This should match the CSS transition duration

            }, 10000); // Rotate every 10 seconds
        }
    }
});
