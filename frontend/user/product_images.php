<?php

declare(strict_types=1);

/** Category illustrations are used only when a product has no usable photo. */
function store_category_default_image(string $category): string
{
    $category = mb_strtolower(trim($category), 'UTF-8');
    $types = [
        'ram' => '/\bram\b|memory|ذاكرة|رام/u',
        'gpu' => '/\bgpu\b|\bgbu\b|graphics|graphic|video card|كرت شاشة|بطاق.*رسوم/u',
        'motherboard' => '/mother.?board|main.?board|لوح.*أم/u',
        'cpu' => '/\bcpu\b|processor|معالج/u',
        'psu' => '/\bpsu\b|power suppl|power sppl|power supply|مزود|مزوّد|باور/u',
        'monitor' => '/monitor|moniter|display|شاش/u',
        'mouse' => '/mouse|mice|ماوس|فأرة/u',
        'keyboard' => '/keyboard|key board|keboard|كيبورد|لوح.*مفاتيح/u',
    ];

    foreach ($types as $type => $pattern) {
        if (preg_match($pattern, $category) === 1) {
            return 'assets/category-defaults/' . $type . '.svg';
        }
    }

    return 'assets/category-defaults/component.svg';
}
