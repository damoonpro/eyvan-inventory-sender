<?php

namespace App\Jobs;

use App\Models\QueueRequest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SendData implements ShouldQueue
{
    use Queueable;

    public $tries = 10;
    public $timeout = 180;

    /**
     * Create a new job instance.
     */
    public function __construct(public string $code, public string $queueId)
    {
        $this->onQueue('send_to_api');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $row = QueueRequest::where('QueueId', $this->queueId)->first();

            if ($row->Sended)
                return;

            $result = DB::connection('MaliDB')->table('Amaster')
                ->select('Amaster.Amas03', 'Gvahed.Gvah02', 'Akala.Akal02', 'Aanbar.Aanb02')
                ->selectRaw('ROUND(ISNULL(SUM(CASE WHEN Ahhvl.Ahhv01 < 30 THEN Adhvl.Adhv05 END), 0) - ISNULL(SUM(CASE WHEN Ahhvl.Ahhv01 > 30 THEN Adhvl.Adhv05 END), 0), 3) as Mojodi')
                ->selectRaw('ISNULL(Fdmosavab.Fdmo04, 0) as Price')
                ->selectRaw('ROUND(ISNULL(SUM(CASE WHEN Ahhvl.Ahhv01 < 30 THEN Adhvl.Adhv05 END), 0) - ISNULL(SUM(CASE WHEN (Ahhvl.Ahhv01 > 30 AND Ahhvl.Ahhv01 <> 35) OR (Ahhvl.Ahhv01 = 35 AND Adhvl.Adhv24 IS NULL) THEN Adhvl.Adhv05 END), 0) - ISNULL(TB_Darkhast.TedDarkhast, 0), 3) as MojodiVaghee')
                ->leftJoin('Adhvl', function($join) {
                    $join->on('Adhvl.Adhv02', '=', 'Amaster.Amas01')
                        ->on('Adhvl.Adhv04', '=', 'Amaster.Amas03');
                })
                ->leftJoin('Ahhvl', function($join) {
                    $join->on('Ahhvl.Ahhv01', '=', 'Adhvl.Adhv01')
                        ->on('Ahhvl.Ahhv02', '=', 'Adhvl.Adhv02')
                        ->on('Ahhvl.Ahhv03', '=', 'Adhvl.Adhv03');
                })
                ->leftJoin('Fdmosavab', function($join) {
                    $join->on('Fdmosavab.Fdmo03', '=', 'Adhvl.Adhv04')
                        ->where('Fdmosavab.Fdmo02', '=', 1)
                        ->whereRaw('Fdmosavab.Fdmo01 = CASE WHEN LEFT(LTRIM(RTRIM(Amaster.Amas03)), 2) = \'92\' THEN 4 ELSE 1 END');
                })
                ->join('Akala', 'Akala.Akal01', '=', 'Amaster.Amas03')
                ->join('Gvahed', 'Gvahed.Gvah01', '=', 'Akala.Akal05')
                ->join('Aanbar', 'Aanbar.Aanb01', '=', 'Amaster.Amas01')
                ->leftJoinSub(function($query) {
                    $query->select('Addarkhast.Adda03', 'Addarkhast.Adda04', DB::raw('SUM(ISNULL(Addarkhast.Adda06, 0)) as TedDarkhast'))
                        ->from('Ahdarkhast')
                        ->join('Addarkhast', 'Addarkhast.Adda01', '=', 'Ahdarkhast.Ahda01')
                        ->where('Addarkhast.Adda08', '=', 1)
                        ->where('Addarkhast.Adda06', '>', 0)
                        ->groupBy('Addarkhast.Adda03', 'Addarkhast.Adda04');
                }, 'TB_Darkhast', function($join) {
                    $join->on('Amaster.Amas01', '=', 'TB_Darkhast.Adda03')
                        ->on('Amaster.Amas03', '=', 'TB_Darkhast.Adda04');
                })
                ->whereRaw('ISNULL(Amaster.Amas15, 0) = 0')
                ->whereRaw('dbo.IsAnbarUser(Amaster.Amas01, 3) = 1')
                ->where(DB::raw('LTRIM(RTRIM(Amaster.Amas03))'), '=', $this->code)
                ->groupBy('Amaster.Amas03', 'Gvahed.Gvah02', 'Akala.Akal02', 'Aanbar.Aanb02', 'TB_Darkhast.TedDarkhast', 'Fdmosavab.Fdmo04')
                ->havingRaw('ROUND(ISNULL(SUM(CASE WHEN Ahhvl.Ahhv01 < 30 THEN Adhvl.Adhv05 END), 0), 3) <> ROUND(ISNULL(SUM(CASE WHEN Ahhvl.Ahhv01 > 30 THEN Adhvl.Adhv05 END), 0), 3)')
                ->orHavingRaw('ROUND(ISNULL(SUM(CASE WHEN Ahhvl.Ahhv01 < 30 THEN Adhvl.Adhv05 END), 0), 3) - ROUND(ISNULL(SUM(CASE WHEN Ahhvl.Ahhv01 > 30 THEN Adhvl.Adhv05 END), 0), 3) = 0')
                ->get();

            $response = Http::post('https://api.eyvancarpet.com/api/v1/update/json', [
                'data' => $result->toArray()
            ]);

            if ($response->failed()) {
                $this->release(now()->addSeconds(30));
            } else if ($response->successful()) {
                $row->update([
                    'Sended' => 1,
                    'SendDate' => now()
                ]);

                QueueRequest::whereAmas03($this->code)->whereSended(0)->where('OnQueue', 0)->delete();
            }
        } catch (\Exception $e) {
            info("Error sending $this->queueId: " . $e->getMessage());
            $this->release(now()->addSeconds(30));
        }
    }
}
