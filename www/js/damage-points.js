/**
 * damage-points.js — Interactive 2-D damage point manager
 *
 * Reads configuration from the #damage-map element's data attributes:
 *   data-ticket-id      — id of the current ticket
 *   data-add-url        — URL for POST /api/damage-point/add
 *   data-remove-url     — URL template for POST /api/damage-point/remove/{id}
 *                         (contains a trailing /0 that is replaced with the real id)
 *   data-can-manage     — "1" if the current user may add/remove points, "0" otherwise
 *   data-points         — JSON array of existing damage points
 *
 * Coordinates are stored and transmitted as percentages (0–100) relative to the
 * blueprint image dimensions, making them resolution-independent.
 */
(function () {
    'use strict';

    /* ── Bootstrap ───────────────────────────────────────────────────── */

    const map = document.getElementById('damage-map');
    if (!map) return;   // no blueprint on this page

    const wrapper    = document.getElementById('blueprint-wrapper');
    const blueprint  = document.getElementById('blueprint-img');
    const list       = document.getElementById('dp-list');
    const badge      = document.getElementById('dp-count-badge');
    const addUrl     = map.dataset.addUrl;
    const removeUrl  = map.dataset.removeUrl;       // ends with /0
    const canManage  = map.dataset.canManage === '1';
    const ticketId   = parseInt(map.dataset.ticketId, 10);

    /* Points array — plain objects: { id, position_x, position_y, description } */
    let points = [];
    try { points = JSON.parse(map.dataset.points || '[]'); } catch (_) {}

    /* Pending click coordinates (set when modal is opened) */
    let pendingX = 0;
    let pendingY = 0;

    /* ── Initial render ──────────────────────────────────────────────── */

    renderAll();

    /* ── Click-to-add ────────────────────────────────────────────────── */

    if (canManage) {
        blueprint.style.cursor = 'crosshair';

        wrapper.addEventListener('click', function (e) {
            // Ignore clicks that originated on a marker (they stopPropagation).
            const rect = blueprint.getBoundingClientRect();
            pendingX = ((e.clientX - rect.left) / blueprint.offsetWidth) * 100;
            pendingY = ((e.clientY - rect.top)  / blueprint.offsetHeight) * 100;

            // Clamp to [0, 100] (handles slight out-of-bounds clicks on the wrapper edge)
            pendingX = Math.max(0, Math.min(100, pendingX));
            pendingY = Math.max(0, Math.min(100, pendingY));

            clearModalError();
            document.getElementById('dp-modal-desc').value = '';

            const modal = new bootstrap.Modal(document.getElementById('dpModal'));
            modal.show();
            setTimeout(function () {
                document.getElementById('dp-modal-desc').focus();
            }, 300);
        });
    }

    /* ── Modal confirm button ────────────────────────────────────────── */

    var confirmBtn = document.getElementById('dp-confirm-btn');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', function () {
            const desc = document.getElementById('dp-modal-desc').value.trim();
            if (!desc) {
                setModalError('Description is required.');
                return;
            }

            setConfirmLoading(true);
            clearModalError();

            fetch(addUrl, {
                method:  'POST',
                headers: {
                    'Content-Type':      'application/json',
                    'X-Requested-With':  'XMLHttpRequest',
                },
                body: JSON.stringify({
                    ticket_id:   ticketId,
                    position_x:  pendingX,
                    position_y:  pendingY,
                    description: desc,
                }),
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('dpModal')).hide();
                    points.push(data.point);
                    addMarkerElement(data.point, points.length);
                    addListItem(data.point, points.length);
                    updateBadge();
                } else {
                    setModalError(data.error || 'Failed to add damage point.');
                }
            })
            .catch(function () {
                setModalError('Network error. Please try again.');
            })
            .finally(function () {
                setConfirmLoading(false);
            });
        });
    }

    /* ── Rendering helpers ───────────────────────────────────────────── */

    function renderAll() {
        // Clear existing dynamic content.
        wrapper.querySelectorAll('.damage-marker').forEach(function (el) { el.remove(); });
        if (list) list.innerHTML = '';

        points.forEach(function (point, idx) {
            addMarkerElement(point, idx + 1);
            addListItem(point, idx + 1);
        });

        updateBadge();
    }

    /**
     * Injects a circular numbered marker onto the blueprint.
     *
     * @param {{id:number, position_x:number, position_y:number, description:string}} point
     * @param {number} num  1-based display number
     */
    function addMarkerElement(point, num) {
        const el = document.createElement('div');
        el.className       = 'damage-marker';
        el.dataset.pointId = point.id;
        el.style.left      = point.position_x + '%';
        el.style.top       = point.position_y + '%';

        el.textContent = num;

        // Tooltip
        const tip = document.createElement('span');
        tip.className   = 'dp-tooltip';
        tip.textContent = point.description;
        el.appendChild(tip);

        // Prevent marker clicks from bubbling to the wrapper (which would open the modal).
        el.addEventListener('click', function (e) {
            e.stopPropagation();
        });

        wrapper.appendChild(el);
    }

    /**
     * Appends an item to the damage-point list below the blueprint.
     *
     * @param {{id:number, position_x:number, position_y:number, description:string}} point
     * @param {number} num  1-based display number
     */
    function addListItem(point, num) {
        if (!list) return;

        const li = document.createElement('li');
        li.className        = 'list-group-item d-flex align-items-start gap-2 py-1 px-2';
        li.dataset.listItem = point.id;

        const numBadge = document.createElement('span');
        numBadge.className   = 'badge bg-danger rounded-circle flex-shrink-0 mt-1';
        numBadge.textContent = num;
        numBadge.style.cssText = 'width:18px;height:18px;font-size:10px;display:flex;align-items:center;justify-content:center;';

        const desc = document.createElement('span');
        desc.className   = 'flex-grow-1';
        desc.textContent = point.description;

        li.appendChild(numBadge);
        li.appendChild(desc);

        if (canManage) {
            const btn = document.createElement('button');
            btn.type      = 'button';
            btn.className = 'btn btn-outline-danger btn-sm py-0 px-1 lh-1 flex-shrink-0';
            btn.title     = 'Remove this damage point';
            btn.innerHTML = '&times;';
            btn.addEventListener('click', function () {
                removePoint(point.id);
            });
            li.appendChild(btn);
        }

        list.appendChild(li);
    }

    /* ── Remove point ────────────────────────────────────────────────── */

    function removePoint(id) {
        if (!confirm('Remove this damage point?')) return;

        // Build the remove URL: data-remove-url ends with /0 (the placeholder id)
        const url = removeUrl.replace(/\/0$/, '/' + id);

        fetch(url, {
            method:  'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                points = points.filter(function (p) { return p.id !== id; });
                renderAll();
            } else {
                alert('Failed to remove damage point: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(function () {
            alert('Network error. Please try again.');
        });
    }

    /* ── Badge ───────────────────────────────────────────────────────── */

    function updateBadge() {
        if (badge) badge.textContent = points.length;
    }

    /* ── Modal helpers ───────────────────────────────────────────────── */

    function setModalError(msg) {
        var el = document.getElementById('dp-modal-error');
        if (el) el.textContent = msg;
    }

    function clearModalError() {
        setModalError('');
    }

    function setConfirmLoading(loading) {
        if (!confirmBtn) return;
        confirmBtn.disabled = loading;
        confirmBtn.textContent = loading ? 'Saving…' : 'Add Point';
    }

}());
