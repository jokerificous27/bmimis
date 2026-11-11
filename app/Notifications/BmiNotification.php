<?php

namespace App\Notifications;

use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Base in-app notification. Email delivery can be switched on later from System Settings
 * ("notifications_email_enabled") without changing any notification class.
 */
abstract class BmiNotification extends Notification
{
    use Queueable;

    abstract protected function title(): string;

    abstract protected function message(): string;

    abstract protected function url(): ?string;

    protected function icon(): string
    {
        return 'bell';
    }

    protected function level(): string
    {
        return 'info';
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (app(Settings::class)->bool('notifications_email_enabled') && filled($notifiable->email ?? null)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->title())->line($this->message());

        return $this->url() ? $mail->action(__('Open'), $this->url()) : $mail;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'message' => $this->message(),
            'url' => $this->url(),
            'icon' => $this->icon(),
            'level' => $this->level(),
        ];
    }
}
