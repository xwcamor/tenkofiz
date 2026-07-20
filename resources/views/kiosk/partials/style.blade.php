<style>
    body {
        background: radial-gradient(1200px 600px at 50% -12%, #17324f 0%, transparent 55%), #0d1420;
        color: #e8eef6;
        min-height: 100vh;
        font-family: system-ui, -apple-system, sans-serif;
    }
    #clock { font-size: clamp(1.9rem, 6vw, 2.7rem); font-weight: 700; letter-spacing: 3px; color: #f4f8fd; }
    #date { color: #94a6bd; }
    #date::first-letter { text-transform: uppercase; }
    h1.title { font-size: clamp(1rem, 4vw, 1.5rem); color: #cdd9e8; letter-spacing: 1px; font-weight: 600; }
    h1.title i { color: #4a90e2; }
    .kiosk-help { color: #8fa2b8; }
    .kiosk-card {
        background: #16202e;
        border: 1px solid #2b3a4e;
        border-radius: 18px;
        padding: 1.4rem;
        width: 100%; max-width: 430px;
        margin: 0 auto;
        text-align: center;
    }
    .dni-display {
        background: #0d141d;
        border: 1px solid #2b3a4e;
        border-radius: 12px;
        font-size: 1.9rem; font-weight: 700; letter-spacing: 6px;
        padding: .5rem; margin-bottom: 1rem; min-height: 3.4rem;
        color: #fff;
    }
    .keypad { display: grid; grid-template-columns: repeat(3, 1fr); gap: .55rem; }
    .keypad button {
        font-size: 1.5rem; font-weight: 600;
        padding: .7rem 0;
        border-radius: 12px;
        border: 1px solid #2b3a4e;
        background: #1d2a3a; color: #fff;
    }
    .keypad button:active { background: #2e75b6; }
    /* Camera pages */
    .video-frame {
        border: 3px solid #2e75b6;
        border-radius: 18px;
        box-shadow: 0 14px 48px rgba(0, 0, 0, .45);
        overflow: hidden;
        width: 100%; max-width: 560px;
        margin: 0 auto;
        position: relative;
    }
    .video-frame video, .video-frame canvas { display: block; width: 100%; height: auto; }
    .video-frame canvas { position: absolute; top: 0; left: 0; }
    /* Natural selfie mirror on EVERY device (some tablets flip the feed, others
       don't — this standardizes it). Only the video and the drawing canvas are
       mirrored; the instruction/countdown overlays are plain divs on top, so
       their text stays readable. Face detection reads the raw pixels, unaffected. */
    .video-frame video, .video-frame canvas { transform: scaleX(-1); }
    #result { min-height: 90px; }
    .progress.kiosk-progress { height: 6px; background: #1d2a3a; max-width: 560px; margin: .5rem auto 0; }
    .consent-box { text-align: left; font-size: .8rem; color: #b9c4d2; background: #0d141d; border: 1px solid #2b3a4e; border-radius: 10px; padding: .7rem .8rem; max-height: 150px; overflow-y: auto; }
    .person-chip { background: #1d2a3a; border: 1px solid #2b3a4e; border-radius: 999px; display: inline-block; padding: .35rem 1.1rem; color: #dbe6f3; font-weight: 600; }

    /* ---------- Clean white camera page (only the circular camera) ----------
       Applied only on the verify page (<body class="kiosk-cam">): a distraction-
       free white screen with the live camera cropped to a circle, RENIEC-style.
       The keypad and other kiosk pages keep the dark theme. */
    body.kiosk-cam { background: #f4f7fb; color: #22303f; }
    body.kiosk-cam .person-chip { background: #eaf1f9; border-color: #cdddef; color: #1f4b73; }
    body.kiosk-cam .btn-outline-light { color: #33475b; border-color: #b7c5d6; }
    body.kiosk-cam .kiosk-help { color: #5b6b7d; }
    body.kiosk-cam .form-check-label.text-light { color: #33475b !important; }
    body.kiosk-cam .consent-box { color: #33475b; background: #f0f4f9; border-color: #cdddef; }
    body.kiosk-cam .kiosk-card { background: #fff; border-color: #d7e0ec; box-shadow: 0 8px 30px rgba(20, 50, 90, .08); }
    body.kiosk-cam .kiosk-card h6.text-white { color: #22303f !important; }
    body.kiosk-cam .alert-secondary { background: #eef2f7; color: #33475b; border-color: #dbe3ec; }
    /* Camera cropped to a circle. video + canvas share the same box and aspect,
       both object-fit:cover, so the guide oval drawn on the canvas stays aligned
       with the face inside the circle. */
    body.kiosk-cam .video-frame {
        width: min(78vw, 360px);
        aspect-ratio: 1 / 1;
        border-radius: 50%;
        border: 5px solid #2e75b6;
        box-shadow: 0 16px 44px rgba(20, 50, 90, .20);
        background: #dfe7f1;
        transition: border-color .2s;
    }
    body.kiosk-cam .video-frame.face-ok { border-color: #28a745; }
    body.kiosk-cam .video-frame video,
    body.kiosk-cam .video-frame canvas {
        position: absolute; inset: 0;
        width: 100%; height: 100%;
        object-fit: cover;
    }
    /* Countdown shown BELOW the circle, big and readable on white */
    body.kiosk-cam .kiosk-countdown {
        font-size: 2.2rem; font-weight: 800; color: #2e75b6;
        line-height: 1; margin: .6rem auto .1rem;
    }
    /* Big, high-contrast instruction banner (readable from a distance) */
    body.kiosk-cam #result .alert {
        font-size: 1.35rem; font-weight: 700; line-height: 1.3;
        padding: .8rem 1.4rem; border-radius: 14px; border: 0;
        max-width: 520px; box-shadow: 0 6px 20px rgba(20, 50, 90, .10);
    }
    body.kiosk-cam #result .alert-info { background: #e7f1fb; color: #14508a; }
    body.kiosk-cam #result .alert-warning { background: #fff3cd; color: #8a5a00; }
    body.kiosk-cam #result .alert-success { background: #d7f3df; color: #17663a; }
    body.kiosk-cam #result .alert-secondary { background: #eef2f7; color: #33475b; }
    body.kiosk-cam #result .alert i { margin-right: .4rem; }
</style>
