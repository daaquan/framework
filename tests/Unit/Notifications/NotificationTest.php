<?php

use Phare\Notifications\Messages\MailMessage;
use Phare\Notifications\Messages\SlackMessage;
use Phare\Notifications\Messages\SmsMessage;
use Phare\Notifications\Notification;

// Test notification classes
class TestNotification extends Notification
{
    public function __construct(protected string $message = 'Test message')
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
            ->subject('Test Notification')
            ->greeting('Hello!')
            ->line($this->message)
            ->action('View Details', 'https://example.com')
            ->line('Thank you for using our application!');
    }

    public function toDatabase(mixed $notifiable): array
    {
        return [
            'message' => $this->message,
            'action_url' => 'https://example.com',
        ];
    }

    public function toArray(mixed $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}

class SmsNotification extends Notification
{
    public function via(mixed $notifiable): array
    {
        return ['sms'];
    }

    public function toSms(mixed $notifiable): SmsMessage
    {
        return (new SmsMessage('Your verification code is: 123456'))
            ->from('+1234567890');
    }
}

class SlackNotification extends Notification
{
    public function via(mixed $notifiable): array
    {
        return ['slack'];
    }

    public function toSlack(mixed $notifiable): SlackMessage
    {
        return (new SlackMessage('Deployment completed successfully!'))
            ->to('#general')
            ->success();
    }
}

class ConditionalNotification extends Notification
{
    public function via(mixed $notifiable): array
    {
        return ['mail', 'sms'];
    }

    public function shouldSend(mixed $notifiable, string $channel): bool
    {
        // Only send SMS to users with phone numbers
        if ($channel === 'sms') {
            return !empty($notifiable->phone ?? '');
        }

        return true;
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage())->subject('Conditional Test');
    }

    public function toSms(mixed $notifiable): SmsMessage
    {
        return new SmsMessage('Conditional SMS test');
    }
}

beforeEach(function () {
    $this->notification = new TestNotification('Hello World!');
});

test('notification has unique id', function () {
    $notification1 = new TestNotification();
    $notification2 = new TestNotification();

    expect($notification1->getId())->not->toBe($notification2->getId());
    expect($notification1->getId())->toMatch('/^notification_/');
});

test('notification can set custom id', function () {
    $this->notification->setId('custom-id');

    expect($this->notification->getId())->toBe('custom-id');
});

test('notification starts as unread', function () {
    expect($this->notification->isRead())->toBeFalse();
    expect($this->notification->getReadAt())->toBeNull();
});

test('notification can be marked as read', function () {
    $this->notification->markAsRead();

    expect($this->notification->isRead())->toBeTrue();
    expect($this->notification->getReadAt())->toBeInstanceOf(\DateTime::class);
});

test('notification can be marked as unread', function () {
    $this->notification->markAsRead();
    $this->notification->markAsUnread();

    expect($this->notification->isRead())->toBeFalse();
    expect($this->notification->getReadAt())->toBeNull();
});

test('notification can have additional data', function () {
    $this->notification->with(['key' => 'value', 'number' => 123]);

    expect($this->notification->getData())->toBe(['key' => 'value', 'number' => 123]);
});

test('notification can chain with data', function () {
    $result = $this->notification->with(['key1' => 'value1'])->with(['key2' => 'value2']);

    expect($result)->toBe($this->notification);
    expect($this->notification->getData())->toBe(['key1' => 'value1', 'key2' => 'value2']);
});

test('notification has creation timestamp', function () {
    expect($this->notification->getCreatedAt())->toBeInstanceOf(\DateTime::class);
});

test('notification via method returns channels', function () {
    $notifiable = (object)['email' => 'test@example.com'];

    expect($this->notification->via($notifiable))->toBe(['mail', 'database']);
});

test('notification to mail returns mail message', function () {
    $notifiable = (object)['email' => 'test@example.com'];
    $mailMessage = $this->notification->toMail($notifiable);

    expect($mailMessage)->toBeInstanceOf(MailMessage::class);
    expect($mailMessage->getSubject())->toBe('Test Notification');
    expect($mailMessage->getGreeting())->toBe('Hello!');
    expect($mailMessage->getIntroLines())->toContain('Hello World!');
    expect($mailMessage->hasAction())->toBeTrue();
    expect($mailMessage->getActionText())->toBe('View Details');
    expect($mailMessage->getActionUrl())->toBe('https://example.com');
});

test('notification to database returns array', function () {
    $notifiable = (object)['id' => 1];
    $data = $this->notification->toDatabase($notifiable);

    expect($data)->toBe([
        'message' => 'Hello World!',
        'action_url' => 'https://example.com',
    ]);
});

test('notification to array returns same as to database', function () {
    $notifiable = (object)['id' => 1];

    expect($this->notification->toArray($notifiable))
        ->toBe($this->notification->toDatabase($notifiable));
});

test('sms notification works correctly', function () {
    $notification = new SmsNotification();
    $notifiable = (object)['phone' => '+1234567890'];

    expect($notification->via($notifiable))->toBe(['sms']);

    $smsMessage = $notification->toSms($notifiable);
    expect($smsMessage)->toBeInstanceOf(SmsMessage::class);
    expect($smsMessage->getContent())->toBe('Your verification code is: 123456');
    expect($smsMessage->getFrom())->toBe('+1234567890');
});

test('slack notification works correctly', function () {
    $notification = new SlackNotification();
    $notifiable = (object)['slack_webhook' => 'https://hooks.slack.com/...'];

    expect($notification->via($notifiable))->toBe(['slack']);

    $slackMessage = $notification->toSlack($notifiable);
    expect($slackMessage)->toBeInstanceOf(SlackMessage::class);
    expect($slackMessage->getText())->toBe('Deployment completed successfully!');
    expect($slackMessage->getChannel())->toBe('#general');
    expect($slackMessage->getAttachments())->toHaveCount(1);
    expect($slackMessage->getAttachments()[0]['color'])->toBe('good');
});

test('notification should send method works', function () {
    $notification = new ConditionalNotification();
    $notifiableWithPhone = (object)['email' => 'test@example.com', 'phone' => '+1234567890'];
    $notifiableWithoutPhone = (object)['email' => 'test@example.com'];

    expect($notification->shouldSend($notifiableWithPhone, 'mail'))->toBeTrue();
    expect($notification->shouldSend($notifiableWithPhone, 'sms'))->toBeTrue();
    expect($notification->shouldSend($notifiableWithoutPhone, 'mail'))->toBeTrue();
    expect($notification->shouldSend($notifiableWithoutPhone, 'sms'))->toBeFalse();
});

test('notification returns null for unsupported channels', function () {
    expect($this->notification->toSms((object)[]))->toBeNull();
    expect($this->notification->toSlack((object)[]))->toBeNull();
});

test('notification can set read at timestamp', function () {
    $timestamp = new \DateTime('2023-01-01 12:00:00');
    $this->notification->setReadAt($timestamp);

    expect($this->notification->getReadAt())->toBe($timestamp);
    expect($this->notification->isRead())->toBeTrue();
});
