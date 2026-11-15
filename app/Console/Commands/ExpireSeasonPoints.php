<?php

namespace App\Console\Commands;

use App\Business;
use App\Contact;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireSeasonPoints extends Command
{
    protected $signature = 'points:expire-season {--dry-run : Only show what would expire}';

    protected $description = 'Clears unspent reward points the day after each business\'s points expiry date';

    public function handle()
    {
        $dry = (bool) $this->option('dry-run');

        $businesses = Business::where('enable_rp', 1)->whereNotNull('rp_expiry_date')->get();

        foreach ($businesses as $business) {
            $tz = ! empty($business->time_zone) ? $business->time_zone : config('app.timezone');
            $today = \Carbon\Carbon::now($tz)->startOfDay();
            $expiry = \Carbon\Carbon::parse($business->rp_expiry_date, $tz)->startOfDay();

            //Points are valid through the expiry date itself
            if ($today->lte($expiry)) {
                continue;
            }

            //Already processed for this expiry date
            if (! empty($business->rp_expired_on) && \Carbon\Carbon::parse($business->rp_expired_on, $tz)->startOfDay()->gte($expiry)) {
                continue;
            }

            //Safety: an expiry date far in the past is probably a typing mistake, so do nothing
            if ($expiry->lt($today->copy()->subDays(60))) {
                $this->warn("Business {$business->id}: expiry date {$expiry->toDateString()} is more than 60 days ago, skipped.");

                continue;
            }

            $contacts = Contact::where('business_id', $business->id)->where('total_rp', '>', 0)->get();
            $total = (int) $contacts->sum('total_rp');

            $this->info("Business {$business->id}: {$contacts->count()} customers, {$total} points ".($dry ? 'would expire' : 'expired').'.');

            if ($dry) {
                continue;
            }

            DB::transaction(function () use ($contacts, $business, $expiry) {
                foreach ($contacts as $contact) {
                    $contact->total_rp_expired = (int) $contact->total_rp_expired + (int) $contact->total_rp;
                    $contact->total_rp = 0;
                    $contact->save();
                }

                DB::table('business')->where('id', $business->id)->update(['rp_expired_on' => $expiry->toDateString()]);
            });
        }

        return 0;
    }
}