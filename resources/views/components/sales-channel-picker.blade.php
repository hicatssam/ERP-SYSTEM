@props(['channels', 'selected' => null])
@php $selected = (string) old('sales_channel_id', $selected ?: optional($channels->firstWhere('slug','branch'))->id); @endphp
<div class="card sc-picker-card">
    <div class="card-header"><span class="card-title">قناة البيع *</span></div>
    <div class="card-body">
        <div class="sc-picker-grid">
            @foreach($channels as $channel)
                <label class="sc-picker-option">
                    <input type="radio" name="sales_channel_id" value="{{ $channel->id }}"
                           data-name="{{ $channel->name }}"
                           data-discount-type="{{ $channel->discount_type->value }}"
                           data-discount-value="{{ $channel->discount_value }}"
                           data-commission-type="{{ $channel->commission_type->value }}"
                           data-commission-value="{{ $channel->commission_value }}"
                           @checked($selected === (string)$channel->id) required>
                    <span class="sc-picker-content">
                        <span class="sc-picker-logo">@if($channel->logo_url)<img src="{{ $channel->logo_url }}" alt="">@else{{ mb_substr($channel->name,0,2) }}@endif</span>
                        <span><strong>{{ $channel->name }}</strong><small>{{ $channel->discountLabel() }} · {{ $channel->commissionLabel() }}</small></span>
                        <i>✓</i>
                    </span>
                </label>
            @endforeach
        </div>
        @error('sales_channel_id')<span class="form-error">{{ $message }}</span>@enderror
        <div class="sc-picker-note" id="salesChannelPreview">يظهر تقدير الخصم هنا، والحساب النهائي يتم من الخادم.</div>
    </div>
</div>
<style>
.sc-picker-card{margin-bottom:1.5rem}.sc-picker-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem}.sc-picker-option{position:relative;cursor:pointer}.sc-picker-option>input{position:absolute;opacity:0}.sc-picker-content{min-height:72px;display:flex;align-items:center;gap:.65rem;padding:.7rem;color:var(--text);background:var(--surface);border:1px solid var(--border);border-radius:12px;transition:.2s}.sc-picker-option:hover .sc-picker-content,.sc-picker-option input:checked+.sc-picker-content{border-color:var(--gold);background:rgba(212,175,55,.08)}.sc-picker-logo{width:44px;height:44px;display:flex;align-items:center;justify-content:center;overflow:hidden;color:var(--gold);font-weight:800;background:#fff;border-radius:10px}.sc-picker-logo img{width:100%;height:100%;object-fit:contain}.sc-picker-content>span:nth-child(2){min-width:0;flex:1}.sc-picker-content strong,.sc-picker-content small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.sc-picker-content small{margin-top:.15rem;color:var(--text-muted);font-size:.72rem}.sc-picker-content i{display:none;width:22px;height:22px;align-items:center;justify-content:center;color:#fff;background:var(--gold);border-radius:50%;font-style:normal}.sc-picker-option input:checked+.sc-picker-content i{display:flex}.sc-picker-note{margin-top:.75rem;padding:.65rem .8rem;color:var(--text-muted);background:rgba(212,175,55,.05);border-radius:9px;font-size:.82rem}.form-error{display:block;margin-top:.35rem;color:#dc3545}@media(max-width:800px){.sc-picker-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:520px){.sc-picker-grid{grid-template-columns:1fr}}
</style>

