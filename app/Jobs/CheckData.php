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
        $processedCodes = [];

        QueueRequest::whereSended(0)
            ->orderBy('Amas03') // مرتب‌سازی بر اساس کد برای گروه‌بندی بهتر
            ->chunk(50, function ($requests) use (&$processedCodes) {
                foreach ($requests as $request) {
                    $code = $request->Amas03;

                    // اگر این کد قبلاً پردازش شده، آن را نادیده بگیر
                    if (in_array($code, $processedCodes)) {
                        continue;
                    }

                    DB::transaction(function () use ($request, $code) {
                        $request->update([
                            'Sended' => 1,
                            'SendDate' => now()
                        ]);

                        // حذف همه درخواست‌های مشابه با همین کد که هنوز ارسال نشده‌اند
                        QueueRequest::whereAmas03($code)->whereSended(0)->delete();

                        // ارسال داده
                        SendData::dispatch($code);
                    }, 3);

                    $processedCodes[] = $code;
                }
            });

        // زمان‌بندی مجدد job برای بررسی درخواست‌های جدید
        $this->release(now()->addSeconds(5));
    }

    // public function failed(\Throwable $exception)
    // {
        // TODO: send notify to Admin after failed
    // }
}
