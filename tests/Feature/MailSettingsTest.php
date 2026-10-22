<?php

namespace Tests\Feature;

use App\Models\EmailLog;
use App\Models\Setting;
use App\Support\MailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class MailSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function smtp(array $overrides = []): array
    {
        return $overrides + [
            'mail_mailer' => 'smtp', 'mail_host' => 'smtp.office365.com', 'mail_port' => 587, 'mail_encryption' => 'starttls',
            'mail_username' => 'system@company.lk', 'mail_password' => 'App-Pass-123', 'mail_from_address' => 'system@company.lk', 'mail_from_name' => 'Company',
        ];
    }

    public function test_admin_saves_smtp_settings_and_they_are_applied_with_the_password_encrypted(): void
    {
        $admin = $this->user('super_admin');
        $this->actingAs($admin)->get('/system/mail')->assertOk()->assertSee('Email settings (SMTP)');
        $this->actingAs($admin)->put('/system/mail', $this->smtp())->assertRedirect('/system/mail')->assertSessionHas('success');

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.office365.com', config('mail.mailers.smtp.host'));
        $this->assertSame(587, config('mail.mailers.smtp.port'));
        $this->assertSame('smtp', config('mail.mailers.smtp.scheme'));
        $this->assertSame('App-Pass-123', config('mail.mailers.smtp.password'));
        $this->assertSame('system@company.lk', config('mail.from.address'));

        // Stored encrypted, never shown back, and not written to the audit log.
        $stored = Setting::find('mail_password')->value;
        $this->assertNotSame('App-Pass-123', $stored);
        $this->assertSame('App-Pass-123', Crypt::decryptString($stored));
        $this->actingAs($admin)->get('/system/mail')->assertDontSee('App-Pass-123')->assertSee('saved – leave blank to keep');
        $this->assertDatabaseMissing('audit_logs', ['description' => 'App-Pass-123']);
        $this->assertStringNotContainsString('App-Pass-123', \App\Models\AuditLog::latest('id')->value('description'));

        // A blank password keeps the saved one; SSL switches the scheme.
        $this->actingAs($admin)->put('/system/mail', $this->smtp(['mail_password' => '', 'mail_encryption' => 'ssl', 'mail_port' => 465]));
        $this->assertSame('App-Pass-123', MailSettings::password());
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));

        // The saved password can be removed.
        $this->actingAs($admin)->put('/system/mail', $this->smtp(['mail_password' => '', 'clear_password' => 1]));
        $this->assertFalse(MailSettings::hasPassword());
    }

    public function test_connection_failure_is_reported_honestly(): void
    {
        $admin = $this->user('super_admin');
        $this->actingAs($admin)->put('/system/mail', $this->smtp(['mail_host' => '127.0.0.1', 'mail_port' => 1, 'mail_username' => '', 'mail_password' => '']));

        $this->actingAs($admin)->post('/system/mail/test')->assertSessionHas('error', fn ($m) => str_starts_with($m, 'Could not connect'));
        $this->actingAs($admin)->post('/system/test-email', ['to' => 'me@test.local'])->assertSessionHas('error');
        $this->assertSame('failed', EmailLog::latest('id')->value('status'));
    }

    public function test_log_only_mode_and_reset_to_env(): void
    {
        $admin = $this->user('super_admin');
        $this->actingAs($admin)->put('/system/mail', ['mail_mailer' => 'log', 'mail_from_address' => 'x@company.lk', 'mail_from_name' => 'X'])->assertSessionHas('success');
        $this->assertSame('log', config('mail.default'));
        $this->actingAs($admin)->post('/system/mail/test')->assertSessionHas('error');

        $this->actingAs($admin)->post('/system/mail/reset')->assertSessionHas('success');
        $this->assertSame(0, Setting::whereIn('key', MailSettings::KEYS)->count());
    }

    public function test_validation_and_access(): void
    {
        $admin = $this->user('super_admin');
        $this->actingAs($admin)->put('/system/mail', $this->smtp(['mail_host' => 'http://smtp.x.com']))->assertSessionHasErrors('mail_host');
        $this->actingAs($admin)->put('/system/mail', $this->smtp(['mail_host' => '']))->assertSessionHasErrors('mail_host');
        $this->actingAs($admin)->put('/system/mail', $this->smtp(['mail_from_address' => 'not-an-email']))->assertSessionHasErrors('mail_from_address');

        foreach (['general_manager', 'accountant', 'sales_rep'] as $role) {
            $user = $this->user($role);
            $this->actingAs($user)->get('/system/mail')->assertForbidden();
            $this->actingAs($user)->put('/system/mail', $this->smtp())->assertForbidden();
        }
    }
}
