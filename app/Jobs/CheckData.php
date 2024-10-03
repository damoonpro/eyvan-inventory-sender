<?php

namespace App\Jobs;

use App\Models\QueueRequest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        $processedCodes = [];
        $lastProcessedId = 0;

        while (true) {
            $requests = QueueRequest::select('QueueId', 'Amas03')
                ->where('QueueId', '>', $lastProcessedId)
                ->whereSended(0)
                ->where('OnQueue', 0)
                ->limit(50)
                ->get();

            if ($requests->isEmpty()) {
                break;
            }

            foreach ($requests as $request) {
                $code = $request->Amas03;

                if (array_key_exists($code, $processedCodes)) {
                    continue;
                }

                try {
                    DB::transaction(function () use ($request, $code) {
                        $request->update([
                            'OnQueue' => 1
                        ]);

                        QueueRequest::whereAmas03($code)->where('OnQueue', 0)->delete();

                        SendData::dispatch($code, $request->QueueId);
                    }, 3);
                } catch (\Exception $e) {
                    Log::error("Error processing code $code: " . $e->getMessage());
                    continue;
                }

                $processedCodes[$code] = true;
            }

            $lastProcessedId = $requests->last()->QueueId;
        }

        if ($this->attempts() > 1000)
            DB::table('jobs')->where('queue', 'default')->update([
                'attempts' => 1,
            ]);

        $this->release(now()->addSeconds(5));
    }


    // public function failed(\Throwable $exception)
    // {
        // TODO: send notify to Admin after failed
    // }
}
