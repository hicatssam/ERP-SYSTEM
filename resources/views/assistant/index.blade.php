@extends('layouts.app')

@section('title', 'المساعد الذكي')
@section('page-title', 'المساعد الذكي')

@push('styles')
<style>
    .assistant-shell { max-width: 1040px; margin: 0 auto; direction: rtl; font-family: inherit; }
    .assistant-hero { color: var(--theme-sidebar-text, #fff); background: var(--theme-sidebar-bg, #0a2948); padding: 30px; border-radius: 22px 22px 0 0; display: flex; align-items: center; justify-content: space-between; gap: 24px; }
    .assistant-hero h1 { margin: 5px 0; font-size: clamp(1.4rem, 2.5vw, 2rem); color: inherit; }
    .assistant-hero p { margin: 0; opacity: .78; }
    .assistant-kicker { color: var(--theme-sidebar-active, #c98516); font-size: .85rem; font-weight: 700; }
    .assistant-spark { font-size: 3rem; color: var(--theme-sidebar-active, #c98516); }
    .assistant-panel { background: var(--theme-surface, #fff); border: 1px solid var(--theme-border, #dde2e7); border-top: 0; border-radius: 0 0 22px 22px; box-shadow: 0 18px 45px #11182712; overflow: hidden; }
    .assistant-notice { padding: 13px 25px; background: color-mix(in srgb, var(--theme-accent, #c98516) 11%, var(--theme-surface, #fff)); color: var(--theme-text, #172435); font-size: .88rem; border-bottom: 1px solid var(--theme-border, #dde2e7); }
    .assistant-messages { min-height: 320px; max-height: 55vh; overflow-y: auto; padding: 25px; display: flex; flex-direction: column; gap: 18px; }
    .assistant-message { max-width: min(85%, 710px); border-radius: 16px; padding: 13px 18px; white-space: pre-wrap; line-height: 1.8; }
    .assistant-message--bot { align-self: flex-start; background: var(--theme-bg, #f5f6f8); color: var(--theme-text, #172435); }
    .assistant-message--user { align-self: flex-end; background: var(--theme-sidebar-bg, #0a2948); color: var(--theme-sidebar-text, #fff); }
    .assistant-message--error { background: color-mix(in srgb, var(--theme-danger, #e22929) 10%, var(--theme-surface, #fff)); color: var(--theme-danger, #e22929); }
    .assistant-links { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
    .assistant-link { display: inline-flex; flex-direction: column; color: var(--theme-text, #172435); background: var(--theme-surface, #fff); border: 1px solid var(--theme-accent, #c98516); border-radius: 10px; padding: 7px 10px; text-decoration: none; line-height: 1.5; font-size: .88rem; }
    .assistant-link:hover { background: color-mix(in srgb, var(--theme-accent, #c98516) 12%, var(--theme-surface, #fff)); color: var(--theme-text, #172435); }
    .assistant-link small { color: var(--theme-text-muted, #687482); }
    .assistant-suggestions { display: flex; gap: 9px; flex-wrap: wrap; padding: 0 25px 20px; }
    .assistant-suggestions button { cursor: pointer; border: 1px solid var(--theme-accent, #c98516); background: var(--theme-surface, #fff); border-radius: 100px; padding: 8px 13px; color: var(--theme-text, #172435); font: inherit; font-size: .85rem; }
    .assistant-suggestions button:hover { background: color-mix(in srgb, var(--theme-accent, #c98516) 12%, var(--theme-surface, #fff)); }
    .assistant-scope { display:flex; gap:7px; flex-wrap:wrap; padding:15px 25px 0; }
    .assistant-scope-chip { border-radius:999px; padding:5px 10px; color:var(--theme-text, #172435); background:color-mix(in srgb, var(--theme-accent, #c98516) 10%, var(--theme-surface, #fff)); border:1px solid color-mix(in srgb, var(--theme-accent, #c98516) 32%, var(--theme-border, #dde2e7)); font-size:.76rem; }
    .assistant-form { display: flex; align-items: flex-end; gap: 12px; border-top: 1px solid var(--theme-border, #dde2e7); padding: 17px 25px; }
    .assistant-form textarea { resize: vertical; flex: 1; min-height: 50px; max-height: 150px; border: 1px solid var(--theme-border, #dde2e7); border-radius: 12px; padding: 11px 14px; background: var(--theme-surface, #fff); color: var(--theme-text, #172435); font: inherit; }
    .assistant-form button { cursor: pointer; background: var(--theme-sidebar-bg, #0a2948); color: var(--theme-sidebar-text, #fff); border: 0; border-radius: 12px; padding: 12px 22px; font: inherit; font-weight: 700; }
    .assistant-form button:disabled { opacity: .5; cursor: not-allowed; }
    @media (max-width: 650px) { .assistant-hero, .assistant-messages, .assistant-notice, .assistant-form { padding-left: 16px; padding-right: 16px; } .assistant-message { max-width: 95%; } .assistant-suggestions { padding-left: 16px; padding-right: 16px; } }
</style>
@endpush

@section('content')
<section class="assistant-shell" aria-label="المساعد الذكي">
    <header class="assistant-hero">
        <div>
            <span class="assistant-kicker">{{ $businessName }} · قراءة وتحليل</span>
            <h1>المساعد الذكي</h1>
            <p>اسأل عن بيانات العمل المتاحة لك، وسأعرض النتائج مع روابطها في النظام.</p>
        </div>
        <span class="assistant-spark" aria-hidden="true">✦</span>
    </header>

    <div class="assistant-panel">
        <div class="assistant-notice">
            يعتمد الرد على صلاحياتك والموضوعات المفعّلة لحسابك والفروع المتاحة لك. يُرسل نص السؤال فقط إلى خدمة الذكاء، وتبقى نتائج بيانات النظام على الخادم.
            @if($allowActionSuggestions)
                <span> يمكنك لاحقًا تفعيل اقتراح إجراءات تتطلب تأكيدك.</span>
            @else
                <span> المساعد في وضع القراءة والتحليل ولا ينشئ أو يعدّل أو يؤكد أي عملية.</span>
            @endif
        </div>
        @if($allowedTopics)
            <div class="assistant-scope" aria-label="الموضوعات المتاحة لك">
                @foreach($allowedTopics as $topic)
                    @if(isset($topicDefinitions[$topic]))
                        <span class="assistant-scope-chip">{{ $topicDefinitions[$topic]['label'] }}</span>
                    @endif
                @endforeach
            </div>
        @endif
        <div class="assistant-messages" id="assistantMessages" role="log" aria-live="polite">
            <div class="assistant-message assistant-message--bot">أهلًا! اسألني عن طلباتك، المخزون، المبيعات، أو أي جزء مفعّل لديك في النظام.</div>
            @if(!$assistantAvailable)
                <div class="assistant-message assistant-message--bot assistant-message--error">لا توجد موضوعات مفعّلة لهذا الحساب أو أن المساعد متوقف من إعدادات النظام. تواصل مع مدير النظام.</div>
            @elseif(!$configured)
                <div class="assistant-message assistant-message--bot assistant-message--error">الخدمة غير مفعّلة حاليًا. تواصل مع مدير النظام لإعداد مزود الذكاء.</div>
            @endif
        </div>

        @if($showSuggestions && $suggestions)
            <div class="assistant-suggestions" aria-label="أسئلة مقترحة">
                @foreach($suggestions as $suggestion)
                    <button type="button" data-suggestion="{{ $suggestion }}" @disabled(!$configured || !$assistantAvailable)>{{ $suggestion }}</button>
                @endforeach
            </div>
        @endif

        <form class="assistant-form" id="assistantForm">
            @csrf
            <textarea id="assistantQuestion" name="question" maxlength="500" rows="2" placeholder="اكتب سؤالك هنا…" aria-label="سؤالك للمساعد" required @disabled(!$configured || !$assistantAvailable)></textarea>
            <button type="submit" id="assistantSubmit" @disabled(!$configured || !$assistantAvailable)>إرسال</button>
        </form>
    </div>
</section>
@endsection

@push('scripts')
<script>
(() => {
    const form = document.getElementById('assistantForm');
    const field = document.getElementById('assistantQuestion');
    const submit = document.getElementById('assistantSubmit');
    const messages = document.getElementById('assistantMessages');
    if (!form || !field || !submit || !messages) return;

    function appendMessage(content, kind = 'bot', links = []) {
        const block = document.createElement('div');
        block.className = `assistant-message assistant-message--${kind}`;
        block.textContent = content;
        if (Array.isArray(links) && links.length) {
            const list = document.createElement('div');
            list.className = 'assistant-links';
            links.forEach(item => {
                try {
                    const target = new URL(item.url, window.location.origin);
                    if (target.origin !== window.location.origin || !['http:', 'https:'].includes(target.protocol)) return;
                    const link = document.createElement('a');
                    link.href = target.href;
                    link.className = 'assistant-link';
                    link.textContent = item.label;
                    if (item.meta) {
                        const meta = document.createElement('small');
                        meta.textContent = item.meta;
                        link.appendChild(meta);
                    }
                    list.appendChild(link);
                } catch (_) { /* Ignore malformed links. */ }
            });
            block.appendChild(list);
        }
        messages.appendChild(block);
        messages.scrollTop = messages.scrollHeight;
        return block;
    }

    document.querySelectorAll('[data-suggestion]').forEach(button => {
        button.addEventListener('click', () => { field.value = button.dataset.suggestion; field.focus(); });
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        const question = field.value.trim();
        if (question.length < 3 || submit.disabled) return;
        appendMessage(question, 'user');
        field.value = '';
        submit.disabled = true;
        const pending = appendMessage('أراجع البيانات المتاحة لك…');
        try {
            const response = await fetch(@json(route('assistant.ask')), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value },
                body: JSON.stringify({ question }),
                credentials: 'same-origin',
            });
            const data = await response.json();
            pending.remove();
            appendMessage(response.ok ? data.message : (response.status === 403 ? 'لا تملك صلاحية الاطلاع على هذه البيانات.' : (data.message || 'تعذر الحصول على الرد.')), response.ok ? 'bot' : 'error', response.ok ? data.items : []);
        } catch (_) {
            pending.remove();
            appendMessage('تعذر الاتصال بالنظام. حاول مرة أخرى.', 'error');
        } finally {
            submit.disabled = false;
            field.focus();
        }
    });
})();
</script>
@endpush
