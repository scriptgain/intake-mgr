{{-- Spam protection for one form. Rendered by App\View\Components\Captcha, which
     decides what appears; this file is markup only. Three independent pieces:
       1. the always-on honeypot + time-trap baseline
       2. the built-in challenge, when that provider is active
       3. a third-party widget (reCAPTCHA / hCaptcha / Turnstile), when active
     The vendor widget script is emitted ONLY when that provider is the active
     one, so a store using the built-in challenge pulls in no external script. --}}

@if ($showHoneypot)
    {{-- Off-screen, aria-hidden bait. Positioned off-canvas (not display:none)
         and given a real label so it is inert to assistive tech but still a
         genuine input a bot will fill. --}}
    <div aria-hidden="true" style="position:absolute!important;left:-9999px!important;top:auto;width:1px;height:1px;overflow:hidden;">
        <label for="{{ $honeypotField }}_hp">Leave This Field Empty</label>
        <input type="text" id="{{ $honeypotField }}_hp" name="{{ $honeypotField }}" tabindex="-1" autocomplete="off" value="">
    </div>
    <input type="hidden" name="{{ $timeField }}" value="{{ $timeToken }}">
@endif

@if ($showWidget && $challenge)
    {{-- Built-in challenge: a real, labelled text question. --}}
    <div class="rounded-lg bg-slate-50 p-4 ring-1 ring-inset ring-slate-200">
        <x-field label="{{ $challenge['question'] }}" for="{{ $answerField }}" required :error="$errors->first('captcha')"
                 hint="A quick question to confirm you are human.">
            <x-input id="{{ $answerField }}" name="{{ $answerField }}" autocomplete="off"
                     inputmode="text" required maxlength="40" placeholder="Your Answer" />
        </x-field>
        <input type="hidden" name="{{ $tokenField }}" value="{{ $challenge['token'] }}">
    </div>
@elseif ($showWidget && ($widget['needs_execute'] ?? false))
    {{-- reCAPTCHA v3: invisible, score-based. captcha.js executes it on submit
         and drops the token into this hidden field before the form posts. --}}
    <input type="hidden" name="{{ $widget['response_field'] }}" value="">
    <div data-captcha-v3
         data-sitekey="{{ $widget['site_key'] }}"
         data-field="{{ $widget['response_field'] }}"
         data-action="{{ $surface }}"></div>
    @error('captcha')<p class="text-sm text-rose-600">{{ $message }}</p>@enderror
    <script src="{{ $widget['script_url'] }}" async defer></script>
    <script src="{{ asset_v('js/captcha.js') }}" defer></script>
@elseif ($showWidget)
    {{-- Third-party checkbox widget (reCAPTCHA v2 / hCaptcha / Turnstile). --}}
    <div>
        <div class="{{ $widget['container_class'] }}" data-sitekey="{{ $widget['site_key'] }}"></div>
        @error('captcha')<p class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
    </div>
    <script src="{{ $widget['script_url'] }}" async defer></script>
@else
    @error('captcha')<p class="text-sm text-rose-600">{{ $message }}</p>@enderror
@endif
