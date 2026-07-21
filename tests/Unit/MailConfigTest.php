<?php

namespace Tests\Unit;

use Illuminate\Mail\Markdown;
use Tests\TestCase;

class MailConfigTest extends TestCase
{
    public function test_mail_markdown_paths_is_configured(): void
    {
        $paths = config('mail.markdown.paths');
        $this->assertNotEmpty($paths, 'mail.markdown.paths should not be empty');

        $markdown = app(Markdown::class);
        $htmlPaths = $markdown->htmlComponentPaths();
        $this->assertNotEmpty($htmlPaths);

        $view = app('view');
        $view->replaceNamespace('mail', $htmlPaths);

        $exists = $view->exists('mail::status-badge');
        $this->assertTrue($exists, 'mail::status-badge should be resolvable');

        $exists2 = $view->exists('mail::message');
        $this->assertTrue($exists2, 'mail::message should be resolvable');
    }
}
