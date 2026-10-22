<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Support\MailSettings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Setup → Email settings: the SMTP connection (Super Admin: system.manage). */
class MailSettingsController extends Controller
{
    public function edit()
    {
        return view('system.mail', [
            'values' => MailSettings::form(),
            'hasPassword' => MailSettings::hasPassword(),
        ]);
    }

    public function update(Request $request)
    {
        $smtp = $request->input('mail_mailer') === 'smtp';
        $data = $request->validate([
            'mail_mailer' => ['required', Rule::in(['smtp', 'log'])],
            'mail_host' => [Rule::requiredIf($smtp), 'nullable', 'max:191', 'regex:/^[A-Za-z0-9.\-]+$/'],
            'mail_port' => [Rule::requiredIf($smtp), 'nullable', 'integer', 'between:1,65535'],
            'mail_encryption' => [Rule::requiredIf($smtp), 'nullable', Rule::in(array_keys(MailSettings::ENCRYPTION))],
            'mail_username' => ['nullable', 'max:191'],
            'mail_password' => ['nullable', 'max:255'],
            'mail_from_address' => ['required', 'email', 'max:191'],
            'mail_from_name' => ['required', 'max:100'],
        ], ['mail_host.regex' => 'Enter a host name only, e.g. smtp.office365.com (no http:// or spaces).']);

        MailSettings::save($data, $request->boolean('clear_password'));
        AuditLog::record('settings.mail', null, 'Email settings changed: '.($smtp ? "SMTP {$data['mail_host']}:{$data['mail_port']} as {$data['mail_username']}" : 'log only (no delivery)')
            .($request->filled('mail_password') ? ', password changed' : '').'.');

        return redirect()->route('system.mail')->with('success', 'Email settings saved. Use "Test connection" and "Send test email" to check them.');
    }

    public function test()
    {
        [$ok, $message] = MailSettings::testConnection();

        return back()->with($ok ? 'success' : 'error', $message);
    }

    public function reset()
    {
        MailSettings::reset();
        AuditLog::record('settings.mail', null, 'Email settings cleared – using the .env values.');

        return redirect()->route('system.mail')->with('success', 'Saved email settings removed. The values from .env are used again.');
    }
}
