<?php

namespace Paymenter\Extensions\Others\ObsidianAboutPage\Livewire;

use Illuminate\Support\Facades\DB;
use Livewire\Component;

class AboutPageEditor extends Component
{
    public array $cfg = [];
    public string $status = '';
    public string $publicImagesBase = 'assets/images';

    public function mount(): void
    {
        $this->cfg = $this->loadConfig();
        $this->cfg = $this->sanitize($this->cfg);
        $this->forceJsonSafe();
    }

    public function hydrate(): void
    {
        $this->cfg = $this->utf8ScrubDeep($this->cfg);
        $this->status = $this->utf8ScrubString($this->status);
        $this->publicImagesBase = $this->utf8ScrubString($this->publicImagesBase);

        $this->cfg = $this->sanitize($this->cfg);
        $this->forceJsonSafe();
    }

    public function dehydrate(): void
    {
        $this->cfg = $this->sanitize($this->cfg);
        $this->forceJsonSafe();

        if (config('app.debug')) {
            $bad = $this->debugFindBadUtf8([
                'cfg' => $this->cfg,
                'status' => $this->status,
                'publicImagesBase' => $this->publicImagesBase,
            ]);

            if ($bad !== null) {
                logger()->error('ObsidianAboutPage UTF-8 issue at: ' . $bad);
            }
        }
    }

