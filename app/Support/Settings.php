<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Business settings edited in Setup → Settings. Stored in the settings table and
 * applied on top of config/distributor.php (the .env values are the defaults).
 */
class Settings
{
    /** setting key => [config key, label, type, help] */
    public const FIELDS = [
        'company_name' => ['distributor.company.name', 'Company name', 'text', ''],
        'company_address' => ['distributor.company.address', 'Address', 'text', ''],
        'company_phone' => ['distributor.company.phone', 'Phone', 'text', ''],
        'company_email' => ['distributor.company.email', 'Email', 'text', ''],
        'company_tax_number' => ['distributor.company.tax_number', 'Tax / VAT number', 'text', ''],
        'currency' => ['distributor.currency', 'Currency symbol', 'text', 'e.g. Rs.'],
        'tax_rate' => ['distributor.tax_rate', 'Default tax %', 'number', 'Offered on new invoices, quotations and orders.'],
        'allow_negative_stock' => ['distributor.allow_negative_stock', 'Allow selling below zero stock', 'bool', ''],
        'stock_low_threshold' => ['distributor.stock_low_threshold', 'Stock colour: yellow at or below this quantity', 'money', 'Reps see red when out of stock, yellow up to this quantity, green above it.'],
        'enforce_credit_limit' => ['distributor.enforce_credit_limit', 'Block invoices over credit limit (managers can override)', 'bool', ''],
        'cheque_recognition' => ['distributor.cheque_recognition', 'When does a dealer cheque settle invoices?', 'select:on_clearance=Only when the bank clears it (recommended),on_verification=When the accountant verifies receipt', 'Received or deposited cheques never count as cleared.'],
        'payment_segregation' => ['distributor.payment_segregation', 'The person who enters a payment cannot verify it', 'bool', 'Segregation of duties for dealer payments.'],
        'payment_verify_limit' => ['distributor.payment_verify_limit', 'Payment verification limit (LKR)', 'money', 'Payments above this need a user with "verify above limit" (e.g. Finance Manager). 0 = no limit.'],
        'credit_note_approval_limit' => ['distributor.credit_note_approval_limit', 'Credit note approval limit (LKR)', 'money', 'Credit notes above this need "approve above limit". 0 = no limit.'],
        'credit_note_segregation' => ['distributor.credit_note_segregation', 'The person who requests a credit note cannot approve it', 'bool', ''],
        'max_upload_mb' => ['distributor.max_upload_mb', 'Maximum upload size for slips / documents (MB)', 'money', 'PDF, JPG, PNG only.'],
        'email_dealer_payment_notices' => ['distributor.notifications.dealer_payment_notices', 'Email dealers when a payment is verified or rejected, or a cheque is returned', 'bool', 'Sent to the dealer\'s email address, if it has one.'],
        'email_overdue_reminders' => ['distributor.notifications.overdue_reminders', 'Email dealers a weekly reminder of overdue invoices (Mondays)', 'bool', ''],
    ];

    public static function apply(): void
    {
        try {
            $values = Cache::rememberForever('settings', fn () => Schema::hasTable('settings') ? Setting::pluck('value', 'key')->all() : []);
        } catch (\Throwable) {
            return; // database not ready (e.g. during install)
        }

        foreach ($values as $key => $value) {
            if (isset(self::FIELDS[$key])) {
                $type = self::FIELDS[$key][2];
                config([self::FIELDS[$key][0] => match ($type) {
                    'bool' => (bool) $value,
                    'number', 'money' => (float) $value,
                    'days' => (int) $value,
                    default => $value,
                }]);
            }
        }
    }

    public static function save(array $values): void
    {
        foreach (self::FIELDS as $key => $field) {
            if (array_key_exists($key, $values)) {
                Setting::updateOrCreate(['key' => $key], ['value' => $values[$key]]);
            }
        }
        Cache::forget('settings');
    }

    /** Options of a 'select:value=Label,value=Label' field. */
    public static function options(string $type): array
    {
        return collect(explode(',', substr($type, 7)))->mapWithKeys(function ($pair) {
            [$k, $v] = explode('=', $pair, 2);

            return [$k => $v];
        })->all();
    }

    public static function current(): array
    {
        return collect(self::FIELDS)->map(fn ($f) => config($f[0]))->all();
    }
}
