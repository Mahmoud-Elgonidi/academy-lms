// In-page layer for recordings: a visible pointer that travels to what the lesson
// points at, a ring around it, and a ripple on each click. It lives on <html>
// (outside <body>), so Livewire's page changes keep it.
(() => {
  if (window.__course) return;
  const css = `
    #course-pointer { position: fixed; left: 0; top: 0; width: 26px; height: 26px; z-index: 2147483647; pointer-events: none;
      transform: translate(var(--x, -60px), var(--y, -60px)); transition: transform var(--ms, 600ms) cubic-bezier(.4,.0,.2,1); filter: drop-shadow(0 2px 3px rgba(0,0,0,.35)); }
    #course-ring { position: fixed; z-index: 2147483646; pointer-events: none; border-radius: 12px; opacity: 0;
      box-shadow: 0 0 0 3px #f59e0b, 0 0 0 9px rgba(245, 158, 11, .28); transition: opacity 250ms ease, left 300ms ease, top 300ms ease, width 300ms ease, height 300ms ease; }
    .course-ripple { position: fixed; z-index: 2147483646; pointer-events: none; width: 18px; height: 18px; margin: -9px 0 0 -9px; border-radius: 50%;
      background: rgba(245, 158, 11, .55); animation: course-ripple 650ms ease-out forwards; }
    @keyframes course-ripple { from { transform: scale(.4); opacity: 1 } to { transform: scale(3.2); opacity: 0 } }`;
  const mount = () => {
    if (document.getElementById('course-pointer')) return;
    const root = document.documentElement;
    const style = document.createElement('style');
    style.id = 'course-style';
    style.textContent = css;
    root.appendChild(style);
    const pointer = document.createElement('div');
    pointer.id = 'course-pointer';
    // A phone lesson shows a fingertip (a soft circle centred on the touch), a desktop one an arrow.
    pointer.innerHTML = window.__courseMode === 'touch'
      ? '<div style="width:34px;height:34px;margin:-4px 0 0 -4px;border-radius:50%;background:rgba(255,255,255,.55);box-shadow:0 0 0 2px rgba(17,17,17,.55)"></div>'
      : '<svg viewBox="0 0 26 26" width="26" height="26"><path d="M4 2 L4 21 L9 16.5 L12.5 24 L16 22.4 L12.6 15 L19 15 Z" fill="#fff" stroke="#111" stroke-width="1.6" stroke-linejoin="round"/></svg>';
    root.appendChild(pointer);
    const ring = document.createElement('div');
    ring.id = 'course-ring';
    root.appendChild(ring);
    const last = window.__courseLast;
    if (last) pointer.style.cssText = `--x:${last.x}px;--y:${last.y}px;--ms:0ms`;
  };
  window.__course = {
    mount,
    move(x, y, ms = 600) {
      mount();
      window.__courseLast = { x, y };
      // The arrow's tip is its top-left corner; a fingertip is centred on the point.
      const [dx, dy] = window.__courseMode === 'touch' ? [-13, -13] : [-3, -2];
      document.getElementById('course-pointer').style.cssText = `--x:${x + dx}px;--y:${y + dy}px;--ms:${ms}ms`;
    },
    ring(rect) {
      mount();
      const ring = document.getElementById('course-ring');
      if (!rect) { ring.style.opacity = '0'; return; }
      const pad = 6;
      Object.assign(ring.style, { left: `${rect.x - pad}px`, top: `${rect.y - pad}px`, width: `${rect.width + pad * 2}px`, height: `${rect.height + pad * 2}px`, opacity: '1' });
    },
    ripple(x, y) {
      const dot = document.createElement('div');
      dot.className = 'course-ripple';
      dot.style.left = `${x}px`;
      dot.style.top = `${y}px`;
      document.documentElement.appendChild(dot);
      setTimeout(() => dot.remove(), 700);
    },
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mount); else mount();
})();
