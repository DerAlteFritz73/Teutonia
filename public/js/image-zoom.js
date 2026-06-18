// Reusable zoom/pan for images marked with class "zoomable-img" (typically inside
// a fullscreen modal). Desktop: mouse wheel to zoom toward the cursor, drag to pan,
// double-click to reset. Mobile: two-finger pinch to zoom, one-finger drag to pan,
// double-tap to reset. Zoom resets automatically when the containing modal closes.
(function () {
    var MIN_SCALE = 1;
    var MAX_SCALE = 8;

    function setup(img) {
        if (img.dataset.zoomInit) return;
        img.dataset.zoomInit = '1';

        var scale = 1, tx = 0, ty = 0;
        var pointers = new Map();
        var startDist = 0, startScale = 1;
        var lastX = 0, lastY = 0;

        img.style.touchAction      = 'none';
        img.style.transformOrigin  = '0 0';
        img.style.willChange       = 'transform';
        img.style.cursor           = 'zoom-in';
        // Clip panned/zoomed content to the viewport box.
        if (img.parentElement) img.parentElement.style.overflow = 'hidden';

        function apply() {
            img.style.transform = 'translate(' + tx + 'px,' + ty + 'px) scale(' + scale + ')';
            img.style.cursor = scale > 1 ? 'grab' : 'zoom-in';
        }

        function reset() {
            scale = 1; tx = 0; ty = 0; apply();
        }

        // Zoom by `factor`, keeping the point under (clientX, clientY) fixed on screen.
        function zoomAt(clientX, clientY, factor) {
            var rect = img.getBoundingClientRect();
            var ox = clientX - rect.left;
            var oy = clientY - rect.top;
            var next = Math.min(MAX_SCALE, Math.max(MIN_SCALE, scale * factor));
            var ratio = next / scale;
            if (ratio === 1) return;
            tx -= ox * (ratio - 1);
            ty -= oy * (ratio - 1);
            scale = next;
            if (scale === MIN_SCALE) { tx = 0; ty = 0; }
            apply();
        }

        img.addEventListener('wheel', function (e) {
            e.preventDefault();
            zoomAt(e.clientX, e.clientY, e.deltaY < 0 ? 1.15 : 1 / 1.15);
        }, { passive: false });

        img.addEventListener('dblclick', function (e) {
            e.preventDefault();
            if (scale > 1) reset();
            else zoomAt(e.clientX, e.clientY, 2.5);
        });

        img.addEventListener('pointerdown', function (e) {
            e.preventDefault();
            img.setPointerCapture(e.pointerId);
            pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
            if (pointers.size === 1) {
                lastX = e.clientX; lastY = e.clientY;
            } else if (pointers.size === 2) {
                var p = Array.from(pointers.values());
                startDist  = Math.hypot(p[0].x - p[1].x, p[0].y - p[1].y);
                startScale = scale;
            }
        });

        img.addEventListener('pointermove', function (e) {
            if (!pointers.has(e.pointerId)) return;
            pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
            var p = Array.from(pointers.values());
            if (pointers.size >= 2) {
                var dist = Math.hypot(p[0].x - p[1].x, p[0].y - p[1].y);
                if (startDist > 0) {
                    var midX = (p[0].x + p[1].x) / 2;
                    var midY = (p[0].y + p[1].y) / 2;
                    zoomAt(midX, midY, (startScale * (dist / startDist)) / scale);
                }
            } else if (scale > 1) {
                tx += e.clientX - lastX;
                ty += e.clientY - lastY;
                lastX = e.clientX; lastY = e.clientY;
                apply();
            }
        });

        function end(e) {
            pointers.delete(e.pointerId);
            if (pointers.size === 1) {
                var p = Array.from(pointers.values())[0];
                lastX = p.x; lastY = p.y;
            }
            if (pointers.size < 2) startDist = 0;
        }
        img.addEventListener('pointerup', end);
        img.addEventListener('pointercancel', end);

        img._resetZoom = reset;
    }

    function initIn(root) {
        (root || document).querySelectorAll('img.zoomable-img').forEach(setup);
    }

    // Reset any zoomed image when its modal closes, so it reopens at 1x.
    document.addEventListener('hidden.bs.modal', function (e) {
        e.target.querySelectorAll('img.zoomable-img').forEach(function (img) {
            if (img._resetZoom) img._resetZoom();
        });
    });

    if (window.pageLoad) {
        window.pageLoad(function () { initIn(document); });
    } else {
        document.addEventListener('DOMContentLoaded', function () { initIn(document); });
    }
})();
