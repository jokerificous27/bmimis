<?php

namespace App\Livewire\Notifications;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Notifications')]
class Index extends Component
{
    use WithPagination;

    #[Url(except: false)]
    public bool $unreadOnly = false;

    public function open(string $id): void
    {
        $notification = Auth::user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $url = $notification->data['url'] ?? null;

        if ($url && str_starts_with($url, url('/'))) {
            $this->redirect($url, navigate: true);
        }
    }

    public function markAllRead(): void
    {
        Auth::user()->unreadNotifications->markAsRead();
    }

    public function render(): View
    {
        $user = Auth::user();

        return view('livewire.notifications.index', [
            'notifications' => ($this->unreadOnly ? $user->unreadNotifications() : $user->notifications())->latest()->paginate(20),
            'unreadCount' => $user->unreadNotifications()->count(),
        ]);
    }
}
