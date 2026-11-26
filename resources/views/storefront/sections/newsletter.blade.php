@php($split = ($s['layout'] ?? 'center') === 'split')
<div class="mv-container" @unless ($split) style="text-align:center" @endunless>
    <div @if ($split) class="mv-split" @endif>
        <div>
            @if (($e = $sf->t($s, 'eyebrow')) !== '')<span class="mv-eyebrow"{!! $sf->e('eyebrow') !!}>{{ $e }}</span>@endif
            @if (($h = $sf->t($s, 'heading')) !== '')<h2 class="mv-h2"{!! $sf->e('heading') !!}>{{ $h }}</h2>@endif
            @if (($t = $sf->t($s, 'text')) !== '')<p class="mv-muted"{!! $sf->e('text') !!}>{{ $t }}</p>@endif
        </div>
        <form class="mv-inline-form {{ $split ? '' : 'is-center' }}" action="{{ $sf->raw('/newsletter/subscribe') }}" method="post" data-newsletter style="margin-top:20px">
            <label class="mv-skip" for="{{ $sid }}-email">{{ $sf->t($s, 'placeholder') }}</label>
            <input id="{{ $sid }}-email" class="mv-input" type="email" name="email" required autocomplete="email" placeholder="{{ $sf->t($s, 'placeholder') }}">
            <button class="mv-btn" type="submit"{!! $sf->e('button_label') !!}>{{ $sf->t($s, 'button_label', 'Subscribe') }}</button>
        </form>
    </div>
</div>
