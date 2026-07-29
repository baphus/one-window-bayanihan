<?php

namespace App\Console\Commands;

use App\Models\Referral;
use App\Models\Service;
use Illuminate\Console\Command;

class BackfillReferralServices extends Command
{
    protected $signature = 'referrals:backfill-services';

    protected $description = 'Parse existing required_services CSV and populate referral_services pivot table';

    public function handle(): int
    {
        $referrals = Referral::whereNotNull('required_services')
            ->where('required_services', '!=', '')
            ->lazy();

        $count = 0;
        $skipped = 0;

        foreach ($referrals as $referral) {
            $names = array_map('trim', explode(',', $referral->required_services));

            $serviceIds = Service::whereIn('name', $names)
                ->where('agcy_id', $referral->agcy_id)
                ->pluck('id');

            if ($serviceIds->isNotEmpty()) {
                $referral->services()->syncWithoutDetaching($serviceIds);
                $count++;
            } else {
                $this->warn("No matching services found for referral {$referral->id} (names: ".implode(', ', $names).')');
                $skipped++;
            }
        }

        $this->info("Backfilled services for {$count} referral(s). Skipped {$skipped} referral(s) with no matching services.");

        return self::SUCCESS;
    }
}
