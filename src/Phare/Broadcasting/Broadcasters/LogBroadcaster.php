<?php

namespace Phare\Broadcasting\Broadcasters;

use Psr\Log\LoggerInterface;

class LogBroadcaster extends Broadcaster
{
    protected LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function auth(mixed $request): mixed
    {
        return true;
    }

    public function validAuthenticationResponse(mixed $request, mixed $result): mixed
    {
        return json_encode($result);
    }

    public function broadcast(array $channels, string $event, array $payload = []): void
    {
        $formattedChannels = $this->formatChannels($channels);

        $this->logger->info('Broadcasting event', [
            'event' => $event,
            'channels' => $formattedChannels,
            'payload' => $payload,
        ]);
    }
}
