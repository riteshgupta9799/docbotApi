<?php
// app/Services/TwilioService.php
namespace App\Services;

use Twilio\Rest\Client;

class TwilioService
{
    protected $client;
    protected $from;

    public function __construct()
    {
        $this->client = new Client(
            config('services.twilio.sid'),
            config('services.twilio.token')
        );

        $this->from = config('services.twilio.whatsapp_from');
    }

  public function sendOtpUsingSmsTemplate($to, $otp)
{
    return $this->client->messages->create($to, [ // ← Note: NO "whatsapp:" prefix
        'from' => $this->from, // Must be an SMS-capable Twilio number
        'contentSid' => 'HX7ce26a871bfa03f1fba9a1d4c7a6de9a',
        'contentVariables' => json_encode([
            '1' => $otp // assuming your template uses {{1}} for OTP
        ]),
    ]);
}

}
