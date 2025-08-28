<?php

use Phare\Notifications\Messages\MailMessage;
use Phare\Notifications\Messages\SlackMessage;
use Phare\Notifications\Messages\SmsMessage;

test('mail message can be created', function () {
    $message = new MailMessage();

    expect($message)->toBeInstanceOf(MailMessage::class);
});

test('mail message can set subject', function () {
    $message = (new MailMessage())->subject('Test Subject');

    expect($message->getSubject())->toBe('Test Subject');
});

test('mail message can set greeting', function () {
    $message = (new MailMessage())->greeting('Hello World!');

    expect($message->getGreeting())->toBe('Hello World!');
});

test('mail message can add lines', function () {
    $message = (new MailMessage())
        ->line('First line')
        ->line('Second line');

    expect($message->getIntroLines())->toBe(['First line', 'Second line']);
});

test('mail message can add multiple lines at once', function () {
    $message = (new MailMessage())->lines(['Line 1', 'Line 2', 'Line 3']);

    expect($message->getIntroLines())->toBe(['Line 1', 'Line 2', 'Line 3']);
});

test('mail message can set action', function () {
    $message = (new MailMessage())->action('Click Here', 'https://example.com');

    expect($message->hasAction())->toBeTrue();
    expect($message->getActionText())->toBe('Click Here');
    expect($message->getActionUrl())->toBe('https://example.com');
});

test('mail message can set level', function () {
    $message = (new MailMessage())->level('warning');

    expect($message->getLevel())->toBe('warning');
});

test('mail message can set success level', function () {
    $message = (new MailMessage())->success();

    expect($message->getLevel())->toBe('success');
});

test('mail message can set error level', function () {
    $message = (new MailMessage())->error();

    expect($message->getLevel())->toBe('error');
});

test('mail message can set recipients', function () {
    $message = (new MailMessage())->to('test@example.com', 'Test User');

    expect($message->getTo())->toBe(['test@example.com' => 'Test User']);
    expect($message->hasTo())->toBeTrue();
});

test('mail message can set array of recipients', function () {
    $message = (new MailMessage())->to(['user1@example.com' => 'User 1', 'user2@example.com' => 'User 2']);

    expect($message->getTo())->toBe(['user1@example.com' => 'User 1', 'user2@example.com' => 'User 2']);
});

test('mail message can set cc recipients', function () {
    $message = (new MailMessage())->cc('cc@example.com', 'CC User');

    expect($message->getCc())->toBe(['cc@example.com' => 'CC User']);
});

test('mail message can set bcc recipients', function () {
    $message = (new MailMessage())->bcc('bcc@example.com', 'BCC User');

    expect($message->getBcc())->toBe(['bcc@example.com' => 'BCC User']);
});

test('mail message can set reply to', function () {
    $message = (new MailMessage())->replyTo('reply@example.com', 'Reply User');

    expect($message->getReplyTo())->toBe(['reply@example.com' => 'Reply User']);
});

test('mail message can attach files', function () {
    $message = (new MailMessage())->attach('/path/to/file.pdf', 'document.pdf');

    $attachments = $message->getAttachments();
    expect($attachments)->toHaveCount(1);
    expect($attachments[0]['path'])->toBe('/path/to/file.pdf');
    expect($attachments[0]['name'])->toBe('document.pdf');
});

test('mail message can set custom view', function () {
    $message = (new MailMessage())->view('emails.custom', ['name' => 'John']);

    expect($message->hasView())->toBeTrue();
    expect($message->getView())->toBe('emails.custom');
    expect($message->getViewData())->toBe(['name' => 'John']);
});

test('mail message can create mailable', function () {
    $message = (new MailMessage())
        ->subject('Test Subject')
        ->greeting('Hello!')
        ->line('This is a test message')
        ->action('Click Here', 'https://example.com')
        ->to('test@example.com');

    $notifiable = (object)['email' => 'test@example.com'];
    $mailable = $message->toMailable('test@example.com', $notifiable);

    expect($mailable)->toBeInstanceOf(\Phare\Mail\Mailable::class);
});

