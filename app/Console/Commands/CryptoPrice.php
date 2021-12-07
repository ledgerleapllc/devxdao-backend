<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Setting;

class CryptoPrice extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cryptoprice:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check CryptoPrice';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function process($crypto = 'ETH', $data = []) {
      if (
        isset($data['quote']) && 
        isset($data['quote']['EUR']) && 
        isset($data['quote']['EUR']['price'])
      ) {
        $price = $data['quote']['EUR']['price'];

        $key = 'btc_price';
        if ($crypto == 'ETH') $key = 'eth_price';

        $setting = Setting::where('name', $key)->first();
        if (!$setting) {
          $setting = new Setting();
          $setting->name = $key;
        }

        $setting->value = round((float)$price, 2);
        $setting->save();
      }
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $url = 'https://pro-api.coinmarketcap.com/v1/tools/price-conversion';

        try {
            //
            $response = Http::withHeaders([
              'X-CMC_PRO_API_KEY' => config('services.x_cmc_pro.api_key')
            ])->get($url, [
              'amount' => 1,
              'symbol' => 'ETH',
              'convert' => 'EUR'
            ]);

            $json = $response->json();

            if ($json && isset($json['data']))
              $this->process('ETH', $json['data']);
            
            //
            $response = Http::withHeaders([
              'X-CMC_PRO_API_KEY' => config('services.x_cmc_pro.api_key')
            ])->get($url, [
              'amount' => 1,
              'symbol' => 'BTC',
              'convert' => 'EUR'
            ]);

            $json = $response->json();

            if ($json && isset($json['data']))
              $this->process('BTC', $json['data']);
        } catch (Exception $e) {
            // Do Nothing
        }

        return 0;
    }
}
