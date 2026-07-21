<?php

namespace App\View\Components;

use App\Services\Captcha\CaptchaManager;
use App\Services\Captcha\CaptchaSettings;
use App\Services\Captcha\Honeypot;
use App\Services\Captcha\Providers\BuiltinProvider;
use Illuminate\View\Component;

/**
 * <x-captcha surface="account_register" /> — the one tag a form drops in.
 *
 * All the decisions (is the baseline on, is the provider active on this surface,
 * which widget, what token) are resolved here, not in Blade, per the house rule
 * that views carry markup only. The view is a dumb switch over what this class
 * hands it.
 */
class Captcha extends Component
{
    public bool $showHoneypot;

    public string $honeypotField;

    public string $timeField;

    public string $timeToken;

    public bool $showWidget;

    public string $providerKey = 'none';

    /** Generic third-party widget config (container class, script, field). */
    public array $widget = [];

    /** Built-in challenge, when that provider is active: [question, token]. */
    public ?array $challenge = null;

    public string $answerField = BuiltinProvider::FIELD_ANSWER;

    public string $tokenField = BuiltinProvider::FIELD_TOKEN;

    public function __construct(public string $surface)
    {
        $manager = app(CaptchaManager::class);

        $this->showHoneypot = CaptchaSettings::honeypotEnabled();
        $this->honeypotField = Honeypot::FIELD;
        $this->timeField = Honeypot::TS_FIELD;
        $this->timeToken = $manager->honeypot()->issueToken();

        $this->showWidget = $manager->providerActiveOn($surface);

        if ($this->showWidget) {
            $provider = $manager->active();
            $this->providerKey = $provider->key();

            if ($provider instanceof BuiltinProvider) {
                $this->challenge = $provider->issue();
            } else {
                $this->widget = $provider->widgetConfig();
            }
        }
    }

    public function render()
    {
        return view('components.captcha');
    }
}
