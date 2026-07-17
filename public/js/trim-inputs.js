/**
 * Global input hygiene: strips leading/trailing whitespace automatically.
 * - Runs on blur (leaving the field) and again on form submit as a safety net.
 * - Textareas keep their INTERNAL line breaks and spacing (paragraphs are
 *   respected); only the outer whitespace is removed.
 * - Passwords, hidden and file inputs are never touched.
 * The server also trims via Laravel's TrimStrings middleware; this just keeps
 * what the user sees consistent with what gets saved.
 */
(function () {
    function shouldTrim(el) {
        if (!el || el.disabled || el.readOnly) return false;
        if (el.tagName === 'TEXTAREA') return true;
        if (el.tagName !== 'INPUT') return false;
        return ['text', 'email', 'search', 'tel', 'url'].includes(el.type);
    }

    function trimValue(el) {
        const trimmed = el.value.trim(); // outer whitespace only: inner newlines survive
        if (trimmed !== el.value) el.value = trimmed;
    }

    document.addEventListener('blur', function (e) {
        if (shouldTrim(e.target)) trimValue(e.target);
    }, true);

    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (!form || !form.elements) return;
        Array.from(form.elements).forEach(function (el) {
            if (shouldTrim(el)) trimValue(el);
        });
    }, true);
})();
