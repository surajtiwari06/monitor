<?php

namespace App\Http\Controllers;

use App\Models\CustomerSite;
use App\Models\Vendor;
use PDF;
use Mail;
use Illuminate\Http\Request;
use Carbon\Carbon;
use DB;
use Illuminate\Support\Facades\Storage;
use QuickChart;
class MonitoringController extends Controller
{
    public function index(Request $request)
    {
        $customerSiteQuery = CustomerSite::query();
        $customerSiteQuery->where('is_active', 1);
        $customerSiteQuery->where('name', 'like', '%'.$request->get('q').'%');
        $customerSiteQuery->orderBy('name');
        $customerSiteQuery->where('owner_id', auth()->id());
        if ($vendorId = $request->get('vendor_id')) {
            if ($vendorId == 'null') {
                $customerSiteQuery->whereNull('vendor_id');
            } else {
                $customerSiteQuery->where('vendor_id', $vendorId);
            }
        }
        $customerSites = $customerSiteQuery->with('vendor')->get();

        $availableVendors = Vendor::orderBy('name')->pluck('name', 'id')->toArray();
        $availableVendors = ['null' => 'n/a'] + $availableVendors;

        return view('monitoring.index', compact('customerSites', 'availableVendors'));
    }
    public function sendReport()
{
    $customerSites = CustomerSite::with('vendor')->get();
    $chartDataArray = [];
    $averageResponseTimes = [];

    foreach ($customerSites as $customerSite) {
        $startTime = Carbon::now()->subDay();
        $endTime = Carbon::now();

        $monitoringLogs = DB::table('monitoring_logs')
            ->where('customer_site_id', $customerSite->id)
            ->whereBetween('created_at', [$startTime, $endTime])
            ->get(['response_time', 'created_at']);

        $chartData = [];
        $totalResponseTime = 0;
        $logCount = $monitoringLogs->count();

        foreach ($monitoringLogs as $monitoringLog) {
            $chartData[] = ['x' => $monitoringLog->created_at, 'y' => $monitoringLog->response_time];
            $totalResponseTime += $monitoringLog->response_time;
        }

        $chartDataArray[$customerSite->id] = $chartData;
        $averageResponseTimes[$customerSite->id] = $logCount > 0 ? $totalResponseTime / $logCount : null;
    }

    // Generate PDF
    $pdf = PDF::loadView('reports.monitoring', [
        'customerSites' => $customerSites,
        'chartDataArray' => $chartDataArray,
        'averageResponseTimes' => $averageResponseTimes,
    ]);

    // Save the PDF to a temporary file
    $pdfPath = storage_path('app/public/monitoring_report.pdf');
    $pdf->save($pdfPath);

    // Optionally, you can send the PDF via email
    // Mail::send([], [], function($message) use ($pdfPath) {
    //     $message->to('recipient@example.com')
    //             ->subject('Monitoring Report')
    //             ->attach($pdfPath);
    // });

    return redirect()->back()->with('success', 'Report sent successfully!');
}

    

    private function getStartTimeByTimeRange($timeRange)
    {
        switch ($timeRange) {
            case '1h':
                return Carbon::now()->subHour();
            case '6h':
                return Carbon::now()->subHours(6);
            case '24h':
                return Carbon::now()->subDay();
            case '7d':
                return Carbon::now()->subDays(7);
            case '14d':
                return Carbon::now()->subDays(14);
            case '30d':
                return Carbon::now()->subMonth();
            case '3Mo':
                return Carbon::now()->subMonths(3);
            case '6Mo':
                return Carbon::now()->subMonths(6);
            default:
                return Carbon::now()->subHour();
        }
    }
    public function saveChartImage(Request $request)
    {
        $customerSiteId = $request->input('customerSiteId');
        $image = $request->input('image');
        $image = str_replace('data:image/png;base64,', '', $image);
        $image = str_replace(' ', '+', $image);
        $imageData = base64_decode($image);

        $filePath = "public/charts/chart_{$customerSiteId}.png";
        Storage::put($filePath, $imageData);

        return response()->json(['message' => 'Chart image saved successfully']);
    }
}
