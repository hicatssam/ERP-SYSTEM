<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReleaseReadinessService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReleaseCenterController extends Controller
{
    public function __construct(
        private readonly ReleaseReadinessService $release
    ) {
    }

    public function index(): View
    {
        return view('admin.release-center.index', [
            'report' => $this->release->report(),
        ]);
    }

    public function export(): StreamedResponse
    {
        $manifest = $this->release->handoverManifest();

        $filename = 'client-handover-'
            . now()->format('Ymd-His')
            . '.json';

        return response()->streamDownload(
            function () use ($manifest): void {
                echo json_encode(
                    $manifest,
                    JSON_PRETTY_PRINT
                    | JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                );
            },
            $filename,
            [
                'Content-Type' => 'application/json; charset=UTF-8',
            ]
        );
    }
}
