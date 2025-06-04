<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class CurrencyService
{
    protected $apiKey;
    protected $baseUrl;

    public function __construct()
    {
        $this->apiKey = env('CURRENCY_API_KEY'); // Add your currency API key to the .env file
        $this->baseUrl = 'https://api.exchangerate-api.com/v4/latest/USD'; // Example API URL
    }

    public function getRates()
    {
        $response = Http::get($this->baseUrl);
        return $response->json();
    }

    public function convert($amount, $from, $to)
    {
        $rates = $this->getRates();
        $rate = $rates['rates'][$to] / $rates['rates'][$from];
        return $amount * $rate;
    }
}
