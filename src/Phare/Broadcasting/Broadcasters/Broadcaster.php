<?php

namespace Phare\Broadcasting\Broadcasters;

abstract class Broadcaster
{
    protected array $channels = [];

    protected array $binding = [];

    abstract public function auth(mixed $request): mixed;

    abstract public function validAuthenticationResponse(mixed $request, mixed $result): mixed;

    abstract public function broadcast(array $channels, string $event, array $payload = []): void;

    public function channel(string $channel, callable $callback): void
    {
        $this->channels[$channel] = $callback;
    }

    protected function normalizeChannelName(string $channel): string
    {
        if (str_starts_with($channel, 'private-')) {
            return substr($channel, 8);
        }

        if (str_starts_with($channel, 'presence-')) {
            return substr($channel, 9);
        }

        return $channel;
    }

    protected function isGuardedChannel(string $channel): bool
    {
        return str_starts_with($channel, 'private-') || str_starts_with($channel, 'presence-');
    }

    protected function retrieveUser(mixed $request, string $channel): mixed
    {
        $options = $this->retrieveChannelOptions($channel);
        $guards = $options['guards'] ?? null;

        if (is_null($guards)) {
            return $request->user();
        }

        foreach (is_array($guards) ? $guards : [$guards] as $guard) {
            if ($user = $request->user($guard)) {
                return $user;
            }
        }

        return null;
    }

    protected function retrieveChannelOptions(string $channel): array
    {
        return $this->binding[
            str_replace(['private-', 'presence-'], '', $channel)
        ] ?? [];
    }

    public function resolveBinding(mixed $request, string $channel): mixed
    {
        $channelName = $this->normalizeChannelName($channel);

        [$callback, $parameters] = $this->matchChannel($channelName);

        if ($callback === null) {
            return false;
        }

        $user = $this->retrieveUser($request, $channel);

        // Laravel 同様、抽出したチャンネルパラメータをユーザーの後に展開して渡す。
        // 例: 'App.User.{id}' に private-App.User.42 → $callback($user, '42')。
        return $callback($user, ...$parameters);
    }

    /**
     * 登録チャンネルから完全一致、なければ {param} ワイルドカード一致を探す。
     * 一致したコールバックと抽出パラメータ（順序どおり）を返す。
     *
     * @return array{0: ?callable, 1: array<int, string>}
     */
    protected function matchChannel(string $channelName): array
    {
        if (isset($this->channels[$channelName])) {
            return [$this->channels[$channelName], []];
        }

        foreach ($this->channels as $pattern => $callback) {
            if (!str_contains($pattern, '{')) {
                continue;
            }

            $segments = array_map(
                fn ($segment) => preg_match('/^\{[^}]+\}$/', $segment) === 1
                    ? '([^.]+)'
                    : preg_quote($segment, '/'),
                explode('.', $pattern)
            );

            if (preg_match('/^' . implode('\.', $segments) . '$/', $channelName, $matches) === 1) {
                array_shift($matches);

                return [$callback, array_values($matches)];
            }
        }

        return [null, []];
    }

    protected function verifyUserCanAccessChannel(mixed $request, string $channel): mixed
    {
        foreach ($this->extractParameters($channel) as $i => $parameter) {
            $request->route()->setParameter("channel_param_{$i}", $parameter);
        }

        return $this->resolveBinding($request, $channel);
    }

    protected function extractParameters(string $channel): array
    {
        if (!str_contains($channel, '.')) {
            return [];
        }

        return explode('.', $channel);
    }

    protected function extractChannelKeys(array $channels): array
    {
        return array_map(function ($channel) {
            if (is_string($channel)) {
                return $channel;
            }

            return $channel->name;
        }, $channels);
    }

    protected function formatChannels(array $channels): array
    {
        return array_map(function ($channel) {
            if (is_string($channel)) {
                return $channel;
            }

            return $channel->name;
        }, $channels);
    }

    public function resolveImplicitBindingIfPossible(string $key, mixed $value): mixed
    {
        return $value;
    }
}
