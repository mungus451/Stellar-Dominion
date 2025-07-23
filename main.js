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
    // This timer counts down to the next resource and turn allocation.
    const timerDisplay = document.getElementById('next-turn-timer');
    if (timerDisplay) {
        // Get the initial countdown time from the 'data-seconds-until-next-turn' attribute
        // This value is passed from the PHP backend.
        let totalSeconds = parseInt(timerDisplay.dataset.secondsUntilNextTurn) || 0;

        const interval = setInterval(() => {
            // If the timer reaches zero, show a processing message and reload the page
            // to get the updated stats from the server.
            if (totalSeconds <= 0) {
                timerDisplay.textContent = "Processing...";
                clearInterval(interval);
                setTimeout(() => {
                    // Append a timestamp to prevent browser caching issues on reload
                    window.location.href = window.location.pathname + '?t=' + new Date().getTime();
                }, 1500); 
                return;
            }

            // Decrement the timer and update the display
            totalSeconds--;
            const minutes = Math.floor(totalSeconds / 60);
            const seconds = totalSeconds % 60;
            timerDisplay.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        }, 1000);
    }

    // --- Dominion Time Clock ---
    // This clock displays the current server time (in UTC).
    const timeDisplay = document.getElementById('dominion-time');
    if (timeDisplay) {
        // Get the initial server time from the 'data-*' attributes
        // These values are passed from the PHP backend.
        const initialHours = parseInt(timeDisplay.dataset.hours) || 0;
        const initialMinutes = parseInt(timeDisplay.dataset.minutes) || 0;
        const initialSeconds = parseInt(timeDisplay.dataset.seconds) || 0;

        let serverTime = new Date();
        // Set the time based on the UTC values provided by the server
        serverTime.setUTCHours(initialHours, initialMinutes, initialSeconds);

        setInterval(() => {
            // Increment the clock every second and update the display
            serverTime.setSeconds(serverTime.getSeconds() + 1);
            const hours = String(serverTime.getUTCHours()).padStart(2, '0');
            const minutes = String(serverTime.getUTCMinutes()).padStart(2, '0');
            const seconds = String(serverTime.getUTCSeconds()).padStart(2, '0');
            timeDisplay.textContent = `${hours}:${minutes}:${seconds}`;
        }, 1000);
    }

    // --- Point Allocation Form Helper ---
    // This logic is used on the levels.php and structures.php pages.
    const availablePointsEl = document.getElementById('available-points');
    const totalSpentEl = document.getElementById('total-spent');
    const inputs = document.querySelectorAll('.point-input');

    // Only run this logic if the necessary form elements are present on the page.
    if (availablePointsEl && totalSpentEl && inputs.length > 0) {
        function updateTotal() {
            let total = 0;
            inputs.forEach(input => {
                // Ensure the value is treated as a number
                total += parseInt(input.value) || 0;
            });
            totalSpentEl.textContent = total;
            
            // Visually indicate if the user tries to spend more points than they have.
            if (total > parseInt(availablePointsEl.textContent)) {
                totalSpentEl.classList.add('text-red-500');
            } else {
                totalSpentEl.classList.remove('text-red-500');
            }
        }
        
        // Add an event listener to each input to update the total on change.
        inputs.forEach(input => input.addEventListener('input', updateTotal));
    }
});
