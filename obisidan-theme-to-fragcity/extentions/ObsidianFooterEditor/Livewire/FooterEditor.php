<?php

namespace Paymenter\Extensions\Others\ObsidianFooterEditor\Livewire;

use Illuminate\Support\Facades\DB;
use Livewire\Component;

class FooterEditor extends Component
{
    /** Setting key */
    public string $settingKey = 'obsidian.footer';

    /** Core settings */
    public bool $footerEnabled = true;
    public string $copyrightText = '';

    /**
     * Columns structure
     * [
     *   [
     *     'title' => 'Company',
     *     'enabled' => true,
     *     'visible' => true,
     *     'visibility' => 'always', // always|guest|auth
     *     'links' => [
     *        [
     *          'label' => 'Status',
     *          'url' => '/status',
     *          'type' => 'internal', // internal|external
     *          'enabled' => true,
     *          'visible' => true,
     *          'visibility' => 'always'
     *        ]
     *     ]
     *   ]
     * ]
     */
    public array $columns = [];

    /** Optional blocks (stored for future UI expansion) */
    public array $socialLinks = [];
    public array $legalLinks = [];

    /** UI state */
    public string $previewMode = 'guest'; // guest|auth

    public bool $showColumnModal = false;
    public int $editingColumnIndex = -1;

    public bool $showLinkModal = false;
    public int $linkColumnIndex = -1;
    public int $editingLinkIndex = -1;

    /** Column form */
    public string $colTitle = '';
    public string $colVisibility = 'always'; // always|guest|auth
    public bool $colEnabled = true;
    public bool $colVisible = true;

    /** Link form */
    public string $linkLabel = '';
    public string $linkUrl = '';
    public string $linkType = 'internal'; // internal|external
    public string $linkVisibility = 'always';
    public bool $linkEnabled = true;
    public bool $linkVisible = true;

    public function mount(): void
    {
        $data = $this->loadFooter();

        // If DB setting exists, use it.
        if (is_array($data) && !empty($data)) {
            $this->applyFooterData($data);
            $this->normalizeIndexes();
            return;
        }

        // Otherwise, seed the editor from the CURRENT theme footer defaults.
        // This makes the editor reflect what people see today, so you can edit it.
        $seed = $this->seedFromThemeDefaults();

        $this->applyFooterData($seed);
        $this->normalizeIndexes();
    }

    public function render()
    {
        return view('obsidian-footer-editor::livewire.footer-editor');
    }

    /* -------------------------
     | Load / Save
     * ------------------------- */

