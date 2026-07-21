<style>
  :root{
    --bg:#0b0a0a; --surface:#141211; --surface-2:#1c1918; --surface-3:#242020;
    --border:#2b2624; --border-soft:#1e1a19;
    --text:#f2eeec; --text-dim:#a89e9a; --text-faint:#6f6663;
    --accent:#f42821; --accent-dark:#a30100;
    --bull:#2fbe8f; --bull-soft:rgba(47,190,143,.13);
    --bear:#f42821; --bear-soft:rgba(244,40,33,.13);
    --gold:#d9a94d; --gold-soft:rgba(217,169,77,.13);
    --radius:10px;
    --font-ui:-apple-system,BlinkMacSystemFont,"Segoe UI",Tahoma,Arial,sans-serif;
    --font-mono:ui-monospace,"SF Mono","Cascadia Mono","Roboto Mono",Consolas,monospace;
  }
  *{box-sizing:border-box;}
  html,body{margin:0;padding:0;}
  body{background:var(--bg);color:var(--text);font-family:var(--font-ui);font-size:14px;line-height:1.55;-webkit-font-smoothing:antialiased;}
  #app{max-width:1180px;margin:0 auto;padding:0 20px 48px;}
  a{color:inherit;} button{font-family:inherit;}
  .num{direction:ltr;unicode-bidi:isolate;font-variant-numeric:tabular-nums;font-family:var(--font-mono);display:inline-block;}
  .topbar{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:16px 0;border-bottom:1px solid var(--border-soft);}
  .brand{display:flex;align-items:center;gap:10px;font-weight:700;font-size:15px;white-space:nowrap;}
  .brand .mark{width:26px;height:26px;border-radius:6px;background:linear-gradient(135deg,var(--accent),var(--accent-dark));display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;color:#fff;}
  .main-nav{display:flex;gap:4px;}
  .main-nav a{padding:7px 12px;border-radius:8px;font-size:12.5px;font-weight:600;color:var(--text-dim);text-decoration:none;}
  .main-nav a:hover{color:var(--text);}
  .main-nav a.is-active{background:var(--surface-2);color:var(--text);}
  .topbar-actions{display:flex;align-items:center;gap:10px;}
  .locale-toggle{display:flex;border:1px solid var(--border);border-radius:8px;overflow:hidden;}
  .locale-toggle button{padding:7px 12px;background:var(--surface);color:var(--text-dim);border:none;cursor:pointer;font-size:12.5px;font-weight:600;}
  .locale-toggle button.is-active{background:var(--surface-3);color:var(--text);}
  .live-status{display:flex;align-items:center;gap:6px;font-size:12px;color:var(--text-dim);}
  .pulse-dot{width:7px;height:7px;border-radius:50%;background:var(--bull);animation:pulse 2s infinite;}
  @keyframes pulse{0%{box-shadow:0 0 0 0 rgba(47,190,143,.45);}70%{box-shadow:0 0 0 7px rgba(47,190,143,0);}100%{box-shadow:0 0 0 0 rgba(47,190,143,0);}}
  .breadcrumb{display:flex;gap:8px;align-items:center;font-size:12.5px;color:var(--text-faint);margin:18px 0 6px;}
  .badge{display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:100px;font-size:11.5px;font-weight:700;}
  .badge-gold{background:var(--gold-soft);color:var(--gold);}
  .badge-bull{background:var(--bull-soft);color:var(--bull);}
  .badge-bear{background:var(--bear-soft);color:var(--bear);}
  .badge-neutral{background:var(--surface-2);color:var(--text-dim);border:1px solid var(--border);}
  .panel{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:18px;}
  .panel-title{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;}
  .panel-title h2{font-size:13px;margin:0;text-transform:uppercase;letter-spacing:.06em;color:var(--text-dim);font-weight:700;}
  .empty-note{font-size:12px;color:var(--text-faint);font-style:italic;}
  #app[dir="rtl"] .brand{flex-direction:row-reverse;}
  #app[dir="rtl"] .locale-toggle{flex-direction:row-reverse;}
  #app[dir="rtl"] .main-nav{flex-direction:row-reverse;}
</style>
