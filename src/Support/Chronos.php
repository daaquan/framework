<?php

namespace Phare\Support;

if (!class_exists('\Chronos\Chronos')) {
    throw new \RuntimeException('Please install chronos-ext.');
}

class Chronos extends \Chronos\Chronos
{
    public static function parse($time = 'now', $timezone = null): static
    {
        return new static($time, $timezone);
    }

    public static function now($timezone = null): static
    {
        return new static('now');
    }

    public function copy(): static
    {
        return new static($this->format('Y-m-d H:i:s.u'), $this->getTimezone());
    }

    public function diffForHumans(Chronos $other, string $language = 'en'): string
    {
        $interval = $this->diff($other);
        $translation = $this->getTranslation($language);
        $suffix = $other < $this ? $translation['suffix_past'] : $translation['suffix_future'];

        return implode(' ', $this->getReadableInterval($interval, $translation)) . $suffix;
    }

    private function getTranslations(): array
    {
        return [
            'en' => [
                'suffix_past' => ' ago',
                'suffix_future' => ' from now',
                'year' => ' year',
                'month' => ' month',
                'day' => ' day',
                'hour' => ' hour',
                'minute' => ' minute',
                'second' => ' second',
            ],
            'ja' => [
                'suffix_past' => '前',
                'suffix_future' => '後',
                'year' => '年',
                'month' => 'ヶ月',
                'day' => '日',
                'hour' => '時間',
                'minute' => '分',
                'second' => '秒',
            ],
        ];
    }

    private function getTranslation(string $language): array
    {
        $translations = $this->getTranslations();

        if (!array_key_exists($language, $translations)) {
            throw new \RuntimeException(sprintf('Language %s is not supported.', $language));
        }

        return $translations[$language];
    }

    private function getReadableInterval(\DateInterval $interval, array $translation): array
    {
        $readable = [];
        foreach (
            [
                'y' => 'year',
                'm' => 'month',
                'd' => 'day',
                'h' => 'hour',
                'i' => 'minute',
                's' => 'second',
            ] as $key => $unit
        ) {
            if ($interval->$key > 0) {
                $readable[] = $interval->$key . ($interval->$key > 1 ?
                        \Phare\Collections\Str::pluralize($translation[$unit]) : $translation[$unit]);
            }

            if (count($readable) > 1) {
                break;
            }
        }

        return $readable;
    }

    // @mixin \Spatie\PestPluginTestTime\TestTime
    public function freeze(\DateTimeInterface $dateTime)
    {
        if (!class_exists('\Spatie\PestPluginTestTime\TestTime')) {
            throw new \RuntimeException('Please install spatie/pest-plugin-test-time');
        }

        $dateTime = $dateTime->format('Y-m-d H:i:s');

        return (new \Spatie\PestPluginTestTime\TestTime())->freeze($dateTime);
    }
}
