<?php
/**
 * YBT Management Software — Safe Defaults
 * Theme, typography, invoice, dashboard, and permission defaults.
 */

return [
    // Theme defaults
    'theme' => [
        'mode'              => 'light', // light | dark | system
        'primary_color'     => '#0EA5E9',
        'secondary_color'   => '#075985',
        'accent_color'      => '#0284C7',
        'bg_color'          => '#F8FAFC',
        'sidebar_bg'        => '#FFFFFF',
        'card_bg'           => '#FFFFFF',
        'text_color'        => '#1E293B',
        'text_muted'        => '#64748B',
        'border_color'      => '#E2E8F0',
        'success_color'     => '#16A34A',
        'warning_color'     => '#EAB308',
        'error_color'       => '#DC2626',
        'info_color'        => '#0EA5E9',
        'dark_surface'      => '#111827',
        'dark_card'         => '#1F2937',
        'dark_border'       => '#374151',
        'dark_text'         => '#F1F5F9',
        'dark_text_muted'   => '#94A3B8',
    ],

    // Typography
    'typography' => [
        'font_family'   => "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif",
        'base_size'     => '14px',
        'heading_scale' => 1.25,
        'line_height'   => 1.6,
        'preset'        => 'default', // compact | small | default | large | extra-large
    ],

    // Layout
    'layout' => [
        'sidebar_width'         => '260px',
        'sidebar_compact_width' => '72px',
        'content_max_width'     => '1400px',
        'card_spacing'          => '24px',
        'border_radius'         => '8px',
        'density'               => 'default', // compact | default | comfortable
    ],

    // Invoice defaults
    'invoice' => [
        'prefix'           => 'INV-',
        'next_number'      => 1,
        'due_days'         => 30,
        'currency'         => 'INR',
        'currency_symbol'  => '₹',
        'decimal_places'   => 2,
        'tax_enabled'      => true,
        'default_tax_rate' => 18.00,
        'show_logo'        => true,
        'show_signature'   => false,
        'amount_in_words'  => true,
        'default_notes'    => 'Thank you for your business.',
        'default_terms'    => 'Payment due within the specified period.',
    ],

    // Quotation defaults
    'quotation' => [
        'prefix'        => 'QT-',
        'next_number'   => 1,
        'validity_days' => 15,
    ],

    // Dashboard
    'dashboard' => [
        'default_period'   => 'this_month',
        'visible_widgets'  => [
            'kpi_financial', 'kpi_customer', 'kpi_sales', 'kpi_activity',
            'chart_revenue_trend', 'chart_invoice_status', 'chart_aging',
            'table_recent_invoices', 'table_recent_payments', 'table_overdue',
        ],
        'columns' => 3,
    ],

    // Pagination
    'pagination' => [
        'per_page'    => 25,
        'max_per_page' => 100,
    ],

    // Regional
    'regional' => [
        'country'          => 'India',
        'timezone'         => 'Asia/Kolkata',
        'currency'         => 'INR',
        'currency_symbol'  => '₹',
        'decimal_separator'  => '.',
        'thousand_separator' => ',',
        'date_format'      => 'd M Y',
        'time_format'      => 'h:i A',
        'datetime_format'  => 'd M Y h:i A',
        'week_start'       => 1, // Monday
        'number_system'    => 'indian', // indian | international
    ],

    // Default permissions for Manager role
    'default_manager_permissions' => [
        'dashboard.view'       => true,
        'customers.view'       => true,
        'customers.create'     => true,
        'customers.edit'       => true,
        'leads.view'           => true,
        'leads.create'         => true,
        'leads.edit'           => true,
        'products.view'        => true,
        'tasks.view'           => true,
        'tasks.create'         => true,
        'quotations.view'      => true,
        'invoices.view'        => true,
        'payments.view'        => true,
        'expenses.view'        => true,
        'reports.view'         => false,
        'ai.access'            => false,
        'settings.access'      => false,
        'managers.manage'      => false,
        'backup.create'        => false,
    ],
];
