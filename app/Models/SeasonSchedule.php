<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeasonSchedule extends Model
{
    protected $fillable = ['season_id', 'configuration'];

    protected function casts(): array
    {
        return ['configuration' => 'array'];
    }

    public function season()
    {
        return $this->belongsTo(Season::class);
    }
}
