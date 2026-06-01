<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class LeadContact extends Model
{

    use SoftDeletes, HasFactory;

    protected $table = 'LeadsContacts';
    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'int',
            'order' => 'int',
            'lead_id' => 'int',
            'is_main' => 'bool',
            'client_id' => 'int',
            'created_at' => 'datetime:' . config('app.datetime_format'),
            'updated_at' => 'datetime:' . config('app.datetime_format'),
            'deleted_at' => 'datetime:' . config('app.datetime_format')
        ];
        parent::__construct($attributes);
    }


    public function client()
    {
        return $this->belongsTo(Client::class);
    }


    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }


    public function leadContactPhones()
    {
        return $this->hasMany(LeadContactPhone::class)->orderBy('order');
    }


    public function leadContactEmails()
    {
        return $this->hasMany(LeadContactEmail::class)->orderBy('order');
    }


    public function leadContactPhonesUnordered()
    {
        return $this->hasMany(LeadContactPhone::class);
    }


    public function leadContactEmailsUnordered()
    {
        return $this->hasMany(LeadContactEmail::class);
    }


    public function getFullNameAttribute()
    {
        $name = $this->name;
        $lastName = $this->last_name;
        if ($lastName) {
            return $name . ' ' . $lastName;
        }
        return $name;
    }

}
