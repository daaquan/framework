<?php

namespace Phare\Broadcasting\Broadcasters;

use Phare\Broadcasting\BroadcastException;
use Pusher\Pusher;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class PusherBroadcaster extends Broadcaster
{
    protected Pusher $pusher;

    public function __construct(
        string $key,
        string $secret,
        string $appId,
        array $options = [],
        ?string $host = null,
        ?int $port = null,
        ?string $scheme = null
    ) {
        $this->pusher = new Pusher(
            $key,
            $secret,
            $appId,
            array_merge([
                'cluster' => 'mt1',
                'useTLS' => true,
            ], $options),
            $host,
            $port,
            null,
            $scheme
        );
    }

    public function auth(mixed $request): mixed
    {
        $channelName = $this->normalizeChannelName($request->get('channel_name'));

        if (empty($request->get('channel_name')) || !$this->isGuardedChannel($request->get('channel_name'))) {
            throw new AccessDeniedHttpException();
        }

        return parent::verifyUserCanAccessChannel($request, $channelName);
    }

    public function validAuthenticationResponse(mixed $request, mixed $result): mixed
    {
        if (is_bool($result)) {
            return json_encode($result);
        }

        $channelName = $request->get('channel_name');
        $socketId = $request->get('socket_id');

        return $this->decodePusherResponse(
            $request,
            $this->pusher->authorizeChannel($channelName, $socketId)
        );
    }

    public function broadcast(array $channels, string $event, array $payload = []): void
    {
        $socket = $payload['socket'] ?? null;

        $response = $this->pusher->trigger(
            $this->formatChannels($channels),
            $event,
            $payload,
            $socket ? ['socket_id' => $socket] : []
        );

        if ((is_array($response) && $response['status'] >= 200 && $response['status'] <= 299)
            || $response === true) {
            return;
        }

        throw new BroadcastException(
            is_bool($response) ? 'Failed to connect to Pusher.' : $response['body']
        );
    }

    public function broadcastToEveryone(array $channels, string $event, array $payload = []): void
    {
        unset($payload['socket']);

        $this->broadcast($channels, $event, $payload);
    }

    protected function decodePusherResponse(mixed $request, mixed $response): string
    {
        if (!$request->get('callback')) {
            return $response;
        }

        return $request->get('callback') . '(' . $response . ');';
    }

    public function getPusher(): Pusher
    {
        return $this->pusher;
    }
}