    private function loadFooter(): array
    {
        $raw = DB::table('settings')->where('key', $this->settingKey)->value('value');

        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function saveFooter(array $data): void
    {
        $payload = json_encode($data, JSON_UNESCAPED_SLASHES);

        DB::table('settings')->updateOrInsert(
            ['key' => $this->settingKey],
            ['value' => $payload]
        );
    }

    private function applyFooterData(array $data): void
    {
        $this->footerEnabled = (bool)($data['enabled'] ?? true);
        $this->copyrightText = (string)($data['copyright'] ?? '');

        $cols = $data['columns'] ?? [];
        $this->columns = is_array($cols) ? $cols : [];

        $social = $data['social'] ?? [];
        $this->socialLinks = is_array($social) ? $social : [];

        $legal = $data['legal'] ?? [];
        $this->legalLinks = is_array($legal) ? $legal : [];
    }

    private function exportFooterData(): array
    {
        $this->normalizeIndexes();

        return [
            'enabled' => (bool)$this->footerEnabled,
            'copyright' => (string)$this->copyrightText,
            'columns' => $this->columns,
            'social' => $this->socialLinks,
            'legal' => $this->legalLinks,
        ];
    }

    private function normalizeIndexes(): void
    {
        $this->columns = array_values(is_array($this->columns) ? $this->columns : []);

        foreach ($this->columns as $ci => $col) {
            $this->columns[$ci]['title'] = (string)($col['title'] ?? '');
            $this->columns[$ci]['visibility'] = (string)($col['visibility'] ?? 'always');
            $this->columns[$ci]['enabled'] = (bool)($col['enabled'] ?? true);
            $this->columns[$ci]['visible'] = (bool)($col['visible'] ?? true);

            if (!isset($this->columns[$ci]['links']) || !is_array($this->columns[$ci]['links'])) {
                $this->columns[$ci]['links'] = [];
            }

            $this->columns[$ci]['links'] = array_values($this->columns[$ci]['links']);

            foreach ($this->columns[$ci]['links'] as $li => $link) {
                $this->columns[$ci]['links'][$li]['label'] = (string)($link['label'] ?? '');
                $this->columns[$ci]['links'][$li]['url'] = (string)($link['url'] ?? '');
                $this->columns[$ci]['links'][$li]['type'] = (string)($link['type'] ?? 'internal');
                $this->columns[$ci]['links'][$li]['visibility'] = (string)($link['visibility'] ?? 'always');
                $this->columns[$ci]['links'][$li]['enabled'] = (bool)($link['enabled'] ?? true);
                $this->columns[$ci]['links'][$li]['visible'] = (bool)($link['visible'] ?? true);
            }
        }

        $this->socialLinks = array_values(is_array($this->socialLinks) ? $this->socialLinks : []);
        $this->legalLinks = array_values(is_array($this->legalLinks) ? $this->legalLinks : []);
    }

    /* -------------------------
     | Seed from theme defaults
     * ------------------------- */

    private function seedFromThemeDefaults(): array
    {
        $enabled = true;
        if (function_exists('theme')) {
            $enabled = (bool) theme('footer_enabled', true);
        }

        $columns = $this->themeDefaultColumns();

        return [
            'enabled' => $enabled,
            'copyright' => '',
            'columns' => $columns,
            'social' => [],
            'legal' => [],
        ];
    }

    private function themeDefaultColumns(): array
    {
        $cols = [];

        // Mirrors your existing footer.blade.php theme keys:
        // footer_col_{n}_enabled
        // footer_col_{n}_title
        // footer_col_{n}_link_{m}_enabled
        // footer_col_{n}_link_{m}_label
        // footer_col_{n}_link_{m}_href
        for ($c = 1; $c <= 4; $c++) {
            $colEnabled = true;
            $title = '';
            if (function_exists('theme')) {
                $colEnabled = (bool) theme('footer_col_' . $c . '_enabled', true);
                $title = trim((string) theme('footer_col_' . $c . '_title', ''));
            }

            $links = [];
            for ($l = 1; $l <= 6; $l++) {
                $lEnabled = true;
                $label = '';
                $href = '';
                if (function_exists('theme')) {
                    $lEnabled = (bool) theme('footer_col_' . $c . '_link_' . $l . '_enabled', true);
                    $label = trim((string) theme('footer_col_' . $c . '_link_' . $l . '_label', ''));
                    $href  = trim((string) theme('footer_col_' . $c . '_link_' . $l . '_href', ''));
                }

                if (!$lEnabled) continue;
                if ($label === '' || $href === '') continue;

                $type = 'internal';
                if (strlen($href) >= 4 && (str_starts_with($href, 'http://') || str_starts_with($href, 'https://'))) {
                    $type = 'external';
                }

                $links[] = [
                    'label' => $label,
                    'url' => $href,
                    'type' => $type,
                    'visibility' => 'always',
                    'enabled' => true,
                    'visible' => true,
                ];
            }

            // Only include a column if it has content or a title.
            $hasLinks = count($links) > 0;
            if (!$colEnabled && !$hasLinks && $title === '') {
                continue;
            }

            $cols[] = [
                'title' => $title,
                'visibility' => 'always',
                'enabled' => (bool) $colEnabled,
                'visible' => true,
                'links' => $links,
            ];
        }

        return $cols;
    }

    /* -------------------------
     | Preview filtering
     * ------------------------- */

    public function setPreviewAs(string $mode): void
    {
        $this->previewMode = in_array($mode, ['guest', 'auth'], true) ? $mode : 'guest';
    }

    public function previewFooter(): array
    {
        $this->normalizeIndexes();

        $mode = $this->previewMode ?: 'guest';

        if (!$this->footerEnabled) {
            return [
                'enabled' => false,
                'columns' => [],
                'copyright' => (string)$this->copyrightText,
            ];
        }

        $outCols = [];

        foreach ($this->columns as $col) {
            if (!(bool)($col['enabled'] ?? true)) continue;
            if (!(bool)($col['visible'] ?? true)) continue;

            $vis = (string)($col['visibility'] ?? 'always');
            if ($vis === 'guest' && $mode !== 'guest') continue;
            if ($vis === 'auth' && $mode !== 'auth') continue;

            $linksOut = [];

            foreach (($col['links'] ?? []) as $link) {
                if (!(bool)($link['enabled'] ?? true)) continue;
                if (!(bool)($link['visible'] ?? true)) continue;

                $lvis = (string)($link['visibility'] ?? 'always');
                if ($lvis === 'guest' && $mode !== 'guest') continue;
                if ($lvis === 'auth' && $mode !== 'auth') continue;

                $linksOut[] = [
                    'label' => (string)($link['label'] ?? ''),
                    'url' => (string)($link['url'] ?? ''),
                    'type' => (string)($link['type'] ?? 'internal'),
                ];
            }

            $outCols[] = [
                'title' => (string)($col['title'] ?? ''),
                'links' => $linksOut,
            ];
        }

        return [
            'enabled' => true,
            'columns' => $outCols,
            'copyright' => (string)$this->copyrightText,
        ];
    }

    /* -------------------------
     | Column modal
     * ------------------------- */

    public function closeModal(): void
    {
        $this->showColumnModal = false;
        $this->showLinkModal = false;

        $this->editingColumnIndex = -1;
        $this->editingLinkIndex = -1;
        $this->linkColumnIndex = -1;

        $this->colTitle = '';
        $this->colVisibility = 'always';
        $this->colEnabled = true;
        $this->colVisible = true;

        $this->linkLabel = '';
        $this->linkUrl = '';
        $this->linkType = 'internal';
        $this->linkVisibility = 'always';
        $this->linkEnabled = true;
        $this->linkVisible = true;
    }

    public function openNewColumn(): void
    {
        $this->closeModal();

        $this->editingColumnIndex = -1;
        $this->showColumnModal = true;

        $this->colTitle = '';
        $this->colVisibility = 'always';
        $this->colEnabled = true;
        $this->colVisible = true;
    }

    public function openEditColumn(int $index): void
    {
        $this->normalizeIndexes();

        if (!isset($this->columns[$index])) return;

        $this->closeModal();

        $col = $this->columns[$index];

        $this->editingColumnIndex = $index;
        $this->showColumnModal = true;

        $this->colTitle = (string)($col['title'] ?? '');
        $this->colVisibility = (string)($col['visibility'] ?? 'always');
        $this->colEnabled = (bool)($col['enabled'] ?? true);
        $this->colVisible = (bool)($col['visible'] ?? true);
    }

    public function saveColumnModal(): void
    {
        $this->normalizeIndexes();

        $payload = [
            'title' => (string)$this->colTitle,
            'visibility' => (string)$this->colVisibility,
            'enabled' => (bool)$this->colEnabled,
            'visible' => (bool)$this->colVisible,
            'links' => [],
        ];

        if ($this->editingColumnIndex >= 0 && isset($this->columns[$this->editingColumnIndex])) {
            $payload['links'] = $this->columns[$this->editingColumnIndex]['links'] ?? [];
            $this->columns[$this->editingColumnIndex] = $payload;
        } else {
            $this->columns[] = $payload;
        }

        $this->normalizeIndexes();
        $this->showColumnModal = false;
    }

    /* -------------------------
     | Link modal
     * ------------------------- */

    public function openNewLink(int $columnIndex): void
    {
        $this->normalizeIndexes();

        if (!isset($this->columns[$columnIndex])) return;

        $this->closeModal();

        $this->linkColumnIndex = $columnIndex;
        $this->editingLinkIndex = -1;
        $this->showLinkModal = true;

        $this->linkLabel = '';
        $this->linkUrl = '';
        $this->linkType = 'internal';
        $this->linkVisibility = 'always';
        $this->linkEnabled = true;
        $this->linkVisible = true;
    }

    public function openEditLink(int $columnIndex, int $linkIndex): void
    {
        $this->normalizeIndexes();

        if (!isset($this->columns[$columnIndex])) return;
        if (!isset($this->columns[$columnIndex]['links'][$linkIndex])) return;

        $this->closeModal();

        $link = $this->columns[$columnIndex]['links'][$linkIndex];

        $this->linkColumnIndex = $columnIndex;
        $this->editingLinkIndex = $linkIndex;
        $this->showLinkModal = true;

        $this->linkLabel = (string)($link['label'] ?? '');
        $this->linkUrl = (string)($link['url'] ?? '');
        $this->linkType = (string)($link['type'] ?? 'internal');
        $this->linkVisibility = (string)($link['visibility'] ?? 'always');
        $this->linkEnabled = (bool)($link['enabled'] ?? true);
        $this->linkVisible = (bool)($link['visible'] ?? true);
    }

    public function saveLinkModal(): void
    {
        $this->normalizeIndexes();

        $ci = $this->linkColumnIndex;
        if ($ci < 0 || !isset($this->columns[$ci])) return;

        $payload = [
            'label' => (string)$this->linkLabel,
            'url' => (string)$this->linkUrl,
            'type' => (string)$this->linkType,
            'visibility' => (string)$this->linkVisibility,
            'enabled' => (bool)$this->linkEnabled,
            'visible' => (bool)$this->linkVisible,
        ];

        if ($this->editingLinkIndex >= 0 && isset($this->columns[$ci]['links'][$this->editingLinkIndex])) {
            $this->columns[$ci]['links'][$this->editingLinkIndex] = $payload;
        } else {
            $this->columns[$ci]['links'][] = $payload;
        }

        $this->normalizeIndexes();
        $this->showLinkModal = false;
    }

    /* -------------------------
     | Save to DB
     * ------------------------- */

    public function saveAll(): void
    {
        $data = $this->exportFooterData();
        $this->saveFooter($data);
        session()->flash('status', 'Footer saved.');
    }

    /* -------------------------
     | Reorder columns
     * ------------------------- */

    public function moveColumnUp(int $index): void
    {
        $this->normalizeIndexes();
        if ($index <= 0 || !isset($this->columns[$index])) return;

        [$this->columns[$index - 1], $this->columns[$index]] = [$this->columns[$index], $this->columns[$index - 1]];
        $this->normalizeIndexes();
    }

    public function moveColumnDown(int $index): void
    {
        $this->normalizeIndexes();
        if (!isset($this->columns[$index])) return;
        if (!isset($this->columns[$index + 1])) return;

        [$this->columns[$index + 1], $this->columns[$index]] = [$this->columns[$index], $this->columns[$index + 1]];
        $this->normalizeIndexes();
    }

    public function deleteColumn(int $index): void
    {
        $this->normalizeIndexes();
        if (!isset($this->columns[$index])) return;

        array_splice($this->columns, $index, 1);
        $this->normalizeIndexes();
    }

    /* -------------------------
     | Reorder links inside column
     * ------------------------- */

    public function moveLinkUp(int $columnIndex, int $linkIndex): void
    {
        $this->normalizeIndexes();
        if (!isset($this->columns[$columnIndex])) return;
        if ($linkIndex <= 0) return;
        if (!isset($this->columns[$columnIndex]['links'][$linkIndex])) return;

        [$this->columns[$columnIndex]['links'][$linkIndex - 1], $this->columns[$columnIndex]['links'][$linkIndex]]
            = [$this->columns[$columnIndex]['links'][$linkIndex], $this->columns[$columnIndex]['links'][$linkIndex - 1]];

        $this->normalizeIndexes();
    }

    public function moveLinkDown(int $columnIndex, int $linkIndex): void
    {
        $this->normalizeIndexes();
        if (!isset($this->columns[$columnIndex])) return;
        if (!isset($this->columns[$columnIndex]['links'][$linkIndex])) return;
        if (!isset($this->columns[$columnIndex]['links'][$linkIndex + 1])) return;

        [$this->columns[$columnIndex]['links'][$linkIndex + 1], $this->columns[$columnIndex]['links'][$linkIndex]]
            = [$this->columns[$columnIndex]['links'][$linkIndex], $this->columns[$columnIndex]['links'][$linkIndex + 1]];

        $this->normalizeIndexes();
    }

    public function deleteLink(int $columnIndex, int $linkIndex): void
    {
        $this->normalizeIndexes();
        if (!isset($this->columns[$columnIndex])) return;
        if (!isset($this->columns[$columnIndex]['links'][$linkIndex])) return;

        array_splice($this->columns[$columnIndex]['links'], $linkIndex, 1);
        $this->normalizeIndexes();
    }
}
