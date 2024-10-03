<?php

namespace App\Jobs;

use App\Models\QueueRequest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public $timeout = 300;

    private const CACHE_KEY = 'check_data_last_processed_id';
    private const BATCH_SIZE = 50;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $lastProcessedId = Cache::get(self::CACHE_KEY, 0);

        $requests = $this->getRequests($lastProcessedId);

        if ($requests->isNotEmpty()) {
            $this->processRequests($requests);
            $this->updateLastProcessedId($requests->last()->QueueId);
        }

        self::dispatch()->delay(now()->addSeconds(5));
    }

    private function getRequests($lastProcessedId)
    {
        return QueueRequest::select('QueueId', 'Amas03')
            ->where('QueueId', '>', $lastProcessedId)
            ->whereSended(0)
            ->where('OnQueue', 0)
            ->limit(self::BATCH_SIZE)
            ->get();
    }

    private function processRequests($requests)
    {
        $processedCodes = [];

        foreach ($requests as $request) {
            $code = $request->Amas03;

            if (isset($processedCodes[$code])) {
                continue;
            }

            $this->processRequest($request, $code);
            $processedCodes[$code] = true;
        }
    }

    private function processRequest($request, $code)
    {
        try {
            DB::transaction(function () use ($request, $code) {
                $request->update(['OnQueue' => 1]);
                QueueRequest::whereAmas03($code)->where('OnQueue', 0)->delete();
                SendData::dispatch($code, $request->QueueId);
            }, 3);
        } catch (\Exception $e) {
            Log::error("Error processing code $code: " . $e->getMessage());
        }
    }

    private function updateLastProcessedId($id)
    {
        Cache::put(self::CACHE_KEY, $id);
    }

    public function failed(\Throwable $exception)
    {
        Log::error('CheckData job failed: ' . $exception->getMessage());
        // TODO: send notify to Admin after failed
    }
}
