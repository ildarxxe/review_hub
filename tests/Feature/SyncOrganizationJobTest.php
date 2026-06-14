<?php

namespace Tests\Feature;

use App\Jobs\SyncOrganization;
use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class SyncOrganizationJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_syncs_current_organization_url(): void
    {
        $organization = $this->organization('https://yandex.ru/maps/org/company/123');
        $service = Mockery::mock(OrganizationSyncService::class);
        $service->shouldReceive('sync')
            ->once()
            ->withArgs(fn (Organization $model, string $url) => $model->is($organization)
                && $url === $organization->source_url);

        (new SyncOrganization(
            $organization->id,
            $organization->source_url,
        ))->handle($service);
    }

    public function test_job_skips_organization_when_url_has_changed(): void
    {
        $organization = $this->organization('https://yandex.ru/maps/org/new-company/456');
        $service = Mockery::mock(OrganizationSyncService::class);
        $service->shouldNotReceive('sync');

        (new SyncOrganization(
            $organization->id,
            'https://yandex.ru/maps/org/old-company/123',
        ))->handle($service);
    }

    public function test_failed_job_marks_current_organization_as_failed(): void
    {
        $organization = $this->organization('https://yandex.ru/maps/org/company/123');
        $job = new SyncOrganization($organization->id, $organization->source_url);

        $job->failed(new RuntimeException('Worker stopped.'));

        $organization->refresh();

        $this->assertSame('failed', $organization->sync_status);
        $this->assertSame(
            'Синхронизация была прервана. Попробуйте сохранить ссылку ещё раз.',
            $organization->sync_error,
        );
    }

    public function test_failed_stale_job_does_not_change_new_organization_status(): void
    {
        $organization = $this->organization('https://yandex.ru/maps/org/new-company/456');
        $job = new SyncOrganization(
            $organization->id,
            'https://yandex.ru/maps/org/old-company/123',
        );

        $job->failed(new RuntimeException('Worker stopped.'));

        $this->assertSame('pending', $organization->refresh()->sync_status);
    }

    private function organization(string $url): Organization
    {
        return User::factory()->create()->organization()->create([
            'source_url' => $url,
            'sync_status' => 'pending',
        ]);
    }
}
