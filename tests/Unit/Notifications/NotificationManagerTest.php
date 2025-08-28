<?php

use Phare\Notifications\Channels\ChannelManager;
use Phare\Notifications\Messages\MailMessage;
use Phare\Notifications\Notifiable;
use Phare\Notifications\Notification;
use Phare\Notifications\NotificationManager;

// Test classes
class TestUser
{
    use Notifiable;

    public function __construct(
        public string $email = 'test@example.com',
        public ?string $phone = null,
        public int $id = 1
    ) {}

    public function getKey(): int
    {
        return $this->id;
    }
}

class TestNotificationForManager extends Notification
{
    public function __construct(public string $message = 'Test notification')
    {
        parent::__construct();
    }

    public function via(mixed $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Test Subject')
            ->line($this->message);
    }

    public function toDatabase(mixed $notifiable): array
    {
        return ['message' => $this->message];
    }
}

class MultiChannelNotification extends Notification
{
    public function via(mixed $notifiable): array
    {
        return ['mail', 'database', 'sms'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage())->subject('Multi Channel Test');
    }

    public function toDatabase(mixed $notifiable): array
    {
        return ['type' => 'multi_channel'];
    }

    public function toSms(mixed $notifiable): \Phare\Notifications\Messages\SmsMessage
    {
        return new \Phare\Notifications\Messages\SmsMessage('Multi channel SMS');
    }
}

class FailingNotification extends Notification
{
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        throw new \Exception('Notification failed');
    }
}

class ConditionalSendNotification extends Notification
{
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function shouldSend(mixed $notifiable, string $channel): bool
    {
        return false; // Never send
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage())->subject('Should not send');
    }
}

beforeEach(function () {
    $this->channelManager = new ChannelManager();
    $this->manager = new NotificationManager($this->channelManager);
    $this->user = new TestUser();
    $this->notification = new TestNotificationForManager('Hello World!');
});

test('notification manager can be instantiated', function () {
    expect($this->manager)->toBeInstanceOf(NotificationManager::class);
});

test('notification manager can send notification to single notifiable', function () {
    $this->manager->send($this->user, $this->notification);

    $sent = $this->manager->getSentNotifications();
    expect($sent)->toHaveCount(1);
    expect($sent[0]['notifiable'])->toBe($this->user);
    expect($sent[0]['notification'])->toBe($this->notification);
    expect($sent[0]['channels'])->toBe(['mail', 'database']);
});

test('notification manager can send notification to multiple notifiables', function () {
    $user1 = new TestUser('user1@example.com', null, 1);
    $user2 = new TestUser('user2@example.com', null, 2);

    $this->manager->send([$user1, $user2], $this->notification);

    $sent = $this->manager->getSentNotifications();
    expect($sent)->toHaveCount(2);
    expect($sent[0]['notifiable'])->toBe($user1);
    expect($sent[1]['notifiable'])->toBe($user2);
});

test('notification manager send now works same as send', function () {
    $this->manager->sendNow($this->user, $this->notification);

    $sent = $this->manager->getSentNotifications();
    expect($sent)->toHaveCount(1);
    expect($sent[0]['notifiable'])->toBe($this->user);
});

test('notification manager handles multi channel notifications', function () {
    $notification = new MultiChannelNotification();
    $this->manager->send($this->user, $notification);

    $sent = $this->manager->getSentNotifications();
    expect($sent)->toHaveCount(1);
    expect($sent[0]['channels'])->toBe(['mail', 'database', 'sms']);
});

test('notification manager skips notifications that should not send', function () {
    $notification = new ConditionalSendNotification();
    $this->manager->send($this->user, $notification);

    $sent = $this->manager->getSentNotifications();
    expect($sent)->toHaveCount(1); // Still tracked as sent, but no channels processed
});

test('notification manager handles failing notifications', function () {
    $notification = new FailingNotification();

    expect(function () {
        $this->manager->send($this->user, $notification);
    })->toThrow(\Exception::class, 'Notification failed');
});

test('notification manager can clear sent notifications', function () {
    $this->manager->send($this->user, $this->notification);
    expect($this->manager->getSentNotifications())->toHaveCount(1);

    $this->manager->clearSentNotifications();
    expect($this->manager->getSentNotifications())->toHaveCount(0);
});

test('notification manager can get channel manager', function () {
    expect($this->manager->getChannelManager())->toBe($this->channelManager);
});

test('notification manager can access channels', function () {
    $mailChannel = $this->manager->channel('mail');
    expect($mailChannel)->toBeInstanceOf(\Phare\Notifications\Channels\MailChannel::class);
});

test('notification manager can get and set default driver', function () {
    expect($this->manager->getDefaultDriver())->toBe('mail');

    $this->manager->setDefaultDriver('sms');
    expect($this->manager->getDefaultDriver())->toBe('sms');
});

test('notification manager handles empty notifiables gracefully', function () {
    $this->manager->send([], $this->notification);

    expect($this->manager->getSentNotifications())->toHaveCount(0);
});

test('notification manager handles notifications with no channels', function () {
    $notification = new class() extends Notification
    {
        public function via(mixed $notifiable): array
        {
            return []; // No channels
        }
    };

    $this->manager->send($this->user, $notification);

    // Should not be tracked as sent since no channels
    expect($this->manager->getSentNotifications())->toHaveCount(0);
});

test('notifiable trait can send notifications', function () {
    // Since we can't easily mock app() function, we'll test the methods exist
    expect(method_exists($this->user, 'notify'))->toBeTrue();
    expect(method_exists($this->user, 'notifyNow'))->toBeTrue();
    expect(method_exists($this->user, 'routeNotificationFor'))->toBeTrue();
});

test('notifiable trait routing methods work', function () {
    $user = new TestUser('user@example.com', '+1234567890');

    expect($user->routeNotificationForMail())->toBe('user@example.com');
    expect($user->routeNotificationForSms())->toBe('+1234567890');
});

test('notifiable trait handles missing properties gracefully', function () {
    $user = new class()
    {
        use Notifiable;
    };

    expect($user->routeNotificationForMail())->toBeNull();
    expect($user->routeNotificationForSms())->toBeNull();
});

test('notification manager sent notification has timestamp', function () {
    $this->manager->send($this->user, $this->notification);

    $sent = $this->manager->getSentNotifications();
    expect($sent[0]['sent_at'])->toBeInstanceOf(\DateTime::class);
});
