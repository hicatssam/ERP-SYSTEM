<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\FaceAttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaceioWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        FaceAttendanceService $face
    ): JsonResponse {
        $expectedToken =
            $face->webhookToken();

        abort_unless(
            $expectedToken,
            503,
            'FACEIO webhook is not configured.'
        );

        $header = trim(
            (string) $request->header(
                'WWW-Authenticate'
            )
        );

        $providedToken = null;

        if (
            preg_match(
                '/^Bearer\s+(.+)$/i',
                $header,
                $matches
            )
        ) {
            $providedToken =
                trim($matches[1]);
        }

        abort_unless(
            $providedToken
            && hash_equals(
                $expectedToken,
                $providedToken
            ),
            401,
            'Invalid FACEIO webhook token.'
        );

        $data = $request->validate([
            'eventName' => [
                'required',
                'string',
                'in:ENROLL,AUTH,DELETION',
            ],
            'facialId' => [
                'required',
                'string',
                'max:255',
            ],
            'appId' => [
                'required',
                'string',
                'max:190',
            ],
            'clientIp' => [
                'nullable',
                'string',
                'max:64',
            ],
            'payload' => [
                'nullable',
            ],
            'details' => [
                'nullable',
                'array',
            ],
        ]);

        abort_unless(
            hash_equals(
                (string) $face->publicId(),
                (string) $data['appId']
            ),
            401,
            'FACEIO application mismatch.'
        );

        $event = $face->storeWebhookEvent(
            $data,
            $request->getContent()
        );

        return response()->json([
            'ok' => true,
            'event' => $event->event_name,
        ]);
    }
}
