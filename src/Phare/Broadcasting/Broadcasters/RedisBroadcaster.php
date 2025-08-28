<?php

namespace Phare\Broadcasting\Broadcasters;

use Phare\Broadcasting\BroadcastException;
use Predis\Client as PredisClient;
use Redis;

class RedisBroadcaster extends Broadcaster
{
    protected mixed $redis;

    protected string $connection;

    public function __construct(mixed $redis, string $connection = 'default')
    {
        $this->redis = $redis;
        $this->connection = $connection;
    }

    public function auth(mixed $request): mixed
    {
        if (str_starts_with($request->get('channel_name'), 'private-') ||
            str_starts_with($request->get('channel_name'), 'presence-')) {
            return $this->verifyUserCanAccessChannel(
                $request,
                str_replace(['private-', 'presence-'], '', $request->get('channel_name'))
            );
        }

        return true;
    }

    public function validAuthenticationResponse(mixed $request, mixed $result): mixed
    {
        if (is_bool($result)) {
            return json_encode($result);
        }

        return json_encode(['channel_data' => [
            'user_id' => $request->user()->id,
            'user_info' => $result,
        ]]);
    }

    public function broadcast(array $channels, string $event, array $payload = []): void
    {
        $connection = $this->redis->connection($this->connection);

        $socket = $payload['socket'] ?? null;

        foreach ($this->formatChannels($channels) as $channel) {
            $this->publishToRedis($connection, $channel, $event, $payload, $socket);
        }
    }

    protected function publishToRedis(mixed $connection, string $channel, string $event, array $payload, ?string $socket = null): void
    {
        $data = json_encode([
            'event' => $event,
            'data' => $payload,
            'socket' => $socket,
        ]);

        if ($connection instanceof Redis) {
            $connection->publish($channel, $data);
        } elseif ($connection instanceof PredisClient) {
            $connection->publish($channel, $data);
        } else {
            throw new BroadcastException('Unsupported Redis connection type.');
        }
    }

    public function getRedis(): mixed
    {
        return $this->redis;
    }
}
