<?php

use Phare\Mail\Mailable;
use Phare\Mail\Mailer;
use Phare\Mail\MailException;
use Phare\Mail\Message;

// Create a test mailable
class TestMailable extends Mailable
{
    public function build(): void
    {
        $this->subject('Test Subject')
            ->html('<h1>Hello {{ $name }}!</h1>')
            ->text('Hello {{ $name }}!')
            ->with('name', 'World');
    }
}

class TestMailableWithView extends Mailable
{
    public function build(): void
    {
        $this->subject('Test with View')
            ->view('<h1>Welcome {{ $name }}</h1>')
            ->with('name', 'User');
    }
}

beforeEach(function () {
    $this->config = [
        'driver' => 'smtp',
        'host' => 'localhost',
        'port' => 587,
        'encryption' => 'tls',
        'username' => 'test@example.com',
        'password' => 'password',
        'from' => [
            'address' => 'noreply@example.com',
            'name' => 'Test App',
        ],
    ];
});

test('mailer can be instantiated with config', function () {
    $mailer = new Mailer($this->config);

    expect($mailer)->toBeInstanceOf(Mailer::class);
    expect($mailer->getConfig()['host'])->toBe('localhost');
    expect($mailer->getConfig()['from']['address'])->toBe('noreply@example.com');
});

test('mailer uses default config when not provided', function () {
    $mailer = new Mailer();

    expect($mailer->getConfig()['driver'])->toBe('smtp');
    expect($mailer->getConfig()['host'])->toBe('localhost');
    expect($mailer->getConfig()['from']['address'])->toBe('noreply@example.com');
});

test('mailable can be built with recipients', function () {
    $mailable = new TestMailable();
    $mailable->to('test@example.com', 'Test User')
        ->cc('cc@example.com')
        ->bcc('bcc@example.com')
        ->replyTo('reply@example.com', 'Reply User');

    $mailable->build();

    expect($mailable->getTo())->toBe(['test@example.com' => 'Test User']);
    expect($mailable->getCc())->toBe(['cc@example.com' => null]);
    expect($mailable->getBcc())->toBe(['bcc@example.com' => null]);
    expect($mailable->getReplyTo())->toBe(['reply@example.com' => 'Reply User']);
    expect($mailable->getSubject())->toBe('Test Subject');
});

test('mailable supports array recipients', function () {
    $mailable = new TestMailable();
    $mailable->to(['user1@example.com' => 'User One', 'user2@example.com' => 'User Two']);

    expect($mailable->getTo())->toBe([
        'user1@example.com' => 'User One',
        'user2@example.com' => 'User Two',
    ]);
});

test('mailable handles html and text content', function () {
    $mailable = new TestMailable();
    $mailable->build();

    expect($mailable->hasHtml())->toBeTrue();
    expect($mailable->hasText())->toBeTrue();
    expect($mailable->getHtmlBody())->toBe('<h1>Hello World!</h1>');
    expect($mailable->getTextBody())->toBe('Hello World!');
});

test('mailable can render views', function () {
    $mailable = new TestMailableWithView();
    $mailable->build();

    expect($mailable->hasHtml())->toBeTrue();
    expect($mailable->getHtmlBody())->toBe('<h1>Welcome User</h1>');
    expect($mailable->getTextBody())->toBe('Welcome User'); // stripped HTML
});

test('mailable can have attachments', function () {
    $mailable = new TestMailable();
    $mailable->attach('/path/to/file.pdf', 'document.pdf', 'application/pdf')
        ->attachData('file content', 'test.txt', 'text/plain');

    $attachments = $mailable->getAttachments();

    expect($attachments)->toHaveCount(2);
    expect($attachments[0]['path'])->toBe('/path/to/file.pdf');
    expect($attachments[0]['name'])->toBe('document.pdf');
    expect($attachments[1]['data'])->toBe('file content');
    expect($attachments[1]['name'])->toBe('test.txt');
});

test('mailable can have custom headers', function () {
    $mailable = new TestMailable();
    $mailable->header('X-Custom-Header', 'custom-value')
        ->header('X-Priority', '1');

    expect($mailable->getHeaders())->toBe([
        'X-Custom-Header' => 'custom-value',
        'X-Priority' => '1',
    ]);
});

