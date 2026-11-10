<?php

namespace Paymenter\Extensions\Others\ObsidianTermsAndCondition\Livewire;

use Illuminate\Support\Facades\DB;
use Livewire\Component;

class TermsPageEditor extends Component
{
    public array $cfg = [];
    public string $status = '';

    public function mount(): void
    {
        $this->cfg = $this->loadConfig();
        $this->forceJsonSafe();
    }

    public function updated($name, $value): void
    {
        if (is_string($name) && str_starts_with($name, 'cfg.')) {
            $this->status = '';

            $keyPath = str_replace('cfg.', '', $name);

            if (is_string($value)) {
                data_set($this->cfg, $keyPath, $this->utf8ScrubString($value));
            } elseif (is_array($value)) {
                data_set($this->cfg, $keyPath, $this->utf8ScrubDeep($value));
            } else {
                data_set($this->cfg, $keyPath, $value);
            }
        }

        $this->forceJsonSafe();
    }

    public function save(): void
    {
        $this->status = '';

        $this->cfg = $this->sanitize($this->cfg);
        $this->forceJsonSafe();

        $payload = $this->encodeCfgOrFail();
        if ($payload === null) {
            return;
        }

        DB::table('settings')->updateOrInsert(
            ['key' => 'obsidian.terms'],
            ['value' => $payload]
        );

        $this->status = 'Saved';
    }

    public function resetToDefaults(): void
    {
        $this->cfg = self::defaultConfig();
        $this->save();
        $this->status = 'Reset to defaults';
    }

    public function addHeroButton(): void
    {
        $buttons = (array) data_get($this->cfg, 'hero.actions.buttons', []);
        $buttons[] = [
            'enabled' => true,
            'label' => 'New Button',
            'url' => '#',
            'style' => 'secondary',
            'new_tab' => false,
        ];

        data_set($this->cfg, 'hero.actions.buttons', array_values($buttons));
        $this->forceJsonSafe();
    }

    public function removeHeroButton(int $i): void
    {
        $buttons = array_values((array) data_get($this->cfg, 'hero.actions.buttons', []));
        unset($buttons[$i]);
        data_set($this->cfg, 'hero.actions.buttons', array_values($buttons));
        $this->forceJsonSafe();
    }

    public function moveHeroButtonUp(int $i): void
    {
        $buttons = array_values((array) data_get($this->cfg, 'hero.actions.buttons', []));
        if (!isset($buttons[$i]) || $i <= 0) {
            return;
        }

        $tmp = $buttons[$i - 1];
        $buttons[$i - 1] = $buttons[$i];
        $buttons[$i] = $tmp;

        data_set($this->cfg, 'hero.actions.buttons', array_values($buttons));
        $this->forceJsonSafe();
    }

    public function moveHeroButtonDown(int $i): void
    {
        $buttons = array_values((array) data_get($this->cfg, 'hero.actions.buttons', []));
        if (!isset($buttons[$i]) || $i >= count($buttons) - 1) {
            return;
        }

        $tmp = $buttons[$i + 1];
        $buttons[$i + 1] = $buttons[$i];
        $buttons[$i] = $tmp;

        data_set($this->cfg, 'hero.actions.buttons', array_values($buttons));
        $this->forceJsonSafe();
    }

    public function addFooterLink(): void
    {
        $links = (array) data_get($this->cfg, 'footer.links', []);
        $links[] = [
            'enabled' => true,
            'label' => 'New Link',
            'url' => '#',
            'new_tab' => false,
        ];

        data_set($this->cfg, 'footer.links', array_values($links));
        $this->forceJsonSafe();
    }

    public function removeFooterLink(int $i): void
    {
        $links = array_values((array) data_get($this->cfg, 'footer.links', []));
        unset($links[$i]);
        data_set($this->cfg, 'footer.links', array_values($links));
        $this->forceJsonSafe();
    }

