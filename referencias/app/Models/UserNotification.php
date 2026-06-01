<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class UserNotification extends Model
{

    use SoftDeletes;


    protected $casts = [];
    public $timestamps = true;
    protected $guarded = ['id'];
    protected $table = 'UsersNotifications';


    public function __construct(array $attributes = [])
    {
        $this->casts = [
            'id' => 'int',
            'user_id' => 'int',
            'client_id' => 'int',
            'comment' => 'string',
            'notification_type' => 'string',
            'unsubscribe_reason' => 'string',
            'sent_date' => 'datetime:' . config('app.datetime_format'),
            'created_at' => 'datetime:' . config('app.datetime_format'),
            'updated_at' => 'datetime:' . config('app.datetime_format'),
            'deleted_at' => 'datetime:' . config('app.datetime_format'),
            'scheduled_date' => 'datetime:' . config('app.datetime_format'),
        ];
        parent::__construct($attributes);
    }


    public function client()
    {
        return $this->belongsTo(Client::class);
    }


    public function user()
    {
        return $this->belongsTo(User::class);
    }


    public function getTypeDescriptionAttribute()
    {
        return $this->types[$this->notification_type];
    }


    public function getTypesAttribute()
    {
        return [
            'unsubscribe' => 'Quiero darme de baja',
            'error_report' => 'Quiero reportar un error',
            'need_more_users' => 'Necesito crear más usuarios',
            'need_callback' => 'Necesito que alguien se comunique conmigo',
            'need_more_email_sending_quota' => 'Necesito poder enviar más emails',
        ];
    }


    public function getUnsubscribeReasonDescriptionAttribute()
    {
        if (!$this->unsubscribe_reason) {
            return '';
        }
        return $this->unsubscribe_reasons[$this->unsubscribe_reason];
    }


    public function getUnsubscribeReasonsAttribute()
    {
        return [
            'other' => 'Otro',
            'no_time_to_implement' => 'No disponemos de tiempo para implementarlo',
            'temporary_unsubscribe' => 'Baja temporal por vacaciones/temporada baja',
            'inconvenient_with_landing_pages' => 'Inconvenientes con la carga de clientes',
            'inconvenient_linking_other_system' => 'Inconvenientes con las “Landing Pages',
            'not_suiting_my_needs' => 'Clienty no se adapta/soluciona nuestra problemática',
            'inconvenient_loading_leads' => 'Inconvenientes al vincularlo a otro sistema de gestión',
        ];
    }

}
