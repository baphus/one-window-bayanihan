<?php

namespace Tests\Feature\ReferralClientInbox;

use App\DTOs\FileStoreResult;
use App\Enums\UserRole;
use App\Models\ReferralClientMessage;
use App\Models\User;
use App\Services\ReferralClientRequestService;
use App\Services\StorageService;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use Mockery\MockInterface;

class ClientReplyFailureTest extends ReferralClientInboxTestCase
{
    /**
     * The object store is down: the client must see a fixed sentence (never
     * the bucket or endpoint) and no reply row may survive the failure.
     */
    public function test_a_storage_outage_hides_its_internals_and_writes_nothing(): void
    {
        $context = $this->ownerContext();
        $this->fakeStorage(fn (int $call) => $this->failedStore());

        try {
            app(ReferralClientRequestService::class)->sendClientMessage(
                $context['request'],
                'Here you go',
                $context['owner'],
                [$this->pdf()],
                [],
            );
            $this->fail('A failed upload should reject the reply.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('We could not save your file. Please try again.', $e->getMessage());
        }

        $this->assertSame(0, ReferralClientMessage::query()->where('request_id', $context['request']->id)->count());
    }

    /**
     * The first file stores, the second fails: the already-stored object is
     * deleted again so the bucket does not collect orphans.
     */
    public function test_a_later_failure_deletes_the_file_already_stored(): void
    {
        $context = $this->ownerContext();
        $deleted = [];
        $this->fakeStorage(
            fn (int $call) => $call === 1
                ? new FileStoreResult('client-request-attachments/first.pdf', 'first.pdf', 'first.pdf', 'application/pdf', 10)
                : $this->failedStore(),
            $deleted,
        );

        try {
            app(ReferralClientRequestService::class)->sendClientMessage(
                $context['request'],
                'Two files',
                $context['owner'],
                [$this->pdf('first.pdf'), $this->pdf('second.pdf')],
                [],
            );
            $this->fail('A failed second upload should reject the reply.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('We could not save your file. Please try again.', $e->getMessage());
        }

        $this->assertSame(['client-request-attachments/first.pdf'], $deleted);
        $this->assertSame(0, ReferralClientMessage::query()->where('request_id', $context['request']->id)->count());
    }

    private function ownerContext(): array
    {
        $context = $this->context();
        $this->issue($context);

        return [
            'request' => $context['clientRequest'],
            'owner' => User::factory()->create([
                'role' => UserRole::OFW->value,
                'client_id' => $context['client']->id,
            ]),
        ];
    }

    /**
     * Replace the storage service: validation passes, store() follows the
     * given rule, and deletions are recorded so the test can inspect them.
     */
    private function fakeStorage(\Closure $store, array &$deleted = []): void
    {
        $call = 0;
        $this->mock(StorageService::class, function (MockInterface $mock) use ($store, &$call, &$deleted) {
            $mock->shouldReceive('validate')->andReturn([]);
            $mock->shouldReceive('store')->andReturnUsing(function () use ($store, &$call) {
                $call++;

                return $store($call);
            });
            $mock->shouldReceive('delete')->andReturnUsing(function (string $path) use (&$deleted) {
                $deleted[] = $path;

                return true;
            });
        });
    }

    private function failedStore(): FileStoreResult
    {
        return new FileStoreResult(
            path: '',
            originalName: 'passport.pdf',
            storedName: 'passport.pdf',
            type: 'application/pdf',
            size: 10,
            success: false,
            error: 'Storage error: bucket case-files unreachable at https://storage.internal',
        );
    }

    private function pdf(string $name = 'passport.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 10, 'application/pdf');
    }
}
