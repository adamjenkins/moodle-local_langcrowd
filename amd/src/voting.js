// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Language Crowdsourcing voting module.
 *
 * Reads page-string data injected by hook_callbacks, calls the web service
 * to register strings and fetch vote state, then adds tick/cross buttons
 * beside each unvoted text node via a DOM TreeWalker scan.
 *
 * @module     local_langcrowd/voting
 * @copyright  2026 hama.history@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/notification'], function(Ajax, Notification) {

    'use strict';

    /** Map of rendered string value -> {stringid, component, key, status, voted, vote} */
    var stringMap = new Map();

    /** UI strings passed from PHP. */
    var ui = {};

    /** 'hover' or 'always' — controls button visibility. */
    var showmode = 'hover';

    /** Highlight colour applied to the wrap on hover. */
    var highlightcolor = '#fff3cd';

    /**
     * Entry point — called via js_call_amd by hook_callbacks.
     * Reads the payload from window.langcrowdInit which is set before RequireJS loads.
     */
    function init() {
        var data = window.langcrowdInit;
        if (!data) {
            return;
        }

        ui = data.uistrings || {};
        showmode = data.showmode || 'hover';
        highlightcolor = data.highlightcolor || '#fff3cd';

        var strings = data.strings;
        var lang = data.lang;

        if (!strings || strings.length === 0) {
            return;
        }

        injectStyles();

        Ajax.call([{
            methodname: 'local_langcrowd_get_string_ids',
            args: {strings: strings, lang: lang},
        }])[0].then(function(results) {

            results.forEach(function(item) {
                // Only add buttons for strings not yet voted on and not locked.
                if (item.status === 'locked' || item.voted) {
                    return;
                }
                var pageStr = strings.find(function(s) {
                    return s.component === item.component && s.key === item.key;
                });
                if (pageStr) {
                    stringMap.set(pageStr.value, item);
                }
            });

            if (stringMap.size > 0) {
                scanAndAnnotate();
                watchForDomChanges();
            }

            return null;
        }).catch(Notification.exception);
    }

    /**
     * Injects a <style> block that controls button visibility based on showmode.
     * In 'hover' mode buttons are hidden until the wrap is hovered/focused.
     * In 'always' mode buttons are permanently visible.
     */
    function injectStyles() {
        var style = document.createElement('style');
        var hcss = '.langcrowd-wrap:hover { background-color:' + highlightcolor +
                   ';border-radius:2px;transition:background-color .1s; }';
        if (showmode === 'always') {
            style.textContent =
                '.langcrowd-wrap .langcrowd-btn { opacity:1; transition:opacity .15s; }' + hcss;
        } else {
            style.textContent =
                '.langcrowd-wrap .langcrowd-btn { opacity:0; transition:opacity .15s; }' +
                '.langcrowd-wrap:hover .langcrowd-btn,' +
                '.langcrowd-wrap:focus-within .langcrowd-btn { opacity:1; }' +
                hcss;
        }
        document.head.appendChild(style);
    }

    /**
     * Walks all text nodes under root and adds buttons to any that match a
     * tracked string value.
     *
     * @param {Element} [root]  Subtree to scan; defaults to document.body.
     */
    function scanAndAnnotate(root) {
        var searchRoot = root || document.body;
        var walker = document.createTreeWalker(
            searchRoot,
            NodeFilter.SHOW_TEXT,
            {
                acceptNode: function(node) {
                    var parent = node.parentElement;
                    if (!parent) {
                        return NodeFilter.FILTER_REJECT;
                    }
                    var tag = parent.tagName.toLowerCase();
                    if (['script', 'style', 'noscript', 'textarea', 'input', 'select',
                         'option', 'button', 'label'].indexOf(tag) !== -1) {
                        return NodeFilter.FILTER_REJECT;
                    }
                    // Skip anything inside an existing button, input or form element.
                    if (parent.closest('button, input, select, textarea')) {
                        return NodeFilter.FILTER_REJECT;
                    }
                    // Skip user-created content areas (activity names, course blocks, etc.).
                    if (parent.closest &&
                            parent.closest('.activityname, .instancename, li.activity, ' +
                                '[data-activityname], [data-region="course-index-section"], ' +
                                '.block_myoverview, .block_timeline')) {
                        return NodeFilter.FILTER_REJECT;
                    }
                    // Skip text nodes that are already inside an annotated wrapper.
                    if (parent.closest && parent.closest('.langcrowd-wrap')) {
                        return NodeFilter.FILTER_REJECT;
                    }
                    return NodeFilter.FILTER_ACCEPT;
                }
            }
        );

        var matches = [];
        var node;
        while ((node = walker.nextNode())) {
            var text = node.textContent.trim();
            if (text && stringMap.has(text)) {
                matches.push({node: node, info: stringMap.get(text)});
            }
        }

        matches.forEach(function(m) {
            injectButtons(m.node, m.info);
        });
    }

    /**
     * Wraps a text node and appends tick and cross buttons beside it.
     *
     * @param {Text}   textNode
     * @param {Object} info      String record from the web service.
     */
    function injectButtons(textNode, info) {
        var parent = textNode.parentElement;
        if (!parent) {
            return;
        }

        var wrap = document.createElement('span');
        wrap.className = 'langcrowd-wrap';

        var tick = document.createElement('button');
        tick.type = 'button';
        tick.className = 'langcrowd-btn';
        tick.setAttribute('title', ui.btn_approve || 'Approve');
        tick.setAttribute('aria-label', ui.btn_approve || 'Approve');
        tick.innerHTML = '&#x2713;';
        tick.style.cssText =
            'display:inline-flex;align-items:center;justify-content:center;' +
            'min-width:18px;height:18px;font-size:11px;font-weight:700;' +
            'background:#198754;color:#fff;border:none;border-radius:3px;' +
            'cursor:pointer;margin-left:4px;vertical-align:middle;padding:0 2px;line-height:1;';

        var cross = document.createElement('button');
        cross.type = 'button';
        cross.className = 'langcrowd-btn';
        cross.setAttribute('title', ui.btn_suggest || 'Suggest alternative');
        cross.setAttribute('aria-label', ui.btn_suggest || 'Suggest alternative');
        cross.innerHTML = '&#x2717;';
        cross.style.cssText =
            'display:inline-flex;align-items:center;justify-content:center;' +
            'min-width:18px;height:18px;font-size:11px;font-weight:700;' +
            'background:#dc3545;color:#fff;border:none;border-radius:3px;' +
            'cursor:pointer;margin-left:2px;vertical-align:middle;padding:0 2px;line-height:1;';

        var originalText = textNode.textContent.trim();

        // If the text is inside a link, wrap the entire <a> so buttons live outside
        // the anchor and can't trigger the link's capture-phase click handlers.
        var linkAncestor = parent.closest ? parent.closest('a') : null;
        if (linkAncestor && linkAncestor.parentElement &&
                !linkAncestor.closest('.langcrowd-wrap')) {
            var linkParent = linkAncestor.parentElement;
            linkParent.insertBefore(wrap, linkAncestor);
            wrap.appendChild(linkAncestor);
        } else {
            parent.insertBefore(wrap, textNode);
            wrap.appendChild(textNode);
        }
        wrap.appendChild(tick);
        wrap.appendChild(cross);

        tick.addEventListener('click', function(e) {
            e.stopPropagation();
            submitVote(info.stringid, 1, wrap);
        });

        cross.addEventListener('click', function(e) {
            e.stopPropagation();
            openModal(info, originalText, wrap);
        });
    }

    /**
     * Submits an approve or reject vote via AJAX.
     *
     * @param {number} stringid
     * @param {number} vote      1 or -1
     * @param {Element} wrap
     */
    function submitVote(stringid, vote, wrap) {
        Ajax.call([{
            methodname: 'local_langcrowd_submit_vote',
            args: {stringid: stringid, vote: vote},
        }])[0].then(function(result) {
            if (result.success) {
                removeButtons(wrap);
                flash(wrap, '&#x2713;', '#28a745');
            }
            return null;
        }).catch(Notification.exception);
    }

    /**
     * Removes the tick and cross buttons from a wrap element.
     *
     * @param {Element} wrap
     */
    function removeButtons(wrap) {
        wrap.querySelectorAll('button').forEach(function(btn) {
            btn.remove();
        });
    }

    /**
     * Briefly shows a confirmation symbol then fades it out.
     *
     * @param {Element} wrap
     * @param {string}  symbol  HTML entity string.
     * @param {string}  color   CSS colour value.
     */
    function flash(wrap, symbol, color) {
        var span = document.createElement('span');
        span.innerHTML = symbol;
        span.style.cssText = 'color:' + color + ';font-size:.7em;vertical-align:super;margin-left:2px';
        wrap.appendChild(span);
        setTimeout(function() {
            if (span.parentNode) {
                span.parentNode.removeChild(span);
            }
        }, 2000);
    }

    /**
     * Escapes a string for safe insertion as HTML text content.
     *
     * @param  {string} str
     * @return {string}
     */
    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(String(str)));
        return div.innerHTML;
    }

    /**
     * Opens a lightweight suggestion modal (no Moodle modal dependency).
     *
     * @param {Object}  info          String record.
     * @param {string}  originalText  Rendered string value for display.
     * @param {Element} wrap          The annotation wrapper element.
     */
    function openModal(info, originalText, wrap) {
        var existing = document.getElementById('langcrowd-modal');
        if (existing) {
            existing.parentNode.removeChild(existing);
        }

        var backdrop = document.createElement('div');
        backdrop.id = 'langcrowd-modal';
        backdrop.setAttribute('role', 'dialog');
        backdrop.setAttribute('aria-modal', 'true');
        backdrop.setAttribute('aria-label', ui.modal_suggest_title || 'Suggest translation');
        backdrop.style.cssText =
            'position:fixed;top:0;left:0;width:100%;height:100%;' +
            'background:rgba(0,0,0,.5);z-index:9999;' +
            'display:flex;align-items:center;justify-content:center';

        backdrop.innerHTML =
            '<div style="background:#fff;border-radius:4px;padding:24px;' +
            'max-width:500px;width:90%;box-shadow:0 4px 16px rgba(0,0,0,.3)">' +
            '<h5 style="margin:0 0 16px">' + escapeHtml(ui.modal_suggest_title || 'Suggest translation') + '</h5>' +
            '<p style="margin:0 0 4px"><strong>' + escapeHtml(ui.modal_original_label || 'Current text') + '</strong></p>' +
            '<p style="color:#555;margin:0 0 16px">' + escapeHtml(originalText) + '</p>' +
            '<p style="margin:0 0 4px"><label for="lc-suggestion"><strong>' +
            escapeHtml(ui.modal_suggestion_label || 'Your suggestion') + '</strong></label></p>' +
            '<textarea id="lc-suggestion" rows="3" maxlength="4096"' +
            ' style="width:100%;margin-bottom:16px;padding:8px;' +
            'border:1px solid #ccc;border-radius:3px;box-sizing:border-box"></textarea>' +
            '<div style="display:flex;gap:8px;justify-content:flex-end">' +
            '<button type="button" id="lc-cancel" style="padding:6px 14px;border:1px solid #6c757d;' +
            'border-radius:3px;background:#fff;cursor:pointer">' +
            escapeHtml(ui.modal_cancel || 'Cancel') + '</button>' +
            '<button type="button" id="lc-submit" style="padding:6px 14px;border:none;' +
            'border-radius:3px;background:#0d6efd;color:#fff;cursor:pointer">' +
            escapeHtml(ui.modal_submit || 'Submit') + '</button>' +
            '</div></div>';

        document.body.appendChild(backdrop);

        var textarea = backdrop.querySelector('#lc-suggestion');

        backdrop.querySelector('#lc-cancel').addEventListener('click', function() {
            backdrop.parentNode.removeChild(backdrop);
        });

        backdrop.addEventListener('click', function(e) {
            if (e.target === backdrop) {
                backdrop.parentNode.removeChild(backdrop);
            }
        });

        backdrop.querySelector('#lc-submit').addEventListener('click', function() {
            var suggestion = textarea.value.trim();
            if (!suggestion) {
                return;
            }
            Ajax.call([{
                methodname: 'local_langcrowd_submit_suggestion',
                args: {stringid: info.stringid, suggestion: suggestion},
            }])[0].then(function(result) {
                if (result.success) {
                    backdrop.parentNode.removeChild(backdrop);
                    removeButtons(wrap);
                    flash(wrap, '&#x2713;', '#6c757d');
                }
                return null;
            }).catch(Notification.exception);
        });

        setTimeout(function() {
            textarea.focus();
        }, 50);
    }

    /**
     * Sets up a MutationObserver to re-scan newly added subtrees.
     * Debounced at 150 ms to coalesce rapid reactive re-renders.
     */
    function watchForDomChanges() {
        var timer = null;
        var pending = [];

        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType !== Node.ELEMENT_NODE) {
                        return;
                    }
                    if (node.classList.contains('langcrowd-wrap') ||
                        node.classList.contains('langcrowd-btn') ||
                        node.id === 'langcrowd-modal') {
                        return;
                    }
                    pending.push(node);
                });
            });

            if (pending.length === 0) {
                return;
            }

            clearTimeout(timer);
            timer = setTimeout(function() {
                var nodes = pending.slice();
                pending = [];
                nodes.forEach(function(node) {
                    if (document.body.contains(node)) {
                        scanAndAnnotate(node);
                    }
                });
            }, 150);
        });

        observer.observe(document.body, {childList: true, subtree: true});
    }

    return {init: init};
});
