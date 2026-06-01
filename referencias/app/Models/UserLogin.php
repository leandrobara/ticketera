<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserLogin extends Model
{
    use SoftDeletes;

    /**
     * @var string
     */
    protected $table = 'UserLogins';

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
            'user_id' => 'int',
            'is_super_user' => 'boolean',
            'created_at' => 'datetime:' . config('app.datetime_format'),
            'updated_at' => 'datetime:' . config('app.datetime_format'),
            'deleted_at' => 'datetime:' . config('app.datetime_format')
        ];

        parent::__construct($attributes);
    }

    public function client()
    {
        return $this->belongsTo(User::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
