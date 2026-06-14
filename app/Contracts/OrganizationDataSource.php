<?php

namespace App\Contracts;

use App\DataTransferObjects\OrganizationData;

interface OrganizationDataSource
{
    public function fetch(string $url): OrganizationData;
}
