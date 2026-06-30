<?php

namespace App\Services\Ezra;

interface LlmClientInterface
{
    public function configured(): bool;

    /**
     * @param  array<int, array{role:string, content:string}>  $messages
     * @return array{text:string, model:string, input_tokens:int, output_tokens:int}
     */
    public function send(string $system, array $messages, ?string $model = null): array;
}
