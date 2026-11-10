<x-filament-panels::page>
    <style>
        .one-wrap { padding: 8px 0; }
        .one-header { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:18px; }
        .one-title { font-size:34px; line-height:1.1; font-weight:800; color:#fff; margin:0; }
        .one-sub { margin-top:8px; color: rgba(255,255,255,0.60); font-size:14px; line-height:1.4; }
        .one-actions { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
        .one-btn { border:1px solid rgba(255,255,255,0.12); background: rgba(255,255,255,0.06); color:#fff; border-radius:14px; padding:10px 14px; font-weight:700; font-size:13px; cursor:pointer; }
        .one-btn:hover { background: rgba(255,255,255,0.10); }
        .one-btn-primary { border-color: rgba(168,85,247,0.35); background: rgba(168,85,247,0.95); }
        .one-btn-primary:hover { background: rgba(168,85,247,0.85); }

        .one-grid { display:grid; grid-template-columns: 1fr; gap:18px; margin-top:18px; }
        .one-card { border:1px solid rgba(255,255,255,0.10); background: rgba(255,255,255,0.04); border-radius:22px; padding:18px; box-shadow: 0 18px 45px rgba(0,0,0,0.35) inset; }
        .one-card-title-row { display:flex; align-items:center; justify-content:space-between; gap:12px; }
        .one-card-title { font-size:18px; font-weight:800; color:#fff; margin:0; }

        .one-toggle { display:inline-flex; align-items:center; gap:6px; border:1px solid rgba(255,255,255,0.10); background: rgba(0,0,0,0.30); border-radius:16px; padding:4px; }
        .one-toggle button { border:0; background: transparent; color: rgba(255,255,255,0.70); font-weight:800; font-size:13px; padding:8px 14px; border-radius:12px; cursor:pointer; }
        .one-toggle button.one-active { background: rgba(168,85,247,0.95); color:#fff; }

        .one-preview-box { margin-top:14px; border:1px solid rgba(255,255,255,0.10); background: rgba(0,0,0,0.22); border-radius:16px; padding:16px; }
        .one-pill-row { display:flex; flex-wrap:wrap; gap:10px; }
        .one-pill { border:1px solid rgba(255,255,255,0.10); background: rgba(0,0,0,0.30); color: rgba(255,255,255,0.92); border-radius:14px; padding:10px 16px; font-weight:800; font-size:13px; }
        .one-muted { color: rgba(255,255,255,0.55); font-size:13px; }

        .one-search-box { margin-top:12px; border:1px solid rgba(255,255,255,0.10); background: rgba(0,0,0,0.22); border-radius:16px; padding:12px 14px; display:flex; gap:10px; align-items:center; }
        .one-search-box input { width:100%; border:0; outline:none; background: transparent; color:#fff; font-size:13px; }
        .one-hint { margin-top:8px; color: rgba(255,255,255,0.45); font-size:12px; }

        .one-table { margin-top:18px; border:1px solid rgba(255,255,255,0.10); background: rgba(255,255,255,0.04); border-radius:22px; overflow:hidden; }
        .one-thead { display:grid; grid-template-columns: 2.2fr 1fr 2.2fr 0.9fr 1fr 1fr 1.3fr; gap:14px; padding:14px 18px; border-bottom:1px solid rgba(255,255,255,0.08); color: rgba(255,255,255,0.45); font-size:11px; font-weight:800; letter-spacing:0.08em; }
        .one-row { display:grid; grid-template-columns: 2.2fr 1fr 2.2fr 0.9fr 1fr 1fr 1.3fr; gap:14px; padding:16px 18px; border-bottom:1px solid rgba(255,255,255,0.08); }
        .one-row:last-child { border-bottom:0; }

        .one-row-child { display:grid; grid-template-columns: 2.2fr 1fr 2.2fr 0.9fr 1fr 1fr 1.3fr; gap:14px; padding:12px 18px; border-bottom:1px solid rgba(255,255,255,0.08); background: rgba(0,0,0,0.12); }
        .one-row-child .one-label { font-size:13px; font-weight:800; color: rgba(255,255,255,0.90); }
        .one-row-child .one-subline { margin-top:4px; font-size:12px; color: rgba(255,255,255,0.40); }

        .one-label { color:#fff; font-weight:800; font-size:14px; }
        .one-subline { margin-top:6px; color: rgba(255,255,255,0.45); font-size:12px; }

        .one-badge { display:inline-flex; align-items:center; justify-content:center; border-radius:999px; padding:6px 10px; font-size:12px; font-weight:800; border:1px solid rgba(255,255,255,0.10); background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.80); }
        .one-badge-blue { border-color: rgba(59,130,246,0.35); background: rgba(59,130,246,0.12); color: rgba(147,197,253,0.95); }
        .one-badge-purple { border-color: rgba(168,85,247,0.35); background: rgba(168,85,247,0.12); color: rgba(216,180,254,0.95); }
        .one-badge-green { border-color: rgba(16,185,129,0.35); background: rgba(16,185,129,0.12); color: rgba(110,231,183,0.95); }
        .one-badge-gray { border-color: rgba(255,255,255,0.12); background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.45); }

        .one-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; color: rgba(255,255,255,0.85); font-size:13px; word-break: break-all; }

        .one-order { display:flex; align-items:center; gap:10px; }
        .one-order-num { color:#fff; font-weight:800; font-size:13px; min-width:18px; }
        .one-order-btns { display:flex; flex-direction:column; gap:6px; }
        .one-mini { width:46px; height:26px; border-radius:8px; border:1px solid rgba(255,255,255,0.12); background: rgba(255,255,255,0.06); color:#fff; font-weight:800; font-size:12px; cursor:pointer; }
        .one-mini:hover { background: rgba(255,255,255,0.10); }

        .one-actions-col { text-align:right; }
        .one-link { background: transparent; border:0; cursor:pointer; font-weight:800; font-size:13px; }
        .one-link-edit { color: rgba(216,180,254,0.95); }
        .one-link-edit:hover { color: rgba(192,132,252,1); }
        .one-link-del { color: rgba(252,165,165,0.95); margin-left:10px; }
        .one-link-del:hover { color: rgba(248,113,113,1); }
        .one-link-sub { color: rgba(191,219,254,0.95); margin-left:10px; }
        .one-link-sub:hover { color: rgba(147,197,253,1); }

        .one-empty { padding:18px; color: rgba(255,255,255,0.55); font-size:13px; }
        .one-modal-wrap { position:fixed; inset:0; z-index:9999; display:flex; align-items:center; justify-content:center; padding:16px; }
        .one-modal-bg { position:absolute; inset:0; background: rgba(0,0,0,0.70); }
        .one-modal { position:relative; width:100%; max-width:720px; border-radius:22px; border:1px solid rgba(255,255,255,0.10); background: #0b0b0f; padding:18px; }
        .one-modal-head { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; }
        .one-modal-title { color:#fff; font-weight:900; font-size:18px; }
        .one-modal-sub { margin-top:6px; color: rgba(255,255,255,0.55); font-size:13px; }
        .one-field { margin-top:14px; }
        .one-label-sm { display:block; margin-bottom:6px; color: rgba(255,255,255,0.45); font-size:11px; font-weight:900; letter-spacing:0.08em; }
        .one-input, .one-select { width:100%; border-radius:16px; border:1px solid rgba(255,255,255,0.10); background: rgba(0,0,0,0.30); color:#fff; padding:12px 14px; font-size:13px; outline:none; }
        .one-row2 { display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-top:14px; }
        .one-check { display:flex; align-items:center; gap:10px; color:#fff; font-weight:800; font-size:13px; }
        .one-check input { width:18px; height:18px; }
        .one-modal-actions { margin-top:16px; display:flex; justify-content:flex-end; gap:10px; }

        @media (max-width: 1100px) {
            .one-thead, .one-row, .one-row-child { grid-template-columns: 2fr 1fr 2fr 1fr 1fr 1fr; }
            .one-thead div:nth-child(7), .one-row div:nth-child(7), .one-row-child div:nth-child(7) { display:none; }
        }
        @media (max-width: 640px) {
            .one-row2 { grid-template-columns: 1fr; }
        }
    </style>

    <div class="one-wrap">
        @livewire('obsidian-navbar-editor.navbar-editor')
    </div>
</x-filament-panels::page>
