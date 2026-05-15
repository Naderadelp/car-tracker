<?php

namespace App\Services;

use App\Mail\EmailOtpMail;
use App\Models\EmailOtp;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class EmailOtpService
{
    public const EXPIRY_MINUTES   = 10;
    public const MAX_ATTEMPTS     = 5;
    public const RESEND_COOLDOWN  = 60;

    public function send(string $email, string $purpose): void
    {
        $row = EmailOtp::firstOrNew([
            'email'   => $email,
            'purpose' => $purpose,
        ]);

        if ($row->exists && $row->last_sent_at && $row->last_sent_at->diffInSeconds(now()) < self::RESEND_COOLDOWN) {
            throw ValidationException::withMessages([
                'email' => ['Please wait before requesting another code.'],
            ]);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $row->fill([
            'code_hash'    => Hash::make($code),
            'expires_at'   => now()->addMinutes(self::EXPIRY_MINUTES),
            'attempts'     => 0,
            'last_sent_at' => now(),
        ])->save();

        Mail::to($email)->send(new EmailOtpMail($code, $purpose));
    }

    public function verify(string $email, string $purpose, string $code): void
    {
        $row = EmailOtp::where('email', $email)
            ->where('purpose', $purpose)
            ->first();

        if (! $row) {
            throw ValidationException::withMessages([
                'otp' => ['Code expired or not found.'],
            ]);
        }

        if ($row->expires_at->isPast()) {
            $row->delete();
            throw ValidationException::withMessages([
                'otp' => ['Code expired or not found.'],
            ]);
        }

        if ($row->attempts >= self::MAX_ATTEMPTS) {
            $row->delete();
            throw ValidationException::withMessages([
                'otp' => ['Too many attempts. Request a new code.'],
            ]);
        }

        if (! Hash::check($code, $row->code_hash)) {
            $row->increment('attempts');
            if ($row->attempts >= self::MAX_ATTEMPTS) {
                $row->delete();
            }
            throw ValidationException::withMessages([
                'otp' => ['Invalid code.'],
            ]);
        }

        $row->delete();
    }
}
