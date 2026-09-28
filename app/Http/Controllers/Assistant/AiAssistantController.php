<?php

namespace App\Http\Controllers\Assistant;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\Assistant\AssistantAccessService;
use App\Services\Assistant\AssistantPlanner;
use App\Services\Assistant\AssistantReadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class AiAssistantController extends Controller
{
    public function index(
        Request $request,
        AssistantReadService $reader,
        AssistantAccessService $access
    ): View
    {
        return view('assistant.index', [
            'suggestions' => $reader->suggestions($request->user()),
            'configured' => trim((string) config('assistant.api_key')) !== '',
            'assistantAvailable' => $access->canUse($request->user()),
            'allowedTopics' => $access->allowedIntents($request->user()),
            'topicDefinitions' => $access->topics(),
            'showSuggestions' => $access->showSuggestions(),
            'allowActionSuggestions' => $access->allowActionSuggestions($request->user()),
            'businessName' => SystemSetting::get('system_name') ?: config('app.name'),
        ]);
    }

    public function ask(
        Request $request,
        AssistantPlanner $planner,
        AssistantReadService $reader,
        AssistantAccessService $access
    ): JsonResponse {
        $data = $request->validate([
            'question' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        abort_unless(
            $access->canUse($request->user()),
            403,
            'لا توجد صلاحيات مفعّلة للمساعد على حسابك.'
        );

        try {
            // No ERP data leaves this server: only the user's question reaches the model.
            $plan = $planner->plan(
                trim($data['question']),
                $request->session()->get('erp_assistant_context'),
                $access->allowedIntents($request->user())
            );
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => trim((string) config('assistant.api_key')) === ''
                    ? 'المساعد غير مفعّل بعد. يلزم إعداد مفتاح مزود الذكاء على الخادم.'
                    : 'تعذر الاتصال بخدمة الذكاء الآن. حاول لاحقًا.',
            ], 503);
        }

        $answer = $reader->answer($request->user(), $plan);
        if ($plan['intent'] !== 'unknown') {
            $request->session()->put('erp_assistant_context', [
                'question' => trim($data['question']),
                'intent' => $plan['intent'],
                'period' => $plan['period'],
                'focus' => $plan['focus'],
            ]);
        }

        return response()->json($answer);
    }
}
