<?php

namespace App\Services\Ai;

interface AiProvider
{
    public function sendMessage(string $message, array $context = []): string;

    public function isConfigured(): bool;

    public function getModel(): string;

    public function setModel(string $model): void;
}
