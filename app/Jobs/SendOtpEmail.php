<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use App\Mail\OtpVerifyMail;

class SendOtpEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userId;
    protected $type;
    protected $otp;

    public function __construct($userId, $type, $otp)
    {
        $this->userId = $userId;
        $this->type = $type; // 'register' or 'forgot'
        $this->otp = $otp;
    }

    public function handle(): void
    {
        $user = User::find($this->userId);

        if ($user) {
            Mail::to($user->email)->send(new OtpVerifyMail($user, $this->otp));
        }
    }
}