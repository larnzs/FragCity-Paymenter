<?php

namespace Paymenter\Extensions\Others\ObsidianAboutPage\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;

class AboutPageController extends Controller
{
    private function getSetting(string $key, $default = null)
    {
        try {
            $val = DB::table('settings')->where('key', $key)->value('value');
            return ($val === null || $val === '') ? $default : $val;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    private function detectLayoutView(): ?string
    {
        // Try the "active theme" idea if Paymenter stores it in settings.
        $theme = (string) $this->getSetting('theme', '');
        $candidates = [];

        if ($theme !== '') {
            // Common patterns (depends on Paymenter/theme loader)
            $candidates[] = "themes.$theme.layouts.app";
            $candidates[] = "themes.$theme.views.layouts.app";
            $candidates[] = "$theme::layouts.app";
            $candidates[] = "$theme::layout";
        }

        // Fallbacks
        $candidates[] = "layouts.app";
        $candidates[] = "layout";
        $candidates[] = "app";

        foreach ($candidates as $view) {
            if (View::exists($view)) {
                return $view;
            }
        }

        return null;
    }

    public function show()
    {
        $raw = (string) $this->getSetting('obsidian.about', '');
        $data = null;

        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }

        $layout = $this->detectLayoutView();

        return view('obsidian-about-page::front.about', [
            'layoutView' => $layout,
            'about' => $data,
        ]);
    }
}
