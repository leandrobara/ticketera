<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationMembership extends Model
{
    public const ROLES = ['administrator', 'collaborator', 'door'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['roles' => 'array', 'show_ids' => 'array', 'is_active' => 'boolean', 'joined_at' => 'datetime'];
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isLeader(): bool
    {
        return (int) $this->organization->creator_user_id === (int) $this->user_id;
    }

    public function managesOrganization(): bool
    {
        return $this->isLeader() || in_array('administrator', $this->roles ?? [], true);
    }
}
