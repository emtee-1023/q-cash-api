<?php

namespace Tests\Fixtures;

use Markt\LaravelAuth\Contracts\SmsSender;

class FakeSmsSender implements SmsSender
{
    /**
     * @var array<string, list<string>>
     */
    public array $messages = [];

    public function send(string $phoneNumber, string $message): void
    {
        $this->messages[$phoneNumber][] = $message;
    }

    public function lastMessageFor(string $phoneNumber): ?string
    {
        $messages = $this->messages[$phoneNumber] ?? [];

        return $messages === [] ? null : $messages[array_key_last($messages)];
    }
}
