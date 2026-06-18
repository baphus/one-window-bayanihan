<?php

namespace App\Helpers;

final class DefaultServqualQuestions
{
    /**
     * Return the 22 standard SERVQUAL questions across 5 dimensions.
     *
     * @return array<int, array{dimension: string, question: string, order: int}>
     */
    public static function get(): array
    {
        return [
            // Tangibles (4 questions)
            ['dimension' => 'Tangibles', 'question' => 'The agency has modern-looking equipment', 'order' => 1],
            ['dimension' => 'Tangibles', 'question' => "The agency's physical facilities are visually appealing", 'order' => 2],
            ['dimension' => 'Tangibles', 'question' => "The agency's employees are well-dressed and appear neat", 'order' => 3],
            ['dimension' => 'Tangibles', 'question' => 'The appearance of the physical facilities of the agency is in keeping with the type of services provided', 'order' => 4],

            // Reliability (5 questions)
            ['dimension' => 'Reliability', 'question' => 'When the agency promises to do something by a certain time, they do so', 'order' => 5],
            ['dimension' => 'Reliability', 'question' => 'When you have problems, the agency is sympathetic and reassuring', 'order' => 6],
            ['dimension' => 'Reliability', 'question' => 'The agency is dependable', 'order' => 7],
            ['dimension' => 'Reliability', 'question' => 'The agency provides their services at the time they promise to do so', 'order' => 8],
            ['dimension' => 'Reliability', 'question' => 'The agency keeps their records accurately', 'order' => 9],

            // Responsiveness (4 questions)
            ['dimension' => 'Responsiveness', 'question' => 'The agency does not tell you exactly when services will be performed', 'order' => 10],
            ['dimension' => 'Responsiveness', 'question' => "You do NOT receive prompt service from the agency's employees", 'order' => 11],
            ['dimension' => 'Responsiveness', 'question' => "The agency's employees are NOT always willing to help you", 'order' => 12],
            ['dimension' => 'Responsiveness', 'question' => "The agency's employees are too busy to respond to your requests promptly", 'order' => 13],

            // Assurance (4 questions)
            ['dimension' => 'Assurance', 'question' => "You can trust the agency's employees", 'order' => 14],
            ['dimension' => 'Assurance', 'question' => 'You feel safe in your transactions with the agency', 'order' => 15],
            ['dimension' => 'Assurance', 'question' => "The agency's employees are polite", 'order' => 16],
            ['dimension' => 'Assurance', 'question' => "The agency's employees get adequate support from the agency to do their jobs well", 'order' => 17],

            // Empathy (5 questions)
            ['dimension' => 'Empathy', 'question' => 'The agency does NOT give you individual attention', 'order' => 18],
            ['dimension' => 'Empathy', 'question' => "The agency's employees do NOT give you personal attention", 'order' => 19],
            ['dimension' => 'Empathy', 'question' => "The agency's employees do NOT know what your needs are", 'order' => 20],
            ['dimension' => 'Empathy', 'question' => 'The agency does NOT have your best interests at heart', 'order' => 21],
            ['dimension' => 'Empathy', 'question' => "The agency's employees do NOT have operating hours convenient to all their customers", 'order' => 22],
        ];
    }

    /**
     * Get questions filtered by dimension.
     *
     * @return array<int, array{dimension: string, question: string, order: int}>
     */
    public static function getByDimension(string $dimension): array
    {
        return array_values(
            array_filter(self::get(), fn (array $q) => $q['dimension'] === $dimension)
        );
    }

    /**
     * Get all unique dimension names in order.
     *
     * @return list<string>
     */
    public static function dimensions(): array
    {
        return ['Tangibles', 'Reliability', 'Responsiveness', 'Assurance', 'Empathy'];
    }
}
