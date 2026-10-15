<?php

namespace App\Mail;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $code,
        public readonly CarbonInterface $expiresAt,
        public readonly string $maskedPhone,
        public readonly string $requestedChannel,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'DUDHO verification code');
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.otp-code',
            with: [
                'code' => $this->code,
                'expiresAt' => $this->expiresAt,
                'maskedPhone' => $this->maskedPhone,
                'requestedChannel' => $this->requestedChannel,
            ],
        );
    }
}
