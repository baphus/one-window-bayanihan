<?php

namespace App\Services\Chatbot;

class ChatbotGuideService
{
    /** Maps topic keys to their markdown heading and a classifier-friendly description. */
    private array $topicMap = [
        'overview' => [
            'heading' => 'What is the Bayanihan One Window System?',
            'description' => 'General overview of the system, what it does, and partner agencies',
        ],
        'case_tracking' => [
            'heading' => 'How to Check Case Status Using a Tracker Number',
            'description' => 'Step-by-step process for tracking a case with a tracker number',
        ],
        'required_info' => [
            'heading' => 'What Information You Need',
            'description' => 'What details are needed (tracker number and email) to look up a case',
        ],
        'otp' => [
            'heading' => 'How the OTP Verification Process Works',
            'description' => 'How the one-time passcode verification works, including rules and time limits',
        ],
        'statuses' => [
            'heading' => 'What Different Case Statuses Mean',
            'description' => 'Explanation of all case statuses: Submitted, Under Review, Approved, Rejected, Completed',
        ],
        'contacts' => [
            'heading' => 'Contact Information for Partner Agencies',
            'description' => 'Contact details, hotlines, websites, and services for DMW, OWWA, TESDA, DSWD, DOLE',
        ],
        'troubleshooting' => [
            'heading' => 'Troubleshooting Tips',
            'description' => 'Common issues like lost tracker number, expired OTP, wrong email, OTP not arriving, stuck cases',
        ],
        'privacy' => [
            'heading' => 'Data Privacy Reminder',
            'description' => 'Data privacy guidelines and security reminders',
        ],
    ];

    private ?array $sections = null;

    /**
     * Return the content of the requested guide sections, formatted as markdown.
     * If a topic is not recognised, it is silently skipped.
     *
     * @param  list<string>  $topics
     */
    public function getSections(array $topics): string
    {
        $all = $this->loadSections();
        $parts = [];

        foreach ($topics as $topic) {
            if (isset($all[$topic])) {
                $heading = $this->topicMap[$topic]['heading'] ?? ucfirst($topic);
                $parts[] = "## {$heading}\n\n".trim($all[$topic]);
            }
        }

        return implode("\n\n---\n\n", $parts);
    }

    /**
     * Return the full reference guide (all sections).
     */
    public function getAll(): string
    {
        return $this->getSections(array_keys($this->topicMap));
    }

    /**
     * Return all topics with their heading and description.
     *
     * @return array<string, array{heading: string, description: string}>
     */
    public function getAllTopics(): array
    {
        return $this->topicMap;
    }

    /**
     * Return a classifier-friendly listing of available topics and their descriptions.
     */
    public function getTopicList(): string
    {
        $lines = [];
        foreach ($this->topicMap as $key => $info) {
            $lines[] = "- {$key}: {$info['description']}";
        }

        return implode("\n", $lines);
    }

    /** @return array<string, string> Section content keyed by topic. */
    private function loadSections(): array
    {
        if ($this->sections !== null) {
            return $this->sections;
        }

        $path = resource_path('guides/ofw-case-tracking.md');
        if (! file_exists($path)) {
            return $this->sections = [];
        }

        $content = file_get_contents($path);
        $sections = [];

        // Split on ## headings (the guide uses ## for section headings)
        $parts = preg_split('/^##\s+/m', $content);

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            // The first line after the split is the heading title
            $lines = explode("\n", $part);
            $heading = trim(array_shift($lines));
            $body = trim(implode("\n", $lines));

            // Map heading to topic key
            $key = $this->resolveKey($heading);
            if ($key !== null) {
                $sections[$key] = $body;
            }
        }

        return $this->sections = $sections;
    }

    private function resolveKey(string $heading): ?string
    {
        foreach ($this->topicMap as $key => $info) {
            if (strcasecmp($info['heading'], $heading) === 0) {
                return $key;
            }
        }

        return null;
    }
}
