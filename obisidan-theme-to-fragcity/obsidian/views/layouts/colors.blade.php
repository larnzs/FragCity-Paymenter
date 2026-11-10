<style>
    :root {
        --color-primary: {{ str_replace(',', '', preg_replace('/^hsl\((.+)\)$/', '$1', theme('primary', '225 60% 46%'))) }};
        --color-secondary: {{ str_replace(',', '', preg_replace('/^hsl\((.+)\)$/', '$1', theme('secondary', '235 18% 46%'))) }};
        --color-neutral: {{ str_replace(',', '', preg_replace('/^hsl\((.+)\)$/', '$1', theme('neutral', '220 16% 86%'))) }};
        --color-base: {{ str_replace(',', '', preg_replace('/^hsl\((.+)\)$/', '$1', theme('base', '222 22% 12%'))) }};
        --color-muted: {{ str_replace(',', '', preg_replace('/^hsl\((.+)\)$/', '$1', theme('muted', '223 14% 38%'))) }};
        --color-inverted: {{ str_replace(',', '', preg_replace('/^hsl\((.+)\)$/', '$1', theme('inverted', '0 0% 100%'))) }};

        --color-success: 142 71% 45%;
        --color-error: 0 75% 60%;
        --color-warning: 25 95% 53%;
        --color-inactive: 0 0% 63%;
        --color-info: 210 100% 60%;

        --color-background: {{ str_replace(',', '', preg_replace('/^hsl\((.+)\)$/', '$1', theme('background', '220 20% 98%'))) }};
        --color-background-secondary: {{ str_replace(',', '', preg_replace('/^hsl\((.+)\)$/', '$1', theme('background-secondary', '220 18% 96%'))) }};

        --obs-bg-tint-1: {{ str_replace(',', '', preg_replace('/^hsl\((.+)\)$/', '$1', theme('obs_bg_light_tint_1', '225 28% 60%'))) }};
        --obs-bg-tint-2: {{ str_replace(',', '', preg_replace('/^hsl\((.+)\)$/', '$1', theme('obs_bg_light_tint_2', '210 18% 58%'))) }};
        --obs-bg-tint-strength: {{ theme('obs_bg_light_strength', '0.05') }};
    }

    .dark {
        --color-primary: {{ str_replace(',', '', preg_replace('/^hsl\((.+)\)$/', '$1', theme('dark-primary', '225 78% 70%'))) }};
        --color-secondary: {{ str_replace(',', '', preg_replace('/^hsl\((.+)\)$/', '$1', theme('dark-secondary', '235 22% 66%'))) }};
        --color-neutral: {{ str_replace(',', '', preg_replace('/^hsl\((.+)\)$/', '$1', theme('dark-neutral', '240 10% 18%'))) }};
        --color-base: {{ str_replace(',', '', preg_replace('/^hsl\((.+)\)$/', '$1', theme('dark-base', '0 0% 98%'))) }};
        --color-muted: {{ str_replace(',', '', preg_replace('/^hsl\((.+)\)$/', '$1', theme('dark-muted', '220 10% 70%'))) }};
        --color-inverted: {{ str_replace(',', '', preg_replace('/^hsl\((.+)\)$/', '$1', theme('dark-inverted', '220 12% 60%'))) }};

        --color-background: {{ str_replace(',', '', preg_replace('/^hsl\((.+)\)$/', '$1', theme('dark-background', '240 18% 9%'))) }};
        --color-background-secondary: {{ str_replace(',', '', preg_replace('/^hsl\((.+)\)$/', '$1', theme('dark-background-secondary', '240 13% 11%'))) }};

        --obs-bg-tint-1: {{ str_replace(',', '', preg_replace('/^hsl\((.+)\)$/', '$1', theme('obs_bg_dark_tint_1', '230 20% 20%'))) }};
        --obs-bg-tint-2: {{ str_replace(',', '', preg_replace('/^hsl\((.+)\)$/', '$1', theme('obs_bg_dark_tint_2', '210 18% 16%'))) }};
        --obs-bg-tint-strength: {{ theme('obs_bg_dark_strength', '0.07') }};
    }
</style>
