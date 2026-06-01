<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Manager extends Model
{
    use SoftDeletes, HasFactory;

    /**
     * @var string
     */
    protected $table = 'Managers';

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
            'created_at' => 'datetime:' . config('app.datetime_format'),
            'updated_at' => 'datetime:' . config('app.datetime_format'),
            'deleted_at' => 'datetime:' . config('app.datetime_format')
        ];

        parent::__construct($attributes);
    }

    public function clients()
    {
        return $this->hasMany(Client::class);
    }
}
