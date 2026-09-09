@php
    $theme = [
        'primary'       => \App\Models\SystemSetting::get('theme_primary', '#0A2948'),
        'secondary'     => \App\Models\SystemSetting::get('theme_secondary', '#C98516'),
        'accent'        => \App\Models\SystemSetting::get('theme_accent', '#C98516'),
        'background'    => \App\Models\SystemSetting::get('theme_background', '#F5F6F8'),
        'surface'       => \App\Models\SystemSetting::get('theme_surface', '#FFFFFF'),
        'text'          => \App\Models\SystemSetting::get('theme_text', '#172435'),
        'textMuted'     => \App\Models\SystemSetting::get('theme_text_muted', '#687482'),
        'border'        => \App\Models\SystemSetting::get('theme_border', '#DDE2E7'),

        'sidebarBg'     => \App\Models\SystemSetting::get('theme_sidebar_bg', '#0A2948'),
        'sidebarText'   => \App\Models\SystemSetting::get('theme_sidebar_text', '#FFFFFF'),
        'sidebarActive' => \App\Models\SystemSetting::get('theme_sidebar_active', '#C98516'),

        /*
        |--------------------------------------------------------------------------
        | Sidebar user footer
        |--------------------------------------------------------------------------
        */
        'sidebarFooterBg'     => \App\Models\SystemSetting::get('theme_sidebar_footer_bg', '#FFFFFF'),
        'sidebarFooterText'   => \App\Models\SystemSetting::get('theme_sidebar_footer_text', '#172435'),
        'sidebarFooterMuted'  => \App\Models\SystemSetting::get('theme_sidebar_footer_muted', '#687482'),
        'sidebarFooterBorder' => \App\Models\SystemSetting::get('theme_sidebar_footer_border', '#DDE2E7'),
        'sidebarFooterIcon'   => \App\Models\SystemSetting::get('theme_sidebar_footer_icon', '#687482'),

        'headerBg'      => \App\Models\SystemSetting::get('theme_header_bg', '#FFFFFF'),

        'success'       => \App\Models\SystemSetting::get('theme_success', '#197438'),
        'warning'       => \App\Models\SystemSetting::get('theme_warning', '#C98516'),
        'danger'        => \App\Models\SystemSetting::get('theme_danger', '#E22929'),
        'info'          => \App\Models\SystemSetting::get('theme_info', '#2F72C4'),

        'font'          => \App\Models\SystemSetting::get('theme_font_family', 'Cairo'),
        'radius'        => (int) \App\Models\SystemSetting::get('theme_radius', 10),
    ];
@endphp

