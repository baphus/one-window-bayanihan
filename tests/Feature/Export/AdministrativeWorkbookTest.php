<?php

namespace Tests\Feature\Export;

use App\Enums\UserRole;
use App\Models\CaseDocument;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\ReferralClientMessage;
use App\Models\ReferralClientRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdministrativeWorkbookTest extends TestCase
{
    use DatabaseMigrations;

    #[Test]
    public function populated_workbook_has_only_allowlisted_operational_content(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $case = CaseFile::factory()->withIntake()->create([
            'created_at' => '2036-06-01 10:00:00', 'summary' => 'SENTINEL-NARRATIVE',
            'first_name' => 'SENTINEL-NAME', 'email' => 'sentinel@example.test',
            'contact_number' => 'SENTINEL-CONTACT', 'address_street' => 'SENTINEL-ADDRESS',
            'case_number' => '=SENTINEL-REFERENCE',
        ]);
        $referral = Referral::factory()->create(['case_id' => $case->id, 'created_at' => '2037-01-02 10:00:00', 'notes' => 'SENTINEL-REFERRAL']);
        CaseDocument::factory()->create([
            'case_id' => $case->id, 'user_id' => $admin->id,
            'file_name' => 'SENTINEL-DOCUMENT.pdf', 'file_path' => 'private/SENTINEL-STORAGE.pdf',
        ]);
        $request = ReferralClientRequest::factory()->create(['referral_id' => $referral->id]);
        ReferralClientMessage::factory()->create([
            'request_id' => $request->id, 'body' => 'SENTINEL-INBOX', 'user_id' => $admin->id,
        ]);
        config(['services.administrative_fixture_secret' => 'SENTINEL-CONFIG-SECRET']);
        $response = $this->actingAsWithMfa($admin)->get(route('admin.exports.administrative', ['from' => '2036-01-01', 'to' => '2036-12-31']));
        $response->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('cache-control'));
        $content = $response->streamedContent();
        $this->assertSame('PK', substr($content, 0, 2));
        $this->assertStringNotContainsString('SENTINEL-NAME', $content);
        $this->assertStringNotContainsString('SENTINEL-NARRATIVE', $content);
        $this->assertStringNotContainsString('sentinel@example.test', $content);
        $path = tempnam(sys_get_temp_dir(), 'admin-inspect-');
        file_put_contents($path, $content);
        try {
            $book = IOFactory::load($path);
            $this->assertSame(['Scope & Definitions', 'Management Summary', 'Cases', 'Referrals', 'Client Statistics'], $book->getSheetNames());
            $this->assertSame(['Case reference (restricted)', 'Created date', 'Status', 'Categories', 'Exported referral count'], $book->getSheetByName('Cases')->toArray()[0]);
            $this->assertSame(['Referral reference (restricted)', 'Case reference (restricted)', 'Agency', 'Sent date', 'Status', 'Completion date'], $book->getSheetByName('Referrals')->toArray()[0]);
            $this->assertSame("'".$case->case_number, $book->getSheetByName('Cases')->getCell('A2')->getValue());
            $this->assertSame('s', $book->getSheetByName('Cases')->getCell('A2')->getDataType());
            $this->assertSame("'".$case->case_number, $book->getSheetByName('Referrals')->getCell('B2')->getValue());
            $zip = new \ZipArchive;
            $this->assertTrue($zip->open($path));
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $xml = $zip->getFromIndex($i);
                foreach (['SENTINEL-NAME', 'SENTINEL-NARRATIVE', 'sentinel@example.test', 'SENTINEL-REFERRAL', '1990-06-15', 'SENTINEL-DOCUMENT', 'SENTINEL-STORAGE', 'SENTINEL-INBOX', 'SENTINEL-CONFIG-SECRET', 'SENTINEL-CONTACT', 'SENTINEL-ADDRESS'] as $private) {
                    $this->assertStringNotContainsString($private, $xml);
                }
            }
            $zip->close();
            $book->disconnectWorksheets();
        } finally {
            @unlink($path);
        }
    }
}
