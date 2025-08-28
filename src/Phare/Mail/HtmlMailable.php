<?php

namespace Phare\Mail;

class HtmlMailable extends Mailable
{
    public function __construct(protected string $content, ?Message $message = null)
    {
        $this->htmlContent = $content;

        if ($message) {
            $this->to = $message->getTo();
            $this->cc = $message->getCc();
            $this->bcc = $message->getBcc();
            $this->replyTo = $message->getReplyTo();
            $this->subject = $message->getSubject();
        }
    }

    public function build(): void
    {
        // HTML mailable is already built
    }
}
