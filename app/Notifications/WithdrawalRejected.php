<?php

namespace App\Notifications;

use App\Models\Withdrawal;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the member that an admin rejected their withdrawal and why.
 * Sent synchronously during the request: the app runs without a queue worker.
 */
class WithdrawalRejected extends Notification
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
            ->subject('Your withdrawal request was rejected')
            ->greeting('Hello '.$notifiable->username.',')
            ->line('Your withdrawal request has been rejected.')
            ->line('Amount: '.Money::format($this->withdrawal->amount_micro, 2))
            ->line('Payment method: '.$this->withdrawal->method->label())
            ->line('Reason: '.$this->withdrawal->admin_note)
            ->line('The full amount has been returned to your available balance. You can submit a new request at any time.')
            ->action('View withdrawals', route('member.withdraws'));
    }
}
