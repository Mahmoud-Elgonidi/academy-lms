'use client';

import { useEffect, useRef } from 'react';

/**
 * The night sky behind the opening: stars that twinkle and drift, a few
 * shooting ones, and a slight lean toward the pointer. Drawn on a canvas
 * only while it is on screen and the person allows motion; otherwise one
 * still frame. Decorative (aria-hidden).
 */
export function Starfield({ density = 0.00018 }: { density?: number }) {
  const ref = useRef<HTMLCanvasElement>(null);

  useEffect(() => {
    const canvas = ref.current;
    const context = canvas?.getContext('2d');
    if (!canvas || !context) return;
    const still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const ratio = Math.min(window.devicePixelRatio || 1, 2);
    type Star = { x: number; y: number; r: number; phase: number; speed: number; depth: number };
    type Streak = { x: number; y: number; vx: number; vy: number; life: number };
    let stars: Star[] = [];
    let streaks: Streak[] = [];
    let width = 0;
    let height = 0;
    let lean = { x: 0, y: 0 };
    let target = { x: 0, y: 0 };
    let frame = 0;
    let visible = true;

    const resize = () => {
      width = canvas.clientWidth;
      height = canvas.clientHeight;
      canvas.width = Math.round(width * ratio);
      canvas.height = Math.round(height * ratio);
      context.setTransform(ratio, 0, 0, ratio, 0, 0);
      const count = Math.min(420, Math.round(width * height * density));
      stars = Array.from({ length: count }, () => ({
        x: Math.random() * width,
        y: Math.random() * height,
        r: Math.random() * 1.3 + 0.2,
        phase: Math.random() * Math.PI * 2,
        speed: 0.6 + Math.random() * 1.6,
        depth: 0.2 + Math.random() * 0.8,
      }));
    };

    const draw = (time: number) => {
      context.clearRect(0, 0, width, height);
      lean.x += (target.x - lean.x) * 0.04;
      lean.y += (target.y - lean.y) * 0.04;
      for (const star of stars) {
        const twinkle = still ? 0.8 : 0.55 + 0.45 * Math.sin(star.phase + (time / 1000) * star.speed);
        const x = (star.x + lean.x * star.depth * 18 + (still ? 0 : time * 0.004 * star.depth)) % (width + 4);
        const y = star.y + lean.y * star.depth * 12;
        context.globalAlpha = twinkle * (0.35 + star.depth * 0.65);
        context.fillStyle = star.r > 1.2 ? '#fff4c9' : '#ffffff';
        context.beginPath();
        context.arc(x < 0 ? x + width : x, y, star.r, 0, Math.PI * 2);
        context.fill();
      }
      if (!still) {
        if (Math.random() < 0.004 && streaks.length < 2) {
          streaks.push({ x: Math.random() * width, y: Math.random() * height * 0.4, vx: -(4 + Math.random() * 4), vy: 2 + Math.random() * 2, life: 1 });
        }
        streaks = streaks.filter((s) => s.life > 0);
        for (const s of streaks) {
          const gradient = context.createLinearGradient(s.x, s.y, s.x - s.vx * 12, s.y - s.vy * 12);
          gradient.addColorStop(0, 'rgba(255,255,255,0.9)');
          gradient.addColorStop(1, 'rgba(255,255,255,0)');
          context.globalAlpha = s.life;
          context.strokeStyle = gradient;
          context.lineWidth = 1.6;
          context.beginPath();
          context.moveTo(s.x, s.y);
          context.lineTo(s.x - s.vx * 12, s.y - s.vy * 12);
          context.stroke();
          s.x += s.vx;
          s.y += s.vy;
          s.life -= 0.015;
        }
      }
      context.globalAlpha = 1;
      if (!still && visible) frame = requestAnimationFrame(draw);
    };

    const point = (event: PointerEvent) => {
      target = { x: event.clientX / window.innerWidth - 0.5, y: event.clientY / window.innerHeight - 0.5 };
    };
    const onScreen = new IntersectionObserver(([entry]) => {
      const was = visible;
      visible = Boolean(entry?.isIntersecting);
      if (visible && !was && !still) frame = requestAnimationFrame(draw);
    });

    resize();
    draw(performance.now());
    onScreen.observe(canvas);
    window.addEventListener('resize', resize);
    window.addEventListener('pointermove', point, { passive: true });
    return () => {
      cancelAnimationFrame(frame);
      onScreen.disconnect();
      window.removeEventListener('resize', resize);
      window.removeEventListener('pointermove', point);
    };
  }, [density]);

  return <canvas ref={ref} className="ls-stars" aria-hidden="true" />;
}
