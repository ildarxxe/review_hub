<?php

namespace App\Services;

use App\Contracts\OrganizationDataSource;
use App\DataTransferObjects\OrganizationData;
use App\DataTransferObjects\ReviewData;
use App\Exceptions\OrganizationSyncException;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class OrganizationSyncService
{
    public function __construct(
        private readonly OrganizationDataSource $source,
    ) {}

    /**
     * @throws OrganizationSyncException
     */
    public function sync(Organization $organization, ?string $expectedSourceUrl = null): Organization
    {
        $organization->update([
            'sync_status' => 'syncing',
            'sync_error' => null,
        ]);

        try {
            $data = $this->source->fetch($organization->source_url);
        } catch (Throwable $exception) {
            $organization->refresh();

            if ($this->isStale($organization, $expectedSourceUrl)) {
                return $organization;
            }

            $message = Str::limit($exception->getMessage(), 2000, '');

            throw $this->fail(
                $organization,
                $message !== '' ? $message : 'Источник не вернул данные организации.',
                $exception,
            );
        }

        try {
            $this->store($organization, $data, $expectedSourceUrl);
        } catch (Throwable $exception) {
            $organization->refresh();

            if ($this->isStale($organization, $expectedSourceUrl)) {
                return $organization;
            }

            throw $this->fail(
                $organization,
                'Не удалось сохранить полученные отзывы.',
                $exception,
            );
        }

        return $organization->refresh();
    }

    private function store(
        Organization $organization,
        OrganizationData $data,
        ?string $expectedSourceUrl,
    ): void {
        DB::transaction(function () use ($organization, $data, $expectedSourceUrl): void {
            $lockedOrganization = Organization::query()
                ->lockForUpdate()
                ->findOrFail($organization->id);

            if ($this->isStale($lockedOrganization, $expectedSourceUrl)) {
                return;
            }

            $lockedOrganization->reviews()->delete();

            collect($data->reviews)
                ->map(fn (ReviewData $review, int $position) => [
                    'organization_id' => $lockedOrganization->id,
                    'external_id' => $review->externalId,
                    'position' => $position,
                    'author' => $review->author,
                    'rating' => $review->rating,
                    'text' => $review->text,
                    'published_at' => $review->publishedAt,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
                ->chunk(500)
                ->each(fn ($reviews) => DB::table('reviews')->insert($reviews->all()));

            $lockedOrganization->update([
                'external_id' => $data->externalId,
                'name' => $data->name,
                'rating' => $data->rating,
                'ratings_count' => $data->ratingsCount,
                'reviews_count' => $data->reviewsCount,
                'sync_status' => 'ready',
                'sync_error' => null,
                'synced_at' => now(),
            ]);
        });
    }

    private function isStale(Organization $organization, ?string $expectedSourceUrl): bool
    {
        return $expectedSourceUrl !== null
            && $organization->source_url !== rtrim($expectedSourceUrl, '/');
    }

    private function fail(
        Organization $organization,
        string $message,
        Throwable $previous,
    ): OrganizationSyncException {
        $organization->update([
            'sync_status' => 'failed',
            'sync_error' => $message,
        ]);

        return OrganizationSyncException::fromFailure($message, $previous);
    }
}
