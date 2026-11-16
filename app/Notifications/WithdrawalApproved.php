<?php

namespace App\Notifications;

use App\Models\Withdrawal;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the member that an admin approved and paid their withdrawal.
 * Sent synchronously: the default queue connection is "database" and no worker is guaranteed.
 */
class WithdrawalApproved extends Notification
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
            ->subject('Your withdrawal has been paid')
            ->greeting('Hello '.$notifiable->username.',')
            ->line('Good news: your withdrawal has been approved and paid.')
            ->line('Amount: '.Money::format($this->withdrawal->amount_micro, 2))
            ->line('Payment method: '.$this->withdrawal->method->label())
            ->line('Depending on the payment method, it can take a little while for the money to show up in your account.')
            ->action('View withdrawals', route('member.withdraws'));
    }
}
