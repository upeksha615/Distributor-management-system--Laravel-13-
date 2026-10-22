<x-layouts.app title="Email settings">
    <x-page-header title="Email settings (SMTP)" subtitle="The mail server the system uses to send password resets, invoices, dealer notices and summaries.">
        <a href="{{ route('system.emails') }}" class="btn btn-secondary">Email log</a>
    </x-page-header>

    <div class="grid gap-6 xl:grid-cols-3">
        <form method="POST" action="{{ route('system.mail.update') }}" class="card card-body space-y-5 xl:col-span-2"
              x-data="{ mailer: @js(old('mail_mailer', $values['mail_mailer'])), enc: @js(old('mail_encryption', $values['mail_encryption'])), port: @js((string) old('mail_port', $values['mail_port'])) }">
            @csrf @method('PUT')

            @if ($values['source'] === 'env')
                <div class="rounded-md border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-800">
                    Nothing is saved here yet, so the values below come from the <span class="font-mono">.env</span> file. Saving this form makes the system use these settings instead.
                </div>
            @endif

            <div>
                <span class="label">How should email be sent?</span>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="flex cursor-pointer gap-3 rounded-lg border p-3" :class="mailer === 'smtp' ? 'border-indigo-400 bg-indigo-50' : 'border-slate-200'">
                        <input type="radio" name="mail_mailer" value="smtp" x-model="mailer" class="mt-1">
                        <span><span class="block font-medium text-slate-800">SMTP server</span><span class="text-xs text-slate-500">Emails are delivered (Google Workspace, Microsoft 365, hosting mail…)</span></span>
                    </label>
                    <label class="flex cursor-pointer gap-3 rounded-lg border p-3" :class="mailer === 'log' ? 'border-amber-400 bg-amber-50' : 'border-slate-200'">
                        <input type="radio" name="mail_mailer" value="log" x-model="mailer" class="mt-1">
                        <span><span class="block font-medium text-slate-800">Do not send (log only)</span><span class="text-xs text-slate-500">For testing – emails are written to the log file, nobody receives them</span></span>
                    </label>
                </div>
            </div>

            <div x-show="mailer === 'smtp'" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-field label="SMTP host" name="mail_host" :value="$values['mail_host']" placeholder="smtp.office365.com" class="sm:col-span-2" />
                    <div>
                        <label for="mail_port" class="label">Port</label>
                        <input id="mail_port" name="mail_port" type="number" min="1" max="65535" x-model="port" class="input">
                        @error('mail_port')<p class="error">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div>
                    <label for="mail_encryption" class="label">Encryption</label>
                    <select id="mail_encryption" name="mail_encryption" x-model="enc" @change="port = enc === 'ssl' ? '465' : '587'" class="input sm:w-80">
                        @foreach (\App\Support\MailSettings::ENCRYPTION as $key => [$label])
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('mail_encryption')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field label="Username" name="mail_username" :value="$values['mail_username']" placeholder="system@yourcompany.lk" autocomplete="off" />
                    <div>
                        <label for="mail_password" class="label">Password</label>
                        <input id="mail_password" name="mail_password" type="password" autocomplete="new-password" class="input"
                               placeholder="{{ $hasPassword ? '•••••••• (saved – leave blank to keep)' : 'App password' }}">
                        <p class="mt-1 text-xs text-slate-500">Stored encrypted. Google / Microsoft usually need an <b>app password</b>, not the normal login password.</p>
                        @if ($hasPassword)
                            <label class="mt-1 flex items-center gap-2 text-xs text-slate-600"><input type="checkbox" name="clear_password" value="1"> Remove the saved password</label>
                        @endif
                        @error('mail_password')<p class="error">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            <div class="grid gap-4 border-t border-slate-200 pt-4 sm:grid-cols-2">
                <x-field label="From address" name="mail_from_address" type="email" :value="$values['mail_from_address']" required help="Usually the same mailbox as the username." />
                <x-field label="From name" name="mail_from_name" :value="$values['mail_from_name']" required help="Shown to the receiver, e.g. your company name." />
            </div>

            <div class="flex flex-wrap gap-2">
                <button class="btn btn-primary">Save email settings</button>
            </div>
        </form>

        <div class="space-y-6">
            <div class="card card-body space-y-3 text-sm">
                <h2 class="font-semibold">Check the connection</h2>
                <p class="text-slate-600">Save first, then:</p>
                <form method="POST" action="{{ route('system.mail.test') }}">
                    @csrf
                    <button class="btn btn-secondary w-full">1. Test connection (log in only)</button>
                </form>
                <form method="POST" action="{{ route('system.test-email') }}" class="space-y-2">
                    @csrf
                    <x-field label="2. Send a test email to" name="to" type="email" :value="auth()->user()->email" required />
                    <button class="btn btn-secondary w-full">Send test email</button>
                </form>
                <p class="text-xs text-slate-500">Results of every email are in the <a href="{{ route('system.emails') }}" class="link">Email log</a>.</p>
            </div>

            <div class="card card-body text-sm">
                <h2 class="mb-2 font-semibold">Common settings</h2>
                <table class="w-full text-xs">
                    <tr class="border-b border-slate-100"><td class="py-1.5 font-medium">Microsoft 365</td><td class="font-mono">smtp.office365.com</td><td>587 STARTTLS</td></tr>
                    <tr class="border-b border-slate-100"><td class="py-1.5 font-medium">Google Workspace</td><td class="font-mono">smtp.gmail.com</td><td>587 STARTTLS</td></tr>
                    <tr class="border-b border-slate-100"><td class="py-1.5 font-medium">Amazon SES</td><td class="font-mono">email-smtp.&lt;region&gt;.amazonaws.com</td><td>587 STARTTLS</td></tr>
                    <tr><td class="py-1.5 font-medium">Hosting / cPanel</td><td>as given by host</td><td>465 SSL or 587</td></tr>
                </table>
                <p class="mt-2 text-xs text-slate-500">Confirm with your email provider. Ask your IT person to add SPF and DKIM records so dealer emails do not go to spam.</p>
            </div>

            @if ($values['source'] === 'system')
                <form method="POST" action="{{ route('system.mail.reset') }}" onsubmit="return confirm('Remove the saved email settings and use the .env file again?')" class="card card-body text-sm">
                    @csrf
                    <p class="mb-2 text-slate-600">Settings are saved in the system and override <span class="font-mono">.env</span>.</p>
                    <button class="btn btn-danger">Use .env settings instead</button>
                </form>
            @endif
        </div>
    </div>
</x-layouts.app>
