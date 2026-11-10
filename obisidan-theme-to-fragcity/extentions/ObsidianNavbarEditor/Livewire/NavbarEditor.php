<?php

namespace Paymenter\Extensions\Others\ObsidianNavbarEditor\Livewire;

use Illuminate\Support\Facades\DB;
use Livewire\Component;

class NavbarEditor extends Component
{
    /** Setting key */
    public string $settingKey = 'obsidian.navbar';

    /** Options key (stored as JSON) */
    public string $optionsKey = 'obsidian.navbar.options';

    /** Theme toggle (theme reads this options key) */
    public bool $themeToggleEnabled = false;
    public bool $themeToggleShowLabel = false;
    public string $themeToggleLabel = 'Theme';
    public string $themeTogglePosition = 'right'; // left|right

    /** Cart / Checkout quick access (theme reads this options key) */
    public bool $cartEnabled = false;
    public bool $cartShowCount = true;
    public string $cartLabel = 'Cart';
    public string $cartVisibility = 'always'; // always|guest|auth
    public string $cartStyle = 'icon'; // icon|text

    /** Navbar links */
    public array $links = [];

    /** UI state */
    public string $search = '';
    public string $previewMode = 'guest';

    public bool $modalOpen = false;
    public bool $modalIsChild = false;

    /** Parent editing */
    public ?int $editingIndex = null;

    /** Child editing */
    public ?int $editingParentIndex = null;
    public ?int $editingChildIndex = null;

    /** Form fields (parent) */
    public string $formLabel = '';
    public string $formUrl = '';
    public string $formType = 'internal'; // internal|external|dropdown
    public string $formVisibility = 'always'; // always|guest|auth
    public bool $formEnabled = true;
    public bool $formVisible = true;

    /** Form fields (child) */
    public string $childLabel = '';
    public string $childUrl = '';
    public string $childType = 'internal'; // internal|external
    public string $childVisibility = 'always';
    public bool $childEnabled = true;
    public bool $childVisible = true;

    public function mount(): void
    {
        $this->links = $this->loadLinks();
        $this->loadOptions();
        $this->normalizeIndexes();
    }

    public function render()
    {
        return view('obsidian-navbar-editor::livewire.navbar-editor');
    }

    /* -------------------------
     | Data helpers
     * ------------------------- */

    private function loadLinks(): array
    {
        $raw = DB::table('settings')->where('key', $this->settingKey)->value('value');

        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function saveLinks(array $links): void
    {
        $payload = json_encode($links, JSON_UNESCAPED_SLASHES);

        DB::table('settings')->updateOrInsert(
            ['key' => $this->settingKey],
            ['value' => $payload]
        );
    }

    private function loadOptions(): void
    {
        $raw = DB::table('settings')->where('key', $this->optionsKey)->value('value');

        $opts = [];
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $opts = $decoded;
            }
        }

        // Theme toggle
        $tt = is_array($opts['theme_toggle'] ?? null) ? $opts['theme_toggle'] : [];

        $this->themeToggleEnabled = (bool) ($tt['enabled'] ?? false);
        $this->themeToggleShowLabel = (bool) ($tt['show_label'] ?? false);

        $ttLabel = trim((string) ($tt['label'] ?? 'Theme'));
        $this->themeToggleLabel = $ttLabel !== '' ? $ttLabel : 'Theme';

        $ttPos = (string) ($tt['position'] ?? 'right');
        $this->themeTogglePosition = in_array($ttPos, ['left', 'right'], true) ? $ttPos : 'right';

        // Cart
        $cart = is_array($opts['cart'] ?? null) ? $opts['cart'] : [];

        $this->cartEnabled = (bool) ($cart['enabled'] ?? false);
        $this->cartShowCount = (bool) ($cart['show_count'] ?? true);

        $label = trim((string) ($cart['label'] ?? 'Cart'));
        $this->cartLabel = $label !== '' ? $label : 'Cart';

        $vis = (string) ($cart['visibility'] ?? 'always');
        $this->cartVisibility = in_array($vis, ['always', 'guest', 'auth'], true) ? $vis : 'always';

