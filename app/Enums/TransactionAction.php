<?php

namespace App\Enums;

enum TransactionAction: string
{
    case ViewEarnings = 'view_earnings';
    case ReferralCommission = 'referral_commission';
    case ReferralReversal = 'referral_reversal';
    case EarningsAdjustment = 'earnings_adjustment';
    case WithdrawRequest = 'withdraw_request';
    case WithdrawPaid = 'withdraw_paid';
    case WithdrawRejected = 'withdraw_rejected';
    case Topup = 'topup';
    case CampaignCharge = 'campaign_charge';
    case ChargeRefund = 'charge_refund';

    public function label(): string
    {
        return match ($this) {
            self::ViewEarnings => 'View earnings',
            self::ReferralCommission => 'Referral commission',
            self::ReferralReversal => 'Referral reversal',
            self::EarningsAdjustment => 'Earnings adjustment',
            self::WithdrawRequest => 'Withdrawal request',
            self::WithdrawPaid => 'Withdrawal paid',
            self::WithdrawRejected => 'Withdrawal rejected',
            self::Topup => 'Top up',
            self::CampaignCharge => 'Campaign charge',
            self::ChargeRefund => 'Charge refund',
        };
    }
}
