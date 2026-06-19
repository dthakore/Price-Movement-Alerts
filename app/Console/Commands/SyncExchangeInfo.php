<?php

namespace App\Console\Commands;

use App\Models\Symbol;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncExchangeInfo extends Command
{
    protected $signature = 'exchange:sync';

    protected $description = 'Sync Binance Futures perpetual symbols';

    public function handle(): int
    {
        $response = Http::timeout(15)
            ->get('https://fapi.binance.com/fapi/v1/exchangeInfo');

        if (! $response->successful()) {
            $this->error('Failed to fetch Binance exchangeInfo');

            return Command::FAILURE;
        }

        Symbol::where('exchange_id', 1)->delete();

        $symbols = collect($response->json('symbols'))
            ->filter(fn ($symbol) => $symbol['status'] === 'TRADING'
                && $symbol['contractType'] === 'PERPETUAL')
            ->pluck('symbol');

        foreach ($symbols as $symbol) {
            Symbol::create(['symbol' => $symbol, 'exchange_id' => 1]);
        }

        $this->info("Synced {$symbols->count()} Binance symbols.");

        return Command::SUCCESS;
    }
}
