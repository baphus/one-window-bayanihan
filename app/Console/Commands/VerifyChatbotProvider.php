<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class VerifyChatbotProvider extends Command
{
    protected $signature = 'chatbot:verify-provider
        {--no-live : Skip the live prompt; only check configuration and model availability}';

    protected $description = 'Verify the configured chatbot provider, API key, and model are usable';

    /**
     * Exit code for a configuration problem the operator must fix.
     */
    private const CONFIG_FAILURE = 1;

    public function handle(): int
    {
        $enabled = (bool) config('ai-chatbot.enabled');
        $provider = (string) config('ai-chatbot.provider');
        $model = (string) config('ai-chatbot.model');

        $this->line("provider: {$provider}");
        $this->line('model: '.($model === '' ? '(empty)' : $model));
        $this->line('enabled: '.($enabled ? 'true' : 'false'));

        // A disabled chatbot is a deliberate state, not a fault. Report and stop
        // rather than failing a deploy that intentionally runs without the bot.
        if (! $enabled) {
            $this->warn('Chatbot is disabled (AI_CHATBOT_ENABLED is not true); nothing to verify.');

            return self::SUCCESS;
        }

        if ($model === '') {
            return $this->reportFailure('AI_CHATBOT_MODEL is empty.');
        }

        $credentials = config("ai.providers.{$provider}");

        if (! is_array($credentials)) {
            return $this->reportFailure("AI_CHATBOT_PROVIDER [{$provider}] is not defined in config/ai.php.");
        }

        // Gemini authenticates with x-goog-api-key; a null key is silently dropped
        // by the SDK client, which turns into an opaque auth failure at prompt time.
        $key = $credentials['key'] ?? null;

        if (! is_string($key) || trim($key) === '') {
            return $this->reportFailure("The API key for provider [{$provider}] is missing. Set the matching key environment variable (for example GEMINI_API_KEY).");
        }

        $this->line('api key: present ('.strlen($key).' characters)');

        if (! $this->modelIsAvailable($provider, $model, $key)) {
            return self::CONFIG_FAILURE;
        }

        // --no-live still issues one minimal call.
        //
        // The models.list endpoint advertises models that the account can no
        // longer call: gemini-2.5-flash appears there and then returns 404 "no
        // longer available to new users". Trusting the listing alone produced a
        // false pass on exactly the misconfiguration this command exists to
        // catch, so the offline mode verifies in the only way that is accurate —
        // by calling. A single short prompt is cheap and does not exercise tools.
        $live = $this->probeModel($provider, $model);

        if ($live === null) {
            return self::CONFIG_FAILURE;
        }

        if ($this->option('no-live')) {
            $this->info('Configuration and model availability verified.');

            return self::SUCCESS;
        }

        return self::reportProbe($live);
    }

    /**
     * Confirm the provider advertises the configured model.
     *
     * This is the check that catches a retired or misspelled model id before a
     * single user message reaches the API.
     */
    private function modelIsAvailable(string $provider, string $model, string $key): bool
    {
        $base = rtrim((string) ($this->credentials()['url'] ?? ''), '/');

        if ($provider !== 'gemini') {
            $this->line('model listing: skipped (only implemented for the gemini driver)');

            return true;
        }

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $key])
                ->timeout(20)
                ->get($base.'/models?pageSize=200');
        } catch (ConnectionException $e) {
            $this->error('Could not reach the provider to list models: '.$e->getMessage());

            return false;
        }

        if ($response->failed()) {
            $this->error("Model listing failed with HTTP {$response->status()}: ".$this->providerError($response->json()));
            $this->line('A 400/403 here normally means the API key is invalid.');

            return false;
        }

        $available = collect($response->json('models', []))
            ->filter(fn ($m) => in_array('generateContent', $m['supportedGenerationMethods'] ?? [], true))
            ->map(fn ($m) => str_replace('models/', '', (string) ($m['name'] ?? '')))
            ->filter()
            ->values();

        if ($available->contains($model)) {
            $this->info("model available: {$model}");

            return true;
        }

        $this->error("Model [{$model}] is not available to this API key.");

        // Suggest near matches rather than dumping ~45 model ids into the logs.
        $suggestions = $available
            ->filter(fn ($name) => str_contains($name, explode('-', $model)[1] ?? '###'))
            ->take(6)
            ->values();

        if ($suggestions->isNotEmpty()) {
            $this->line('Near matches: '.$suggestions->implode(', '));
        }

        return false;
    }

    /**
     * Send one minimal prompt through the SDK to prove the whole path works,
     * including that the model returns usable text within the token budget.
     */
    private function probeModel(string $provider, string $model): ?array
    {
        try {
            $response = Http::withHeaders(['x-goog-api-key' => (string) $this->credentials()['key']])
                ->timeout(min(20, max(1, (int) config('ai-chatbot.timeout'))))
                ->post(rtrim((string) $this->credentials()['url'], '/')."/models/{$model}:generateContent", [
                    'contents' => [['parts' => [['text' => 'Reply with exactly: OK']]]],
                    'generationConfig' => ['maxOutputTokens' => 64, 'temperature' => 0],
                ]);
        } catch (ConnectionException $e) {
            $this->error('Probe could not reach the provider: '.$e->getMessage());

            return null;
        }

        if ($response->failed()) {
            $this->error("Probe failed with HTTP {$response->status()}: ".$this->providerError($response->json()));

            return null;
        }

        $candidate = $response->json('candidates.0') ?? [];

        return [
            'text' => trim((string) ($candidate['content']['parts'][0]['text'] ?? '')),
            'finish' => (string) ($candidate['finishReason'] ?? ''),
        ];
    }

    /**
     * Interpret a successful probe.
     *
     * An empty reply is not automatically a failure: MAX_TOKENS means the model
     * spent the whole budget on hidden reasoning, which is a token-budget problem
     * rather than a broken provider. That is still worth failing on, because the
     * chatbot would return nothing useful, so it is reported with the remedy.
     */
    private function reportProbe(array $probe): int
    {
        ['text' => $text, 'finish' => $finish] = $probe;

        if ($text === '') {
            $this->error("Probe returned no text (finishReason: {$finish}).");

            if ($finish === 'MAX_TOKENS') {
                $this->line('The model consumed the whole output budget before answering. Raise AI_CHATBOT_MAX_TOKENS (the chatbot recommends 4000 for thinking-capable models).');
            }

            return self::CONFIG_FAILURE;
        }

        $this->info("Probe OK (finishReason: {$finish}, reply: {$text})");

        return self::SUCCESS;
    }

    /**
     * Get the provider connection configuration.
     */
    private function credentials(): array
    {
        return (array) config('ai.providers.'.config('ai-chatbot.provider'), []);
    }

    /**
     * Extract a readable message from a provider error payload.
     */
    private function providerError(mixed $json): string
    {
        $message = is_array($json) ? ($json['error']['message'] ?? $json['error'] ?? '') : '';

        return is_string($message) && $message !== '' ? mb_strimwidth($message, 0, 300, '…') : 'no error message returned';
    }

    /**
     * Report a configuration failure and return the exit code.
     */
    private function reportFailure(string $message): int
    {
        $this->error($message);

        return self::CONFIG_FAILURE;
    }
}
