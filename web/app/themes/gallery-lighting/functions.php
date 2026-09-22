<?php

function gallery_lighting_setup(): void
{
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'gallery', 'caption', 'style', 'script']);
    register_nav_menus(['primary' => '주 메뉴']);
}
add_action('after_setup_theme', 'gallery_lighting_setup');

function gallery_lighting_assets(): void
{
    wp_enqueue_style('gallery-lighting', get_stylesheet_uri(), [], '1.0.0');
    wp_enqueue_script('gallery-lighting-motion', get_template_directory_uri() . '/assets/js/site.js', [], '1.0.0', true);
}
add_action('wp_enqueue_scripts', 'gallery_lighting_assets');

function gallery_lighting_module_script(string $tag, string $handle): string
{
    if ($handle !== 'gallery-lighting-motion') {
        return $tag;
    }

    return str_replace('<script ', '<script type="module" ', $tag);
}
add_filter('script_loader_tag', 'gallery_lighting_module_script', 10, 2);

function gallery_lighting_description(): void
{
    if (is_front_page()) {
        echo '<meta name="description" content="울산 갤러리조명은 바리솔 조명, LED 엣지등, 해외 수입 식탁등, 병원과 은행 및 기관 조명을 전문 시공합니다. 부산 울산 경남 출장 상담 010-4588-8709">' . "\n";
    }
}
add_action('wp_head', 'gallery_lighting_description', 1);

function gallery_lighting_schema(): void
{
    if (!is_front_page()) {
        return;
    }

    $schema = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => ['LocalBusiness', 'HomeAndConstructionBusiness'],
                '@id' => home_url('/#business'),
                'name' => '갤러리조명',
                'description' => '울산을 기반으로 부산 울산 경남의 바리솔 조명, LED 엣지등, 수입 식탁등과 상업 및 기관 조명을 시공하는 조명 전문 업체',
                'url' => home_url('/'),
                'telephone' => ['010-4588-8709', '052-257-0644'],
                'email' => 'luxurylusso@naver.com',
                'address' => [
                    '@type' => 'PostalAddress',
                    'streetAddress' => '갈밭로10번길 9',
                    'addressLocality' => '남구',
                    'addressRegion' => '울산광역시',
                    'addressCountry' => 'KR',
                ],
                'areaServed' => ['울산광역시', '부산광역시', '경상남도'],
                'sameAs' => ['https://blog.naver.com/luxurylusso'],
                'knowsAbout' => ['바리솔 조명', 'LED 엣지등', '수입 식탁등', '병원 조명', '은행 조명', '기관 조명'],
            ],
            [
                '@type' => 'FAQPage',
                'mainEntity' => gallery_lighting_faq_schema(),
            ],
        ],
    ];

    echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
}
add_action('wp_head', 'gallery_lighting_schema', 20);

function gallery_lighting_faq_schema(): array
{
    $faqs = [
        ['바리솔 조명은 어떤 공간에 적합한가요?', '그림자 없이 부드럽고 균일한 빛이 필요한 병원, 피부과, 은행, 로비, 사무실과 주거 공간에 적합합니다. 공간 형태에 맞춰 크기와 모양을 제작할 수 있어 천장 디자인을 깔끔하게 완성할 수 있습니다.'],
        ['울산 외 지역도 출장 시공이 가능한가요?', '네. 울산을 중심으로 부산과 경남 지역을 주로 시공하며, 현장 규모와 일정에 따라 포항을 포함한 타 지역 출장도 상담할 수 있습니다.'],
        ['기존 바리솔 조명의 LED만 교체할 수 있나요?', '가능합니다. 밝기가 떨어졌거나 일부가 점등되지 않는 경우 현장 상태를 확인한 뒤 LED 모듈, 컨버터, 배선 문제를 진단하고 필요한 부분만 수리합니다.'],
        ['가정용 LED 엣지등도 설치하나요?', '네. 아파트와 주택의 거실등, 방등, 주방등, 욕실등을 슬림 엣지등과 LED 조명으로 교체하며 기존 배선 상태도 함께 확인합니다.'],
        ['해외 수입 식탁등 설치도 가능한가요?', '가능합니다. 베르판을 비롯한 해외 수입 펜던트와 직구 조명의 조립, 높이 조절, 천장 보강, 전선 수리와 설치를 상담합니다.'],
    ];

    return array_map(fn($faq) => [
        '@type' => 'Question',
        'name' => $faq[0],
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq[1]],
    ], $faqs);
}

function gallery_lighting_excerpt_length(): int
{
    return 26;
}
add_filter('excerpt_length', 'gallery_lighting_excerpt_length');