    public function moveFooterLinkUp(int $i): void
    {
        $links = array_values((array) data_get($this->cfg, 'footer.links', []));
        if (!isset($links[$i]) || $i <= 0) {
            return;
        }

        $tmp = $links[$i - 1];
        $links[$i - 1] = $links[$i];
        $links[$i] = $tmp;

        data_set($this->cfg, 'footer.links', array_values($links));
        $this->forceJsonSafe();
    }

    public function moveFooterLinkDown(int $i): void
    {
        $links = array_values((array) data_get($this->cfg, 'footer.links', []));
        if (!isset($links[$i]) || $i >= count($links) - 1) {
            return;
        }

        $tmp = $links[$i + 1];
        $links[$i + 1] = $links[$i];
        $links[$i] = $tmp;

        data_set($this->cfg, 'footer.links', array_values($links));
        $this->forceJsonSafe();
    }

    public function addSection(): void
    {
        $sections = (array) data_get($this->cfg, 'sections', []);
        $n = count($sections) + 1;

        $sections[] = [
            'enabled' => true,
            'id' => 'section-' . $n,
            'number' => (string) $n,
            'title' => 'New Section',
            'visible_in_toc' => true,
            'blocks' => [
                [
                    'enabled' => true,
                    'type' => 'paragraph',
                    'text' => 'New paragraph...',
                ],
            ],
        ];

        data_set($this->cfg, 'sections', array_values($sections));
        $this->forceJsonSafe();
    }

    public function removeSection(int $i): void
    {
        $sections = array_values((array) data_get($this->cfg, 'sections', []));
        unset($sections[$i]);
        data_set($this->cfg, 'sections', array_values($sections));
        $this->forceJsonSafe();
    }

    public function moveSectionUp(int $i): void
    {
        $sections = array_values((array) data_get($this->cfg, 'sections', []));
        if (!isset($sections[$i]) || $i <= 0) {
            return;
        }

        $tmp = $sections[$i - 1];
        $sections[$i - 1] = $sections[$i];
        $sections[$i] = $tmp;

        data_set($this->cfg, 'sections', array_values($sections));
        $this->forceJsonSafe();
    }

    public function moveSectionDown(int $i): void
    {
        $sections = array_values((array) data_get($this->cfg, 'sections', []));
        if (!isset($sections[$i]) || $i >= count($sections) - 1) {
            return;
        }

        $tmp = $sections[$i + 1];
        $sections[$i + 1] = $sections[$i];
        $sections[$i] = $tmp;

        data_set($this->cfg, 'sections', array_values($sections));
        $this->forceJsonSafe();
    }

    public function addBlock(int $sectionIndex, string $type): void
    {
        $sections = array_values((array) data_get($this->cfg, 'sections', []));
        if (!isset($sections[$sectionIndex])) {
            return;
        }

        $blocks = array_values((array) data_get($sections[$sectionIndex], 'blocks', []));

        $block = ['enabled' => true, 'type' => $type];

        if ($type === 'paragraph') {
            $block['text'] = 'New paragraph...';
        } elseif ($type === 'heading') {
            $block['text'] = 'New heading';
        } elseif ($type === 'bullets') {
            $block['items'] = [
                ['text' => 'Bullet item...'],
            ];
        } elseif ($type === 'callout') {
            $block['variant'] = 'info';
            $block['title'] = 'Callout title';
            $block['text'] = 'Callout text...';
        }

        $blocks[] = $block;

        $sections[$sectionIndex]['blocks'] = array_values($blocks);
        data_set($this->cfg, 'sections', array_values($sections));
        $this->forceJsonSafe();
    }

    public function removeBlock(int $sectionIndex, int $blockIndex): void
    {
        $sections = array_values((array) data_get($this->cfg, 'sections', []));
        if (!isset($sections[$sectionIndex])) {
            return;
        }

        $blocks = array_values((array) data_get($sections[$sectionIndex], 'blocks', []));
        unset($blocks[$blockIndex]);

        $sections[$sectionIndex]['blocks'] = array_values($blocks);
        data_set($this->cfg, 'sections', array_values($sections));
        $this->forceJsonSafe();
    }