test('mailable supports with method for data', function () {
    $mailable = new TestMailable();
    $mailable->with('key', 'value')
        ->with(['name' => 'John', 'email' => 'john@example.com']);

    $data = $mailable->getData();
    expect($data)->toHaveKey('key', 'value');
    expect($data)->toHaveKey('name', 'John');
    expect($data)->toHaveKey('email', 'john@example.com');
});

test('message class works correctly', function () {
    $message = new Message();
    $message->to('test@example.com', 'Test User')
        ->cc('cc@example.com')
        ->bcc('bcc@example.com')
        ->replyTo('reply@example.com')
        ->subject('Test Message');

    expect($message->getTo())->toBe(['test@example.com' => 'Test User']);
    expect($message->getCc())->toBe(['cc@example.com' => null]);
    expect($message->getBcc())->toBe(['bcc@example.com' => null]);
    expect($message->getReplyTo())->toBe(['reply@example.com' => null]);
    expect($message->getSubject())->toBe('Test Message');
});

test('mailer can send mailable and track sent mails', function () {
    $mailer = new Mailer($this->config);
    $mailable = new TestMailable();
    $mailable->to('test@example.com', 'Test User');

    $result = $mailer->send($mailable);

    expect($result)->toBeTrue();
    expect($mailer->getSentMails())->toHaveCount(1);

    $sentMail = $mailer->getSentMails()[0];
    expect($sentMail['to'])->toBe(['test@example.com' => 'Test User']);
    expect($sentMail['subject'])->toBe('Test Subject'); // This comes from TestMailable build()
    expect($sentMail['htmlBody'])->toContain('Hello World!');
});

test('mailer can clear sent mails', function () {
    $mailer = new Mailer($this->config);
    $mailable = new TestMailable();
    $mailable->to('test@example.com');

    $mailer->send($mailable);
    expect($mailer->getSentMails())->toHaveCount(1);

    $mailer->clearSentMails();
    expect($mailer->getSentMails())->toHaveCount(0);
});

test('mailer raw method works', function () {
    $mailer = new Mailer($this->config);

    $result = $mailer->raw('Plain text message', function ($message) {
        $message->to('test@example.com')
            ->subject('Raw message test');
    });

    expect($result)->toBeTrue();
    expect($mailer->getSentMails())->toHaveCount(1);

    $sentMail = $mailer->getSentMails()[0];
    expect($sentMail['textBody'])->toBe('Plain text message');
    expect($sentMail['subject'])->toBe('Raw message test');
});

test('mailer html method works', function () {
    $mailer = new Mailer($this->config);

    $result = $mailer->html('<h1>HTML message</h1>', function ($message) {
        $message->to('test@example.com')
            ->subject('HTML message test');
    });

    expect($result)->toBeTrue();
    expect($mailer->getSentMails())->toHaveCount(1);

    $sentMail = $mailer->getSentMails()[0];
    expect($sentMail['htmlBody'])->toBe('<h1>HTML message</h1>');
    expect($sentMail['subject'])->toBe('HTML message test');
});

test('mail exception can be thrown', function () {
    $exception = new MailException('Test exception');

    expect($exception)->toBeInstanceOf(MailException::class);
    expect($exception->getMessage())->toBe('Test exception');
});

test('mailable text fallback from html', function () {
    $mailable = new class() extends Mailable
    {
        public function build(): void
        {
            $this->html('<h1>Title</h1><p>Content</p>');
        }
    };

    $mailable->build();

    expect($mailable->hasHtml())->toBeTrue();
    expect($mailable->hasText())->toBeFalse();
    expect($mailable->getTextBody())->toBe('TitleContent'); // HTML stripped
});

test('mailable only text content', function () {
    $mailable = new class() extends Mailable
    {
        public function build(): void
        {
            $this->text('Plain text content');
        }
    };

    $mailable->build();

    expect($mailable->hasHtml())->toBeFalse();
    expect($mailable->hasText())->toBeTrue();
    expect($mailable->getTextBody())->toBe('Plain text content');
});
