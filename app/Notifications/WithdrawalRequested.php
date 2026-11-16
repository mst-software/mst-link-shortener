<?php

namespace App\Notifications;

use App\Models\Withdrawal;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Confirms to the member that their withdrawal request was received.
 * Sent synchronously during the request: the app runs without a queue worker.
 */
class WithdrawalRequested extends Notification
{
    public function __construct(public Withdrawal $withdrawal) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('We received your withdrawal request')
            ->greeting('Hello '.$notifiable->username.',')
            ->line('We have received your withdrawal request and it is now waiting for review.')
            ->line('Amount: '.Money::format($this->withdrawal->amount_micro, 2))
            ->line('Payment method: '.$this->withdrawal->method->label())
            ->line('The amount has been moved from your available balance to your processing balance until the request is reviewed.')
            ->action('View withdrawals', route('member.withdraws'));
    }
}
