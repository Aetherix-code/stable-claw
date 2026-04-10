<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\AIProvider;
use App\Services\AI\Providers\AnthropicProvider;
use App\Services\AI\Providers\GeminiProvider;
use App\Services\AI\Providers\OllamaProvider;
use App\Services\AI\Providers\OpenAIProvider;
use Illuminate\Support\Manager;
use OpenAI;

class AIManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return config('ai.default', 'openai');
    }

    public function connection(string $name): AIProvider
    {
        return $this->driver($name);
    }

    protected function createOpenaiDriver(): AIProvider
    {
        $config = $this->config('openai');

        $client = OpenAI::factory()
            ->withApiKey($config['api_key'])
            ->make();

        return new OpenAIProvider($client, $config['model']);
    }

    protected function createAnthropicDriver(): AIProvider
    {
        $config = $this->config('anthropic');

        return new AnthropicProvider(
            apiKey: $config['api_key'],
            model: $config['model'],
            baseUrl: $config['base_url'],
        );
    }

    protected function createOllamaDriver(): AIProvider
    {
        $config = $this->config('ollama');

        return new OllamaProvider(
            baseUrl: $config['base_url'],
            model: $config['model'],
        );
    }

    protected function createOllamaRemoteDriver(): AIProvider
    {
        $config = $this->config('ollama_remote');

        return new OllamaProvider(
            baseUrl: $config['base_url'],
            model: $config['model'],
        );
    }

    protected function createGeminiDriver(): AIProvider
    {
        $config = $this->config('gemini');

        return new GeminiProvider(
            apiKey: $config['api_key'],
            model: $config['model'],
            baseUrl: $config['base_url'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function config(string $name): array
    {
        return config("ai.connections.{$name}", []);
    }
}