test('sms message can be created', function () {
    $message = new SmsMessage('Hello World!');

    expect($message->getContent())->toBe('Hello World!');
});

test('sms message can set content', function () {
    $message = (new SmsMessage())->content('Updated content');

    expect($message->getContent())->toBe('Updated content');
});

test('sms message can set recipient', function () {
    $message = (new SmsMessage())->to('+1234567890');

    expect($message->getTo())->toBe('+1234567890');
    expect($message->hasTo())->toBeTrue();
});

test('sms message can set sender', function () {
    $message = (new SmsMessage())->from('+0987654321');

    expect($message->getFrom())->toBe('+0987654321');
});

test('sms message methods are chainable', function () {
    $message = (new SmsMessage())
        ->content('Test message')
        ->to('+1234567890')
        ->from('+0987654321');

    expect($message->getContent())->toBe('Test message');
    expect($message->getTo())->toBe('+1234567890');
    expect($message->getFrom())->toBe('+0987654321');
});

test('slack message can be created', function () {
    $message = new SlackMessage('Hello Slack!');

    expect($message->getText())->toBe('Hello Slack!');
});

test('slack message can set text', function () {
    $message = (new SlackMessage())->text('Updated text');

    expect($message->getText())->toBe('Updated text');
});

test('slack message can set channel', function () {
    $message = (new SlackMessage())->to('#general');

    expect($message->getChannel())->toBe('#general');
});

test('slack message can set webhook', function () {
    $message = (new SlackMessage())->webhook('https://hooks.slack.com/test');

    expect($message->getWebhook())->toBe('https://hooks.slack.com/test');
    expect($message->hasWebhook())->toBeTrue();
});

test('slack message can set username', function () {
    $message = (new SlackMessage())->username('Bot');

    expect($message->getUsername())->toBe('Bot');
});

test('slack message can set icon', function () {
    $message = (new SlackMessage())->icon(':robot:');

    expect($message->getIcon())->toBe(':robot:');
});

test('slack message can add attachment', function () {
    $attachment = ['title' => 'Test', 'text' => 'Attachment text'];
    $message = (new SlackMessage())->attachment($attachment);

    expect($message->getAttachments())->toBe([$attachment]);
});

test('slack message can set success style', function () {
    $message = (new SlackMessage('Success message'))->success();

    $attachments = $message->getAttachments();
    expect($attachments)->toHaveCount(1);
    expect($attachments[0]['color'])->toBe('good');
    expect($attachments[0]['text'])->toBe('Success message');
});

test('slack message can set error style', function () {
    $message = (new SlackMessage('Error message'))->error();

    $attachments = $message->getAttachments();
    expect($attachments)->toHaveCount(1);
    expect($attachments[0]['color'])->toBe('danger');
    expect($attachments[0]['text'])->toBe('Error message');
});

test('slack message can set warning style', function () {
    $message = (new SlackMessage('Warning message'))->warning();

    $attachments = $message->getAttachments();
    expect($attachments)->toHaveCount(1);
    expect($attachments[0]['color'])->toBe('warning');
    expect($attachments[0]['text'])->toBe('Warning message');
});

test('slack message methods are chainable', function () {
    $message = (new SlackMessage())
        ->text('Test message')
        ->to('#general')
        ->username('TestBot')
        ->icon(':test:');

    expect($message->getText())->toBe('Test message');
    expect($message->getChannel())->toBe('#general');
    expect($message->getUsername())->toBe('TestBot');
    expect($message->getIcon())->toBe(':test:');
});

test('mail message without action has no action', function () {
    $message = new MailMessage();

    expect($message->hasAction())->toBeFalse();
    expect($message->getActionText())->toBeNull();
    expect($message->getActionUrl())->toBeNull();
});

test('sms message without recipient has no recipient', function () {
    $message = new SmsMessage();

    expect($message->hasTo())->toBeFalse();
});

test('slack message without webhook has no webhook', function () {
    $message = new SlackMessage();

    expect($message->hasWebhook())->toBeFalse();
});
