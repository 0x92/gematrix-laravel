<?php

namespace App\Services;

use App\Models\AppSetting;

class AppSettingService
{
    /**
     * @return array<int, string>
     */
    public function enabledCiphers(): array
    {
        $all = array_keys(config('gematria.ciphers'));

        $setting = AppSetting::query()->where('key', 'enabled_ciphers')->first();

        if (! $setting || ! is_array($setting->value)) {
            return config('gematria.default_enabled', $all);
        }

        $enabled = array_values(array_intersect($all, $setting->value));

        if (! in_array('english_gematria', $enabled, true) && in_array('english_gematria', $all, true)) {
            array_unshift($enabled, 'english_gematria');
        }

        return array_values(array_unique($enabled));
    }

    /**
     * @param array<int, string> $ciphers
     */
    public function setEnabledCiphers(array $ciphers): void
    {
        $all = array_keys(config('gematria.ciphers'));
        $safe = array_values(array_intersect($all, $ciphers));

        AppSetting::query()->updateOrCreate(
            ['key' => 'enabled_ciphers'],
            ['value' => $safe]
        );
    }
}