    public function moveBlockUp(int $sectionIndex, int $blockIndex): void
    {
        $sections = array_values((array) data_get($this->cfg, 'sections', []));
        if (!isset($sections[$sectionIndex])) {
            return;
        }

        $blocks = array_values((array) data_get($sections[$sectionIndex], 'blocks', []));
        if (!isset($blocks[$blockIndex]) || $blockIndex <= 0) {
            return;
        }

        $tmp = $blocks[$blockIndex - 1];
        $blocks[$blockIndex - 1] = $blocks[$blockIndex];
        $blocks[$blockIndex] = $tmp;

        $sections[$sectionIndex]['blocks'] = array_values($blocks);
        data_set($this->cfg, 'sections', array_values($sections));
        $this->forceJsonSafe();
    }

    public function moveBlockDown(int $sectionIndex, int $blockIndex): void
    {
        $sections = array_values((array) data_get($this->cfg, 'sections', []));
        if (!isset($sections[$sectionIndex])) {
            return;
        }

        $blocks = array_values((array) data_get($sections[$sectionIndex], 'blocks', []));
        if (!isset($blocks[$blockIndex]) || $blockIndex >= count($blocks) - 1) {
            return;
        }

        $tmp = $blocks[$blockIndex + 1];
        $blocks[$blockIndex + 1] = $blocks[$blockIndex];
        $blocks[$blockIndex] = $tmp;

        $sections[$sectionIndex]['blocks'] = array_values($blocks);
        data_set($this->cfg, 'sections', array_values($sections));
        $this->forceJsonSafe();
    }

    public function addBulletItem(int $sectionIndex, int $blockIndex): void
    {
        $sections = array_values((array) data_get($this->cfg, 'sections', []));
        if (!isset($sections[$sectionIndex])) {
            return;
        }

        $blocks = array_values((array) data_get($this->cfg, 'sections.' . $sectionIndex . '.blocks', []));
        if (!isset($blocks[$blockIndex]) || data_get($blocks[$blockIndex], 'type') !== 'bullets') {
            return;
        }

        $items = array_values((array) data_get($blocks[$blockIndex], 'items', []));
        $items[] = ['text' => 'Bullet item...'];

        data_set($this->cfg, 'sections.' . $sectionIndex . '.blocks.' . $blockIndex . '.items', array_values($items));
        $this->forceJsonSafe();
    }

    public function removeBulletItem(int $sectionIndex, int $blockIndex, int $itemIndex): void
    {
        $sections = array_values((array) data_get($this->cfg, 'sections', []));
        if (!isset($sections[$sectionIndex])) {
            return;
        }

        $blocks = array_values((array) data_get($this->cfg, 'sections.' . $sectionIndex . '.blocks', []));
        if (!isset($blocks[$blockIndex]) || data_get($blocks[$blockIndex], 'type') !== 'bullets') {
            return;
        }

        $items = array_values((array) data_get($blocks[$blockIndex], 'items', []));
        unset($items[$itemIndex]);

        data_set($this->cfg, 'sections.' . $sectionIndex . '.blocks.' . $blockIndex . '.items', array_values($items));
        $this->forceJsonSafe();
    }

    public function render()
    {
        // This MUST match the namespace you registered in ObsidianTermsAndCondition::boot()
        return view('obsidian-terms-and-condition::livewire.terms-page-editor');
    }

    private function loadConfig(): array
    {
        $row = DB::table('settings')->where('key', 'obsidian.terms')->value('value');

        if (is_string($row) && $row !== '') {
            $decoded = json_decode($row, true);

            if (is_array($decoded)) {
                $merged = $this->mergeDefaults($decoded, self::defaultConfig());
                return $this->sanitize($merged);
            }
        }

        return self::defaultConfig();
    }

