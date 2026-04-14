/**
 * OSSP MOP — Notification polling & dropdown
 *
 * Polls the API every POLL_INTERVAL ms to update the bell badge count.
 * On dropdown open, fetches the 5 most recent unread notifications and
 * renders them.  Clicking a notification marks it as read via AJAX, then
 * navigates to the stored link.  The "Mark all as read" button in the
 * dropdown fires a bulk-mark endpoint.
 */

// ──────────────────────────────────────────────────────────────────────
//  Configuration — change POLL_INTERVAL to adjust polling frequency
// ──────────────────────────────────────────────────────────────────────
const POLL_INTERVAL = 30_000; // milliseconds (30 s)

const API = {
    unreadCount : '/api/notifications/unread-count',
    recent      : '/api/notifications/recent',
    markRead    : (id) => `/api/notifications/mark-read/${id}`,
    markAllRead : '/api/notifications/mark-all-read',
};

// ──────────────────────────────────────────────────────────────────────
//  DOM references (set after DOMContentLoaded)
// ──────────────────────────────────────────────────────────────────────
let badge        = null;   // <span> that shows the unread count
let dropdownList = null;   // <ul> inside the notification dropdown
let markAllBtn   = null;   // "Mark all as read" button in dropdown

// ──────────────────────────────────────────────────────────────────────
//  Helpers
// ──────────────────────────────────────────────────────────────────────

/**
 * Sends a fetch request with the XHR header Nette requires.
 */
async function apiFetch(url, method = 'GET') {
    const res = await fetch(url, {
        method,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        },
        credentials: 'same-origin',
    });
    if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
    }
    return res.json();
}

/**
 * Returns a Bootstrap Icons class name for a notification type string.
 */
function iconForType(type) {
    const map = {
        user_pending          : 'bi-person-exclamation text-warning',
        user_approved         : 'bi-person-check text-success',
        user_rejected         : 'bi-person-x text-danger',
        ticket_created        : 'bi-ticket text-primary',
        ticket_assigned       : 'bi-person-fill-gear text-info',
        ticket_status_changed : 'bi-arrow-repeat text-secondary',
        damage_point_added    : 'bi-geo-alt text-danger',
        image_added           : 'bi-image text-success',
        service_history_added : 'bi-wrench text-warning',
    };
    return map[type] || 'bi-bell text-muted';
}

// ──────────────────────────────────────────────────────────────────────
//  Badge update
// ──────────────────────────────────────────────────────────────────────

/**
 * Fetches the unread count and updates the bell badge.
 * Called on load and every POLL_INTERVAL ms.
 */
async function refreshBadge() {
    try {
        const data = await apiFetch(API.unreadCount);
        setBadge(data.count ?? 0);
    } catch (_) {
        // Silently ignore network errors — do not spam the console.
    }
}

function setBadge(count) {
    if (!badge) return;
    if (count > 0) {
        badge.textContent = count > 99 ? '99+' : String(count);
        badge.classList.remove('d-none');
    } else {
        badge.classList.add('d-none');
    }
}

// ──────────────────────────────────────────────────────────────────────
//  Dropdown rendering
// ──────────────────────────────────────────────────────────────────────

/**
 * Fetches recent notifications and renders them into the dropdown <ul>.
 */
async function loadDropdown() {
    if (!dropdownList) return;

    // Show a loading state.
    dropdownList.innerHTML =
        '<li><span class="dropdown-item-text text-muted small py-2">Loading…</span></li>';

    try {
        const data = await apiFetch(API.recent);
        renderDropdown(data.notifications ?? []);
    } catch (_) {
        dropdownList.innerHTML =
            '<li><span class="dropdown-item-text text-danger small py-2">Could not load notifications.</span></li>';
    }
}

/**
 * Renders notification rows into the dropdown list.
 *
 * @param {Array} notifications  Array of notification objects from the API.
 */
function renderDropdown(notifications) {
    if (!dropdownList) return;

    if (notifications.length === 0) {
        dropdownList.innerHTML =
            '<li><span class="dropdown-item-text text-muted small py-2">No new notifications.</span></li>';
        // Hide mark-all button when nothing to mark.
        if (markAllBtn) markAllBtn.classList.add('d-none');
        return;
    }

    let html = '';
    for (const n of notifications) {
        const icon = iconForType(n.type);
        const msg  = escapeHtml(n.message);
        const time = escapeHtml(n.time_ago);
        const link = n.link_url ? escapeHtml(n.link_url) : '';

        html += `
            <li>
                <a class="dropdown-item d-flex align-items-start gap-2 py-2 px-3 notification-item"
                   href="#"
                   data-notification-id="${n.id}"
                   data-link-url="${link}">
                    <i class="bi ${icon} flex-shrink-0 mt-1" style="font-size:1rem;"></i>
                    <div class="overflow-hidden">
                        <div class="small lh-sm text-wrap">${msg}</div>
                        <div class="text-muted" style="font-size:.7rem;">${time}</div>
                    </div>
                </a>
            </li>`;
    }

    dropdownList.innerHTML = html;

    // Show mark-all button.
    if (markAllBtn) markAllBtn.classList.remove('d-none');

    // Attach click handlers.
    dropdownList.querySelectorAll('.notification-item').forEach((el) => {
        el.addEventListener('click', handleNotificationClick);
    });
}

// ──────────────────────────────────────────────────────────────────────
//  Click handlers
// ──────────────────────────────────────────────────────────────────────

/**
 * Marks a notification as read via AJAX, then navigates to its link (if any).
 */
async function handleNotificationClick(e) {
    e.preventDefault();

    const el      = e.currentTarget;
    const id      = parseInt(el.dataset.notificationId, 10);
    const linkUrl = el.dataset.linkUrl || '';

    try {
        await apiFetch(API.markRead(id), 'POST');
    } catch (_) {
        // If the API call fails, still navigate — don't block the user.
    }

    if (linkUrl) {
        window.location.href = linkUrl;
    } else {
        // Refresh the badge and close dropdown visually by reloading notifications.
        await refreshBadge();
        await loadDropdown();
    }
}

/**
 * Marks all notifications as read and refreshes the dropdown.
 */
async function handleMarkAllRead(e) {
    e.preventDefault();
    try {
        await apiFetch(API.markAllRead, 'POST');
        setBadge(0);
        renderDropdown([]);
    } catch (_) {
        // Silently ignore.
    }
}

// ──────────────────────────────────────────────────────────────────────
//  Utility
// ──────────────────────────────────────────────────────────────────────

function escapeHtml(str) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(str));
    return d.innerHTML;
}

// ──────────────────────────────────────────────────────────────────────
//  Bootstrap dropdown event — load notifications on open
// ──────────────────────────────────────────────────────────────────────

function attachDropdownListener() {
    const toggle = document.getElementById('notificationDropdownToggle');
    if (!toggle) return;

    // Bootstrap fires 'show.bs.dropdown' on the toggle element.
    toggle.addEventListener('show.bs.dropdown', () => {
        loadDropdown();
    });
}

// ──────────────────────────────────────────────────────────────────────
//  Init
// ──────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    badge        = document.getElementById('notificationBadge');
    dropdownList = document.getElementById('notificationDropdownList');
    markAllBtn   = document.getElementById('notificationMarkAllBtn');

    if (!badge) return; // Not logged in or layout doesn't have notification UI.

    if (markAllBtn) {
        markAllBtn.addEventListener('click', handleMarkAllRead);
    }

    attachDropdownListener();

    // Initial badge fetch + start polling.
    refreshBadge();
    setInterval(refreshBadge, POLL_INTERVAL);
});
