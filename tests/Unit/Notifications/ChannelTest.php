<?php

use Phare\Notifications\Channels\ChannelManager;
use Phare\Notifications\Channels\DatabaseChannel;
use Phare\Notifications\Channels\MailChannel;
use Phare\Notifications\Channels\SlackChannel;
use Phare\Notifications\Channels\SmsChannel;
use Phare\Notifications\Messages\MailMessage;
use Phare\Notifications\Messages\SlackMessage;
use Phare\Notifications\Messages\SmsMessage;
use Phare\Notifications\Notification;

// Test notification for channels
class ChannelTestNotification extends Notification
{
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Channel Test')
            ->line('Testing channel functionality');
    }

    public function toDatabase(mixed $notifiable): array
    {
        return ['message' => 'Database test'];
    }

    public function toSms(mixed $notifiable): SmsMessage
    {
        return new SmsMessage('SMS test message');
    }

    public function toSlack(mixed $notifiable): SlackMessage
    {
        return (new SlackMessage('Slack test message'))->to('#general');
    }
}

class NotifiableForChannelTest
{
    public function __construct(
        public string $email = 'test@example.com',
        public ?string $phone = '+1234567890',
        public ?string $slack_webhook = 'https://hooks.slack.com/test',
        public int $id = 1
    ) {}

    public function getKey(): int
    {
        return $this->id;
    }

    public function routeNotificationForMail(): string
    {
        return $this->email;
    }

    public function routeNotificationForSms(): string
    {
        return $this->phone;
    }

    public function routeNotificationForSlack(): string
    {
        return $this->slack_webhook;
    }
}

beforeEach(function () {
    $this->channelManager = new ChannelManager();
    $this->notification = new ChannelTestNotification();
    $this->notifiable = new NotifiableForChannelTest();
});

test('channel manager can be instantiated', function () {
    expect($this->channelManager)->toBeInstanceOf(ChannelManager::class);
});

test('channel manager has default driver', function () {
    expect($this->channelManager->getDefaultDriver())->toBe('mail');
});

test('channel manager can set default driver', function () {
    $this->channelManager->setDefaultDriver('sms');
    expect($this->channelManager->getDefaultDriver())->toBe('sms');
});

test('channel manager can get mail driver', function () {
    $driver = $this->channelManager->driver('mail');
    expect($driver)->toBeInstanceOf(MailChannel::class);
});

test('channel manager can get database driver', function () {
    $driver = $this->channelManager->driver('database');
    expect($driver)->toBeInstanceOf(DatabaseChannel::class);
});

test('channel manager can get sms driver', function () {
    $driver = $this->channelManager->driver('sms');
    expect($driver)->toBeInstanceOf(SmsChannel::class);
});

test('channel manager can get slack driver', function () {
    $driver = $this->channelManager->driver('slack');
    expect($driver)->toBeInstanceOf(SlackChannel::class);
});

test('channel manager returns default driver when none specified', function () {
    $driver = $this->channelManager->driver();
    expect($driver)->toBeInstanceOf(MailChannel::class);
});

test('channel manager throws exception for unknown driver', function () {
    expect(function () {
        $this->channelManager->driver('unknown');
    })->toThrow(\InvalidArgumentException::class, 'Driver [unknown] not supported.');
});

test('channel manager can extend with custom driver', function () {
    $this->channelManager->extend('custom', function () {
        return new class() implements \Phare\Notifications\Channels\ChannelInterface
        {
            public function send(mixed $notifiable, Notification $notification): void
            {
                // Custom implementation
            }
        };
    });

    $driver = $this->channelManager->driver('custom');
    expect($driver)->toBeInstanceOf(\Phare\Notifications\Channels\ChannelInterface::class);
});

test('channel manager can clear drivers', function () {
    $this->channelManager->driver('mail'); // Create a driver
    expect($this->channelManager->getDrivers())->toHaveCount(1);

    $this->channelManager->clearDrivers();
    expect($this->channelManager->getDrivers())->toHaveCount(0);
});

test('mail channel can send notification', function () {
    $channel = new MailChannel();

    // Should not throw exception
    $channel->send($this->notifiable, $this->notification);
    expect(true)->toBeTrue(); // Test passes if no exception
});

