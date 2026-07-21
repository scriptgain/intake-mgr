/**
 * ShopMGR spam protection — reCAPTCHA v3 token fetch.
 *
 * v3 is invisible: there is no widget to tick, so the token has to be produced
 * on submit and posted in a hidden field. This script hooks the form that owns a
 * [data-captcha-v3] marker, runs grecaptcha.execute() once, injects the token,
 * and lets the submit proceed.
 *
 * Deliberately plain DOM, no Alpine. It registers no Alpine.data() and does not
 * depend on Alpine at all, so its load order relative to the Alpine CDN is
 * irrelevant — it is safe on the admin login page, which does not preload the
 * admin bundle. The other providers (v2, hCaptcha, Turnstile, built-in, none)
 * need no JS and never load this file.
 */
(function () {
    'use strict';

    function wire(node) {
        var form = node.closest('form');
        if (!form) {
            return;
        }

        var siteKey = node.getAttribute('data-sitekey');
        var field = node.getAttribute('data-field') || 'g-recaptcha-response';
        var action = node.getAttribute('data-action') || 'submit';
        var settled = false;

        form.addEventListener('submit', function (event) {
            if (settled) {
                return; // token already injected; let this pass through
            }

            // If grecaptcha never loaded, don't trap the user: submit as-is and
            // let the server apply its fail policy for a missing token.
            if (typeof grecaptcha === 'undefined' || !grecaptcha.execute) {
                return;
            }

            event.preventDefault();

            grecaptcha.ready(function () {
                grecaptcha.execute(siteKey, { action: action })
                    .then(function (token) {
                        var input = form.querySelector('[name="' + field + '"]');
                        if (input) {
                            input.value = token;
                        }
                        settled = true;
                        if (form.requestSubmit) {
                            form.requestSubmit();
                        } else {
                            form.submit();
                        }
                    })
                    .catch(function () {
                        // Could not get a token: submit anyway; server decides.
                        settled = true;
                        if (form.requestSubmit) {
                            form.requestSubmit();
                        } else {
                            form.submit();
                        }
                    });
            });
        });
    }

    function init() {
        var nodes = document.querySelectorAll('[data-captcha-v3]');
        for (var i = 0; i < nodes.length; i++) {
            wire(nodes[i]);
        }
    }

    if (document.readyState !== 'loading') {
        init();
    } else {
        document.addEventListener('DOMContentLoaded', init);
    }
})();