        $style = (string) ($cart['style'] ?? 'icon');
        $this->cartStyle = in_array($style, ['icon', 'text'], true) ? $style : 'icon';
    }

    private function saveOptions(): void
    {
        // IMPORTANT:
        // Do not overwrite the whole options JSON with only one section.
        // Always merge existing options so other keys are preserved.
        $raw = DB::table('settings')->where('key', $this->optionsKey)->value('value');

        $existing = [];
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $existing = $decoded;
            }
        }

        $payload = is_array($existing) ? $existing : [];

        $payload['theme_toggle'] = [
            'enabled' => (bool) $this->themeToggleEnabled,
            'show_label' => (bool) $this->themeToggleShowLabel,
            'label' => trim((string) $this->themeToggleLabel) !== '' ? (string) $this->themeToggleLabel : 'Theme',
            'position' => in_array((string) $this->themeTogglePosition, ['left', 'right'], true) ? (string) $this->themeTogglePosition : 'right',
        ];

        $payload['cart'] = [
            'enabled' => (bool) $this->cartEnabled,
            'show_count' => (bool) $this->cartShowCount,
            'label' => trim((string) $this->cartLabel) !== '' ? (string) $this->cartLabel : 'Cart',
            'visibility' => in_array((string) $this->cartVisibility, ['always', 'guest', 'auth'], true) ? (string) $this->cartVisibility : 'always',
            'style' => in_array((string) $this->cartStyle, ['icon', 'text'], true) ? (string) $this->cartStyle : 'icon',
        ];

        DB::table('settings')->updateOrInsert(
            ['key' => $this->optionsKey],
            ['value' => json_encode($payload, JSON_UNESCAPED_SLASHES)]
        );
    }

    private function normalizeIndexes(): void
    {
        // Ensure parent numeric indexes
        $this->links = array_values($this->links);

        foreach ($this->links as $i => $link) {
            $this->links[$i]['label'] = (string) ($link['label'] ?? '');
            $this->links[$i]['url'] = (string) ($link['url'] ?? '');
            $this->links[$i]['type'] = (string) ($link['type'] ?? 'internal');
            $this->links[$i]['visibility'] = (string) ($link['visibility'] ?? 'always');
            $this->links[$i]['enabled'] = (bool) ($link['enabled'] ?? true);
            $this->links[$i]['visible'] = (bool) ($link['visible'] ?? true);

            if (!isset($this->links[$i]['children']) || !is_array($this->links[$i]['children'])) {
                $this->links[$i]['children'] = [];
            }

            // Ensure children numeric indexes
            $this->links[$i]['children'] = array_values($this->links[$i]['children']);

            foreach ($this->links[$i]['children'] as $ci => $child) {
                $this->links[$i]['children'][$ci]['label'] = (string) ($child['label'] ?? '');
                $this->links[$i]['children'][$ci]['url'] = (string) ($child['url'] ?? '');
                $this->links[$i]['children'][$ci]['type'] = (string) ($child['type'] ?? 'internal');
                $this->links[$i]['children'][$ci]['visibility'] = (string) ($child['visibility'] ?? 'always');
                $this->links[$i]['children'][$ci]['enabled'] = (bool) ($child['enabled'] ?? true);
                $this->links[$i]['children'][$ci]['visible'] = (bool) ($child['visible'] ?? true);
            }
        }
    }

    /* -------------------------
     | Computed-ish helpers
     * ------------------------- */

    public function filteredLinks(): array
    {
        $this->normalizeIndexes();

        $q = trim(mb_strtolower($this->search));
        $out = [];

        foreach ($this->links as $i => $link) {
            $label = (string) ($link['label'] ?? '');
            $url = (string) ($link['url'] ?? '');

            $match = true;
            if ($q !== '') {
                $match = str_contains(mb_strtolower($label), $q) || str_contains(mb_strtolower($url), $q);
            }

            if ($match) {
                $link['_i'] = $i;

                // add child indexes
                if (isset($link['children']) && is_array($link['children'])) {
                    $kids = [];
                    foreach ($link['children'] as $ci => $child) {
                        $child['_ci'] = $ci;
                        $kids[] = $child;
                    }
                    $link['children'] = $kids;
                } else {
                    $link['children'] = [];
                }

                $out[] = $link;
            }
        }

        return $out;
    }

    public function previewLinks(): array
    {
        $this->normalizeIndexes();

        $mode = $this->previewMode ?: 'guest';
        $out = [];

        foreach ($this->links as $link) {
            if (!(bool) ($link['enabled'] ?? true)) continue;
            if (!(bool) ($link['visible'] ?? true)) continue;

            $vis = (string) ($link['visibility'] ?? 'always');
            if ($vis === 'guest' && $mode !== 'guest') continue;
            if ($vis === 'auth' && $mode !== 'auth') continue;

            $out[] = $link;
        }

        return $out;
    }

    public function setPreviewAs(string $mode): void
    {
        $this->previewMode = in_array($mode, ['guest', 'auth'], true) ? $mode : 'guest';
    }

    /* -------------------------
     | Modal open/close
     * ------------------------- */

    public function closeModal(): void
    {
        $this->modalOpen = false;
        $this->modalIsChild = false;

        $this->editingIndex = null;
        $this->editingParentIndex = null;
        $this->editingChildIndex = null;

        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->formLabel = '';
        $this->formUrl = '';
        $this->formType = 'internal';
        $this->formVisibility = 'always';
        $this->formEnabled = true;
        $this->formVisible = true;

        $this->childLabel = '';
        $this->childUrl = '';
        $this->childType = 'internal';
        $this->childVisibility = 'always';
        $this->childEnabled = true;
        $this->childVisible = true;
    }

    public function openNew(): void
    {
        $this->resetForm();
        $this->editingIndex = null;

        $this->modalIsChild = false;
        $this->modalOpen = true;
    }

    public function openEdit(int $index): void
    {
        $this->normalizeIndexes();

        if (!isset($this->links[$index])) return;

        $this->resetForm();
        $this->editingIndex = $index;

        $link = $this->links[$index];

        $this->formLabel = (string) ($link['label'] ?? '');
        $this->formUrl = (string) ($link['url'] ?? '');
        $this->formType = (string) ($link['type'] ?? 'internal');
        $this->formVisibility = (string) ($link['visibility'] ?? 'always');
        $this->formEnabled = (bool) ($link['enabled'] ?? true);
        $this->formVisible = (bool) ($link['visible'] ?? true);

        $this->modalIsChild = false;
        $this->modalOpen = true;
    }

    public function openChildManager(int $parentIndex): void
    {
        $this->normalizeIndexes();

        if (!isset($this->links[$parentIndex])) return;

        $this->resetForm();

        $this->modalIsChild = true;
        $this->modalOpen = true;

        $this->editingParentIndex = $parentIndex;
        $this->editingChildIndex = null; // child form will be used when you click edit/new
    }

    public function openEditChild(int $parentIndex, int $childIndex): void
    {
        $this->normalizeIndexes();

        if (!isset($this->links[$parentIndex])) return;

        $this->resetForm();
        $this->modalIsChild = true;
        $this->modalOpen = true;

        $this->editingParentIndex = $parentIndex;

        // New child
        if ($childIndex < 0) {
            $this->editingChildIndex = null;
            return;
        }

        if (!isset($this->links[$parentIndex]['children'][$childIndex])) return;

        $this->editingChildIndex = $childIndex;
        $child = $this->links[$parentIndex]['children'][$childIndex];

        $this->childLabel = (string) ($child['label'] ?? '');
        $this->childUrl = (string) ($child['url'] ?? '');
        $this->childType = (string) ($child['type'] ?? 'internal');
        $this->childVisibility = (string) ($child['visibility'] ?? 'always');
        $this->childEnabled = (bool) ($child['enabled'] ?? true);
        $this->childVisible = (bool) ($child['visible'] ?? true);
    }

    /* -------------------------
     | Save actions
     * ------------------------- */

    public function saveModal(): void
    {
        $this->normalizeIndexes();

        if ($this->modalIsChild) {
            $p = $this->editingParentIndex;
            if ($p === null || !isset($this->links[$p])) return;

            // ensure dropdown
            $this->links[$p]['type'] = 'dropdown';
            if (!isset($this->links[$p]['children']) || !is_array($this->links[$p]['children'])) {
                $this->links[$p]['children'] = [];
            }

            $child = [
                'label' => $this->childLabel,
                'type' => in_array($this->childType, ['internal', 'external'], true) ? $this->childType : 'internal',
                'url' => $this->childUrl,
                'visibility' => in_array($this->childVisibility, ['always', 'guest', 'auth'], true) ? $this->childVisibility : 'always',
                'enabled' => (bool) $this->childEnabled,
                'visible' => (bool) $this->childVisible,
            ];

            if ($this->editingChildIndex === null) {
                $this->links[$p]['children'][] = $child;
            } else {
                $ci = $this->editingChildIndex;
                if (!isset($this->links[$p]['children'][$ci])) return;
                $this->links[$p]['children'][$ci] = $child;
            }

            $this->normalizeIndexes();
            $this->saveLinks($this->links);

            session()->flash('status', 'Child saved.');
            $this->closeModal();
            return;
        }

        // Parent save
        $link = [
            'label' => $this->formLabel,
            'type' => in_array($this->formType, ['internal', 'external', 'dropdown'], true) ? $this->formType : 'internal',
            'url' => $this->formUrl,
            'visibility' => in_array($this->formVisibility, ['always', 'guest', 'auth'], true) ? $this->formVisibility : 'always',
            'enabled' => (bool) $this->formEnabled,
            'visible' => (bool) $this->formVisible,
            'children' => [],
        ];

        // dropdown should not keep URL
        if ($link['type'] === 'dropdown') {
            $link['url'] = '';
        }

        // preserve children when editing
        if ($this->editingIndex !== null && isset($this->links[$this->editingIndex])) {
            $existing = $this->links[$this->editingIndex];
            if (isset($existing['children']) && is_array($existing['children'])) {
                $link['children'] = $existing['children'];
            }
        }

        if ($this->editingIndex === null) {
            $this->links[] = $link;
        } else {
            $this->links[$this->editingIndex] = $link;
        }

        $this->normalizeIndexes();
        $this->saveLinks($this->links);

        session()->flash('status', 'Link saved.');
        $this->closeModal();
    }

    public function saveAll(): void
    {
        $this->normalizeIndexes();
        $this->saveLinks($this->links);
        $this->saveOptions();
        session()->flash('status', 'Navbar saved.');
    }

    public function addCartLink(): void
    {
        $this->normalizeIndexes();

        foreach ($this->links as $link) {
            $type = (string) ($link['type'] ?? 'internal');
            $url = trim((string) ($link['url'] ?? ''));
            if ($type === 'internal' && $url === '/cart') {
                session()->flash('status', 'Cart link already exists.');
                return;
            }
        }

        $this->links[] = [
            'label' => $this->cartLabel !== '' ? $this->cartLabel : 'Cart',
            'type' => 'internal',
            'url' => '/cart',
            'visibility' => in_array($this->cartVisibility, ['always', 'guest', 'auth'], true) ? $this->cartVisibility : 'always',
            'enabled' => true,
            'visible' => true,
            'children' => [],
        ];

        $this->normalizeIndexes();
        $this->saveLinks($this->links);
        session()->flash('status', 'Cart link added.');
    }

    /* -------------------------
     | Reorder parent links
     * ------------------------- */

    public function moveUp(int $index): void
    {
        $this->normalizeIndexes();
        if ($index <= 0 || !isset($this->links[$index])) return;

        [$this->links[$index - 1], $this->links[$index]] = [$this->links[$index], $this->links[$index - 1]];
        $this->normalizeIndexes();
    }

    public function moveDown(int $index): void
    {
        $this->normalizeIndexes();
        if (!isset($this->links[$index])) return;
        if ($index >= count($this->links) - 1) return;

        [$this->links[$index + 1], $this->links[$index]] = [$this->links[$index], $this->links[$index + 1]];
        $this->normalizeIndexes();
    }

    /* -------------------------
     | Delete parent link
     * ------------------------- */

    public function deleteLink(int $index): void
    {
        $this->normalizeIndexes();
        if (!isset($this->links[$index])) return;

        unset($this->links[$index]);
        $this->normalizeIndexes();
        $this->saveLinks($this->links);

        session()->flash('status', 'Link deleted.');
    }

    /* -------------------------
     | Child actions
     * ------------------------- */

    public function deleteChild(int $parentIndex, int $childIndex): void
    {
        $this->normalizeIndexes();

        if (!isset($this->links[$parentIndex])) return;
        if (!isset($this->links[$parentIndex]['children']) || !is_array($this->links[$parentIndex]['children'])) return;
        if (!isset($this->links[$parentIndex]['children'][$childIndex])) return;

        unset($this->links[$parentIndex]['children'][$childIndex]);
        $this->links[$parentIndex]['children'] = array_values($this->links[$parentIndex]['children']);

        $this->normalizeIndexes();
        $this->saveLinks($this->links);

        session()->flash('status', 'Child deleted.');
    }

    public function moveChildUp(int $parentIndex, int $childIndex): void
    {
        $this->normalizeIndexes();

        if (!isset($this->links[$parentIndex]['children'][$childIndex])) return;
        if ($childIndex <= 0) return;

        $kids = $this->links[$parentIndex]['children'];
        [$kids[$childIndex - 1], $kids[$childIndex]] = [$kids[$childIndex], $kids[$childIndex - 1]];
        $this->links[$parentIndex]['children'] = array_values($kids);

        $this->normalizeIndexes();
    }

    public function moveChildDown(int $parentIndex, int $childIndex): void
    {
        $this->normalizeIndexes();

        if (!isset($this->links[$parentIndex]['children'][$childIndex])) return;

        $kids = $this->links[$parentIndex]['children'];
        if ($childIndex >= count($kids) - 1) return;

        [$kids[$childIndex + 1], $kids[$childIndex]] = [$kids[$childIndex], $kids[$childIndex + 1]];
        $this->links[$parentIndex]['children'] = array_values($kids);

        $this->normalizeIndexes();
    }
}
