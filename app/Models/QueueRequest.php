<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QueueRequest extends Model
{
    protected $connection = 'APIDB';
    protected $table = 'dbo.QueueRequests';

    protected $fillable = ['QueueId', 'CreateDate', 'SendDate', 'Amas03', 'Sended', 'TriggerType'];
}
