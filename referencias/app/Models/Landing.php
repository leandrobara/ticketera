<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Landing extends Model
{
    use SoftDeletes, HasFactory;

    /**
     * @var string
     */
    protected $table = 'Landings';

    /**
     * @var bool
     */
    public $timestamps = true;

    /**
     * @var array
     */
    protected $guarded = ['id'];

    protected $casts = [];

    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'int',
            'client_id' => 'int',
            'enabled' => 'boolean',
            'leads_landing_id' => 'int',
            'created_at' => 'datetime:' . config('app.datetime_format'),
            'updated_at' => 'datetime:' . config('app.datetime_format'),
            'deleted_at' => 'datetime:' . config('app.datetime_format')
        ];

        parent::__construct($attributes);
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public static function buildHash(string $url): string
    {
        return md5($url);
    }
}