    public function render()
    {
        return view('obsidian-about-page::livewire.about-page-editor', [
            'publicImagesBase' => $this->publicImagesBase,
        ]);
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

    public function imageUrl(string $filename): string
    {
        $filename = $this->cleanFilename($filename);
        if ($filename === '') {
            return '';
        }

        return asset(trim($this->publicImagesBase, '/') . '/' . $filename);
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
            ['key' => 'obsidian.about'],
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

    public function removeHeroImage(): void
    {
        data_set($this->cfg, 'hero.image.file', '');
        data_set($this->cfg, 'hero.image.path', '');
        data_set($this->cfg, 'hero.image.url', '');

        $this->save();
        $this->status = 'Hero image cleared';
    }

    public function removeTeamMemberImage(int $i): void
    {
        data_set($this->cfg, "team.members.$i.image_file", '');
        data_set($this->cfg, "team.members.$i.image_path", '');
        data_set($this->cfg, "team.members.$i.image", '');

        $this->save();
        $this->status = 'Team image cleared';
    }

    public function removeGalleryImageFile(int $i): void
    {
        data_set($this->cfg, "gallery.images.$i.file", '');
        data_set($this->cfg, "gallery.images.$i.path", '');
        data_set($this->cfg, "gallery.images.$i.url", '');

        $this->save();
        $this->status = 'Gallery image cleared';
    }

    public function addStat(): void
    {
        $stats = (array) data_get($this->cfg, 'stats', []);
        $stats[] = ['value' => '0', 'label' => 'New Stat'];
        data_set($this->cfg, 'stats', array_values($stats));
    }

    public function removeStat(int $i): void
    {
        $stats = array_values((array) data_get($this->cfg, 'stats', []));
        unset($stats[$i]);
        data_set($this->cfg, 'stats', array_values($stats));
    }

    public function addStoryParagraph(): void
    {
        $pars = (array) data_get($this->cfg, 'story.paragraphs', []);
        $pars[] = 'New paragraph...';
        data_set($this->cfg, 'story.paragraphs', array_values($pars));
    }

    public function removeStoryParagraph(int $i): void
    {
        $pars = array_values((array) data_get($this->cfg, 'story.paragraphs', []));
        unset($pars[$i]);
        data_set($this->cfg, 'story.paragraphs', array_values($pars));
    }

    public function addValueItem(): void
    {
        $items = (array) data_get($this->cfg, 'values.items', []);
        $items[] = ['title' => 'New value', 'text' => 'Describe it...'];
        data_set($this->cfg, 'values.items', array_values($items));
    }

    public function removeValueItem(int $i): void
    {
        $items = array_values((array) data_get($this->cfg, 'values.items', []));
        unset($items[$i]);
        data_set($this->cfg, 'values.items', array_values($items));
    }

    public function addTeamMember(): void
    {
        $members = (array) data_get($this->cfg, 'team.members', []);
        $members[] = [
            'name' => 'New member',
            'role' => 'Role',
            'image_file' => '',
            'image_fit' => 'cover',
            'image_zoom' => 1.0,
            'image' => '',
            'image_path' => '',
        ];
        data_set($this->cfg, 'team.members', array_values($members));
    }

    public function removeTeamMember(int $i): void
    {
        $members = array_values((array) data_get($this->cfg, 'team.members', []));
        if (!array_key_exists($i, $members)) {
            return;
        }

        unset($members[$i]);
        data_set($this->cfg, 'team.members', array_values($members));
        $this->save();
    }

    public function addTimelineItem(): void
    {
        $items = (array) data_get($this->cfg, 'timeline.items', []);
        $items[] = ['year' => date('Y'), 'title' => 'Milestone', 'text' => 'What happened?'];
        data_set($this->cfg, 'timeline.items', array_values($items));
    }

    public function removeTimelineItem(int $i): void
    {
        $items = array_values((array) data_get($this->cfg, 'timeline.items', []));
        unset($items[$i]);
        data_set($this->cfg, 'timeline.items', array_values($items));
    }

    public function addGalleryImage(): void
    {
        $imgs = (array) data_get($this->cfg, 'gallery.images', []);
        $imgs[] = [
            'file' => '',
            'alt' => '',
            'fit' => 'cover',
            'zoom' => 1.0,
            'url' => '',
            'path' => '',
        ];
        data_set($this->cfg, 'gallery.images', array_values($imgs));
    }

    public function removeGalleryImage(int $i): void
    {
        $imgs = array_values((array) data_get($this->cfg, 'gallery.images', []));
        if (!array_key_exists($i, $imgs)) {
            return;
        }

        unset($imgs[$i]);
        data_set($this->cfg, 'gallery.images', array_values($imgs));
        $this->save();
    }

    public static function defaultConfig(): array
    {
        return [
            'seo' => [
                'title' => 'About',
                'description' => '',
            ],
            'hero' => [
                'enabled' => true,
                'title' => 'About Us',
                'subtitle' => '',
                'image' => [
                    'file' => '',
                    'fit' => 'cover',
                    'zoom' => 1.0,
                    'path' => '',
                    'url' => '',
                    'alt' => 'About image',
                ],
                'cta_primary' => [
                    'enabled' => true,
                    'label' => 'Contact us',
                    'url' => '/contact',
                ],
                'cta_secondary' => [
                    'enabled' => true,
                    'label' => 'View services',
                    'url' => '/services',
                ],
            ],
            'stats_enabled' => true,
            'stats' => [
                ['value' => '99.9%', 'label' => 'Uptime'],
                ['value' => '24/7', 'label' => 'Support'],
                ['value' => '10k+', 'label' => 'Customers'],
                ['value' => '1ms', 'label' => 'Latency'],
            ],
            'story' => [
                'enabled' => true,
                'title' => 'Our story',
                'paragraphs' => [
                    'Write your story here...',
                ],
            ],
            'values' => [
                'enabled' => true,
                'title' => 'What we stand for',
                'items' => [
                    ['title' => 'Speed', 'text' => 'Fast setup and fast support.'],
                    ['title' => 'Quality', 'text' => 'Reliable infrastructure.'],
                    ['title' => 'Honesty', 'text' => 'Clear pricing and policies.'],
                    ['title' => 'Care', 'text' => 'We treat customers like humans.'],
                ],
            ],
            'team' => [
                'enabled' => true,
                'title' => 'Meet the team',
                'members' => [
                    [
                        'name' => 'PJ',
                        'role' => 'Owner',
                        'image_file' => '',
                        'image_fit' => 'cover',
                        'image_zoom' => 1.0,
                        'image' => '',
                        'image_path' => '',
                    ],
                    [
                        'name' => 'Support',
                        'role' => 'Customer Success',
                        'image_file' => '',
                        'image_fit' => 'cover',
                        'image_zoom' => 1.0,
                        'image' => '',
                        'image_path' => '',
                    ],
                    [
                        'name' => 'Ops',
                        'role' => 'Infrastructure',
                        'image_file' => '',
                        'image_fit' => 'cover',
                        'image_zoom' => 1.0,
                        'image' => '',
                        'image_path' => '',
                    ],
                ],
            ],
            'timeline' => [
                'enabled' => true,
                'title' => 'Milestones',
                'items' => [
                    ['year' => '2023', 'title' => 'Launched', 'text' => 'First customers onboarded.'],
                    ['year' => '2024', 'title' => 'Grew', 'text' => 'Expanded services and support.'],
                ],
            ],
            'gallery' => [
                'enabled' => true,
                'title' => 'Behind the scenes',
                'images' => [
                    ['file' => '', 'alt' => '', 'fit' => 'cover', 'zoom' => 1.0, 'url' => '', 'path' => ''],
                    ['file' => '', 'alt' => '', 'fit' => 'cover', 'zoom' => 1.0, 'url' => '', 'path' => ''],
                    ['file' => '', 'alt' => '', 'fit' => 'cover', 'zoom' => 1.0, 'url' => '', 'path' => ''],
                    ['file' => '', 'alt' => '', 'fit' => 'cover', 'zoom' => 1.0, 'url' => '', 'path' => ''],
                ],
            ],
        ];
    }

    private function loadConfig(): array
    {
        $row = DB::table('settings')->where('key', 'obsidian.about')->value('value');

        if (is_string($row) && $row !== '') {
            $decoded = json_decode($row, true);

            if (is_array($decoded)) {
                $merged = $this->mergeDefaults($decoded, self::defaultConfig());
                return $this->sanitize($merged);
            }
        }

        return self::defaultConfig();
    }

    private function mergeDefaults(array $cfg, array $defaults): array
    {
        foreach ($defaults as $k => $v) {
            if (!array_key_exists($k, $cfg)) {
                $cfg[$k] = $v;
                continue;
            }

            if (is_array($v) && is_array($cfg[$k])) {
                if ($this->isAssoc($v) && $this->isAssoc($cfg[$k])) {
                    $cfg[$k] = $this->mergeDefaults($cfg[$k], $v);
                }
            }
        }

        return $cfg;
    }

    private function sanitize(array $cfg): array
    {
        $cfg = $this->mergeDefaults($cfg, self::defaultConfig());

        $cfg['stats'] = array_values((array) ($cfg['stats'] ?? []));
        $cfg['story']['paragraphs'] = array_values((array) ($cfg['story']['paragraphs'] ?? []));
        $cfg['values']['items'] = array_values((array) ($cfg['values']['items'] ?? []));
        $cfg['team']['members'] = array_values((array) ($cfg['team']['members'] ?? []));
        $cfg['timeline']['items'] = array_values((array) ($cfg['timeline']['items'] ?? []));
        $cfg['gallery']['images'] = array_values((array) ($cfg['gallery']['images'] ?? []));

        $heroImg = (array) data_get($cfg, 'hero.image', []);
        $heroImg['file'] = $this->cleanFilename((string) ($heroImg['file'] ?? ''));
        $heroImg['alt'] = (string) ($heroImg['alt'] ?? 'About image');
        $heroImg['fit'] = $this->cleanFit((string) ($heroImg['fit'] ?? 'cover'));
        $heroImg['zoom'] = $this->cleanZoom($heroImg['zoom'] ?? 1);
        $heroImg['path'] = (string) ($heroImg['path'] ?? '');
        $heroImg['url'] = (string) ($heroImg['url'] ?? '');
        data_set($cfg, 'hero.image', $heroImg);

        foreach ((array) ($cfg['team']['members'] ?? []) as $i => $m) {
            $m = is_array($m) ? $m : [];
            $m['name'] = (string) ($m['name'] ?? '');
            $m['role'] = (string) ($m['role'] ?? '');
            $m['image_file'] = $this->cleanFilename((string) ($m['image_file'] ?? ''));
            $m['image_fit'] = $this->cleanFit((string) ($m['image_fit'] ?? 'cover'));
            $m['image_zoom'] = $this->cleanZoom($m['image_zoom'] ?? 1);
            $m['image'] = (string) ($m['image'] ?? '');
            $m['image_path'] = (string) ($m['image_path'] ?? '');
            $cfg['team']['members'][$i] = $m;
        }

        foreach ((array) ($cfg['gallery']['images'] ?? []) as $i => $g) {
            $g = is_array($g) ? $g : [];
            $g['file'] = $this->cleanFilename((string) ($g['file'] ?? ''));
            $g['alt'] = (string) ($g['alt'] ?? '');
            $g['fit'] = $this->cleanFit((string) ($g['fit'] ?? 'cover'));
            $g['zoom'] = $this->cleanZoom($g['zoom'] ?? 1);
            $g['url'] = (string) ($g['url'] ?? '');
            $g['path'] = (string) ($g['path'] ?? '');
            $cfg['gallery']['images'][$i] = $g;
        }

        return $this->utf8ScrubDeep($cfg);
    }

    private function cleanFit(string $fit): string
    {
        $fit = trim(strtolower($fit));
        return in_array($fit, ['cover', 'contain'], true) ? $fit : 'cover';
    }

    private function cleanZoom(mixed $zoom): float
    {
        $z = (float) $zoom;
        if ($z <= 0) {
            $z = 1.0;
        }
        if ($z < 0.5) {
            $z = 0.5;
        }
        if ($z > 3.0) {
            $z = 3.0;
        }
        return $z;
    }

    private function cleanFilename(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }

        $name = str_replace('\\', '/', $name);
        $name = basename($name);

        if ($name === '.' || $name === '..') {
            return '';
        }

        $name = str_replace("\0", '', $name);

        return $name;
    }

    private function forceJsonSafe(): void
    {
        $this->cfg = $this->utf8ScrubDeep($this->cfg);
        $this->status = $this->utf8ScrubString($this->status);
        $this->publicImagesBase = $this->utf8ScrubString($this->publicImagesBase);

        $encoded = json_encode(
            [
                'cfg' => $this->cfg,
                'status' => $this->status,
                'publicImagesBase' => $this->publicImagesBase,
            ],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );

        $decoded = json_decode((string) $encoded, true);

        $this->cfg = is_array($decoded['cfg'] ?? null) ? $decoded['cfg'] : [];
        $this->status = (string) ($decoded['status'] ?? '');
        $this->publicImagesBase = (string) ($decoded['publicImagesBase'] ?? 'assets/images');
    }

    private function utf8ScrubString(string $value): string
    {
        if (str_starts_with($value, "\xEF\xBB\xBF")) {
            $value = substr($value, 3);
        }

        $value = str_replace("\0", '', $value);

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

        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value) ?? '';
        $value = preg_replace('/[\xF0-\xF4][\x80-\xBF]{3}/', '', $value) ?? '';

        return $value;
    }

    private function utf8ScrubDeep(mixed $value): mixed
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
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_INVALID_UTF8_SUBSTITUTE
                | JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $e) {
            $this->status = 'Failed to save (encoding error). Remove any weird characters and try again.';
            return null;
        }
    }

    private function debugFindBadUtf8(mixed $v, string $path = 'root'): ?string
    {
        if (is_string($v)) {
            if (!mb_check_encoding($v, 'UTF-8')) {
                return $path;
            }
            return null;
        }

        if (!is_array($v)) {
            return null;
        }

        foreach ($v as $k => $val) {
            if (is_string($k) && !mb_check_encoding($k, 'UTF-8')) {
                return $path . '.[BAD_KEY]';
            }

            $kp = is_string($k) ? $k : (string) $k;
            $found = $this->debugFindBadUtf8($val, $path . '.' . $kp);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
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
