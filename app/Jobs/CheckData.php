<?php

namespace App\Jobs;

use App\Models\QueueRequest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class CheckData implements ShouldQueue
{
    use Queueable;

    public $tries = 0;
    public $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $data = QueueRequest::whereSended(0)->first();

        if (!$data) {
            $this->release(now()->addSeconds(5));
            return;
        }

        DB::transaction(function () use ($data) {
            $code = $data->Amas03;

            $data->update([
                'Sended' => 1,
                'SendDate' => now()
            ]);

            QueueRequest::whereAmas03($code)->whereSended(0)->delete();

            SendData::dispatch($code);
        }, 3);

        $this->release(now()->addSeconds(2));
    }

    public function failed(\Throwable $exception)
    {
        // TODO: send notify to Admin after failed
    }
}
