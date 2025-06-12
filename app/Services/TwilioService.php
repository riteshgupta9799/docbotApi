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

       $this->from = config('services.twilio.sms_from');

    }

public function sendOtpUsingSmsTemplate($to, $otp)
{
    return $this->client->messages->create($to, [
        'from' => $this->from,  // Must be your Twilio SMS-enabled number
        'contentSid' => 'HX7ce26a871bfa03f1fba9a1d4c7a6de9a',
        'contentVariables' => json_encode([
            'otp' => (string) $otp  // Must be string and key should match `{{otp}}`
        ]),
    ]);
}



}
