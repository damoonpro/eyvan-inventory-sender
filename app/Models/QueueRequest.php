<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QueueRequest extends Model
{
    protected $connection = 'APIDB';
    protected $table = 'dbo.QueueRequests';
    public $timestamps = false;
    protected $primaryKey = 'QueueId';

    protected $fillable = ['QueueId', 'CreateDate', 'SendDate', 'Amas03', 'Sended', 'TriggerType'];
}
