<?php

namespace App\Http\Controllers\Assistant;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\Assistant\AssistantPlanner;
use App\Services\Assistant\AssistantReadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class AiAssistantController extends Controller
{
    public function index(Request $request, AssistantReadService $reader): View
    {
        return view('assistant.index', [
            'suggestions' => $reader->suggestions($request->user()),
            'configured' => trim((string) config('assistant.api_key')) !== '',
            'businessName' => SystemSetting::get('system_name') ?: config('app.name'),
        ]);
    }

    public function ask(
        Request $request,
        AssistantPlanner $planner,
        AssistantReadService $reader
    ): JsonResponse {
        $data = $request->validate([
            'question' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        try {
            // No ERP data leaves this server: only the user's question reaches the model.
            $plan = $planner->plan(
                trim($data['question']),
                $request->session()->get('erp_assistant_context')
            );
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => trim((string) config('assistant.api_key')) === ''
                    ? 'المساعد غير مفعّل بعد. أضف مفتاح ERP_AI_API_KEY على الخادم.'
                    : 'تعذر الاتصال بخدمة الذكاء الآن. حاول لاحقًا.',
            ], 503);
        }

        $answer = $reader->answer($request->user(), $plan);
        if ($plan['intent'] !== 'unknown') {
            $request->session()->put('erp_assistant_context', [
                'intent' => $plan['intent'],
                'period' => $plan['period'],
            ]);
        }

        return response()->json($answer);
    }
}
