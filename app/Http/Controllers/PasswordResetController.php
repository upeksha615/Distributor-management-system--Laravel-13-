<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

/** "Forgot password" by email. Links expire (config/auth.php) and work once; only active accounts get one. */
class PasswordResetController extends Controller
{
    public const SENT = 'If that email belongs to an active account, a password reset link has been sent to it.';

    public function request()
    {
        return view('auth.forgot-password');
    }

    public function email(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink(['email' => $request->input('email'), 'is_active' => true]);

        if ($status === Password::RESET_THROTTLED) {
            throw ValidationException::withMessages(['email' => 'Please wait a minute before asking for another link.']);
        }

        // Same answer whether or not the account exists, so the form cannot be used to discover emails.
        return back()->with('status', self::SENT);
    }

    public function edit(Request $request, string $token)
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token') + ['is_active' => true],
            function (User $user, string $password) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                DB::table('sessions')->where('user_id', $user->id)->delete(); // sign out everywhere else
                event(new PasswordReset($user));
                AuditLog::create(['user_id' => $user->id, 'action' => 'auth.password_reset', 'subject_type' => $user->getMorphClass(),
                    'subject_id' => $user->id, 'description' => "Password reset by email link for {$user->email}.", 'ip' => request()->ip()]);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'This reset link is invalid or has expired. Please ask for a new one.']);
        }

        return redirect()->route('login')->with('status', 'Your password has been changed. Please log in.');
    }

    /** Administrator sends a reset link to a user (Users → Edit). */
    public function sendForUser(User $user)
    {
        abort_unless($user->is_active, 422, 'This account is inactive. Activate it first.');
        $status = Password::sendResetLink(['email' => $user->email]);
        AuditLog::record('user.password_reset_link', $user, "Password reset link requested for {$user->email}.");

        if ($status === Password::RESET_THROTTLED) {
            return back()->with('error', 'A link was sent less than a minute ago. Please wait before sending another.');
        }
        $log = \App\Models\EmailLog::where('type', 'password_reset')->where('to', $user->email)->latest('id')->first();
        [$type, $message] = $log ? \App\Services\MailService::outcome($log) : ['error', 'The reset link could not be created.'];

        return back()->with($type, $message);
    }
}
