<?php

namespace App\Console\Commands;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SetRestaurantSubscriptionExpiry extends Command
{
    protected $signature = 'subscription:set-expiry {date : Expiry date in UTC (YYYY-MM-DD)}';

    protected $description = 'Set this restaurant deployment subscription expiry date';

    public function handle(): int
    {
        $date = $this->argument('date');
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));

        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            $this->error('Use a valid UTC date in YYYY-MM-DD format.');

            return self::FAILURE;
        }

        DB::table('restaurant_subscription')->updateOrInsert(
            ['id' => 1],
            ['expires_on' => $date]
        );

        $this->info("Subscription expires at the end of {$date} UTC.");

        return self::SUCCESS;
    }
}
