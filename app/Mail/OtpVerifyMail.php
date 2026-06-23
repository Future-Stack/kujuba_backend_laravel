<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpVerifyMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public User $user;
    public int $otp;
    public string $type;

    public function __construct(User $user, int $otp = 0, string $type = 'register')
    {
        $this->user = $user;
        $this->otp = $otp;
        $this->type = $type;
    }

    public function envelope(): Envelope
    {
        $subject = match ($this->type) {
            'forgot'        => 'Password Reset OTP',
            'reset-password' => 'Password Changed Successfully',
            default         => 'Email Verification OTP',
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $view = match ($this->type) {
            'forgot'        => 'emails.forgot_otp',
            'reset-password' => 'emails.password_reset_success',
            default         => 'emails.register_otp',
        };

        return new Content(
            view: $view,
            with: [
                'user' => $this->user,
                'otp'  => $this->otp,
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}