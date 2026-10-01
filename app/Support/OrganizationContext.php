<?php

namespace App\Support;

use App\Models\OrganizationMembership;

class OrganizationContext
{
    public ?OrganizationMembership $membership = null;

    public function id(): ?int
    {
        return $this->membership?->organization_id;
    }

    public function showIds(): ?array
    {
        return $this->membership?->managesOrganization() ? null : $this->membership?->show_ids;
    }
}
