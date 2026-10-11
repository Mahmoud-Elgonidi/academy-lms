{{--
    The games' drawings (shared::games.creature, .island, .scene), wherever they appear (the family's
    islands and album, the console's studio): the shared outlines and their motion (wings, blinks,
    foam, smoke, lava), transform and opacity only, inside prefers-reduced-motion: no-preference.
--}}
<style>
    .gw-abs { position: absolute; display: block; }
    .locked-art { filter: saturate(.35) brightness(1.05); }
    .gw-smoke { position: absolute; display: block; border-radius: 999px; }
    .gw-zzz { font-weight: 800; color: #FFFFFF; }
    .cr-wl { transform-origin: 30px 52px; }
    .cr-wr { transform-origin: 90px 52px; }
    .cr-blink { transform-box: fill-box; transform-origin: center; }
    .cr-claw { transform-origin: 20% 80%; transform-box: fill-box; }
    /* An opened island's scene (shared::games.scene): 390 x 330. */
    .gw-bubble { border-radius: 999px; border: 2px solid #FFFFFF; }
    .gw-snow { width: 6px; height: 6px; border-radius: 999px; background: #FFFFFF; }
    .gw-scene { position: absolute; left: 0; top: 0; width: 390px; height: 330px; overflow: hidden; }
    @media (prefers-reduced-motion: no-preference) {
        .gw-cloud { animation: gw-cloud 40s linear infinite; }
        .gw-cloud.gw-d2 { animation-duration: 55s; animation-delay: -20s; }
        .gw-swim { animation: gw-swim 9s ease-in-out infinite; }
        .gw-swim.gw-d2 { animation-duration: 11s; animation-delay: -4s; }
        .gw-swim.gw-d3 { animation-duration: 8s; animation-delay: -6s; }
        .gw-paddle { animation: gw-paddle .5s ease-in-out infinite alternate; }
        .gw-fig8 { animation: gw-fig8 7s ease-in-out infinite; }
        .gw-peek { animation: gw-peek 5s ease-in-out infinite; }
        .gw-perch { animation: gw-perch 2.4s ease-in-out infinite; }
        .gw-egg-wob { animation: gw-eggwob 2.6s ease-in-out infinite; transform-origin: 50% 90%; }
        .gw-rays { animation: gw-spin 30s linear infinite; }
        .gw-caustic { animation: gw-caustic 5s ease-in-out infinite alternate; }
        .gw-bubble { animation: gw-bubble 3s ease-in infinite; }
        .gw-bubble.gw-d2 { animation-delay: 1s; } .gw-bubble.gw-d3 { animation-delay: 2s; }
        .gw-snow { animation: gw-snow 5s linear infinite; }
        .gw-snow.gw-d2 { animation-delay: -1.7s; } .gw-snow.gw-d3 { animation-delay: -3.4s; }
        .cr-wl { animation: gw-flapL .22s ease-in-out infinite alternate; }
        .cr-wr { animation: gw-flapR .22s ease-in-out infinite alternate; }
        .cr-blink { animation: gw-blink 4.2s infinite; }
        .cr-claw { animation: gw-claw .9s ease-in-out infinite alternate; }
        .cr-spout { animation: gw-twinkle 2.2s ease-in-out infinite; }
        .cr-flame { animation: gw-twinkle 1.2s ease-in-out infinite; }
        .gw-foam { transform-box: fill-box; transform-origin: center; animation: gw-foam 3s ease-in-out infinite; }
        .gw-monkey { animation: gw-monkey 1.1s cubic-bezier(.3, 0, .5, 1) infinite; }
        .gw-slide { animation: gw-slide 4s ease-in-out infinite; }
        .gw-smoke { animation: gw-smoke 3.6s ease-out infinite; }
        .gw-smoke.gw-d2 { animation-delay: 1.2s; } .gw-smoke.gw-d3 { animation-delay: 2.4s; }
        .gw-lava { animation: gw-lava 1.6s ease-in-out infinite alternate; }
        .gw-zzz { animation: gw-zzz 3s ease-out infinite; }
        .gw-zzz.gw-d2 { animation-delay: 1s; }
    }
    @keyframes gw-flapL { from { transform: rotate(18deg); } to { transform: rotate(-26deg); } }
    @keyframes gw-flapR { from { transform: rotate(-18deg); } to { transform: rotate(26deg); } }
    @keyframes gw-twinkle { 0%, 100% { opacity: .15; transform: scale(.6); } 50% { opacity: 1; transform: scale(1.1); } }
    @keyframes gw-foam { 0%, 100% { transform: scale(1); opacity: .75; } 50% { transform: scale(1.05); opacity: .95; } }
    @keyframes gw-monkey { 0%, 100% { transform: translateY(0) scale(1.1, .9); } 20% { transform: translateY(-4px) scale(.95, 1.05); } 50% { transform: translateY(-18px) scale(1, 1); } 80% { transform: translateY(-4px) scale(.95, 1.05); } }
    @keyframes gw-slide { 0%, 100% { transform: translateX(-24px) rotate(-8deg); } 50% { transform: translateX(24px) rotate(8deg); } }
    @keyframes gw-smoke { 0% { transform: translateY(0) scale(.5); opacity: .9; } 100% { transform: translateY(-60px) scale(1.6); opacity: 0; } }
    @keyframes gw-lava { from { opacity: .75; } to { opacity: 1; } }
    @keyframes gw-blink { 0%, 92%, 100% { transform: scaleY(1); } 95% { transform: scaleY(.1); } }
    @keyframes gw-claw { from { transform: rotate(-12deg); } to { transform: rotate(22deg); } }
    @keyframes gw-zzz { 0% { transform: translate(0, 0) scale(.6); opacity: 0; } 30% { opacity: 1; } 100% { transform: translate(14px, -34px) scale(1.2); opacity: 0; } }
    @keyframes gw-cloud { from { transform: translateX(-200px); } to { transform: translateX(600px); } }
    @keyframes gw-swim { 0% { transform: translateX(-50px) scaleX(-1); } 45% { transform: translateX(50px) scaleX(-1); } 50% { transform: translateX(50px) scaleX(1); } 95% { transform: translateX(-50px) scaleX(1); } 100% { transform: translateX(-50px) scaleX(-1); } }
    @keyframes gw-paddle { from { transform: rotate(-10deg); } to { transform: rotate(14deg); } }
    @keyframes gw-fig8 { 0% { transform: translate(0, 0) rotate(-4deg); } 25% { transform: translate(-70px, -24px) rotate(6deg); } 50% { transform: translate(0, 10px) rotate(-2deg); } 75% { transform: translate(70px, -20px) rotate(-6deg); } 100% { transform: translate(0, 0) rotate(-4deg); } }
    @keyframes gw-peek { 0%, 30%, 100% { transform: translateX(26px); } 40%, 70% { transform: translateX(0); } }
    @keyframes gw-perch { 0%, 100% { transform: translateY(0) scale(1); } 50% { transform: translateY(-2px) scale(1.02, .98); } }
    @keyframes gw-eggwob { 0%, 80%, 100% { transform: rotate(0); } 85% { transform: rotate(-8deg); } 90% { transform: rotate(7deg); } 95% { transform: rotate(-4deg); } }
    @keyframes gw-spin { to { transform: rotate(360deg); } }
    @keyframes gw-caustic { from { transform: translateX(-14px); } to { transform: translateX(14px); } }
    @keyframes gw-bubble { 0% { transform: translateY(0) scale(.6); opacity: 0; } 20% { opacity: .9; } 100% { transform: translateY(-70px) scale(1.2); opacity: 0; } }
    @keyframes gw-snow { 0% { transform: translateY(-20px); opacity: 0; } 10% { opacity: 1; } 100% { transform: translate(20px, 220px); opacity: 0; } }
</style>
