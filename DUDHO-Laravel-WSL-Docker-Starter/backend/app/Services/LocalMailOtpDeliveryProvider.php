<?php

namespace App\Services;

use App\Contracts\OtpDeliveryProvider;
use App\Mail\OtpCodeMail;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class LocalMailOtpDeliveryProvider implements OtpDeliveryProvider
{
    public function __construct(private readonly string $recipient) {}

    public function deliver(
        string $phone,
        string $code,
        CarbonInterface $expiresAt,
        string $channel,
    ): void {
        $maskedPhone = '***'.Str::substr($phone, -4);

        Mail::mailer('smtp')
            ->to($this->recipient)
            ->send(new OtpCodeMail($code, $expiresAt, $maskedPhone, $channel));
    }
}
