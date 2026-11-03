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
        .wrap { max-width: 1200px; margin: 0 auto; padding: 18px 20px 60px; }
        h1 { color: #b8862b; text-transform: uppercase; letter-spacing: .03em; margin: 0 0 12px; font-size: 34px; }
        .bar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 14px; }
        input[type=text] { font: inherit; font-size: 17px; padding: 4px 8px; border: 2px solid #d8c9a6; border-radius: 8px; background: #fff; }
        .btn { font: inherit; font-size: 16px; cursor: pointer; border: 2px solid #b8862b; background: #fff; color: #8a5a12; border-radius: 8px; padding: 3px 12px; text-transform: uppercase; text-decoration: none; }
        .btn:hover { background: #fbf1da; }
        .btn-danger { border-color: #d9534f; color: #b52b27; }
        .maps { list-style: none; padding: 0; margin: 0; display: grid; gap: 8px; max-width: 640px; }
        .maps li { display: flex; align-items: center; justify-content: space-between; gap: 10px; background: #fff; border: 2px solid #f0e4c6; border-radius: 10px; padding: 8px 12px; font-size: 20px; }
        .group { color: #8a5a12; font-size: 22px; text-transform: uppercase; margin: 18px 0 8px; }
        .client { color: #8b8270; font-size: 24px; }
        .muted { color: #8b8270; font-size: 15px; }
        .err { color: #b52b27; }
        .board { overflow: auto; border-top: 2px dashed #eadfc3; padding-top: 6px; }
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
