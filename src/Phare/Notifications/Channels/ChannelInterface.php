<?php

namespace Phare\Notifications\Channels;

use Phare\Notifications\Notification;

interface ChannelInterface
{
    /**
     * Send the given notification.
     */
    public function send(mixed $notifiable, Notification $notification): void;
}