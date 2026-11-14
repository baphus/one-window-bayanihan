<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CaseDocument;
use App\Models\CaseFile;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Tests\TestCase;

class CaseDossierExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_exports_labeled_pdf_and_image_annexes(): void
    {
        Storage::fake('object-storage');
        config(['filesystems.default' => 'object-storage']);
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $case = CaseFile::factory()->withIntake()->create(['user_id' => $manager->id]);
        $pdf = Pdf::loadHTML('<p>Passport evidence</p>')->output();
        $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a0WQAAAAASUVORK5CYII=');
        foreach ([['passport_front_page', 'application/pdf', 'passport.pdf', $pdf], ['arrival_stamp', 'image/png', 'stamp.png', $image]] as [$category, $type, $filename, $bytes]) {
            $path = 'case-documents/'.$case->id.'/'.$filename;
            Storage::disk('object-storage')->put($path, $bytes);
            CaseDocument::factory()->create(['case_id' => $case->id, 'category' => $category, 'referral_id' => null, 'file_path' => $path, 'file_name' => $filename, 'file_type' => $type]);
        }

        $response = $this->actingAsWithMfa($manager)->get(route('cases.export-pdf', $case->id));

        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertGreaterThan(2, (new Fpdi)->setSourceFile(StreamReader::createByString($response->getContent())));
    }

    public function test_missing_required_annex_fails_without_partial_export(): void
    {
        Storage::fake('object-storage');
        config(['filesystems.default' => 'object-storage']);
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $case = CaseFile::factory()->withIntake()->create();
        CaseDocument::factory()->create(['case_id' => $case->id, 'category' => 'passport_front_page', 'referral_id' => null, 'file_path' => 'missing.pdf']);

        $this->actingAsWithMfa($manager)->get(route('cases.export-pdf', $case->id))->assertStatus(422);
    }

    public function test_agency_cannot_export_internal_dossier(): void
    {
        $agency = User::factory()->create(['role' => UserRole::AGENCY->value]);
        $case = CaseFile::factory()->withIntake()->create();

        $this->actingAsWithMfa($agency)->get(route('cases.export-pdf', $case->id))->assertForbidden();
    }
}
