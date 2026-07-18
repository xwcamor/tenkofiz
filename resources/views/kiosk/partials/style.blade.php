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
    #result { min-height: 90px; }
    .progress.kiosk-progress { height: 6px; background: #1d2a3a; max-width: 560px; margin: .5rem auto 0; }
    .consent-box { text-align: left; font-size: .8rem; color: #b9c4d2; background: #0d141d; border: 1px solid #2b3a4e; border-radius: 10px; padding: .7rem .8rem; max-height: 150px; overflow-y: auto; }
    .person-chip { background: #1d2a3a; border: 1px solid #2b3a4e; border-radius: 999px; display: inline-block; padding: .35rem 1.1rem; color: #dbe6f3; font-weight: 600; }
</style>
