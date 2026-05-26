<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            [
                'code' => 'USD',
                'name' => 'US Dollar',
                'symbol' => '$'
            ],
            [
                'code' => 'CDF',
                'name' => 'Congolese Franc',
                'symbol' => 'FC'
            ],
            [
                'code' => 'EUR',
                'name' => 'Euro',
                'symbol' => '€'
            ],
            [
                'code' => 'BIF',
                'name' => 'Burundian Franc',
                'symbol' => 'FBu'
            ],
        ];

        foreach ($currencies as $currency) {
            Currency::updateOrCreate(
                ['code' => $currency['code']],
                $currency
            );
        }
    }
}
