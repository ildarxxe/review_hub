<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Services\OrganizationSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncOrganization implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly int $organizationId,
        public readonly string $sourceUrl,
    ) {
        $this->onQueue('sync');
    }

    public function handle(OrganizationSyncService $syncService): void
    {
        $organization = Organization::query()->find($this->organizationId);

        if (
            ! $organization
            || $organization->source_url !== rtrim($this->sourceUrl, '/')
        ) {
            return;
        }

        $syncService->sync($organization, $this->sourceUrl);
    }

    public function failed(?Throwable $exception): void
    {
        $organization = Organization::query()->find($this->organizationId);

        if (
            ! $organization
            || $organization->source_url !== rtrim($this->sourceUrl, '/')
            || $organization->sync_status === 'failed'
        ) {
            return;
        }

        $organization->update([
            'sync_status' => 'failed',
            'sync_error' => 'Синхронизация была прервана. Попробуйте сохранить ссылку ещё раз.',
        ]);
    }
}
