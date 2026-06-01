<?php

namespace App\Models\MongoDB;

use App\Models\Client;
use MongoDB\Laravel\Eloquent\Model;


class GmailMessageLog extends Model
{

    protected $guarded = [];
    public $timestamps = false;
    protected $table = 'messages';
    protected $connection = 'mongodb_gmail_messages';

    protected $casts = [
        // 'user_id' => 'int',
        'sentDate' => 'datetime',
        'createdAt' => 'datetime',
    ];


    public static function buildHash(Client $client, array $data): string
    {
        $secret = config('app.eventLogs.secret');
        $hash = md5($secret . $client->id . $data['gmailId'] . $data['threadId'] . $data['body'] . $data['subject']);
        return $hash;
    }

}
