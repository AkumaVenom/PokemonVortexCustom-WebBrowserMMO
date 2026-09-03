(function () {
    'use strict';

    if (typeof fixStyles === 'function') {
        try { fixStyles(); } catch (e) {}
    }

    function naturalLayout() {
        ['scroll', 'scrollContent', 'sidebarContent', 'sidebarTabs'].forEach(function (id) {
            var el = document.getElementById(id);
            if (!el) return;
            el.style.height = 'auto';
            el.style.maxHeight = 'none';
        });
    }

    if (typeof addResizeEvent === 'function') addResizeEvent(naturalLayout);
    if (typeof addLoadEvent === 'function') addLoadEvent(naturalLayout);
    naturalLayout();

    function bindMenu(id, menuName) {
        var tab = document.getElementById(id);
        if (!tab || typeof showMenu !== 'function' || typeof hideMenu !== 'function') return;
        tab.addEventListener('mouseenter', function () { showMenu(menuName, 0); });
        tab.addEventListener('mouseleave', function () { hideMenu(0); });
        // Clicks deliberately keep normal anchor navigation. The historic build
        // intercepted them and could strand users inside an empty dropdown.
    }

    bindMenu('mapsTab', 'maps');
    bindMenu('battleTab', 'battle');
    bindMenu('yourAccountTab', 'yourAccount');
    bindMenu('communityTab', 'community');

    function bindSidebar(id) {
        var tab = document.getElementById(id);
        if (!tab || typeof showSidebar !== 'function') return;
        tab.addEventListener('click', function (event) {
            // Enhanced in-page sidebar is optional. Modified-clicks and browsers
            // without the recovered Ajax UI retain the real href fallback.
            if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            try {
                showSidebar(tab, 1);
                event.preventDefault();
            } catch (e) {
                // Leave normal navigation available if legacy sidebar code fails.
            }
        });
    }

    bindSidebar('optionsTab');
    bindSidebar('pokedexTab');
    bindSidebar('membersTab');

    function notificationUrl() {
        var base = typeof window.PV_BASE === 'string' ? window.PV_BASE.replace(/\/$/, '') : '';
        return base + '/tabs/toolbox.php';
    }

    // Message notification lookup is read-only. Use a normal same-origin GET
    // rather than the recovered AjaxRequest class, whose default POST can be
    // rejected by modern request-origin protection and should never interrupt
    // a battle with a global "Forbidden" overlay.
    window.notifymessageshow = function () {
        var notify = document.getElementById('notification');
        if (!notify || typeof window.fetch !== 'function') return;
        window.fetch(notificationUrl() + '?notify=show', {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (response) {
            if (!response.ok) return '';
            return response.text();
        }).then(function (text) {
            if (!text || text === '1') return;
            notify.innerHTML = text + '<br><br><a href="#" data-pv-hide-notification>Dismiss notification</a>';
            notify.style.visibility = 'visible';
            var dismiss = notify.querySelector('[data-pv-hide-notification]');
            if (dismiss) dismiss.addEventListener('click', function (event) {
                event.preventDefault();
                window.notifymessagehide();
            });
        }).catch(function () {
            // Background notification failure must never block gameplay.
        });
    };

    window.notifymessagehide = function () {
        var notify = document.getElementById('notification');
        if (notify) notify.style.visibility = 'hidden';
        if (typeof window.fetch !== 'function') return;
        var csrf = document.querySelector('input[name="csrf_token"]');
        if (!csrf || !csrf.value) return;
        window.fetch(notificationUrl(), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'notify=hide&csrf_token=' + encodeURIComponent(csrf.value)
        }).catch(function () {
            // The notification is already hidden locally; no gameplay alert.
        });
    };

    window.notifymessageshow();
})();