<style id="dynamic-system-theme">
    :root {
        --theme-primary: {{ $theme['primary'] }};
        --theme-secondary: {{ $theme['secondary'] }};
        --theme-accent: {{ $theme['accent'] }};

        --theme-bg: {{ $theme['background'] }};
        --theme-surface: {{ $theme['surface'] }};
        --theme-text: {{ $theme['text'] }};
        --theme-text-muted: {{ $theme['textMuted'] }};
        --theme-border: {{ $theme['border'] }};

        --theme-sidebar-bg: {{ $theme['sidebarBg'] }};
        --theme-sidebar-text: {{ $theme['sidebarText'] }};
        --theme-sidebar-active: {{ $theme['sidebarActive'] }};

        --theme-sidebar-footer-bg: {{ $theme['sidebarFooterBg'] }};
        --theme-sidebar-footer-text: {{ $theme['sidebarFooterText'] }};
        --theme-sidebar-footer-muted: {{ $theme['sidebarFooterMuted'] }};
        --theme-sidebar-footer-border: {{ $theme['sidebarFooterBorder'] }};
        --theme-sidebar-footer-icon: {{ $theme['sidebarFooterIcon'] }};

        --theme-header-bg: {{ $theme['headerBg'] }};

        --theme-success: {{ $theme['success'] }};
        --theme-warning: {{ $theme['warning'] }};
        --theme-danger: {{ $theme['danger'] }};
        --theme-info: {{ $theme['info'] }};

        --theme-radius: {{ $theme['radius'] }}px;
        --theme-font: '{{ $theme['font'] }}', 'Cairo', Arial, sans-serif;

        /*
        |--------------------------------------------------------------------------
        | Aliases used by the current app.css
        |--------------------------------------------------------------------------
        */
        --navy: var(--theme-primary);
        --gold: var(--theme-accent);

        --bg: var(--theme-bg);
        --surface: var(--theme-surface);
        --surface-2: var(--theme-surface);
        --card-bg: var(--theme-surface);

        --text: var(--theme-text);
        --text-main: var(--theme-text);
        --text-muted: var(--theme-text-muted);

        --border: var(--theme-border);

        --success: var(--theme-success);
        --warning: var(--theme-warning);
        --error: var(--theme-danger);
        --danger: var(--theme-danger);
        --info: var(--theme-info);
    }


    /*
    |--------------------------------------------------------------------------
    | Global typography
    |--------------------------------------------------------------------------
    */

    html,
    body,
    button,
    input,
    select,
    textarea {
        font-family: var(--theme-font) !important;
    }


    /*
    |--------------------------------------------------------------------------
    | Main page
    |--------------------------------------------------------------------------
    */

    body,
    .dahab-body,
    .dahab-main,
    .dahab-content {
        background-color: var(--theme-bg);
        color: var(--theme-text);
    }


    /*
    |--------------------------------------------------------------------------
    | Sidebar
    |--------------------------------------------------------------------------
    */

    .dahab-sidebar {
        background: var(--theme-sidebar-bg) !important;
        color: var(--theme-sidebar-text);
    }

    .dahab-sidebar .nav-item {
        color: var(--theme-sidebar-text);
    }

    .dahab-sidebar .nav-section-title {
        color: color-mix(
            in srgb,
            var(--theme-sidebar-text) 52%,
            transparent
        );
    }

    .dahab-sidebar .nav-item:hover {
        background: color-mix(
            in srgb,
            var(--theme-sidebar-text) 8%,
            transparent
        );
    }

    .dahab-sidebar .nav-item.active {
        color: var(--theme-sidebar-active) !important;
        background: color-mix(
            in srgb,
            var(--theme-sidebar-active) 13%,
            transparent
        ) !important;
        border-color: color-mix(
            in srgb,
            var(--theme-sidebar-active) 28%,
            transparent
        ) !important;
    }

    .dahab-sidebar .nav-item.active::before {
        background: var(--theme-sidebar-active) !important;
    }

    .dahab-sidebar .nav-item:focus-visible {
        outline-color: var(--theme-sidebar-active);
    }


    /*
    |--------------------------------------------------------------------------
    | Sidebar footer / user account area
    |--------------------------------------------------------------------------
    */

    .dahab-sidebar .sidebar-footer,
    .dahab-sidebar .sidebar-footer .user-info {
        background: var(--theme-sidebar-footer-bg) !important;
    }

    .dahab-sidebar .sidebar-footer {
        color: var(--theme-sidebar-footer-text) !important;
        border-top: 1px solid var(--theme-sidebar-footer-border) !important;
    }

    .dahab-sidebar .sidebar-footer .user-name {
        color: var(--theme-sidebar-footer-text) !important;
    }

    .dahab-sidebar .sidebar-footer .user-role {
        color: var(--theme-sidebar-footer-muted) !important;
    }

    .dahab-sidebar .sidebar-footer .logout-form {
        background: transparent !important;
    }

    .dahab-sidebar .sidebar-footer .logout-btn {
        color: var(--theme-sidebar-footer-icon) !important;
        background: transparent !important;
        border-color: transparent !important;
    }

    .dahab-sidebar .sidebar-footer .logout-btn:hover {
        color: var(--theme-danger) !important;
        background: color-mix(
            in srgb,
            var(--theme-danger) 9%,
            transparent
        ) !important;
    }

    .dahab-sidebar .sidebar-footer .user-avatar-sm {
        border-color: color-mix(
            in srgb,
            var(--theme-sidebar-footer-border) 80%,
            transparent
        ) !important;
    }


    /*
    |--------------------------------------------------------------------------
    | Topbar
    |--------------------------------------------------------------------------
    */

    .dahab-topbar {
        background: var(--theme-header-bg) !important;
        color: var(--theme-text);
        border-color: var(--theme-border) !important;
    }

    .topbar-title {
        color: var(--theme-text) !important;
    }

    .sidebar-toggle,
    .notif-btn,
    .user-menu-btn {
        color: var(--theme-text) !important;
    }

    .sidebar-toggle:hover,
    .notif-btn:hover,
    .user-menu-btn:hover {
        background: color-mix(
            in srgb,
            var(--theme-accent) 10%,
            transparent
        ) !important;
        color: var(--theme-accent) !important;
    }

    .notif-badge {
        background: var(--theme-danger) !important;
        color: #FFFFFF !important;
    }

    .notif-dropdown,
    .user-dropdown {
        background: var(--theme-surface) !important;
        border-color: var(--theme-border) !important;
        color: var(--theme-text) !important;
    }

    .notif-header,
    .notif-footer,
    .dropdown-divider {
        border-color: var(--theme-border) !important;
    }

    .notif-mark-all,
    .notif-footer a {
        color: var(--theme-accent) !important;
    }

    .notif-item:hover,
    .dropdown-item:hover {
        background: color-mix(
            in srgb,
            var(--theme-accent) 7%,
            transparent
        ) !important;
    }

    .dropdown-item {
        color: var(--theme-text) !important;
    }

    .dropdown-item-danger {
        color: var(--theme-danger) !important;
    }


    /*
    |--------------------------------------------------------------------------
    | Cards / Modals / Dropdowns
    |--------------------------------------------------------------------------
    */

    .card,
    .dropdown-menu,
    .modal-box,
    .modal-content {
        background: var(--theme-surface);
        border-color: var(--theme-border);
        border-radius: var(--theme-radius);
        color: var(--theme-text);
    }


    /*
    |--------------------------------------------------------------------------
    | Forms
    |--------------------------------------------------------------------------
    */

    .form-input,
    .form-textarea,
    select.form-input {
        background: var(--theme-surface);
        color: var(--theme-text);
        border-color: var(--theme-border);
        border-radius: var(--theme-radius);
    }

    .form-input:focus,
    .form-textarea:focus,
    select.form-input:focus {
        border-color: var(--theme-accent);
        box-shadow: 0 0 0 3px color-mix(
            in srgb,
            var(--theme-accent) 16%,
            transparent
        );
    }

    input[type="radio"],
    input[type="checkbox"] {
        accent-color: var(--theme-accent);
    }


    /*
    |--------------------------------------------------------------------------
    | Buttons
    |--------------------------------------------------------------------------
    */

    .btn-gold,
    .btn-primary {
        background: var(--theme-accent) !important;
        border-color: var(--theme-accent) !important;
        color: #FFFFFF !important;
    }

    .btn-outline {
        border-color: var(--theme-accent) !important;
        color: var(--theme-accent) !important;
    }

    .btn-outline:hover {
        background: color-mix(
            in srgb,
            var(--theme-accent) 9%,
            transparent
        ) !important;
    }

    .btn-danger {
        background: var(--theme-danger) !important;
        border-color: var(--theme-danger) !important;
    }


    /*
    |--------------------------------------------------------------------------
    | Text
    |--------------------------------------------------------------------------
    */

    .page-heading,
    .card-title,
    .topbar-title,
    .stat-value {
        color: var(--theme-text);
    }

    .page-subheading,
    .form-hint,
    .stat-label {
        color: var(--theme-text-muted);
    }


    /*
    |--------------------------------------------------------------------------
    | Tables
    |--------------------------------------------------------------------------
    */

    .data-table,
    .data-table th,
    .data-table td {
        border-color: var(--theme-border);
    }

    .data-table thead th {
        background: var(--theme-primary);
        color: #FFFFFF;
    }


    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */

    .app-page-btn {
        background: var(--theme-surface);
        color: var(--theme-text);
        border-color: var(--theme-border);
    }

    .app-page-btn.active {
        background: var(--theme-accent);
        border-color: var(--theme-accent);
        color: #FFFFFF;
    }

    a.app-page-btn:hover {
        color: var(--theme-accent);
        border-color: var(--theme-accent);
        background: color-mix(
            in srgb,
            var(--theme-accent) 9%,
            transparent
        );
    }
</style>