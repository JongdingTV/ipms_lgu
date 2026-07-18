// ============================================================
// Staff session guard — logout confirmation + inactivity auto-logout.
// Mirrors the citizen portal's setupLogoutConfirm()/setupIdleLogout()
// (citizen/assets/js/citizen.js) with a tighter staff policy: 3 minutes
// of total inactivity ends the session, with the final 60 seconds spent
// on a countdown modal that only "Stay Logged In" can dismiss.
// Markup lives in includes/topbar.php (staff roles only).
// ============================================================
(function () {
    'use strict';

    var IDLE_LOGOUT_MS = 3 * 60 * 1000;
    var IDLE_WARNING_MS = 60 * 1000;

    function setupLogoutConfirm() {
        var modal = document.getElementById('logoutConfirmModal');
        if (!modal) return;

        document.querySelectorAll('.user-menu-logout, .btn-logout').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                modal.style.display = 'flex';
            });
        });

        var closeModal = function () { modal.style.display = 'none'; };
        document.getElementById('logoutConfirmClose').addEventListener('click', closeModal);
        document.getElementById('logoutCancelBtn').addEventListener('click', closeModal);
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeModal();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.style.display !== 'none') closeModal();
        });
    }

    function setupIdleLogout() {
        var modal = document.getElementById('idleWarningModal');
        if (!modal) return;

        var countdownEl = document.getElementById('idleCountdown');
        var logoutUrl = modal.dataset.logoutUrl;

        var warnTimer = null;
        var countdownTimer = null;
        var warningShown = false;
        var lastReset = 0;

        var startCountdown = function () {
            warningShown = true;
            var secondsLeft = Math.round(IDLE_WARNING_MS / 1000);
            countdownEl.textContent = secondsLeft;
            modal.style.display = 'flex';
            countdownTimer = setInterval(function () {
                secondsLeft--;
                countdownEl.textContent = Math.max(secondsLeft, 0);
                if (secondsLeft <= 0) {
                    clearInterval(countdownTimer);
                    window.location.href = logoutUrl;
                }
            }, 1000);
        };

        var resetIdleTimer = function () {
            if (warningShown) return;
            // Activity events fire constantly; only re-arm the timer once a second.
            var now = Date.now();
            if (now - lastReset < 1000) return;
            lastReset = now;
            clearTimeout(warnTimer);
            warnTimer = setTimeout(startCountdown, IDLE_LOGOUT_MS - IDLE_WARNING_MS);
        };

        document.getElementById('idleStayBtn').addEventListener('click', function () {
            clearInterval(countdownTimer);
            modal.style.display = 'none';
            warningShown = false;
            lastReset = 0;
            resetIdleTimer();
        });

        ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart'].forEach(function (evt) {
            document.addEventListener(evt, resetIdleTimer, { passive: true });
        });
        resetIdleTimer();
    }

    function init() {
        setupLogoutConfirm();
        setupIdleLogout();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
