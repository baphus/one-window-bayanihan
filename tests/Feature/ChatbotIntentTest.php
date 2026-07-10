<?php

namespace Tests\Feature;

use App\Services\Chatbot\ChatbotIntentService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ChatbotIntentTest extends TestCase
{
    private ChatbotIntentService $intent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->intent = app(ChatbotIntentService::class);
    }

    public static function greetingFixtures(): array
    {
        return [
            ['hello'],
            ['Hi!'],
            ['hi po'],
            ['hey there'],
            ['Good morning'],
            ['good day po'],
            ['kumusta'],
            ['Kamusta po kayo'],
            ['magandang umaga'],
        ];
    }

    #[DataProvider('greetingFixtures')]
    public function test_greetings_detected(string $message): void
    {
        $this->assertSame(ChatbotIntentService::GREETING, $this->intent->classify($message));
    }

    public static function identityFixtures(): array
    {
        return [
            ['who are you'],
            ['Who are you?'],
            ['what are you'],
            ['what can you do'],
            ['how can you help me'],
            ['what is your name'],
            ['who is bayani'],
            ['are you a bot?'],
            ['are you human'],
            ['sino ka ba'],
        ];
    }

    #[DataProvider('identityFixtures')]
    public function test_identity_questions_detected(string $message): void
    {
        $this->assertSame(ChatbotIntentService::IDENTITY, $this->intent->classify($message));
    }

    public static function gibberishFixtures(): array
    {
        return [
            ['what?'],
            ['huh'],
            ['say that again'],
            ['come again?'],
            ['asdfgh'],
            ['jkl asdf asdfjkl'],
            ['??'],
            ['zzzzzzz'],
            ['the of an'],
        ];
    }

    #[DataProvider('gibberishFixtures')]
    public function test_gibberish_detected(string $message): void
    {
        $this->assertSame(ChatbotIntentService::GIBBERISH, $this->intent->classify($message));
    }

    public static function contentQueryFixtures(): array
    {
        return [
            ['how do I track my case'],
            ['hi, how do I track my case status?'], // greeting + question → content
            ['what services are available for OFWs?'],
            ['why is my OTP not arriving'],
            ['paano mag follow up sa kaso ko'],
            ['I lost my tracker number, what should I do?'],
            ['what does the status Under Review mean'],
            ['contact number of OWWA'],
        ];
    }

    #[DataProvider('contentQueryFixtures')]
    public function test_content_queries_detected(string $message): void
    {
        $this->assertSame(ChatbotIntentService::CONTENT_QUERY, $this->intent->classify($message));
    }

    // ── Follow-up candidate signal ──

    public static function followUpFixtures(): array
    {
        return [
            ['What documents do I need?', true],   // short, no own topic
            ['what about the requirements for that service', true], // deictic
            ['and then?', true],
            ['how long does it take?', true],
            ['can you explain it again in more detail please', true], // deictic "it"
            ['how do I use the public tracking portal to check my case status', false],
            ['what services does OWWA provide for repatriated overseas workers', false],
        ];
    }

    #[DataProvider('followUpFixtures')]
    public function test_follow_up_candidates(string $message, bool $expected): void
    {
        $this->assertSame($expected, $this->intent->isFollowUpCandidate($message), "Message: {$message}");
    }
}
