<?php

return [
    'name' => 'obsidian',
    'author' => 'TamelessDev',
    'url' => 'https://paymenter.org',

    'settings' => [
        /*
        |--------------------------------------------------------------------------
        | HERO
        |--------------------------------------------------------------------------
        */
        [
            'name' => 'hero_kicker',
            'label' => 'Homepage - Hero Kicker',
            'type' => 'text',
            'default' => 'Premium hosting built for performance',
        ],
        [
            'name' => 'hero_title',
            'label' => 'Homepage - Hero Title Line 1',
            'type' => 'text',
            'default' => 'Infrastructure',
        ],
        [
            'name' => 'hero_title_line_2',
            'label' => 'Homepage - Hero Title Line 2',
            'type' => 'text',
            'default' => 'Forged in Precision',
        ],
        [
            'name' => 'hero_subtitle',
            'label' => 'Homepage - Hero Subtitle',
            'type' => 'text',
            'default' => 'Performance hosting for developers, gamers, and businesses who demand reliability with zero compromise.',
        ],
        [
            'name' => 'hero_primary_text',
            'label' => 'Homepage - Primary Button Text',
            'type' => 'text',
            'default' => 'Deploy Now',
        ],
        [
            'name' => 'hero_primary_href',
            'label' => 'Homepage - Primary Button Link',
            'type' => 'text',
            'default' => '',
        ],
        [
            'name' => 'hero_primary_category_slug',
            'label' => 'Homepage - Primary Category Slug (Optional)',
            'type' => 'text',
            'default' => '',
        ],
        [
            'name' => 'hero_secondary_text',
            'label' => 'Homepage - Secondary Button Text',
            'type' => 'text',
            'default' => 'Explore Services',
        ],
        [
            'name' => 'hero_secondary_href',
            'label' => 'Homepage - Secondary Button Link',
            'type' => 'text',
            'default' => '#services',
        ],

        /*
        |--------------------------------------------------------------------------
        | SERVICES (legacy labels kept)
        |--------------------------------------------------------------------------
        */
        [
            'name' => 'services_title',
            'label' => 'Homepage - Services Title',
            'type' => 'text',
            'default' => 'Choose Your Infrastructure',
        ],
        [
            'name' => 'services_subtitle',
            'label' => 'Homepage - Services Subtitle',
            'type' => 'text',
            'default' => 'Three specialized platforms. One engineered foundation.',
        ],
        [
            'name' => 'services_fallback_description',
            'label' => 'Homepage - Services Fallback Description',
            'type' => 'text',
            'default' => 'Premium plans and fast provisioning.',
        ],
        [
            'name' => 'services_empty_text',
            'label' => 'Homepage - Services Empty Text',
            'type' => 'text',
            'default' => 'No services are available to display yet.',
        ],

        /*
        |--------------------------------------------------------------------------
        | INFRASTRUCTURE
        |--------------------------------------------------------------------------
        */
        [
            'name' => 'infrastructure_title',
            'label' => 'Infrastructure Section - Title',
            'type' => 'text',
            'default' => 'Choose Your Infrastructure',
        ],
        [
            'name' => 'infrastructure_subtitle',
            'label' => 'Infrastructure Section - Subtitle',
            'type' => 'text',
            'default' => 'Three specialized platforms. One engineered foundation.',
        ],
        [
            'name' => 'infrastructure_fullscreen_images',
            'label' => 'Infrastructure - Fullscreen Images (like featured cards)',
            'type' => 'checkbox',
            'default' => false,
            'database_type' => 'boolean',
        ],

        // Infrastructure Card 1
        [
            'name' => 'infrastructure_card_1_title',
            'label' => 'Infrastructure Card 1 - Title',
            'type' => 'text',
            'default' => 'Minecraft Servers',
        ],
        [
            'name' => 'infrastructure_card_1_description',
            'label' => 'Infrastructure Card 1 - Description',
            'type' => 'text',
            'default' => 'High-performance Minecraft hosting engineered for smooth gameplay and instant scalability. Deploy modded or vanilla servers in seconds with powerful hardware, low latency, and full control over your world.',
        ],
        [
            'name' => 'infrastructure_card_1_image',
            'label' => 'Infrastructure Card 1 - Image (Upload)',
            'type' => 'file',
            'accept' => ['image/*'],
            'disk' => 'public',
            'visibility' => 'public',
            'default' => '',
        ],
        [
            'name' => 'infrastructure_card_1_button_text',
            'label' => 'Infrastructure Card 1 - Button Text',
            'type' => 'text',
            'default' => 'View Plans',
        ],
        [
            'name' => 'infrastructure_card_1_button_href',
            'label' => 'Infrastructure Card 1 - Button Link',
            'type' => 'text',
            'default' => '',
        ],

        // Infrastructure Card 2
        [
            'name' => 'infrastructure_card_2_title',
            'label' => 'Infrastructure Card 2 - Title',
            'type' => 'text',
            'default' => 'VPS (Virtual Private Servers)',
        ],
        [
            'name' => 'infrastructure_card_2_description',
            'label' => 'Infrastructure Card 2 - Description',
            'type' => 'text',
            'default' => 'Flexible virtual servers with dedicated resources and full root access. Perfect for developers, businesses, and power users who need performance, reliability, and complete control.',
        ],
        [
            'name' => 'infrastructure_card_2_image',
            'label' => 'Infrastructure Card 2 - Image (Upload)',
            'type' => 'file',
            'accept' => ['image/*'],
            'disk' => 'public',
            'visibility' => 'public',
            'default' => '',
        ],
        [
            'name' => 'infrastructure_card_2_button_text',
            'label' => 'Infrastructure Card 2 - Button Text',
            'type' => 'text',
            'default' => 'View Plans',
        ],
        [
            'name' => 'infrastructure_card_2_button_href',
            'label' => 'Infrastructure Card 2 - Button Link',
            'type' => 'text',
            'default' => '',
        ],

        // Infrastructure Card 3
        [
            'name' => 'infrastructure_card_3_title',
            'label' => 'Infrastructure Card 3 - Title',
            'type' => 'text',
            'default' => 'Web Hosting',
        ],
        [
            'name' => 'infrastructure_card_3_description',
            'label' => 'Infrastructure Card 3 - Description',
            'type' => 'text',
            'default' => 'Fast, secure web hosting built for modern applications and businesses. Enjoy optimized performance, automatic scaling, and reliable uptime on infrastructure designed to grow with your site.',
        ],
        [
            'name' => 'infrastructure_card_3_image',
            'label' => 'Infrastructure Card 3 - Image (Upload)',
            'type' => 'file',
            'accept' => ['image/*'],
            'disk' => 'public',
            'visibility' => 'public',
            'default' => '',
        ],
        [
            'name' => 'infrastructure_card_3_button_text',
            'label' => 'Infrastructure Card 3 - Button Text',
            'type' => 'text',
            'default' => 'View Plans',
        ],
        [
            'name' => 'infrastructure_card_3_button_href',
            'label' => 'Infrastructure Card 3 - Button Link',
            'type' => 'text',
            'default' => '',
        ],

        /*
        |--------------------------------------------------------------------------
        | STATS STRIP (HOME)
        |--------------------------------------------------------------------------
        */
        [
            'name' => 'stats_strip_enabled',
            'label' => 'Stats Strip - Enable',
            'type' => 'checkbox',
            'default' => true,
            'database_type' => 'boolean',
        ],
        [
            'name' => 'stats_strip_title',
            'label' => 'Stats Strip - Title (Optional)',
            'type' => 'text',
            'default' => '',
        ],
        [
            'name' => 'stats_strip_subtitle',
            'label' => 'Stats Strip - Subtitle (Optional)',
            'type' => 'text',
            'default' => '',
        ],

        [
            'name' => 'stats_strip_1_value',
            'label' => 'Stats Strip - Item 1 Value',
            'type' => 'text',
            'default' => '99.99%',
        ],
        [
            'name' => 'stats_strip_1_label',
            'label' => 'Stats Strip - Item 1 Label',
            'type' => 'text',
            'default' => 'Uptime',
        ],

        [
            'name' => 'stats_strip_2_value',
            'label' => 'Stats Strip - Item 2 Value',
            'type' => 'text',
            'default' => '<50ms',
        ],
        [
            'name' => 'stats_strip_2_label',
            'label' => 'Stats Strip - Item 2 Label',
            'type' => 'text',
            'default' => 'Latency',
        ],

        [
            'name' => 'stats_strip_3_value',
            'label' => 'Stats Strip - Item 3 Value',
            'type' => 'text',
            'default' => '24/7',
        ],
        [
            'name' => 'stats_strip_3_label',
            'label' => 'Stats Strip - Item 3 Label',
            'type' => 'text',
            'default' => 'Human Support',
        ],

        [
            'name' => 'stats_strip_4_value',
            'label' => 'Stats Strip - Item 4 Value',
            'type' => 'text',
            'default' => 'Instant',
        ],
        [
            'name' => 'stats_strip_4_label',
            'label' => 'Stats Strip - Item 4 Label',
            'type' => 'text',
            'default' => 'Deploy',
        ],

        /*
        |--------------------------------------------------------------------------
        | WHY OBSIDIAN (FEATURES) - 6 cards toggleable
        |--------------------------------------------------------------------------
        */
        [
            'name' => 'features_title',
            'label' => 'Why Obsidian - Title',
            'type' => 'text',
            'default' => 'Why Obsidian',
        ],
        [
            'name' => 'features_subtitle',
            'label' => 'Why Obsidian - Subtitle',
            'type' => 'text',
            'default' => 'Infrastructure engineered for stability, performance, and scale.',
        ],

        // Card 1
        [
            'name' => 'feature_one_enabled',
            'label' => 'Why Obsidian - Card 1 Enabled',
            'type' => 'checkbox',
            'default' => true,
            'database_type' => 'boolean',
        ],
        [
            'name' => 'feature_one_title',
            'label' => 'Why Obsidian - Card 1 Title',
            'type' => 'text',
            'default' => 'Enterprise Hardware',
        ],
        [
            'name' => 'feature_one_text',
            'label' => 'Why Obsidian - Card 1 Description',
            'type' => 'text',
            'default' => 'High-performance CPUs and NVMe storage deployed across all regions.',
        ],

        // Card 2
        [
            'name' => 'feature_two_enabled',
            'label' => 'Why Obsidian - Card 2 Enabled',
            'type' => 'checkbox',
            'default' => true,
            'database_type' => 'boolean',
        ],
        [
            'name' => 'feature_two_title',
            'label' => 'Why Obsidian - Card 2 Title',
            'type' => 'text',
            'default' => 'Instant Provisioning',
        ],
        [
            'name' => 'feature_two_text',
            'label' => 'Why Obsidian - Card 2 Description',
            'type' => 'text',
            'default' => 'Your services are deployed automatically within seconds of checkout.',
        ],

        // Card 3
        [
            'name' => 'feature_three_enabled',
            'label' => 'Why Obsidian - Card 3 Enabled',
            'type' => 'checkbox',
            'default' => true,
            'database_type' => 'boolean',
        ],
        [
            'name' => 'feature_three_title',
            'label' => 'Why Obsidian - Card 3 Title',
            'type' => 'text',
            'default' => 'Global Network',
        ],
        [
            'name' => 'feature_three_text',
            'label' => 'Why Obsidian - Card 3 Description',
            'type' => 'text',
            'default' => 'Multiple locations worldwide for low latency and reliability.',
        ],

        // Card 4
        [
            'name' => 'feature_four_enabled',
            'label' => 'Why Obsidian - Card 4 Enabled',
            'type' => 'checkbox',
            'default' => true,
            'database_type' => 'boolean',
        ],
        [
            'name' => 'feature_four_title',
            'label' => 'Why Obsidian - Card 4 Title',
            'type' => 'text',
            'default' => 'DDoS Protection',
        ],
        [
            'name' => 'feature_four_text',
            'label' => 'Why Obsidian - Card 4 Description',
            'type' => 'text',
            'default' => 'Always-on mitigation to keep services online during attacks and traffic spikes.',
        ],

        // Card 5
        [
            'name' => 'feature_five_enabled',
            'label' => 'Why Obsidian - Card 5 Enabled',
            'type' => 'checkbox',
            'default' => true,
            'database_type' => 'boolean',
        ],
        [
            'name' => 'feature_five_title',
            'label' => 'Why Obsidian - Card 5 Title',
            'type' => 'text',
            'default' => '24/7 Expert Support',
        ],
        [
            'name' => 'feature_five_text',
            'label' => 'Why Obsidian - Card 5 Description',
            'type' => 'text',
            'default' => 'Real humans, fast response times, and help that actually solves the problem.',
        ],

        // Card 6
        [
            'name' => 'feature_six_enabled',
            'label' => 'Why Obsidian - Card 6 Enabled',
            'type' => 'checkbox',
            'default' => true,
            'database_type' => 'boolean',
        ],
        [
            'name' => 'feature_six_title',
            'label' => 'Why Obsidian - Card 6 Title',
            'type' => 'text',
            'default' => 'Simple Management',
        ],
        [
            'name' => 'feature_six_text',
            'label' => 'Why Obsidian - Card 6 Description',
            'type' => 'text',
            'default' => 'A clean panel that makes deploying, upgrading, and managing services effortless.',
        ],

        /*
        |--------------------------------------------------------------------------
        | TRUST STRIP (RATING BAR)
        |--------------------------------------------------------------------------
        */
        [
            'name' => 'trust_strip_enabled',
            'label' => 'Trust Strip - Enable',
            'type' => 'checkbox',
            'default' => true,
            'database_type' => 'boolean',
        ],
        [
            'name' => 'trust_strip_rating',
            'label' => 'Trust Strip - Rating (0.0 - 5.0)',
            'type' => 'text',
            'default' => '4.5',
        ],
        [
            'name' => 'trust_strip_reviews',
            'label' => 'Trust Strip - Review Count',
            'type' => 'text',
            'default' => '101',
        ],
        [
            'name' => 'trust_strip_source',
            'label' => 'Trust Strip - Source Name (e.g. Trustpilot, Google)',
            'type' => 'text',
            'default' => 'Trustpilot',
        ],
        [
            'name' => 'trust_strip_href',
            'label' => 'Trust Strip - Link (Optional)',
            'type' => 'text',
            'default' => '',
        ],

        /*
        |--------------------------------------------------------------------------
        | TRANSPARENT PRICING
        |--------------------------------------------------------------------------
        */
        [
            'name' => 'pricing_section_enabled',
            'label' => 'Transparent Pricing - Enable Section',
            'type' => 'checkbox',
            'default' => true,
            'database_type' => 'boolean',
        ],
        [
            'name' => 'pricing_fullscreen_images',
            'label' => 'Transparent Pricing - Fullscreen Images (background)',
            'type' => 'checkbox',
            'default' => false,
            'database_type' => 'boolean',
        ],
        [
            'name' => 'pricing_title',
            'label' => 'Transparent Pricing - Title',
            'type' => 'text',
            'default' => 'Transparent Pricing',
        ],
        [
            'name' => 'pricing_subtitle',
            'label' => 'Transparent Pricing - Subtitle',
            'type' => 'text',
            'default' => 'Simple monthly rates. No hidden fees. Cancel anytime.',
        ],
        [
            'name' => 'pricing_footer_text',
            'label' => 'Transparent Pricing - Footer Text (Optional)',
            'type' => 'text',
            'default' => 'All plans include money-back guarantee. Pay monthly or annually.',
        ],

        // Pricing Card 1
        [
            'name' => 'pricing_card_1_enabled',
            'label' => 'Transparent Pricing - Card 1 Enabled',
            'type' => 'checkbox',
            'default' => true,
            'database_type' => 'boolean',
        ],
        [
            'name' => 'pricing_card_1_title',
            'label' => 'Transparent Pricing - Card 1 Title',
            'type' => 'text',
            'default' => 'Game Servers',
        ],
        [
            'name' => 'pricing_card_1_subtitle',
            'label' => 'Transparent Pricing - Card 1 Subtitle',
            'type' => 'text',
            'default' => 'High-performance servers built for low latency and smooth gameplay',
        ],
        [
            'name' => 'pricing_card_1_price',
            'label' => 'Transparent Pricing - Card 1 Price',
            'type' => 'text',
            'default' => '3.99',
        ],
        [
            'name' => 'pricing_card_1_price_suffix',
            'label' => 'Transparent Pricing - Card 1 Price Suffix',
            'type' => 'text',
            'default' => '/month',
        ],
        [
            'name' => 'pricing_card_1_features',
            'label' => 'Transparent Pricing - Card 1 Features (one per line)',
            'type' => 'textarea',
            'default' => "High-clock CPU cores\nDDR4 / DDR5 RAM\nNVMe SSD storage\nUnlimited player slots\nLow-latency global network\nDDoS protection\nInstant deployment\nMod & plugin support",
        ],
        [
            'name' => 'pricing_card_1_image',
            'label' => 'Transparent Pricing - Card 1 Image (Upload)',
            'type' => 'file',
            'accept' => ['image/*'],
            'disk' => 'public',
            'visibility' => 'public',
            'default' => '',
        ],
        [
            'name' => 'pricing_card_1_button_text',
            'label' => 'Transparent Pricing - Card 1 Button Text',
            'type' => 'text',
            'default' => 'Get Started',
        ],
        [
            'name' => 'pricing_card_1_button_href',
            'label' => 'Transparent Pricing - Card 1 Button Link',
            'type' => 'text',
            'default' => '',
        ],

        // Pricing Card 2
        [
            'name' => 'pricing_card_2_enabled',
            'label' => 'Transparent Pricing - Card 2 Enabled',
            'type' => 'checkbox',
            'default' => true,
            'database_type' => 'boolean',
        ],
        [
            'name' => 'pricing_card_2_highlighted',
            'label' => 'Transparent Pricing - Card 2 Highlighted (middle card)',
            'type' => 'checkbox',
            'default' => true,
            'database_type' => 'boolean',
        ],
        [
            'name' => 'pricing_card_2_title',
            'label' => 'Transparent Pricing - Card 2 Title',
            'type' => 'text',
            'default' => 'VPS',
        ],
        [
            'name' => 'pricing_card_2_subtitle',
            'label' => 'Transparent Pricing - Card 2 Subtitle',
            'type' => 'text',
            'default' => 'Dedicated resources for production workloads and full control',
        ],
        [
            'name' => 'pricing_card_2_price',
            'label' => 'Transparent Pricing - Card 2 Price',
            'type' => 'text',
            'default' => '34',
        ],
        [
            'name' => 'pricing_card_2_price_suffix',
            'label' => 'Transparent Pricing - Card 2 Price Suffix',
            'type' => 'text',
            'default' => '/month',
        ],
        [
            'name' => 'pricing_card_2_features',
            'label' => 'Transparent Pricing - Card 2 Features (one per line)',
            'type' => 'textarea',
            'default' => "4 vCPU cores\n16 GB RAM\n200 GB NVMe storage\n6 TB bandwidth\nDDoS protection\nHourly backups\nPriority support\nCustom ISO support",
        ],
        [
            'name' => 'pricing_card_2_image',
            'label' => 'Transparent Pricing - Card 2 Image (Upload)',
            'type' => 'file',
            'accept' => ['image/*'],
            'disk' => 'public',
            'visibility' => 'public',
            'default' => '',
        ],
        [
            'name' => 'pricing_card_2_button_text',
            'label' => 'Transparent Pricing - Card 2 Button Text',
            'type' => 'text',
            'default' => 'Get Started',
        ],
        [
            'name' => 'pricing_card_2_button_href',
            'label' => 'Transparent Pricing - Card 2 Button Link',
            'type' => 'text',
            'default' => '',
        ],

        // Pricing Card 3
        [
            'name' => 'pricing_card_3_enabled',
            'label' => 'Transparent Pricing - Card 3 Enabled',
            'type' => 'checkbox',
            'default' => true,
            'database_type' => 'boolean',
        ],
        [
            'name' => 'pricing_card_3_title',
            'label' => 'Transparent Pricing - Card 3 Title',
            'type' => 'text',
            'default' => 'Web Hosting',
        ],
        [
            'name' => 'pricing_card_3_subtitle',
            'label' => 'Transparent Pricing - Card 3 Subtitle',
            'type' => 'text',
            'default' => 'Fast, secure hosting optimized for modern sites and apps',
        ],
        [
            'name' => 'pricing_card_3_price',
            'label' => 'Transparent Pricing - Card 3 Price',
            'type' => 'text',
            'default' => '9.99',
        ],
        [
            'name' => 'pricing_card_3_price_suffix',
            'label' => 'Transparent Pricing - Card 3 Price Suffix',
            'type' => 'text',
            'default' => '/month',
        ],
        [
            'name' => 'pricing_card_3_features',
            'label' => 'Transparent Pricing - Card 3 Features (one per line)',
            'type' => 'textarea',
            'default' => "SSD/NVMe storage\nFree SSL certificates\nDaily backups\nGlobal CDN caching\nStaging environments\nMalware scanning\nEmail accounts included\nOne-click installs",
        ],
        [
            'name' => 'pricing_card_3_image',
            'label' => 'Transparent Pricing - Card 3 Image (Upload)',
            'type' => 'file',
            'accept' => ['image/*'],
            'disk' => 'public',
            'visibility' => 'public',
            'default' => '',
        ],
        [
            'name' => 'pricing_card_3_button_text',
            'label' => 'Transparent Pricing - Card 3 Button Text',
            'type' => 'text',
            'default' => 'Get Started',
        ],
        [
            'name' => 'pricing_card_3_button_href',
            'label' => 'Transparent Pricing - Card 3 Button Link',
            'type' => 'text',
            'default' => '',
        ],

        /*
        |--------------------------------------------------------------------------
        | VIDEO HUB (HOME)
        |--------------------------------------------------------------------------
        */
        [
            'name' => 'video_hub_enabled',
            'label' => 'Video Hub - Enable Section',
            'type' => 'checkbox',
            'default' => true,
            'database_type' => 'boolean',
        ],
        [
            'name' => 'video_hub_title',
            'label' => 'Video Hub - Title',
            'type' => 'text',
            'default' => 'Video Hub',
        ],
        [
            'name' => 'video_hub_subtitle',
            'label' => 'Video Hub - Subtitle',
            'type' => 'text',
            'default' => 'Tutorials, walkthroughs, and quick tips to get the most out of your services.',
        ],
        [
            'name' => 'video_hub_youtube_api_key',
            'label' => 'Video Hub - YouTube API Key',
            'type' => 'text',
            'default' => '',
        ],
        [
            'name' => 'video_hub_channel_id',
            'label' => 'Video Hub - YouTube Channel ID (Optional)',
            'type' => 'text',
            'default' => '',
        ],

        // Category 1
        [
            'name' => 'video_hub_category_1_title',
            'label' => 'Video Hub - Category 1 Title',
            'type' => 'text',
            'default' => 'Getting Started',
        ],
        [
            'name' => 'video_hub_category_1_playlist_id',
            'label' => 'Video Hub - Category 1 Playlist ID',
            'type' => 'text',
            'default' => '',
        ],

        // Category 2
        [
            'name' => 'video_hub_category_2_title',
            'label' => 'Video Hub - Category 2 Title',
            'type' => 'text',
            'default' => 'Game Servers',
        ],
        [
            'name' => 'video_hub_category_2_playlist_id',
            'label' => 'Video Hub - Category 2 Playlist ID',
            'type' => 'text',
            'default' => '',
        ],

        // Category 3
        [
            'name' => 'video_hub_category_3_title',
            'label' => 'Video Hub - Category 3 Title',
            'type' => 'text',
            'default' => 'VPS & Networking',
        ],
        [
            'name' => 'video_hub_category_3_playlist_id',
            'label' => 'Video Hub - Category 3 Playlist ID',
            'type' => 'text',
            'default' => '',
        ],

        // Category 4
        [
            'name' => 'video_hub_category_4_title',
            'label' => 'Video Hub - Category 4 Title',
            'type' => 'text',
            'default' => 'Web Hosting',
        ],
        [
            'name' => 'video_hub_category_4_playlist_id',
            'label' => 'Video Hub - Category 4 Playlist ID',
            'type' => 'text',
            'default' => '',
        ],

        /*
        |--------------------------------------------------------------------------
        | THEME COLORS / GLOBALS
        |--------------------------------------------------------------------------
        */
        [
            'name' => 'primary',
            'label' => 'Primary - Brand Color (Light)',
            'type' => 'color',
            'default' => 'hsl(225, 60%, 46%)',
        ],
        [
            'name' => 'secondary',
            'label' => 'Secondary - Brand Color (Light)',
            'type' => 'color',
            'default' => 'hsl(235, 18%, 46%)',
        ],
        [
            'name' => 'neutral',
            'label' => 'Neutral - Borders (Light)',
            'type' => 'color',
            'default' => 'hsl(220, 16%, 86%)',
        ],
        [
            'name' => 'base',
            'label' => 'Base - Text Color (Light)',
            'type' => 'color',
            'default' => 'hsl(222, 22%, 12%)',
        ],
        [
            'name' => 'muted',
            'label' => 'Muted - Text Color (Light)',
            'type' => 'color',
            'default' => 'hsl(223, 14%, 38%)',
        ],
        [
            'name' => 'inverted',
            'label' => 'Inverted - Text Color (Light)',
            'type' => 'color',
            'default' => 'hsl(0, 0%, 100%)',
        ],
        [
            'name' => 'background',
            'label' => 'Background - Color (Light)',
            'type' => 'color',
            'default' => 'hsl(220, 20%, 98%)',
        ],
        [
            'name' => 'background-secondary',
            'label' => 'Surface / Card Color (Light)',
            'type' => 'color',
            'default' => 'hsl(220, 18%, 96%)',
        ],
        [
            'name' => 'obs_bg_light_tint_1',
            'label' => 'Background Tint (Light) - Color 1',
            'type' => 'color',
            'default' => 'hsl(225, 28%, 60%)',
        ],
        [
            'name' => 'obs_bg_light_tint_2',
            'label' => 'Background Tint (Light) - Color 2',
            'type' => 'color',
            'default' => 'hsl(210, 18%, 58%)',
        ],
        [
            'name' => 'obs_bg_light_strength',
            'label' => 'Background Tint (Light) - Strength (0.00 to 0.20)',
            'type' => 'text',
            'default' => '0.05',
        ],

        [
            'name' => 'dark-primary',
            'label' => 'Primary - Brand Color (Dark)',
            'type' => 'color',
            'default' => 'hsl(225, 78%, 70%)',
        ],
        [
            'name' => 'dark-secondary',
            'label' => 'Secondary - Brand Color (Dark)',
            'type' => 'color',
            'default' => 'hsl(235, 22%, 66%)',
        ],
        [
            'name' => 'dark-neutral',
            'label' => 'Neutral - Borders (Dark)',
            'type' => 'color',
            'default' => 'hsl(240, 10%, 18%)',
        ],
        [
            'name' => 'dark-base',
            'label' => 'Base - Text Color (Dark)',
            'type' => 'color',
            'default' => 'hsl(0, 0%, 98%)',
        ],
        [
            'name' => 'dark-muted',
            'label' => 'Muted - Text Color (Dark)',
            'type' => 'color',
            'default' => 'hsl(220, 10%, 70%)',
        ],
        [
            'name' => 'dark-inverted',
            'label' => 'Inverted - Text Color (Dark)',
            'type' => 'color',
            'default' => 'hsl(220, 12%, 60%)',
        ],
        [
            'name' => 'dark-background',
            'label' => 'Background - Color (Dark)',
            'type' => 'color',
            'default' => 'hsl(240, 18%, 9%)',
        ],
        [
            'name' => 'dark-background-secondary',
            'label' => 'Surface / Card Color (Dark)',
            'type' => 'color',
            'default' => 'hsl(240, 13%, 11%)',
        ],
        [
            'name' => 'obs_bg_dark_tint_1',
            'label' => 'Background Tint (Dark) - Color 1',
            'type' => 'color',
            'default' => 'hsl(230, 20%, 20%)',
        ],
        [
            'name' => 'obs_bg_dark_tint_2',
            'label' => 'Background Tint (Dark) - Color 2',
            'type' => 'color',
            'default' => 'hsl(210, 18%, 16%)',
        ],
        [
            'name' => 'obs_bg_dark_strength',
            'label' => 'Background Tint (Dark) - Strength (0.00 to 0.20)',
            'type' => 'text',
            'default' => '0.07',
        ],

        [
            'name' => 'direct_checkout',
            'label' => 'Direct Checkout',
            'type' => 'checkbox',
            'default' => false,
            'database_type' => 'boolean',
        ],
        [
            'name' => 'small_images',
            'label' => 'Small Images',
            'type' => 'checkbox',
            'default' => false,
            'database_type' => 'boolean',
        ],
        [
            'name' => 'show_category_description',
            'label' => 'Show Category Description',
            'type' => 'checkbox',
            'default' => true,
            'database_type' => 'boolean',
        ],
        [
            'name' => 'logo_display',
            'label' => 'Logo display',
            'type' => 'select',
            'options' => [
                'logo-only' => 'Logo only',
                'logo-and-name' => 'Logo and Name',
            ],
            'default' => 'logo-and-name',
        ],

        /*
        |--------------------------------------------------------------------------
        | FOOTER
        |--------------------------------------------------------------------------
        */
        [
            'name' => 'footer_enabled',
            'label' => 'Footer - Enable',
            'type' => 'checkbox',
            'default' => true,
            'database_type' => 'boolean',
        ],
        [
            'name' => 'footer_show_logo',
            'label' => 'Footer - Show Logo',
            'type' => 'checkbox',
            'default' => true,
            'database_type' => 'boolean',
        ],
        [
            'name' => 'footer_logo',
            'label' => 'Footer - Logo Image',
            'type' => 'file',
            'accept' => ['image/*'],
            'disk' => 'public',
            'visibility' => 'public',
            'default' => '',
        ],
        [
            'name' => 'footer_brand_href',
            'label' => 'Footer - Brand Link (href)',
            'type' => 'text',
            'default' => '/',
        ],
        [
            'name' => 'footer_tagline',
            'label' => 'Footer - Tagline',
            'type' => 'text',
            'default' => 'Infrastructure forged in precision.',
        ],

        // Column 1
        [
            'name' => 'footer_col_1_enabled',
            'label' => 'Footer - Column 1 Enabled',
            'type' => 'checkbox',
            'default' => true,
            'database_type' => 'boolean',
        ],
        [
            'name' => 'footer_col_1_title',
            'label' => 'Footer - Column 1 Title',
            'type' => 'text',
            'default' => 'Services',
        ],
        [
            'name' => 'footer_col_1_link_1_enabled',
            'label' => 'Footer - Column 1 Link 1 Enabled',
            'type' => 'checkbox',
            'default' => true,
            'database_type' => 'boolean',
        ],
        [
            'name' => 'footer_col_1_link_1_label',
            'label' => 'Footer - Column 1 Link 1 Label',
            'type' => 'text',
            'default' => 'Game Servers',
        ],
        [
            'name' => 'footer_col_1_link_1_href',
            'label' => 'Footer - Column 1 Link 1 Href',
            'type' => 'text',
            'default' => '#game-servers',
        ],
        [
            'name' => 'footer_col_1_link_2_enabled',
            'label' => 'Footer - Column 1 Link 2 Enabled',
            'type' => 'checkbox',
            'default' => true,
            'database_type' => 'boolean',
        ],
        [
            'name' => 'footer_col_1_link_2_label',
            'label' => 'Footer - Column 1 Link 2 Label',
            'type' => 'text',
            'default' => 'VPS',
        ],
        [
            'name' => 'footer_col_1_link_2_href',
            'label' => 'Footer - Column 1 Link 2 Href',
            'type' => 'text',
            'default' => '#vps',
        ],
        [
            'name' => 'footer_col_1_link_3_enabled',
            'label' => 'Footer - Column 1 Link 3 Enabled',
            'type' => 'checkbox',
            'default' => true,
            'database_type' => 'boolean',
        ],
        [
            'name' => 'footer_col_1_link_3_label',
            'label' => 'Footer - Column 1 Link 3 Label',
            'type' => 'text',
            'default' => 'Web Hosting',
        ],
        [
            'name' => 'footer_col_1_link_3_href',
            'label' => 'Footer - Column 1 Link 3 Href',
            'type' => 'text',
            'default' => '#web-hosting',
        ],
        [
            'name' => 'footer_col_1_link_4_enabled',
            'label' => 'Footer - Column 1 Link 4 Enabled',
            'type' => 'checkbox',
            'default' => true,
            'database_type' => 'boolean',
        ],
        [
            'name' => 'footer_col_1_link_4_label',
            'label' => 'Footer - Column 1 Link 4 Label',
            'type' => 'text',
            'default' => 'Dedicated Servers',
        ],
        [
            'name' => 'footer_col_1_link_4_href',
            'label' => 'Footer - Column 1 Link 4 Href',
            'type' => 'text',
            'default' => '#dedicated',
        ],

        // Column 2
        [
            'name' => 'footer_col_2_enabled',
            'label' => 'Footer - Column 2 Enabled',
            'type' => 'checkbox',
            'default' => true,
            'database_type' => 'boolean',
        ],
        [
            'name' => 'footer_col_2_title',
            'label' => 'Footer - Column 2 Title',
            'type' => 'text',
            'default' => 'Company',
        ],
        [
            'name' => 'footer_col_2_link_1_enabled',
            'label' => 'Footer - Column 2 Link 1 Enabled',
            'type' => 'checkbox',
            'default' => true,
            'database_type' => 'boolean',
        ],
        [
            'name' => 'footer_col_2_link_1_label',
            'label' => 'Footer - Column 2 Link 1 Label',
            'type' => 'text',
            'default' => 'About',
        ],
        [
            'name' => 'footer_col_2_link_1_href',
            'label' => 'Footer - Column 2 Link 1 Href',
            'type' => 'text',
            'default' => '#about',
        ],
        [
            'name' => 'footer_col_2_link_2_enabled',
            'label' => 'Footer - Column 2 Link 2 Enabled',
            'type' => 'checkbox',
            'default' => true,
            'database_type' => 'boolean',
        ],
        [
            'name' => 'footer_col_2_link_2_label',
            'label' => 'Footer - Column 2 Link 2 Label',
            'type' => 'text',
            'default' => 'Status',
        ],
        [
            'name' => 'footer_col_2_link_2_href',
            'label' => 'Footer - Column 2 Link 2 Href',
            'type' => 'text',
            'default' => '#status',
        ],
        [
            'name' => 'footer_col_2_link_3_enabled',
            'label' => 'Footer - Column 2 Link 3 Enabled',
            'type' => 'checkbox',
            'default' => true,
            'database_type' => 'boolean',
        ],
        [
            'name' => 'footer_col_2_link_3_label',
            'label' => 'Footer - Column 2 Link 3 Label',
            'type' => 'text',
            'default' => 'Careers',
        ],
        [
            'name' => 'footer_col_2_link_3_href',
            'label' => 'Footer - Column 2 Link 3 Href',
            'type' => 'text',
            'default' => '#careers',
        ],
        [
            'name' => 'footer_col_2_link_4_enabled',
            'label' => 'Footer - Column 2 Link 4 Enabled',
            'type' => 'checkbox',
            'default' => true,
            'database_type' => 'boolean',
        ],
        [
            'name' => 'footer_col_2_link_4_label',
            'label' => 'Footer - Column 2 Link 4 Label',
            'type' => 'text',
            'default' => 'Blog',
        ],
        [
            'name' => 'footer_col_2_link_4_href',
            'label' => 'Footer - Column 2 Link 4 Href',
            'type' => 'text',
            'default' => '#blog',
        ],

        // Column 3
        [
            'name' => 'footer_col_3_enabled',
            'label' => 'Footer - Column 3 Enabled',
            'type' => 'checkbox',
            'default' => true,
            'database_type' => 'boolean',
        ],
        [
            'name' => 'footer_col_3_title',
            'label' => 'Footer - Column 3 Title',
            'type' => 'text',
            'default' => 'Support',
        ],
        [
            'name' => 'footer_col_3_link_1_enabled',
            'label' => 'Footer - Column 3 Link 1 Enabled',
            'type' => 'checkbox',
            'default' => true,
            'database_type' => 'boolean',
        ],
        [
            'name' => 'footer_col_3_link_1_label',
            'label' => 'Footer - Column 3 Link 1 Label',
            'type' => 'text',
            'default' => 'Docs',
        ],
        [
            'name' => 'footer_col_3_link_1_href',
            'label' => 'Footer - Column 3 Link 1 Href',
            'type' => 'text',
            'default' => '#docs',
        ],
        [
            'name' => 'footer_col_3_link_2_enabled',
            'label' => 'Footer - Column 3 Link 2 Enabled',
            'type' => 'checkbox',
            'default' => true,
            'database_type' => 'boolean',
        ],
        [
            'name' => 'footer_col_3_link_2_label',
            'label' => 'Footer - Column 3 Link 2 Label',
            'type' => 'text',
            'default' => 'Contact',
        ],
        [
            'name' => 'footer_col_3_link_2_href',
            'label' => 'Footer - Column 3 Link 2 Href',
            'type' => 'text',
            'default' => '#contact',
        ],
        [
            'name' => 'footer_col_3_link_3_enabled',
            'label' => 'Footer - Column 3 Link 3 Enabled',
            'type' => 'checkbox',
            'default' => true,
            'database_type' => 'boolean',
        ],
        [
            'name' => 'footer_col_3_link_3_label',
            'label' => 'Footer - Column 3 Link 3 Label',
            'type' => 'text',
            'default' => 'Knowledgebase',
        ],
        [
            'name' => 'footer_col_3_link_3_href',
            'label' => 'Footer - Column 3 Link 3 Href',
            'type' => 'text',
            'default' => '#knowledgebase',
        ],
        [
            'name' => 'footer_col_3_link_4_enabled',
            'label' => 'Footer - Column 3 Link 4 Enabled',
            'type' => 'checkbox',
            'default' => true,
            'database_type' => 'boolean',
        ],
        [
            'name' => 'footer_col_3_link_4_label',
            'label' => 'Footer - Column 3 Link 4 Label',
            'type' => 'text',
            'default' => 'System Status',
        ],
        [
            'name' => 'footer_col_3_link_4_href',
            'label' => 'Footer - Column 3 Link 4 Href',
            'type' => 'text',
            'default' => '#system-status',
        ],

        // Column 4
        [
            'name' => 'footer_col_4_enabled',
            'label' => 'Footer - Column 4 Enabled',
            'type' => 'checkbox',
            'default' => true,
            'database_type' => 'boolean',
        ],
        [
            'name' => 'footer_col_4_title',
            'label' => 'Footer - Column 4 Title',
            'type' => 'text',
            'default' => 'Legal',
        ],
        [
            'name' => 'footer_col_4_link_1_enabled',
            'label' => 'Footer - Column 4 Link 1 Enabled',
            'type' => 'checkbox',
            'default' => true,
            'database_type' => 'boolean',
        ],
        [
            'name' => 'footer_col_4_link_1_label',
            'label' => 'Footer - Column 4 Link 1 Label',
            'type' => 'text',
            'default' => 'Terms',
        ],
        [
            'name' => 'footer_col_4_link_1_href',
            'label' => 'Footer - Column 4 Link 1 Href',
            'type' => 'text',
            'default' => '#terms',
        ],
        [
            'name' => 'footer_col_4_link_2_enabled',
            'label' => 'Footer - Column 4 Link 2 Enabled',
            'type' => 'checkbox',
            'default' => true,
            'database_type' => 'boolean',
        ],
        [
            'name' => 'footer_col_4_link_2_label',
            'label' => 'Footer - Column 4 Link 2 Label',
            'type' => 'text',
            'default' => 'Privacy',
        ],
        [
            'name' => 'footer_col_4_link_2_href',
            'label' => 'Footer - Column 4 Link 2 Href',
            'type' => 'text',
            'default' => '#privacy',
        ],
        [
            'name' => 'footer_col_4_link_3_enabled',
            'label' => 'Footer - Column 4 Link 3 Enabled',
            'type' => 'checkbox',
            'default' => true,
            'database_type' => 'boolean',
        ],
        [
            'name' => 'footer_col_4_link_3_label',
            'label' => 'Footer - Column 4 Link 3 Label',
            'type' => 'text',
            'default' => 'Refund Policy',
        ],
        [
            'name' => 'footer_col_4_link_3_href',
            'label' => 'Footer - Column 4 Link 3 Href',
            'type' => 'text',
            'default' => '#refund',
        ],

        // Bottom row toggles
        [
            'name' => 'footer_powered_by_enabled',
            'label' => 'Footer - Show "Powered by Paymenter"',
            'type' => 'checkbox',
            'default' => true,
            'database_type' => 'boolean',
        ],

        // Socials
        [
            'name' => 'footer_social_github_enabled',
            'label' => 'Footer - GitHub Icon Enabled',
            'type' => 'checkbox',
            'default' => true,
            'database_type' => 'boolean',
        ],
        [
            'name' => 'footer_social_github_href',
            'label' => 'Footer - GitHub Link (href)',
            'type' => 'text',
            'default' => '#github',
        ],

        [
            'name' => 'footer_social_twitter_enabled',
            'label' => 'Footer - Twitter Icon Enabled',
            'type' => 'checkbox',
            'default' => true,
            'database_type' => 'boolean',
        ],
        [
            'name' => 'footer_social_twitter_href',
            'label' => 'Footer - Twitter Link (href)',
            'type' => 'text',
            'default' => '#twitter',
        ],

        [
            'name' => 'footer_social_linkedin_enabled',
            'label' => 'Footer - LinkedIn Icon Enabled',
            'type' => 'checkbox',
            'default' => true,
            'database_type' => 'boolean',
        ],
        [
            'name' => 'footer_social_linkedin_href',
            'label' => 'Footer - LinkedIn Link (href)',
            'type' => 'text',
            'default' => '#linkedin',
        ],
    ],
];
