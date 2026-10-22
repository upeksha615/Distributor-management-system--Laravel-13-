<?php

return [
    'company' => [
        'name' => env('COMPANY_NAME', 'My Distributor (Pvt) Ltd'),
        'address' => env('COMPANY_ADDRESS', ''),
        'phone' => env('COMPANY_PHONE', ''),
        'email' => env('COMPANY_EMAIL', ''),
        'tax_number' => env('COMPANY_TAX_NUMBER', ''),
    ],

    'currency' => env('CURRENCY_SYMBOL', 'Rs.'),

    // Default tax % offered on new sales orders and invoices.
    'tax_rate' => (float) env('DEFAULT_TAX_RATE', 0),

    // Rep stock colours: red = 0, yellow = 1..threshold, green = above.
    'stock_low_threshold' => (float) env('STOCK_LOW_THRESHOLD', 10),

    // When false, an invoice cannot take a warehouse below zero stock.
    'allow_negative_stock' => (bool) env('ALLOW_NEGATIVE_STOCK', false),

    // When true, invoices that push a customer over their credit limit are blocked (managers can still override).
    'enforce_credit_limit' => (bool) env('ENFORCE_CREDIT_LIMIT', true),

    // Dealer cheques settle invoices: on_clearance (bank has cleared it) or on_verification (accountant verified receipt).
    'cheque_recognition' => env('CHEQUE_RECOGNITION', 'on_clearance'),

    // Segregation of duties and approval limits (LKR, 0 = no limit).
    'payment_segregation' => (bool) env('PAYMENT_SEGREGATION', true),
    'payment_verify_limit' => (float) env('PAYMENT_VERIFY_LIMIT', 500000),
    'credit_note_approval_limit' => (float) env('CREDIT_NOTE_APPROVAL_LIMIT', 100000),
    'credit_note_segregation' => (bool) env('CREDIT_NOTE_SEGREGATION', true),

    // Payment slips and supporting documents (stored privately, never in public/).
    'max_upload_mb' => (float) env('MAX_UPLOAD_MB', 5),
    'upload_mimes' => ['pdf', 'jpg', 'jpeg', 'png'],

    // Emails to dealers (sent through the mailer configured by MAIL_* in .env).
    'notifications' => [
        'dealer_payment_notices' => (bool) env('EMAIL_DEALER_PAYMENT_NOTICES', true), // payment verified / rejected, cheque returned
        'overdue_reminders' => (bool) env('EMAIL_OVERDUE_REMINDERS', false), // weekly reminder of overdue invoices
        'daily_summary_time' => env('DAILY_SUMMARY_TIME', '07:30'),
        'overdue_reminder_time' => env('OVERDUE_REMINDER_TIME', '08:30'), // Mondays
    ],
];
