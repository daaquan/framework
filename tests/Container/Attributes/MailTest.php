<?php

declare(strict_types=1);

namespace Tests\Container\Attributes;

use Phare\Container\Attributes\Mail;
use Phare\Container\Container;
use PHPUnit\Framework\TestCase;

class MailTest extends TestCase
{
    public function test_resolves_default_mailer_when_no_arg(): void
    {
        $container = new Container();
        $defaultMailer = (object)['name' => 'default-mailer'];
        $container->singleton('mailer', fn () => $defaultMailer);

        $consumer = $container->make(MailDefaultStubConsumer::class);

        $this->assertSame($defaultMailer, $consumer->mailer);
    }

    public function test_resolves_named_mailer_via_mail_manager(): void
    {
        $container = new Container();
        $manager = new FakeMailManager();

        $container->singleton('mail.manager', fn () => $manager);
        $container->singleton('mailer', fn () => new \stdClass());

        $consumer = $container->make(MailNamedStubConsumer::class);

        $this->assertSame('smtp-mailer', $consumer->mailer);
    }
}

class FakeMailManager
{
    public function mailer(?string $name = null): string
    {
        return $name === null ? 'default-mailer' : "$name-mailer";
    }
}

class MailDefaultStubConsumer
{
    public function __construct(#[Mail] public mixed $mailer) {}
}

class MailNamedStubConsumer
{
    public function __construct(#[Mail('smtp')] public mixed $mailer) {}
}
