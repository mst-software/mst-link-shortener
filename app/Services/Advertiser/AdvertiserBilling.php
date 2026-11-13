<?php

namespace App\Services\Advertiser;

use App\Enums\TransactionAction;
use App\Enums\ViewStatus;
use App\Exceptions\InsufficientBalanceException;
use App\Models\Campaign;
use App\Models\Transaction;
use App\Models\View;
use App\Services\Settings;
use App\Services\WalletService;

/**
 * Charges advertisers for valid keyword views and gives the money back when a view is cancelled.
 * Campaigns without an owner (created by an admin) are never charged.
 */
class AdvertiserBilling
{
    public function __construct(private readonly WalletService $wallet) {}

    /**
     * Price of one valid view in micro-USD: the campaign's own price or the default setting.
     */
    public function pricePerViewMicro(Campaign $campaign): int
    {
        return $campaign->price_micro ?? Settings::int('advertiser_default_price_micro');
    }

    /**
     * Whether the owner's wallet can pay for at least one more view. Always true without an owner.
     */
    public function canAfford(Campaign $campaign): bool
    {
        if ($campaign->user_id === null) {
            return true;
        }

        $campaign->loadMissing('owner');

        return $campaign->owner !== null
            && $campaign->owner->balance_micro >= $this->pricePerViewMicro($campaign);
    }

    /**
     * Charge the campaign owner for a freshly recorded valid view. Meant to run inside the transaction
     * that records the view. A wallet that cannot pay leaves the view free (cost 0); it never goes negative.
     */
    public function chargeView(View $view, Campaign $campaign): void
    {
        if ($campaign->user_id === null || $view->status === ViewStatus::Invalid) {
            return;
        }

        $price = $this->pricePerViewMicro($campaign);

        if ($price <= 0 || $campaign->owner === null) {
            return;
        }

        try {
            $this->wallet->debit(
                $campaign->owner,
                $price,
                TransactionAction::CampaignCharge,
                'view:'.$view->getKey(),
                'Campaign '.$campaign->name,
            );
        } catch (InsufficientBalanceException) {
            return;
        }

        $view->update(['advertiser_cost_micro' => $price]);
    }

    /**
     * Give back the cost of cancelled views. Safe to call twice: a view is refunded only once.
     *
     * @param  iterable<View>  $views
     */
    public function refundViews(iterable $views): void
    {
        foreach ($views as $view) {
            if ($view->advertiser_cost_micro <= 0 || $view->campaign_id === null) {
                continue;
            }

            $reference = 'view-refund:'.$view->getKey();

            if (Transaction::query()->where('reference_id', $reference)->exists()) {
                continue;
            }

            $owner = Campaign::query()->find($view->campaign_id)?->owner;

            if ($owner === null) {
                continue;
            }

            $this->wallet->credit(
                $owner,
                $view->advertiser_cost_micro,
                TransactionAction::ChargeRefund,
                $reference,
                'Refund for a cancelled view',
            );
        }
    }
}
