<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CoinPerformanceAlert extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public array $logs) {}

    /**
     * Get the notification's delivery channels.
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    // public function toDatabase($notifiable)
    // {
    //     return [
    //         'symbol'      => $this->alertLog->symbol,
    //         'performance' => $this->alertLog->performance,
    //         'price_from'  => $this->alertLog->price_from,
    //         'price_to'    => $this->alertLog->price_to,
    //         'alert_id'    => $this->alertLog->id,
    //     ];
    // }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('🚨 Coin Performance Alert')
            ->markdown('mail.coin-performance', ['logs' => $this->logs]);
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(mixed $notifiable): array
    {
        return [
            //
        ];
    }
}
