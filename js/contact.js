/**
 * Lamixtape — contact form AJAX submit (Phase 9.3).
 *
 * Vanilla JS (no jQuery dep). Intercepts <form#lmt-contact-form>
 * submit, runs client-side validation, POSTs to the REST endpoint
 * (Phase 9.1) with the wp_rest nonce, then swaps the form for a
 * success state on 200 or shows an inline error on failure.
 *
 * Listeners are delegated on document so they survive PJAX swaps
 * (the form lives in footer.php which is preserved across swaps —
 * but delegation is a simpler invariant than rebinding on every
 * lmt:pjax:swapped event).
 *
 * @package Lamixtape
 * @since   1.0.0 (Phase 9 — Item 1, CF7 → native form)
 */
(function () {
    'use strict';

    var initialized = false;

    function init() {
        if (initialized) { return; }
        initialized = true;
        document.addEventListener('submit', handleSubmit);
        document.addEventListener('click', handleResetClick);
    }

    function handleSubmit(event) {
        var form = event.target;
        if (!form || form.id !== 'lmt-contact-form') { return; }
        event.preventDefault();
        if (form.classList.contains('submitting')) { return; }

        var values = readFormValues(form);
        var clientErr = validateClient(values);
        if (clientErr) {
            showError(form, clientErr);
            return;
        }

        submitForm(form, values, form.getAttribute('data-nonce'));
    }

    function readFormValues(form) {
        return {
            name:    (form.querySelector('[name="name"]').value || '').trim(),
            email:   (form.querySelector('[name="email"]').value || '').trim(),
            message: (form.querySelector('[name="message"]').value || '').trim(),
            hp:      form.querySelector('[name="hp"]').value || ''
        };
    }

    function validateClient(v) {
        if (!v.email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.email)) {
            return 'Please provide a valid email address.';
        }
        if (v.message.length < 10) {
            return 'Message is too short (minimum 10 characters).';
        }
        if (v.message.length > 5000) {
            return 'Message is too long (maximum 5000 characters).';
        }
        return null;
    }

    function submitForm(form, payload, nonce) {
        form.classList.add('submitting');
        clearError(form);

        fetch('/wp-json/lamixtape/v1/contact', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce
            },
            body: JSON.stringify(payload)
        })
        .then(function (response) {
            return response.json().then(function (data) {
                return { status: response.status, data: data };
            });
        })
        .then(function (result) {
            form.classList.remove('submitting');
            if (result.status === 200 && result.data && result.data.success) {
                showSuccess(form, result.data.message);
                return;
            }
            showError(form, mapErrorMessage(result.status, result.data));
        })
        .catch(function () {
            form.classList.remove('submitting');
            showError(form, 'Network error. Please try again.');
        });
    }

    function mapErrorMessage(status, data) {
        if (status === 429) { return 'Too many messages. Please try again in an hour.'; }
        if (status === 422) { return 'Invalid request.'; }
        if (status === 403) { return 'Session expired. Please reload the page.'; }
        if (status === 500) { return 'Server error. Please try again later.'; }
        if (data && data.message) { return data.message; }
        return 'An error occurred. Please try again.';
    }

    function showError(form, message) {
        var feedback = form.querySelector('.lmt-contact-feedback');
        if (!feedback) { return; }
        feedback.textContent = message;
        feedback.classList.add('lmt-contact-error');
    }

    function clearError(form) {
        var feedback = form.querySelector('.lmt-contact-feedback');
        if (!feedback) { return; }
        feedback.textContent = '';
        feedback.classList.remove('lmt-contact-error');
    }

    function showSuccess(form, message) {
        form.hidden = true;
        var container = form.parentElement;
        if (!container) { return; }
        var successState = container.querySelector('.lmt-contact-success-state');
        if (!successState) { return; }
        var msgEl = successState.querySelector('.lmt-contact-success-message');
        if (msgEl) { msgEl.textContent = message; }
        successState.hidden = false;
    }

    function handleResetClick(event) {
        var btn = event.target;
        if (!btn || !btn.classList || !btn.classList.contains('lmt-contact-reset')) { return; }
        event.preventDefault();
        var container = btn.closest('.form-container, dialog');
        if (!container) { return; }
        var form = container.querySelector('#lmt-contact-form');
        var successState = container.querySelector('.lmt-contact-success-state');
        if (form) {
            form.reset();
            form.hidden = false;
            clearError(form);
        }
        if (successState) {
            successState.hidden = true;
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
