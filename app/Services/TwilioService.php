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
    return $this->client->messages->create($to, [
        'from' => $this->from,
        'contentSid' => 'HX7ce26a871bfa03f1fba9a1d4c7a6de9a',
        'contentVariables' => json_encode([
            'otp' => $otp  // ✅ must match {{otp}} from the template
        ]),
    ]);
}


}
