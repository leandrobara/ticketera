<?php

namespace App\Models;

use DateTime;
use DateTimeZone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Traits\ModelCache\BaseModelRelationCache;
use App\Models\Traits\ModelCache\ClientModelRelationCache;


class Task extends Model
{

    use SoftDeletes, BaseModelRelationCache, ClientModelRelationCache, HasFactory;

    protected $casts = [];
    protected $table = 'Tasks';
    public $timestamps = true;
    protected $guarded = ['id'];


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'int',
            'lead_id' => 'int',
            'user_id' => 'int',
            'client_id' => 'int',
            'is_important' => 'bool',
            'automation_log_id' => 'int',
            'expiring_browser_notification_sent' => 'bool',
            'limit_date' => 'datetime:' . config('app.datetime_format'),
            'created_at' => 'datetime:' . config('app.datetime_format'),
            'updated_at' => 'datetime:' . config('app.datetime_format'),
            'deleted_at' => 'datetime:' . config('app.datetime_format'),
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


    public function user()
    {
        return $this->belongsTo(User::class);
    }


    public function getUserAttribute(): ?User
    {
        return $this->getModelRelationFromCache('user', 'User', $this->user_id);
    }


    public function newTaskNotificationEmail()
    {
        return $this->hasOne(TaskNotificationEmail::class)->where('type', 'new_task');
    }
    

    public function taskDailyNotificationEmail()
    {
        return $this->hasOne(TaskNotificationEmail::class)->where('type', 'daily');
    }


    public function taskExpiresNowNotificationEmail()
    {
        return $this->hasOne(TaskNotificationEmail::class)->where('type', 'expires_now');
    }


    public function taskExpiredNotificationEmail()
    {
        return $this->hasOne(TaskNotificationEmail::class)->where('type', 'expired');
    }


    public function newTaskNotificationWhatsAppMessage()
    {
        return $this->hasOne(TaskNotificationWhatsAppMessage::class)->where('type', 'new_task');
    }


    public function taskDailyNotificationWhatsAppMessage()
    {
        return $this->hasOne(TaskNotificationWhatsAppMessage::class)->where('type', 'daily');
    }


    public function taskExpiresNowNotificationWhatsAppMessage()
    {
        return $this->hasOne(TaskNotificationWhatsAppMessage::class)->where('type', 'expires_now');
    }


    public function taskExpiredNotificationWhatsAppMessage()
    {
        return $this->hasOne(TaskNotificationWhatsAppMessage::class)->where('type', 'expired');
    }


    public function getClientTimezoneLimitDateAttribute()
    {
        $limitDate = $this->limit_date->toDateTime()->setTimezone(new DateTimeZone($this->client->timezone));
        return $limitDate;
    }


    public function getExpiresTodayAttribute()
    {
        $dateNow = new DateTime('now');
        $dateLimit = new DateTime('now', new DateTimeZone($this->client->timezone));
        $dateLimit->setTime(23, 59, 59)->setTimezone(new DateTimeZone('UTC'));
        $expiresToday = $this->limit_date <= $dateLimit;

        return $expiresToday && $this->limit_date > $dateNow;
    }


    public function getIsExpiredAttribute(): bool
    {
        return $this->limit_date->getTimestamp() < (new DateTime('now'))->getTimestamp();
    }

}
