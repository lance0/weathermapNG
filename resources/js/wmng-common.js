/**
 * WeathermapNG shared client helpers (no build step).
 * Load before page scripts. Exposes window.WMNG.* utilities.
 */
(function (global) {
    'use strict';

    var WMNG = global.WMNG || {};

    /**
     * CSRF token from meta tag (LibreNMS layout) with empty fallback.
     */
    WMNG.getCsrfToken = function getCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && meta.getAttribute) {
            return meta.getAttribute('content') || meta.content || '';
        }
        if (meta && meta.content) {
            return meta.content || '';
        }
        return '';
    };

    /**
     * fetch() + JSON parse with non-2xx surfaced as Error('HTTP {status} ...').
     * @param {string} url
     * @param {RequestInit} [options]
     * @returns {Promise<any>}
     */
    WMNG.fetchJson = function fetchJson(url, options) {
        return fetch(url, options).then(function (r) {
            if (!r.ok) {
                throw new Error('HTTP ' + r.status + (r.statusText ? ' ' + r.statusText : ''));
            }
            return r.json();
        });
    };

    /**
     * If ui-helpers.js failed to load or is stale, install safe no-op / console
     * fallbacks so save and other mutations never crash on missing methods.
     */
    WMNG.ensureUiHelpers = function ensureUiHelpers() {
        global.WMNGLoading = global.WMNGLoading || {};
        ['show', 'hide', 'toggle'].forEach(function (m) {
            if (typeof global.WMNGLoading[m] !== 'function') {
                global.WMNGLoading[m] = function () {};
            }
        });

        global.WMNGToast = global.WMNGToast || {};
        ['success', 'error', 'warning', 'info'].forEach(function (m) {
            if (typeof global.WMNGToast[m] !== 'function') {
                global.WMNGToast[m] = function (msg) {
                    var level = m === 'error' ? 'error' : 'log';
                    if (typeof console !== 'undefined' && console[level]) {
                        console[level](msg);
                    }
                };
            }
        });
    };

    /**
     * Detect LibreNMS dark/light theme and toggle dark-theme on a container.
     * @param {string} containerSelector CSS selector for the plugin root
     */
    WMNG.detectTheme = function detectTheme(containerSelector) {
        var container = document.querySelector(containerSelector);
        if (!container) {
            return;
        }

        var isDark = null;
        var navbar = document.querySelector('.navbar, .navbar-default, .navbar-static-top, nav');
        var elementsToCheck = [navbar, document.body].filter(Boolean);

        for (var i = 0; i < elementsToCheck.length; i++) {
            var element = elementsToCheck[i];
            var bg = window.getComputedStyle(element).backgroundColor;
            var rgb = bg.match(/\d+/g);
            if (rgb && rgb.length >= 3) {
                if (rgb.length === 4 && parseInt(rgb[3], 10) === 0) {
                    continue;
                }
                if (bg === 'rgba(0, 0, 0, 0)' || bg === 'transparent') {
                    continue;
                }
                var brightness =
                    (parseInt(rgb[0], 10) * 299 +
                        parseInt(rgb[1], 10) * 587 +
                        parseInt(rgb[2], 10) * 114) /
                    1000;
                isDark = brightness < 128;
                break;
            }
        }

        if (isDark === null) {
            var allClasses =
                (document.body.className || '') +
                ' ' +
                (document.documentElement.className || '');
            if (/\bdark\b|\bnight\b|\bdark-mode\b/i.test(allClasses)) {
                isDark = true;
            }
        }

        if (isDark === null) {
            isDark = false;
        }

        container.classList.toggle('dark-theme', isDark);
    };

    /**
     * Run theme detection on load and watch class / data-bs-theme changes.
     * Attribute filter intentionally excludes style (LibreNMS toggles inline
     * styles often; theme is signaled via class or data-bs-theme).
     * @param {string} containerSelector
     */
    WMNG.observeTheme = function observeTheme(containerSelector) {
        var run = function () {
            WMNG.detectTheme(containerSelector);
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () {
                setTimeout(run, 100);
            });
        } else {
            setTimeout(run, 100);
        }

        if (typeof MutationObserver === 'undefined') {
            return;
        }

        var observer = new MutationObserver(function () {
            setTimeout(run, 50);
        });
        observer.observe(document.body, { attributes: true, attributeFilter: ['class'] });
        observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['class', 'data-bs-theme'],
        });
    };

    global.WMNG = WMNG;
})(typeof window !== 'undefined' ? window : this);