    private static function defaultConfig(): array
    {
        return [
            'seo' => [
                'title' => 'Terms & Conditions',
                'description' => '',
                'robots' => '',
                'canonical_url' => '',
            ],
            'hero' => [
                'enabled' => true,
                'last_updated' => [
                    'enabled' => true,
                    'label' => 'Last updated:',
                    'date' => '',
                    'display' => '',
                ],
                'title' => 'Terms & Conditions',
                'summary' => '',
                'actions' => [
                    'enabled' => true,
                    'buttons' => [],
                ],
            ],
            'toc' => [
                'enabled' => true,
                'title' => 'Table of contents',
                'mobile_collapse' => [
                    'enabled' => true,
                ],
            ],
            'sections' => [
                [
                    'enabled' => true,
                    'id' => 'introduction',
                    'number' => '1',
                    'title' => 'Introduction',
                    'visible_in_toc' => true,
                    'blocks' => [
                        [
                            'enabled' => true,
                            'type' => 'paragraph',
                            'text' => 'Replace this with your own terms content.',
                        ],
                    ],
                ],
            ],
            'footer' => [
                'enabled' => true,
                'left_text' => 'Copyright ' . date('Y') . ' Your Company. All rights reserved.',
                'links' => [],
            ],
        ];
    }

    private function mergeDefaults(array $incoming, array $defaults): array
    {
        foreach ($defaults as $k => $v) {
            if (!array_key_exists($k, $incoming)) {
                $incoming[$k] = $v;
                continue;
            }

            if (is_array($v) && is_array($incoming[$k])) {
                if ($this->isAssoc($v) || $this->isAssoc($incoming[$k])) {
                    $incoming[$k] = $this->mergeDefaults($incoming[$k], $v);
                }
            }
        }

        return $incoming;
    }

    private function sanitize($value)
    {
        if (is_string($value)) {
            return $this->utf8ScrubString($value);
        }

        if (!is_array($value)) {
            return $value;
        }

        $out = [];
        foreach ($value as $k => $v) {
            $newKey = $k;

            if (is_string($k)) {
                $newKey = $this->utf8ScrubString($k);
                if ($newKey === '') {
                    $newKey = '_';
                }
            }

            $out[$newKey] = $this->sanitize($v);
        }

        return $out;
    }

    private function forceJsonSafe(): void
    {
        $this->cfg = $this->utf8ScrubDeep($this->cfg);
        $this->status = $this->utf8ScrubString($this->status);

        $encoded = json_encode(
            [
                'cfg' => $this->cfg,
                'status' => $this->status,
            ],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );

        $decoded = json_decode((string) $encoded, true);

        if (is_array($decoded)) {
            $this->cfg = is_array($decoded['cfg'] ?? null) ? $decoded['cfg'] : [];
            $this->status = is_string($decoded['status'] ?? null) ? $decoded['status'] : '';
        }
    }

    private function utf8ScrubDeep($value)
    {
        if (is_string($value)) {
            return $this->utf8ScrubString($value);
        }

        if (!is_array($value)) {
            return $value;
        }

        $out = [];
        foreach ($value as $k => $v) {
            $newKey = $k;

            if (is_string($k)) {
                $newKey = $this->utf8ScrubString($k);
                if ($newKey === '') {
                    $newKey = '_';
                }
            }

            $out[$newKey] = $this->utf8ScrubDeep($v);
        }

        return $out;
    }

    private function encodeCfgOrFail(): ?string
    {
        $this->cfg = $this->utf8ScrubDeep($this->cfg);

        try {
            return json_encode(
                $this->cfg,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
            );
        } catch (\Throwable $e) {
            $this->status = 'Could not save: JSON encoding failed';
        }

        return null;
    }

    private function utf8ScrubString(string $value): string
    {
        $value = str_replace("\xEF\xBB\xBF", '', $value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value) ?? '';

        if (!mb_check_encoding($value, 'UTF-8')) {
            $converted = @mb_convert_encoding($value, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
            if (is_string($converted) && $converted !== '') {
                $value = $converted;
            }
        }

        $value = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
        if ($value === false) {
            $value = '';
        }

        return $value;
    }

    private function isAssoc(array $arr): bool
    {
        if ($arr === []) {
            return false;
        }

        $keys = array_keys($arr);
        return array_keys($keys) !== $keys;
    }
}
