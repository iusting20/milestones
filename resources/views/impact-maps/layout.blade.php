<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Impact Maps')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Patrick+Hand&display=swap">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #fffdf7; color: #2b2b2b; font-family: 'Patrick Hand', 'Comic Sans MS', 'Segoe Print', cursive; }
        a { color: #8a5a12; }
        .wrap { max-width: 1280px; margin: 0 auto; padding: 18px 20px 60px; }
        h1 { color: #b8862b; text-transform: uppercase; letter-spacing: .03em; margin: 0 0 12px; font-size: 34px; }
        .bar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 14px; }
        input[type=text] { font: inherit; font-size: 17px; padding: 4px 8px; border: 2px solid #d8c9a6; border-radius: 8px; background: #fff; }
        .btn { font: inherit; font-size: 16px; cursor: pointer; border: 2px solid #b8862b; background: #fff; color: #8a5a12; border-radius: 8px; padding: 3px 12px; text-transform: uppercase; text-decoration: none; }
        .btn:hover { background: #fbf1da; }
        .btn-danger { border-color: #d9534f; color: #b52b27; }
        .maps { list-style: none; padding: 0; margin: 0; display: grid; gap: 8px; max-width: 640px; }
        .maps li { display: flex; align-items: center; justify-content: space-between; gap: 10px; background: #fff; border: 2px solid #f0e4c6; border-radius: 10px; padding: 8px 12px; font-size: 20px; }
        .group { color: #8a5a12; font-size: 22px; text-transform: uppercase; margin: 18px 0 8px; }
        .group-link { color: inherit; text-decoration: none; cursor: pointer; }
        .group-link:hover { color: #b8862b; }
        .client { color: #8b8270; font-size: 24px; }
        .muted { color: #8b8270; font-size: 15px; }
        .err { color: #b52b27; }
        .board { overflow: auto; border-top: 2px dashed #eadfc3; padding-top: 6px; }
        .im-divider { border: 0; border-top: 2px dashed #eadfc3; margin: 18px 0 12px; } /* same line as under the map title */
        .canvas { position: relative; }
        .canvas svg { position: absolute; inset: 0; pointer-events: none; }
        .col { position: absolute; top: 8px; color: #b8862b; font-size: 30px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
        .node { position: absolute; transform: translateY(-50%); border-radius: 10px; padding: 6px 10px; font-weight: 700; text-transform: uppercase; line-height: 1.1; font-size: 16px; color: #1f1f1f; box-shadow: 0 1px 0 rgba(0,0,0,.08); }
        .lvl-0 { background: #f9b11b; color: #fff; font-size: 24px; padding: 14px 14px; min-height: 90px; display: flex; flex-direction: column; justify-content: center; }
        .lvl-1 { background: #7b86c6; font-size: 18px; }
        .lvl-2 { background: #9fbf3b; }
        .lvl-3 { background: #ef7a7a; }
        .lvl-4 { background: #fbd75b; font-size: 14px; }
        .node .t { cursor: text; display: block; word-wrap: break-word; }
        .lvl-0 .t, .lvl-4 .t { text-wrap: balance; overflow-wrap: anywhere; }
        .node form.f-edit, .node form.f-add { display: none; margin: 4px 0 0; }
        .node.editing .t { display: none; }
        .node.editing form.f-edit, .node.adding form.f-add { display: block; }
        .node input[type=text] { width: 100%; font-size: 15px; padding: 2px 6px; text-transform: none; }
        .tools { position: absolute; right: -8px; top: -12px; display: none; gap: 3px; }
        .node:hover .tools, .node:focus-within .tools { display: flex; }
        .tools button { font: inherit; font-size: 14px; width: 22px; height: 22px; line-height: 18px; padding: 0; border-radius: 50%; border: 2px solid #2b2b2b; background: #fff; color: #2b2b2b; cursor: pointer; }
        .tools form { margin: 0; }
    </style>
    <style>
        .maps li { position: relative; }
        .meta { display: flex; align-items: center; gap: 8px; }
        .wrench { background: none; border: 0; cursor: pointer; padding: 0; line-height: 0; transition: transform .15s; }
        .wrench:hover, .wrench[aria-expanded=true] { transform: rotate(-18deg) scale(1.1); }
        .cog-menu { position: absolute; z-index: 10; right: 0; top: 100%; margin-top: 6px; background: #fffdf7; border: 3px solid #2b2b2b;
            border-radius: 14px; box-shadow: 4px 4px 0 #2b2b2b; padding: 12px; display: flex; flex-direction: column; gap: 10px; min-width: 280px; }
        .cog-menu form { margin: 0; display: flex; gap: 6px; }
        .toon-btn { font: inherit; font-size: 17px; cursor: pointer; border: 3px solid #2b2b2b; border-radius: 12px; padding: 4px 14px;
            text-transform: uppercase; box-shadow: 3px 3px 0 #2b2b2b; background: #f9b11b; color: #2b2b2b; }
        .toon-btn:hover { transform: translate(-1px, -1px); box-shadow: 4px 4px 0 #2b2b2b; }
        .toon-btn:active { transform: translate(2px, 2px); box-shadow: 1px 1px 0 #2b2b2b; }
        .toon-btn.plain { background: #fff; }
        .modal-bg { position: fixed; inset: 0; z-index: 50; background: rgba(43, 43, 43, .45); display: flex; align-items: center; justify-content: center; padding: 20px; }
        .modal { background: #fffdf7; border: 3px solid #2b2b2b; border-radius: 18px; box-shadow: 6px 6px 0 #2b2b2b; padding: 20px 22px;
            width: 100%; max-width: 420px; transform: rotate(-.6deg); }
        .modal h2 { margin: 0 0 14px; color: #b8862b; text-transform: uppercase; font-size: 28px; }
        .modal label { display: block; font-size: 18px; color: #8a5a12; margin: 0 0 4px; }
        .modal input[type=text] { width: 100%; border: 3px solid #2b2b2b; border-radius: 12px; padding: 6px 10px; font-size: 19px; margin-bottom: 12px; }
        .modal input[type=text]:focus { outline: none; box-shadow: 3px 3px 0 #f9b11b; }
        .modal .actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 4px; }
        .picker { display: flex; flex-direction: column; gap: 8px; flex: 1; }
        .toon-select { font: inherit; font-size: 19px; width: 100%; padding: 6px 36px 6px 10px; border: 3px solid #2b2b2b; border-radius: 12px; cursor: pointer;
            background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='12' viewBox='0 0 16 12'%3E%3Cpath d='M2 2.5 8 9.5 14 2.5' fill='none' stroke='%232b2b2b' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") no-repeat right 12px center;
            appearance: none; -webkit-appearance: none; color: #2b2b2b; box-shadow: 3px 3px 0 #f9b11b; }
        .toon-select:focus { outline: none; box-shadow: 3px 3px 0 #b8862b; }
        .modal .picker { margin-bottom: 12px; }
        .name-row { display: flex; justify-content: space-between; align-items: baseline; gap: 10px; }
        .tpl-btn { font: inherit; font-size: 15px; background: #fbd75b; border: 2px solid #2b2b2b; border-radius: 10px; box-shadow: 2px 2px 0 #2b2b2b;
            padding: 0 8px; margin-bottom: 4px; cursor: pointer; transition: transform .12s, box-shadow .12s; }
        .tpl-btn:hover { transform: translate(-1px, -1px); box-shadow: 3px 3px 0 #2b2b2b; }
        .tpl-bg { z-index: 60; }
        .tpl-modal { max-width: 560px; }
        .tpl-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 10px; margin-bottom: 12px; }
        .tpl-card { font: inherit; font-size: 18px; text-align: left; display: flex; align-items: center; gap: 10px; background: #fff;
            border: 3px solid #2b2b2b; border-left: 10px solid var(--c); border-radius: 12px; box-shadow: 3px 3px 0 #2b2b2b; padding: 8px 12px; cursor: pointer;
            transition: transform .12s, box-shadow .12s; }
        .tpl-card:hover, .tpl-card:focus-visible { transform: translate(-2px, -2px); box-shadow: 5px 5px 0 #2b2b2b; outline: none; }
        .tpl-ico { font-size: 22px; }
        @media (prefers-reduced-motion: reduce) { .tpl-btn, .tpl-card { transition: none; } }
        .modal .picker input[type=text] { margin-bottom: 0; }
        .cog-menu form:first-child { flex-direction: column; align-items: stretch; }
        .cog-menu input[type=text] { border: 3px solid #2b2b2b; border-radius: 12px; }
        #im-groups .group, #im-groups .maps li { cursor: grab; }
        #im-groups .maps li .cog-menu { cursor: default; }
        .im-chosen, body.im-dragging, body.im-dragging * { cursor: grabbing !important; }
        .im-ghost { opacity: .55; background: #fbf1da !important; border: 2px dashed #b8862b !important; }
        .im-chosen { box-shadow: 4px 4px 0 #2b2b2b; transform: rotate(-1deg); }
        #im-groups .maps { min-height: 12px; }
        .node[data-reorder] { cursor: grab; touch-action: none; user-select: none; }
        .node[data-reorder] .t { cursor: grab; }
        .canvas .node { transition: top .2s ease, transform .2s ease, box-shadow .2s ease; }
        .canvas.no-anim .node { transition: none !important; }
        .canvas .node.lifted { transition: transform .2s ease, box-shadow .2s ease; z-index: 20;
            transform: translateY(-50%) scale(1.04) rotate(-1.5deg); box-shadow: 6px 8px 0 rgba(43,43,43,.35); }
        .canvas .node.lift-root { z-index: 21; }
        .canvas svg path { transition: none; }
        @media (prefers-reduced-motion: reduce) {
            .canvas .node, .canvas .node.lifted { transition: none; }
            .canvas .node.lifted { transform: translateY(-50%); }
            .im-ghost, .im-chosen { transition: none !important; }
        }
        .sub-label { position: absolute; left: 0; right: 0; text-align: center; bottom: 100%; margin-bottom: 8px; font-size: 15px; font-weight: 400; line-height: 1;
            text-transform: none; letter-spacing: .02em; pointer-events: none; user-select: none; white-space: nowrap; }
        .sub-3 { color: #d95c5c; }
        .sub-4 { color: #c99a06; }
        /* Progress tracker */
        .track-wrap { margin-top: 28px; }
        .track-toggle { font-family: 'Patrick Hand', cursive; font-size: 20px; text-transform: lowercase; background: none; border: 0; padding: 0; color: #2b2b2b; opacity: .45; cursor: pointer; transition: opacity .2s; white-space: nowrap; }
        .track-toggle:hover, .track-toggle:focus-visible, .track-toggle[aria-expanded=true] { opacity: .9; }
        .track-panel { display: grid; grid-template-rows: 0fr; transition: grid-template-rows .45s cubic-bezier(.2,.8,.2,1); }
        .track-wrap.open .track-panel { grid-template-rows: 1fr; }
        .track-inner { overflow: hidden; }
        .track-card { margin: 16px 6px 10px 0; background: #fffdf7; border: 3px solid #2b2b2b; border-radius: 18px; box-shadow: 6px 6px 0 #2b2b2b;
            padding: 18px 22px 20px; opacity: 0; transform: translateY(-10px); transition: opacity .35s ease, transform .35s ease; }
        .track-wrap.open .track-card { opacity: 1; transform: none; }
        .track-card h2 { margin: 0 0 6px; color: #b8862b; text-transform: uppercase; font-size: 28px; }
        .track-line { position: relative; height: 150px; margin: 0 70px; }
        .track-bar { position: absolute; left: 0; right: 0; top: 66px; height: 16px; background: #fff; border: 3px solid #2b2b2b; border-radius: 12px; overflow: hidden; }
        .track-fill { height: 100%; width: 0; background: repeating-linear-gradient(-45deg, #f9b11b 0 12px, #fbc94a 12px 24px); border-right: 3px solid #2b2b2b;
            transition: width 1.1s cubic-bezier(.2,.8,.2,1) .25s; }
        .track-wrap.open .track-fill { width: var(--fill); }
        .track-fill[style*="0%"] { border-right: 0; }
        .track-end, .track-pt { position: absolute; top: 74px; transform: translate(-50%, -50%); display: flex; flex-direction: column; align-items: center; }
        .t-start { left: 0; } .t-goal { left: 100%; }
        .track-end .dot { width: 26px; height: 26px; border-radius: 50%; background: #9fbf3b; border: 3px solid #2b2b2b; box-shadow: 2px 2px 0 #2b2b2b; }
        .track-end .lbl, .track-pt .lbl { position: absolute; top: 26px; white-space: nowrap; text-align: center; font-size: 17px; line-height: 1.1; }
        .track-end .lbl small { display: block; color: #8b8270; font-size: 13px; text-transform: uppercase; letter-spacing: .05em; }
        .flag { position: relative; width: 6px; height: 54px; margin-top: -40px; background: #2b2b2b; border-radius: 3px; }
        .flag::after { content: ''; position: absolute; left: 4px; top: 2px; width: 38px; height: 26px; border: 3px solid #2b2b2b; border-radius: 3px 6px 6px 3px;
            background: repeating-conic-gradient(#2b2b2b 0 25%, #fff 0 50%) 0 0 / 12px 12px; transform: skewY(-6deg); }
        .t-goal .lbl { top: 40px; }
        .track-pt { z-index: 2; cursor: help; }
        .track-pt .dot { width: 16px; height: 16px; border-radius: 50%; background: #7b86c6; border: 3px solid #2b2b2b; transition: transform .2s; }
        .track-pt:hover .dot { transform: scale(1.25); }
        .track-pt.latest { z-index: 3; }
        .track-pt.latest .dot { width: 26px; height: 26px; background: #ef7a7a; box-shadow: 2px 2px 0 #2b2b2b; animation: imPulse 1.8s ease-in-out infinite; }
        .track-pt.done .dot { background: #f9b11b; }
        .track-pt .badge { position: absolute; bottom: 30px; white-space: nowrap; background: #fbd75b; border: 3px solid #2b2b2b; border-radius: 14px;
            box-shadow: 3px 3px 0 #2b2b2b; padding: 2px 10px; font-size: 17px; transform: rotate(-2deg); }
        .track-pt .badge::after { content: ''; position: absolute; left: 50%; bottom: -9px; width: 12px; height: 12px; background: #fbd75b;
            border-right: 3px solid #2b2b2b; border-bottom: 3px solid #2b2b2b; transform: translateX(-50%) rotate(45deg); }
        .track-pt.done .badge { background: #9fbf3b; } .track-pt.done .badge::after { background: #9fbf3b; }
        .track-pt { outline: none; -webkit-tap-highlight-color: transparent; }
        .track-pt:hover, .track-pt:focus { z-index: 6; }
        .track-pt:focus-visible .dot { box-shadow: 0 0 0 3px #f9b11b; }
        .track-pt .lbl { transition: opacity .15s; }
        .track-pt:hover .lbl, .track-pt:focus .lbl { opacity: 0; }
        .tip { position: absolute; top: 30px; left: 50%; transform: translate(-50%, -5px); opacity: 0; visibility: hidden; pointer-events: none;
            transition: opacity .15s ease, transform .15s ease, visibility .15s; background: #fffdf7; border: 3px solid #2b2b2b; border-radius: 12px;
            box-shadow: 3px 3px 0 #2b2b2b; padding: 4px 12px 5px; font-size: 17px; line-height: 1.2; white-space: nowrap; text-align: center; z-index: 10; }
        .tip::before { content: ''; position: absolute; top: -9px; left: 50%; width: 12px; height: 12px; background: #fffdf7;
            border-left: 3px solid #2b2b2b; border-top: 3px solid #2b2b2b; transform: translateX(-50%) rotate(45deg); }
        .tip-meta { display: block; font-size: 14px; color: #8a5a12; }
        .tip-note { display: block; max-width: 240px; white-space: normal; font-size: 14px; color: #555; margin-top: 2px; }
        .track-pt:hover .tip, .track-pt:focus .tip { opacity: 1; visibility: visible; transform: translate(-50%, 0); }
        .track-pt.edge-l .tip { left: -14px; transform: translateY(-5px); text-align: left; }
        .track-pt.edge-r .tip { left: auto; right: -14px; transform: translateY(-5px); text-align: right; }
        .track-pt.edge-l:hover .tip, .track-pt.edge-l:focus .tip, .track-pt.edge-r:hover .tip, .track-pt.edge-r:focus .tip { transform: none; }
        .track-pt.edge-l .tip::before { left: 22px; }
        .track-pt.edge-r .tip::before { left: auto; right: 10px; transform: rotate(45deg); }
        @keyframes imPulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.12); } }
        .checkins { list-style: none; padding: 0; margin: 4px 0 14px; display: grid; gap: 6px; max-width: 720px; }
        .checkins li { display: flex; align-items: center; gap: 10px; background: #fff; border: 2px solid #2b2b2b; border-radius: 10px; padding: 4px 10px; font-size: 17px; }
        .checkins .ck-date { color: #8a5a12; min-width: 96px; }
        .checkins .ck-note { color: #555; flex: 1; }
        .checkins form { margin: 0 0 0 auto; }
        .ck-del { font: inherit; width: 24px; height: 24px; line-height: 18px; padding: 0; border-radius: 50%; border: 2px solid #2b2b2b; background: #fff; cursor: pointer; }
        .track-form { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 10px; margin: 10px 0; }
        .track-form label { display: flex; flex-direction: column; font-size: 15px; color: #8a5a12; gap: 2px; }
        .track-form label.grow { flex: 1; min-width: 140px; }
        .track-form input { font: inherit; font-size: 17px; padding: 4px 8px; border: 3px solid #2b2b2b; border-radius: 10px; background: #fff; }
        .track-form input[type=number] { width: 120px; }
        .track-settings { margin-top: 8px; }
        .track-settings summary { cursor: pointer; color: #8a5a12; font-size: 18px; }
        .track-settings > form:last-child { margin-top: 4px; }
        @media (prefers-reduced-motion: reduce) {
            .track-panel, .track-card, .track-fill { transition: none; }
            .track-pt.latest .dot { animation: none; }
            .tip, .track-pt .lbl { transition: none; }
        }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body>
<div class="wrap">
    @yield('content')
</div>
    @stack('scripts')
</body>
</html>
