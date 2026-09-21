/* Citizen login password visibility control. */
(function () {
    var toggle = document.getElementById('passwordToggle');
    var input = document.getElementById('password');
    if (!toggle || !input) return;

    var holdTimer = null;
    var holdActive = false;
    var suppressClick = false;

    function setVisible(visible) {
        input.type = visible ? 'text' : 'password';
        toggle.setAttribute('aria-pressed', String(visible));
        toggle.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
        toggle.innerHTML = '<i class="fa-solid fa-eye' + (visible ? '-slash' : '') + '" aria-hidden="true"></i>';
    }

    function stopHold() {
        if (holdTimer) {
            clearTimeout(holdTimer);
            holdTimer = null;
        }
        if (holdActive) {
            setVisible(false);
            holdActive = false;
            suppressClick = true;
        }
    }

    toggle.addEventListener('click', function (event) {
        if (suppressClick) {
            suppressClick = false;
            event.preventDefault();
            return;
        }
        setVisible(input.type !== 'text');
    });

    toggle.addEventListener('pointerdown', function (event) {
        if (event.pointerType === 'mouse' && event.button !== 0) return;
        holdTimer = setTimeout(function () {
            holdActive = true;
            setVisible(true);
        }, 180);
    });

    toggle.addEventListener('pointerup', stopHold);
    toggle.addEventListener('pointercancel', stopHold);
    toggle.addEventListener('pointerleave', function (event) {
        if (event.pointerType === 'mouse') stopHold();
    });
})();
