{{-- DropPilot custom theme: modern SaaS look (Stripe / Linear / Vercel feel) --}}
<style>
    :root {
        --dp-bg: #f8fafc;
        --dp-card-bg: #ffffff;
        --dp-border: rgba(15, 23, 42, 0.06);
        --dp-shadow-sm: 0 1px 2px 0 rgba(15, 23, 42, 0.04), 0 1px 3px 0 rgba(15, 23, 42, 0.06);
        --dp-shadow-md: 0 4px 6px -1px rgba(15, 23, 42, 0.05), 0 2px 4px -2px rgba(15, 23, 42, 0.04);
        --dp-shadow-lg: 0 10px 25px -5px rgba(15, 23, 42, 0.08), 0 8px 10px -6px rgba(15, 23, 42, 0.06);
    }

    /* Sayfa zemini — sade off-white */
    .fi-body, .fi-main {
        background:
            radial-gradient(1200px 600px at 100% -10%, rgba(16, 185, 129, 0.06), transparent 60%),
            radial-gradient(800px 400px at 0% 0%, rgba(20, 184, 166, 0.04), transparent 50%),
            var(--dp-bg) !important;
    }

    /* Sidebar — temiz beyaz + soft accent */
    .fi-sidebar {
        background: #ffffff !important;
        border-right: 1px solid var(--dp-border) !important;
        box-shadow: 0 0 30px rgba(15, 23, 42, 0.02);
    }

    .fi-sidebar-nav-groups .fi-sidebar-group-label {
        font-size: 0.7rem !important;
        font-weight: 600 !important;
        letter-spacing: 0.08em !important;
        text-transform: uppercase !important;
        color: #94a3b8 !important;
        padding-top: 1rem;
    }

    /* Sidebar item hover */
    .fi-sidebar-item-button {
        border-radius: 10px !important;
        transition: all 0.15s ease !important;
        font-weight: 500;
    }
    .fi-sidebar-item-button:hover {
        background: rgba(16, 185, 129, 0.06) !important;
    }
    .fi-sidebar-item.fi-active .fi-sidebar-item-button {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.12), rgba(20, 184, 166, 0.08)) !important;
        color: #047857 !important;
        font-weight: 600;
    }

    /* Topbar — glassmorphism */
    .fi-topbar {
        background: rgba(255, 255, 255, 0.7) !important;
        backdrop-filter: blur(16px) saturate(180%) !important;
        -webkit-backdrop-filter: blur(16px) saturate(180%) !important;
        border-bottom: 1px solid var(--dp-border) !important;
        box-shadow: 0 1px 0 rgba(15, 23, 42, 0.02);
    }

    /* Card / section — soft shadow + smooth radius */
    .fi-section {
        background: var(--dp-card-bg) !important;
        border-radius: 14px !important;
        border: 1px solid var(--dp-border) !important;
        box-shadow: var(--dp-shadow-sm) !important;
        transition: box-shadow 0.2s ease;
    }
    .fi-section:hover {
        box-shadow: var(--dp-shadow-md) !important;
    }

    /* ===== Custom Stat Grid ===== */
    .dp-stats-grid {
        display: grid;
        grid-template-columns: repeat(1, 1fr);
        gap: 1rem;
    }
    @media (min-width: 640px) { .dp-stats-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (min-width: 1024px) { .dp-stats-grid { grid-template-columns: repeat(4, 1fr); } }

    .dp-stat {
        position: relative;
        background: #ffffff;
        border: 1px solid var(--dp-border);
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: var(--dp-shadow-sm);
        transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
        overflow: hidden;
    }
    .dp-stat::after {
        content: '';
        position: absolute;
        inset: 0;
        border-radius: inherit;
        opacity: 0;
        transition: opacity 0.25s ease;
        background: radial-gradient(120% 80% at 100% 0%, var(--dp-stat-glow), transparent 70%);
        pointer-events: none;
    }
    .dp-stat:hover {
        transform: translateY(-2px);
        box-shadow: var(--dp-shadow-lg);
        border-color: var(--dp-stat-border);
    }
    .dp-stat:hover::after { opacity: 1; }

    .dp-stat-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1rem;
        position: relative;
        z-index: 1;
    }
    .dp-stat-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2.5rem;
        height: 2.5rem;
        border-radius: 0.75rem;
        background: var(--dp-stat-icon-bg);
        color: var(--dp-stat-icon-fg);
        box-shadow: var(--dp-stat-icon-shadow);
    }
    .dp-stat-trend {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.25rem 0.5rem;
        border-radius: 9999px;
        background: rgba(16, 185, 129, 0.1);
        color: #047857;
    }
    .dp-stat-body { position: relative; z-index: 1; }
    .dp-stat-label {
        font-size: 0.825rem;
        font-weight: 500;
        color: #64748b;
        margin-bottom: 0.35rem;
    }
    .dp-stat-value {
        font-size: 2.25rem;
        font-weight: 700;
        line-height: 1;
        letter-spacing: -0.03em;
        color: #0f172a;
        margin-bottom: 0.4rem;
    }
    .dp-stat-desc {
        font-size: 0.78rem;
        color: #94a3b8;
    }

    /* Tone variations */
    .dp-stat-emerald {
        --dp-stat-icon-bg: linear-gradient(135deg, #10b981, #059669);
        --dp-stat-icon-fg: #ffffff;
        --dp-stat-icon-shadow: 0 6px 14px -3px rgba(16, 185, 129, 0.45);
        --dp-stat-glow: rgba(16, 185, 129, 0.08);
        --dp-stat-border: rgba(16, 185, 129, 0.35);
    }
    .dp-stat-sky {
        --dp-stat-icon-bg: linear-gradient(135deg, #0ea5e9, #0284c7);
        --dp-stat-icon-fg: #ffffff;
        --dp-stat-icon-shadow: 0 6px 14px -3px rgba(14, 165, 233, 0.45);
        --dp-stat-glow: rgba(14, 165, 233, 0.08);
        --dp-stat-border: rgba(14, 165, 233, 0.35);
    }
    .dp-stat-amber {
        --dp-stat-icon-bg: linear-gradient(135deg, #f59e0b, #d97706);
        --dp-stat-icon-fg: #ffffff;
        --dp-stat-icon-shadow: 0 6px 14px -3px rgba(245, 158, 11, 0.45);
        --dp-stat-glow: rgba(245, 158, 11, 0.08);
        --dp-stat-border: rgba(245, 158, 11, 0.35);
    }
    .dp-stat-violet {
        --dp-stat-icon-bg: linear-gradient(135deg, #8b5cf6, #7c3aed);
        --dp-stat-icon-fg: #ffffff;
        --dp-stat-icon-shadow: 0 6px 14px -3px rgba(139, 92, 246, 0.45);
        --dp-stat-glow: rgba(139, 92, 246, 0.08);
        --dp-stat-border: rgba(139, 92, 246, 0.35);
    }

    /* Sayfa başlık — daha büyük + modern */
    .fi-header-heading {
        font-size: 1.875rem !important;
        font-weight: 700 !important;
        letter-spacing: -0.025em !important;
        color: #0f172a !important;
    }

    /* Tablo */
    .fi-ta-table {
        border-radius: 12px !important;
    }
    .fi-ta-header-cell {
        background: #f8fafc !important;
        font-size: 0.75rem !important;
        font-weight: 600 !important;
        letter-spacing: 0.05em !important;
        text-transform: uppercase !important;
        color: #64748b !important;
    }
    .fi-ta-row:hover {
        background: rgba(16, 185, 129, 0.03) !important;
    }

    /* Butonlar — gradient primary */
    .fi-btn-color-primary {
        background: linear-gradient(135deg, #10b981, #059669) !important;
        box-shadow: 0 4px 14px 0 rgba(16, 185, 129, 0.25) !important;
        border: none !important;
        font-weight: 600;
        transition: all 0.2s ease;
    }
    .fi-btn-color-primary:hover {
        box-shadow: 0 6px 20px 0 rgba(16, 185, 129, 0.35) !important;
        transform: translateY(-1px);
    }

    /* Input focus ring — emerald */
    .fi-input:focus,
    .fi-select-input:focus,
    .fi-textarea:focus {
        --tw-ring-color: rgba(16, 185, 129, 0.4) !important;
        border-color: rgb(16, 185, 129) !important;
    }

    /* Badge'ler — daha yumuşak */
    .fi-badge {
        font-weight: 600 !important;
        letter-spacing: 0.01em;
    }

    /* Global font smoothing */
    body {
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
        font-feature-settings: 'cv11', 'ss01';
    }

    /* ===== Hero Widget ===== */
    .dp-hero {
        position: relative;
        overflow: hidden;
        border-radius: 1rem;
        padding: 2rem;
        color: #ffffff;
        background: linear-gradient(135deg, #10b981 0%, #14b8a6 50%, #0891b2 100%);
        box-shadow:
            0 20px 25px -5px rgba(16, 185, 129, 0.25),
            0 10px 10px -5px rgba(16, 185, 129, 0.1),
            inset 0 1px 0 rgba(255, 255, 255, 0.15);
    }
    .dp-hero-blob {
        position: absolute;
        border-radius: 9999px;
        filter: blur(48px);
        pointer-events: none;
    }
    .dp-hero-blob-1 {
        top: -3rem;
        right: -3rem;
        height: 12rem;
        width: 12rem;
        background: rgba(255, 255, 255, 0.18);
    }
    .dp-hero-blob-2 {
        bottom: -4rem;
        left: -2rem;
        height: 14rem;
        width: 14rem;
        background: rgba(165, 243, 252, 0.25);
    }
    .dp-hero-grid {
        position: absolute;
        inset: 0;
        opacity: 0.07;
        background-image:
            linear-gradient(rgba(255,255,255,.7) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255,255,255,.7) 1px, transparent 1px);
        background-size: 32px 32px;
        pointer-events: none;
    }
    .dp-hero-content {
        position: relative;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1.5rem;
    }
    .dp-hero-text {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }
    .dp-hero-eyebrow {
        font-size: 0.875rem;
        font-weight: 500;
        letter-spacing: 0.025em;
        color: rgba(236, 253, 245, 0.95);
        margin: 0;
    }
    .dp-hero-title {
        font-size: 1.875rem;
        font-weight: 700;
        letter-spacing: -0.025em;
        line-height: 1.15;
        color: #ffffff;
        margin: 0;
    }
    .dp-hero-subtitle {
        max-width: 42rem;
        font-size: 0.9rem;
        line-height: 1.5;
        color: rgba(236, 253, 245, 0.9);
        margin: 0;
    }
    .dp-hero-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        margin-top: 0.5rem;
    }
    .dp-hero-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.5rem 0.85rem;
        border-radius: 0.625rem;
        font-size: 0.78rem;
        font-weight: 600;
        color: #ffffff !important;
        background: rgba(255, 255, 255, 0.18);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.12);
        text-decoration: none;
        transition: all 0.2s ease;
    }
    .dp-hero-btn:hover {
        background: rgba(255, 255, 255, 0.28);
        transform: translateY(-1px);
    }
    .dp-hero-icon {
        display: none;
        flex-shrink: 0;
        align-items: center;
        justify-content: center;
        height: 5rem;
        width: 5rem;
        border-radius: 1rem;
        background: rgba(255, 255, 255, 0.18);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: #ffffff;
    }
    @media (min-width: 640px) {
        .dp-hero-icon { display: flex; }
    }
</style>
