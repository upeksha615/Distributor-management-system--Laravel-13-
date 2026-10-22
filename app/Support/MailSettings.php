<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * Outgoing email (SMTP) connection entered in Setup → Email settings.
 * Stored in the settings table (password encrypted with APP_KEY) and applied over the MAIL_* values in .env.
 * When nothing has been saved here, the .env values are used unchanged.
 */
class MailSettings
{
    public const KEYS = ['mail_mailer', 'mail_host', 'mail_port', 'mail_encryption', 'mail_username', 'mail_password', 'mail_from_address', 'mail_from_name'];

    /** encryption option => [label, Symfony mailer scheme] */
    public const ENCRYPTION = [
        'starttls' => ['STARTTLS (usually port 587)', 'smtp'],
        'ssl' => ['SSL/TLS (usually port 465)', 'smtps'],
    ];

    public static function stored(): array
    {
        try {
            return Cache::rememberForever('mail_settings', fn () => Schema::hasTable('settings')
                ? Setting::whereIn('key', self::KEYS)->pluck('value', 'key')->all() : []);
        } catch (\Throwable) {
            return [];
        }
    }

    public static function apply(): void
    {
        $s = self::stored();
        if (empty($s['mail_mailer'])) {
            return; // nothing saved: keep .env
        }

        $config = ['mail.default' => $s['mail_mailer']];
        if ($s['mail_mailer'] === 'smtp') {
            $config += [
                'mail.mailers.smtp.host' => $s['mail_host'] ?? null,
                'mail.mailers.smtp.port' => (int) ($s['mail_port'] ?? 587),
                'mail.mailers.smtp.scheme' => self::ENCRYPTION[$s['mail_encryption'] ?? 'starttls'][1] ?? 'smtp',
                'mail.mailers.smtp.username' => $s['mail_username'] ?? null,
                'mail.mailers.smtp.password' => self::password(),
                'mail.mailers.smtp.timeout' => 15,
                'mail.mailers.smtp.url' => null,
            ];
        }
        if (! empty($s['mail_from_address'])) {
            $config['mail.from.address'] = $s['mail_from_address'];
        }
        if (! empty($s['mail_from_name'])) {
            $config['mail.from.name'] = $s['mail_from_name'];
        }
        config($config);
    }

    /** Decrypted password, or null (also null if APP_KEY changed and it can no longer be read). */
    public static function password(): ?string
    {
        $encrypted = self::stored()['mail_password'] ?? null;
        if (! $encrypted) {
            return null;
        }
        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function hasPassword(): bool
    {
        return ! empty(self::stored()['mail_password']);
    }

    /** Save the form. A blank password keeps the saved one. */
    public static function save(array $data, bool $clearPassword = false): void
    {
        foreach (self::KEYS as $key) {
            if ($key === 'mail_password') {
                continue;
            }
            Setting::updateOrCreate(['key' => $key], ['value' => $data[$key] ?? null]);
        }
        if ($clearPassword) {
            Setting::where('key', 'mail_password')->delete();
        } elseif (! empty($data['mail_password'])) {
            Setting::updateOrCreate(['key' => 'mail_password'], ['value' => Crypt::encryptString($data['mail_password'])]);
        }
        self::refresh();
    }

    /** Forget saved settings: .env is used again. */
    public static function reset(): void
    {
        Setting::whereIn('key', self::KEYS)->delete();
        self::refresh();
    }

    /** Re-read and re-apply in this request, and drop the already-built mailer so the new connection is used. */
    public static function refresh(): void
    {
        Cache::forget('mail_settings');
        self::apply();
        Mail::purge('smtp');
        Mail::purge(config('mail.default'));
    }

    /** Values for the form (never the password). */
    public static function form(): array
    {
        $s = self::stored();

        return [
            'mail_mailer' => $s['mail_mailer'] ?? (config('mail.default') === 'smtp' ? 'smtp' : 'log'),
            'mail_host' => $s['mail_host'] ?? config('mail.mailers.smtp.host'),
            'mail_port' => $s['mail_port'] ?? config('mail.mailers.smtp.port', 587),
            'mail_encryption' => $s['mail_encryption'] ?? (config('mail.mailers.smtp.scheme') === 'smtps' ? 'ssl' : 'starttls'),
            'mail_username' => $s['mail_username'] ?? config('mail.mailers.smtp.username'),
            'mail_from_address' => $s['mail_from_address'] ?? config('mail.from.address'),
            'mail_from_name' => $s['mail_from_name'] ?? config('mail.from.name'),
            'source' => empty($s['mail_mailer']) ? 'env' : 'system',
        ];
    }

    /**
     * Connect and log in to the SMTP server without sending anything.
     *
     * @return array{0: bool, 1: string}
     */
    public static function testConnection(): array
    {
        if (config('mail.default') !== 'smtp') {
            return [false, 'The mailer is not SMTP – choose "SMTP server" and save first.'];
        }
        try {
            Mail::purge('smtp');
            $transport = Mail::mailer('smtp')->getSymfonyTransport();
            if (method_exists($transport, 'start')) {
                $transport->start();
                $transport->stop();
            }

            return [true, 'Connected and logged in to '.config('mail.mailers.smtp.host').':'.config('mail.mailers.smtp.port').' successfully.'];
        } catch (\Throwable $e) {
            return [false, 'Could not connect: '.\Illuminate\Support\Str::limit($e->getMessage(), 300)];
        }
    }
}
