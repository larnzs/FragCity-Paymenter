{{-- extensions/Others/ObsidianCartEditor/Resources/views/admin/cart.blade.php --}}

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
        .one-btn-danger { border-color: rgba(248,113,113,0.35); background: rgba(248,113,113,0.14); color: rgba(254,202,202,0.95); }
        .one-btn-danger:hover { background: rgba(248,113,113,0.20); }

        .one-grid { display:grid; grid-template-columns: 1fr; gap:18px; margin-top:18px; }
        .one-card { border:1px solid rgba(255,255,255,0.10); background: rgba(255,255,255,0.04); border-radius:22px; padding:18px; box-shadow: 0 18px 45px rgba(0,0,0,0.35) inset; }
        .one-card-title-row { display:flex; align-items:center; justify-content:space-between; gap:12px; }
        .one-card-title { font-size:18px; font-weight:800; color:#fff; margin:0; }
        .one-card-sub { margin-top:6px; color: rgba(255,255,255,0.55); font-size:13px; }

        .one-divider { height:1px; background: rgba(255,255,255,0.08); margin:14px 0; border-radius:999px; }

        .one-field { margin-top:14px; }
        .one-label-sm { display:block; margin-bottom:6px; color: rgba(255,255,255,0.45); font-size:11px; font-weight:900; letter-spacing:0.08em; text-transform:uppercase; }
        .one-input, .one-select, .one-textarea {
            width:100%;
            border-radius:16px;
            border:1px solid rgba(255,255,255,0.10);
            background: rgba(0,0,0,0.30);
            color:#fff;
            padding:12px 14px;
            font-size:13px;
            outline:none;
        }
        .one-textarea { min-height: 90px; resize: vertical; }
        .one-input::placeholder, .one-textarea::placeholder { color: rgba(255,255,255,0.35); }

        .one-row2 { display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-top:14px; }
        .one-row3 { display:grid; grid-template-columns: 1fr 1fr 1fr; gap:12px; margin-top:14px; }

        .one-check { display:flex; align-items:center; gap:10px; color:#fff; font-weight:800; font-size:13px; }
        .one-check input { width:18px; height:18px; accent-color: #a855f7; }

        .one-section-box { border:1px solid rgba(255,255,255,0.10); background: rgba(0,0,0,0.22); border-radius:18px; padding:16px; margin-top:14px; }
        .one-section-head { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; }
        .one-hint { margin-top:6px; color: rgba(255,255,255,0.45); font-size:12px; }

        .one-status { border-radius:18px; border:1px solid rgba(16,185,129,0.35); background: rgba(16,185,129,0.10); color: rgba(110,231,183,0.95); padding:12px 14px; font-weight:800; font-size:13px; }
        .one-danger { border-radius:18px; border:1px solid rgba(248,113,113,0.35); background: rgba(248,113,113,0.10); color: rgba(254,202,202,0.95); padding:12px 14px; font-weight:800; font-size:13px; }

        @media (max-width: 900px) {
            .one-row2, .one-row3 { grid-template-columns: 1fr; }
        }
    </style>

    <div class="one-wrap">
        <livewire:obsidian-cart-editor.editor />
    </div>
</x-filament-panels::page>
