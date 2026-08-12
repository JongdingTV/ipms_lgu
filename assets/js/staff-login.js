/* auth/login.php — password visibility toggle. External file (not inline)
   because this page's CSP is script-src 'self' ... with no 'unsafe-inline',
   so an inline <script> block is silently blocked by the browser. */
(function () {
    var toggle = document.getElementById('passwordToggle');
    var input = document.getElementById('password');
    if (!toggle || !input) return;

    function syncVisibility() {
        toggle.classList.toggle('has-value', input.value.length > 0);
    }

    input.addEventListener('input', syncVisibility);
    // Browser/password-manager autofill sets .value without firing a normal
    // 'input' event in every case — 'change' catches most of the rest, and
    // the CSS 'animationstart' hook (see .password-field input:-webkit-autofill
    // in login.php) catches Chrome/Edge/Safari's autofill highlight, which
    // fires even when neither input nor change does.
    input.addEventListener('change', syncVisibility);
    input.addEventListener('animationstart', function (e) {
        if (e.animationName === 'passwordFieldAutofilled') syncVisibility();
    });
    syncVisibility(); // covers a value already filled in by back-forward cache

    toggle.addEventListener('click', function () {
        var showing = input.type === 'text';
        input.type = showing ? 'password' : 'text';
        toggle.setAttribute('aria-pressed', String(!showing));
        toggle.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
        toggle.innerHTML = '<i class="fa-solid fa-eye' + (showing ? '' : '-slash') + '"></i>';
    });
})();
