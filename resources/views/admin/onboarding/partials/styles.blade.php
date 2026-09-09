<style>
    .onb-shell{max-width:1180px;margin:0 auto}
    .onb-top{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap}
    .onb-progress{display:grid;grid-template-columns:repeat(6,1fr);gap:.5rem;margin:1.25rem 0 1.4rem}
    .onb-step-dot{position:relative;padding-top:30px;text-align:center;color:var(--text-muted);font-size:.75rem}
    .onb-step-dot::before{content:'';position:absolute;top:7px;left:50%;width:16px;height:16px;border-radius:50%;transform:translateX(-50%);border:2px solid var(--border);background:var(--surface);z-index:2}
    .onb-step-dot::after{content:'';position:absolute;top:14px;right:50%;width:100%;height:2px;background:var(--border);z-index:1}
    .onb-step-dot:first-child::after{display:none}
    .onb-step-dot.done::before,.onb-step-dot.active::before{border-color:var(--theme-primary);background:var(--theme-primary)}
    .onb-step-dot.done::after,.onb-step-dot.active::after{background:var(--theme-primary)}
    .onb-step-dot.active{color:var(--text);font-weight:800}
    .onb-card{background:var(--surface);border:1px solid var(--border);border-radius:18px;box-shadow:0 8px 30px rgba(15,23,42,.05)}
    .onb-card-head{padding:1.15rem 1.25rem;border-bottom:1px solid var(--border)}
    .onb-card-body{padding:1.25rem}
    .onb-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}
    .onb-full{grid-column:1/-1}
    .onb-help{display:block;margin-top:.35rem;color:var(--text-muted);font-size:.78rem;line-height:1.7}
    .onb-actions{display:flex;align-items:center;justify-content:space-between;gap:.75rem;margin-top:1.25rem;flex-wrap:wrap}
    .onb-actions-end{display:flex;gap:.65rem;flex-wrap:wrap}
    .onb-profile-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.9rem}
    .onb-profile{position:relative;display:block;padding:1rem;border:1px solid var(--border);border-radius:14px;background:var(--surface);cursor:pointer;transition:.18s}
    .onb-profile:hover{transform:translateY(-2px);border-color:var(--theme-primary)}
    .onb-profile input{position:absolute;opacity:0;pointer-events:none}
    .onb-profile:has(input:checked){border-color:var(--theme-primary);box-shadow:0 0 0 3px color-mix(in srgb,var(--theme-primary) 14%,transparent)}
    .onb-profile strong{display:block;margin-bottom:.35rem}
    .onb-profile small{color:var(--text-muted);line-height:1.6}
    .onb-preview{display:flex;align-items:center;gap:1rem;padding:1rem;border:1px dashed var(--border);border-radius:14px;background:color-mix(in srgb,var(--theme-primary) 3%,transparent)}
    .onb-preview img{width:96px;height:70px;object-fit:contain;border-radius:10px;background:#fff;border:1px solid var(--border)}
    .onb-module-list{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem}
    .onb-module{padding:.85rem;border:1px solid var(--border);border-radius:12px}
    .onb-module-head{display:flex;align-items:flex-start;gap:.6rem}
    .onb-chip{display:inline-flex;align-items:center;padding:.2rem .55rem;border-radius:999px;background:color-mix(in srgb,var(--theme-primary) 10%,transparent);font-size:.68rem;color:var(--theme-primary);margin-top:.4rem}
    .onb-review{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}
    .onb-review-box{padding:1rem;border:1px solid var(--border);border-radius:14px}
    .onb-review-box h3{margin:0 0 .75rem;font-size:.95rem}
    .onb-review-row{display:flex;justify-content:space-between;gap:1rem;padding:.45rem 0;border-bottom:1px dashed var(--border);font-size:.82rem}
    .onb-review-row:last-child{border-bottom:0}
    .onb-warning{padding:.9rem 1rem;border:1px solid color-mix(in srgb,var(--theme-warning) 35%,var(--border));border-radius:12px;background:color-mix(in srgb,var(--theme-warning) 7%,transparent);line-height:1.8}
    @media(max-width:900px){.onb-profile-grid{grid-template-columns:repeat(2,1fr)}.onb-module-list{grid-template-columns:repeat(2,1fr)}}
    @media(max-width:680px){.onb-grid,.onb-review{grid-template-columns:1fr}.onb-full{grid-column:auto}.onb-profile-grid,.onb-module-list{grid-template-columns:1fr}.onb-progress{grid-template-columns:repeat(3,1fr)}}
</style>
