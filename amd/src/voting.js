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
 * Adds an opt-in "translate mode" toggle. When the user turns it on, the module
 * registers the page strings, then annotates matching text nodes with approve /
 * suggest buttons. Suggestions use a core/modal dialog. Voting can be undone for
 * a few seconds afterwards.
 *
 * @module     local_langcrowd/voting
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'core/ajax',
    'core/notification',
    'core/modal_save_cancel',
    'core/modal_events',
], function(Ajax, Notification, ModalSaveCancel, ModalEvents) {

    'use strict';

    /** Map of rendered string value -> string record from the web service. */
    var stringMap = new Map();

    /** UI strings passed from PHP. */
    var ui = {};

    /** 'hover' or 'always' — controls button visibility while translate mode is on. */
    var showmode = 'hover';

    /** Highlight colour applied to the wrap outline on hover. */
    var highlightcolor = '#fff3cd';

    /** Approve-vote threshold (0 = disabled), used for the progress hint. */
    var threshold = 0;

    /** Whether translate mode has fetched + annotated the page at least once. */
    var annotated = false;

    /** Whether translate mode is currently on. */
    var active = false;

    /** Session-storage key remembering the toggle state across pages. */
    var STORAGE_KEY = 'langcrowd-active';

    /**
     * Entry point — called via js_call_amd by hook_callbacks.
     */
    function init() {
        var data = window.langcrowdInit;
        if (!data || !data.strings || data.strings.length === 0) {
            return;
        }

        ui = data.uistrings || {};
        showmode = data.showmode || 'hover';
        highlightcolor = data.highlightcolor || '#fff3cd';
        threshold = parseInt(data.threshold, 10) || 0;

        // Touch devices have no hover, so buttons must be permanently visible there.
        if (window.matchMedia && window.matchMedia('(hover: none)').matches) {
            showmode = 'always';
        }

        injectStyles();

        // Master switch: when forced on, the overlay is always active and no toggle is shown.
        if (data.forceon) {
            setActive(true);
            return;
        }

        createToggle();

        // Restore the previous session's choice.
        if (sessionStorage.getItem(STORAGE_KEY) === '1') {
            setActive(true);
        }
    }

    /**
     * Builds the floating "translate mode" toggle button.
     */
    function createToggle() {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.id = 'langcrowd-toggle';
        btn.setAttribute('aria-pressed', 'false');
        btn.textContent = ui.toggle_label || 'Improve translations';
        btn.style.cssText =
            'position:fixed;bottom:16px;right:16px;z-index:1050;' +
            'padding:8px 14px;border:none;border-radius:20px;cursor:pointer;' +
            'background:#0d6efd;color:#fff;font-size:13px;font-weight:600;' +
            'box-shadow:0 2px 8px rgba(0,0,0,.3);';
        btn.addEventListener('click', function() {
            setActive(!active);
        });
        document.body.appendChild(btn);
    }

    /**
     * Turns translate mode on or off.
     *
     * @param {boolean} on
     */
    function setActive(on) {
        active = on;
        sessionStorage.setItem(STORAGE_KEY, on ? '1' : '0');

        var btn = document.getElementById('langcrowd-toggle');
        if (btn) {
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
            btn.style.background = on ? '#198754' : '#0d6efd';
        }

        if (on) {
            document.body.classList.add('langcrowd-active');
            ensureAnnotated();
        } else {
            document.body.classList.remove('langcrowd-active');
        }
    }

    /**
     * Fetches string state and annotates the page, once per page load.
     */
    function ensureAnnotated() {
        if (annotated) {
            return;
        }
        annotated = true;

        var data = window.langcrowdInit;
        // The service only takes component + key; rendered values stay client-side
        // for DOM matching (the server resolves values from the lang packs itself).
        var keys = data.strings.map(function(s) {
            return {component: s.component, key: s.key};
        });
        Ajax.call([{
            methodname: 'local_langcrowd_get_string_ids',
            args: {strings: keys, lang: data.lang},
        }])[0].then(function(results) {
            results.forEach(function(item) {
                if (item.status === 'locked' || item.voted) {
                    return;
                }
                var pageStr = data.strings.find(function(s) {
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
     * Injects the stylesheet controlling button visibility and the hover outline.
     */
    function injectStyles() {
        var style = document.createElement('style');
        // Buttons occupy no space until translate mode is on; within translate mode
        // they reserve their space (opacity) so revealing them on hover never reflows.
        var css =
            '.langcrowd-btn{display:none;}' +
            'body.langcrowd-active .langcrowd-wrap .langcrowd-btn{' +
            'display:inline-flex;opacity:0;transition:opacity .15s;}';
        if (showmode === 'always') {
            css += 'body.langcrowd-active .langcrowd-wrap .langcrowd-btn{opacity:1;}';
        } else {
            css +=
                'body.langcrowd-active .langcrowd-wrap:hover .langcrowd-btn,' +
                'body.langcrowd-active .langcrowd-wrap:focus-within .langcrowd-btn{opacity:1;}';
        }
        // Use an outline rather than a background fill so the text keeps its own contrast.
        css +=
            'body.langcrowd-active .langcrowd-wrap:hover{' +
            'outline:2px solid ' + highlightcolor + ';outline-offset:1px;border-radius:2px;}';
        style.textContent = css;
        document.head.appendChild(style);
    }

    /**
     * Walks text nodes under root and annotates any matching a tracked string.
     *
     * @param {Element} [root] Subtree to scan; defaults to document.body.
     */
    function scanAndAnnotate(root) {
        var searchRoot = root || document.body;
        var walker = document.createTreeWalker(searchRoot, NodeFilter.SHOW_TEXT, {
            acceptNode: acceptTextNode,
        });

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
     * TreeWalker filter deciding whether a text node may be annotated.
     *
     * @param {Node} node
     * @return {number}
     */
    function acceptTextNode(node) {
        var parent = node.parentElement;
        if (!parent) {
            return NodeFilter.FILTER_REJECT;
        }
        var tag = parent.tagName.toLowerCase();
        if (['script', 'style', 'noscript', 'textarea', 'input', 'select',
             'option', 'button', 'label'].indexOf(tag) !== -1) {
            return NodeFilter.FILTER_REJECT;
        }
        if (parent.closest('button, input, select, textarea')) {
            return NodeFilter.FILTER_REJECT;
        }
        // Skip user content, the plugin's own chrome, and fixed/sticky regions
        // (navbars, drawers and footers) where floating buttons would collide.
        if (parent.closest('.activityname, .instancename, li.activity, ' +
                '[data-activityname], [data-region="course-index-section"], ' +
                '.block_myoverview, .block_timeline, ' +
                '.navbar, .fixed-top, .fixed-bottom, [data-region="drawer"], ' +
                '.drawer, nav.navbar, footer, .footer, #page-footer, #langcrowd-toggle')) {
            return NodeFilter.FILTER_REJECT;
        }
        if (parent.closest('.langcrowd-wrap')) {
            return NodeFilter.FILTER_REJECT;
        }
        return NodeFilter.FILTER_ACCEPT;
    }

    /**
     * Creates one small square action button.
     *
     * @param {string} label   Accessible label / title.
     * @param {string} glyph   HTML entity for the icon.
     * @param {string} bg      Background colour.
     * @param {string} marginleft Left margin.
     * @return {HTMLButtonElement}
     */
    function makeButton(label, glyph, bg, marginleft) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'langcrowd-btn';
        b.setAttribute('title', label);
        b.setAttribute('aria-label', label);
        b.innerHTML = glyph;
        b.style.cssText =
            'align-items:center;justify-content:center;' +
            'min-width:18px;height:18px;font-size:11px;font-weight:700;' +
            'background:' + bg + ';color:#fff;border:none;border-radius:3px;' +
            'cursor:pointer;margin-left:' + marginleft + ';vertical-align:middle;' +
            'padding:0 2px;line-height:1;';
        return b;
    }

    /**
     * Wraps a text node (or its anchor) and attaches vote buttons.
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

        // If the text is inside a link, wrap the whole <a> so the buttons live
        // outside it and can't trigger the link's capture-phase click handlers.
        var linkAncestor = parent.closest ? parent.closest('a') : null;
        if (linkAncestor && linkAncestor.parentElement && !linkAncestor.closest('.langcrowd-wrap')) {
            linkAncestor.parentElement.insertBefore(wrap, linkAncestor);
            wrap.appendChild(linkAncestor);
        } else {
            parent.insertBefore(wrap, textNode);
            wrap.appendChild(textNode);
        }

        wrap.langcrowdInfo = info;
        wrap.langcrowdText = textNode.textContent.trim();
        addVoteButtons(wrap);
    }

    /**
     * Adds (or re-adds) the tick and cross buttons to an existing wrap.
     *
     * @param {Element} wrap
     */
    function addVoteButtons(wrap) {
        var info = wrap.langcrowdInfo;
        var approveLabel = ui.btn_approve || 'Approve';
        if (threshold > 0) {
            approveLabel += ' (' + info.votecount + '/' + threshold +
                ' ' + (ui.progress_label || 'approvals') + ')';
        }

        var tick = makeButton(approveLabel, '&#x2713;', '#198754', '4px');
        var cross = makeButton(ui.btn_suggest || 'Suggest alternative', '&#x2717;', '#dc3545', '2px');

        wrap.appendChild(tick);
        wrap.appendChild(cross);

        tick.addEventListener('click', function(e) {
            e.stopPropagation();
            submitVote(info.stringid, 1, wrap);
        });
        cross.addEventListener('click', function(e) {
            e.stopPropagation();
            openModal(info, wrap.langcrowdText, wrap);
        });
    }

    /**
     * Submits (or withdraws, when vote === 0) a vote via AJAX.
     *
     * @param {number}  stringid
     * @param {number}  vote 1, -1 or 0 (withdraw).
     * @param {Element} wrap
     */
    function submitVote(stringid, vote, wrap) {
        Ajax.call([{
            methodname: 'local_langcrowd_submit_vote',
            args: {stringid: stringid, vote: vote},
        }])[0].then(function(result) {
            if (!result.success) {
                return null;
            }
            if (vote === 0) {
                // Undo succeeded: restore the vote buttons.
                clearWrap(wrap);
                addVoteButtons(wrap);
            } else {
                showUndo(wrap);
            }
            return null;
        }).catch(Notification.exception);
    }

    /**
     * Removes every button/affordance child from a wrap, keeping its content.
     *
     * @param {Element} wrap
     */
    function clearWrap(wrap) {
        wrap.querySelectorAll('button, .langcrowd-undo').forEach(function(el) {
            el.remove();
        });
    }

    /**
     * Replaces the buttons with a short-lived "Voted — Undo" affordance.
     *
     * @param {Element} wrap
     */
    function showUndo(wrap) {
        clearWrap(wrap);

        var box = document.createElement('span');
        box.className = 'langcrowd-undo';
        box.style.cssText = 'margin-left:4px;font-size:.75em;color:#198754;vertical-align:middle;';

        var tick = document.createElement('span');
        tick.innerHTML = '&#x2713; ';

        var undo = document.createElement('button');
        undo.type = 'button';
        undo.textContent = ui.undo || 'Undo';
        undo.style.cssText =
            'background:none;border:none;padding:0;color:#0d6efd;cursor:pointer;' +
            'text-decoration:underline;font-size:inherit;';
        undo.addEventListener('click', function(e) {
            e.stopPropagation();
            submitVote(wrap.langcrowdInfo.stringid, 0, wrap);
        });

        box.appendChild(tick);
        box.appendChild(undo);
        wrap.appendChild(box);

        // The undo window closes after a few seconds.
        setTimeout(function() {
            if (box.parentNode === wrap) {
                box.remove();
            }
        }, 6000);
    }

    /**
     * Escapes a string for safe insertion as HTML text.
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
     * Sends the suggestion typed into the modal, then closes it.
     *
     * @param {Object}  modal The core/modal instance.
     * @param {Object}  info  String record.
     * @param {Element} wrap  The annotation wrapper.
     */
    function submitSuggestion(modal, info, wrap) {
        var textarea = modal.getRoot()[0].querySelector('#lc-suggestion');
        var suggestion = (textarea.value || '').trim();
        if (!suggestion) {
            return;
        }
        Ajax.call([{
            methodname: 'local_langcrowd_submit_suggestion',
            args: {stringid: info.stringid, suggestion: suggestion},
        }])[0].then(function(result) {
            if (result.success) {
                clearWrap(wrap);
                showUndo(wrap);
            }
            modal.hide();
            return null;
        }).catch(Notification.exception);
    }

    /**
     * Opens the suggestion dialog (core/modal) for a string.
     *
     * @param {Object}  info         String record.
     * @param {string}  originalText Rendered value shown to the user.
     * @param {Element} wrap         The annotation wrapper.
     */
    function openModal(info, originalText, wrap) {
        var body = '<div>';
        // Show the English source only when it differs from what's on screen.
        if (info.source && info.source !== originalText) {
            body += '<p class="mb-1"><strong>' + escapeHtml(ui.modal_source_label || 'English source') +
                '</strong></p><p class="text-muted">' + escapeHtml(info.source) + '</p>';
        }
        body += '<p class="mb-1"><strong>' + escapeHtml(ui.modal_original_label || 'Current translation') +
            '</strong></p><p class="text-muted">' + escapeHtml(originalText) + '</p>' +
            '<label for="lc-suggestion" class="fw-bold">' +
            escapeHtml(ui.modal_suggestion_label || 'Your suggestion') + '</label>' +
            '<textarea id="lc-suggestion" class="form-control" rows="3" maxlength="4096"></textarea>' +
            '</div>';

        ModalSaveCancel.create({
            title: ui.modal_suggest_title || 'Suggest translation',
            body: body,
        }).then(function(modal) {
            modal.setSaveButtonText(ui.modal_submit || 'Submit suggestion');
            modal.setRemoveOnClose(true);

            modal.getRoot().on(ModalEvents.save, function(e) {
                e.preventDefault();
                submitSuggestion(modal, info, wrap);
            });

            modal.show();
            return modal;
        }).catch(Notification.exception);
    }

    /**
     * Re-scans subtrees added by reactive frameworks (debounced).
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
                        node.id === 'langcrowd-toggle') {
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