test('database channel can send notification', function () {
    $channel = new DatabaseChannel();
    $channel->send($this->notifiable, $this->notification);

    $stored = $channel->getStoredNotifications();
    expect($stored)->toHaveCount(1);
    expect($stored[0]['type'])->toBe(ChannelTestNotification::class);
    expect($stored[0]['notifiable_type'])->toBe(NotifiableForChannelTest::class);
    expect($stored[0]['notifiable_id'])->toBe(1);
    expect($stored[0]['data'])->toBe(['message' => 'Database test']);
});

test('database channel can clear stored notifications', function () {
    $channel = new DatabaseChannel();
    $channel->send($this->notifiable, $this->notification);
    expect($channel->getStoredNotifications())->toHaveCount(1);

    $channel->clearStoredNotifications();
    expect($channel->getStoredNotifications())->toHaveCount(0);
});

test('sms channel can send notification', function () {
    $channel = new SmsChannel();
    $channel->send($this->notifiable, $this->notification);

    $sent = $channel->getSentMessages();
    expect($sent)->toHaveCount(1);
    expect($sent[0]['to'])->toBe('+1234567890');
    expect($sent[0]['message'])->toBe('SMS test message');
});

test('sms channel can clear sent messages', function () {
    $channel = new SmsChannel();
    $channel->send($this->notifiable, $this->notification);
    expect($channel->getSentMessages())->toHaveCount(1);

    $channel->clearSentMessages();
    expect($channel->getSentMessages())->toHaveCount(0);
});

test('sms channel handles missing phone number', function () {
    $notifiable = new NotifiableForChannelTest('test@example.com', null);
    $channel = new SmsChannel();

    $channel->send($notifiable, $this->notification);

    // Should not send if no phone number
    expect($channel->getSentMessages())->toHaveCount(0);
});

test('slack channel can send notification', function () {
    $channel = new SlackChannel();
    $channel->send($this->notifiable, $this->notification);

    $sent = $channel->getSentMessages();
    expect($sent)->toHaveCount(1);
    expect($sent[0]['webhook'])->toBe('https://hooks.slack.com/test');
    expect($sent[0]['channel'])->toBe('#general');
    expect($sent[0]['text'])->toBe('Slack test message');
});

test('slack channel can clear sent messages', function () {
    $channel = new SlackChannel();
    $channel->send($this->notifiable, $this->notification);
    expect($channel->getSentMessages())->toHaveCount(1);

    $channel->clearSentMessages();
    expect($channel->getSentMessages())->toHaveCount(0);
});

test('slack channel handles missing webhook', function () {
    $notifiable = new NotifiableForChannelTest('test@example.com', null, null);
    $channel = new SlackChannel();

    $channel->send($notifiable, $this->notification);

    // Should not send if no webhook
    expect($channel->getSentMessages())->toHaveCount(0);
});

test('database channel handles notifiable without id', function () {
    $notifiable = new class()
    {
        // No getKey, getId, or id property
    };

    $channel = new DatabaseChannel();
    $channel->send($notifiable, $this->notification);

    $stored = $channel->getStoredNotifications();
    expect($stored)->toHaveCount(1);
    expect($stored[0]['notifiable_id'])->toBeNull();
});

test('channels handle notifications returning null gracefully', function () {
    $notification = new class() extends Notification
    {
        public function via(mixed $notifiable): array
        {
            return ['mail', 'sms', 'slack'];
        }

        public function toMail(mixed $notifiable): ?MailMessage
        {
            return null;
        }

        public function toSms(mixed $notifiable): ?SmsMessage
        {
            return null;
        }

        public function toSlack(mixed $notifiable): ?SlackMessage
        {
            return null;
        }
    };

    $mailChannel = new MailChannel();
    $smsChannel = new SmsChannel();
    $slackChannel = new SlackChannel();

    // Should not throw exceptions
    $mailChannel->send($this->notifiable, $notification);
    $smsChannel->send($this->notifiable, $notification);
    $slackChannel->send($this->notifiable, $notification);

    expect($smsChannel->getSentMessages())->toHaveCount(0);
    expect($slackChannel->getSentMessages())->toHaveCount(0);
});
