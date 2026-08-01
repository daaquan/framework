<?php

namespace Phare\Broadcasting\Broadcasters;

use Phare\Broadcasting\AccessDeniedException;
use Phare\Broadcasting\BroadcastException;
use Pusher\Pusher;

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
        // Normalise only after the guard: a missing channel_name is null, and
        // normalizeChannelName() takes a string, so normalising first turned a
        // denial into a TypeError.
        $channel = (string)$request->get('channel_name');

        if ($channel === '' || !$this->isGuardedChannel($channel)) {
            throw new AccessDeniedException();
        }

        return parent::verifyUserCanAccessChannel($request, $this->normalizeChannelName($channel));
    }

    public function validAuthenticationResponse(mixed $request, mixed $result): mixed
    {
        // Deny the subscription if the authorization callback returns false.
        if ($result === false) {
            throw new AccessDeniedException();
        }

        $channelName = $request->get('channel_name');
        $socketId = $request->get('socket_id');

        // Presence channels require a signature containing member information. Pass the array
        // returned by the callback to Pusher signing as user_id / user_info.
        if (str_starts_with((string)$channelName, 'presence-')) {
            $userId = is_array($result)
                ? ($result['id'] ?? $result['user_id'] ?? null)
                : $result;
            $userInfo = is_array($result) ? ($result['user_info'] ?? $result) : [];

            return $this->decodePusherResponse(
                $request,
                $this->pusher->authorizePresenceChannel($channelName, $socketId, (string)$userId, $userInfo)
            );
        }

        // Return a signature token for private channels (only reached when authorization returns true).
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
