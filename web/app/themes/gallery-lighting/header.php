<!doctype html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<header class="site-header" id="site-header">
  <div class="wrap nav">
    <a class="brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="갤러리조명 홈">
      <span>갤러리조명</span>
    </a>
    <nav class="nav-links" aria-label="주 메뉴">
      <a href="<?php echo esc_url(home_url('/#services')); ?>">전문 시공</a>
      <a href="<?php echo esc_url(home_url('/#barrisol')); ?>">바리솔</a>
      <a href="<?php echo esc_url(home_url('/#area')); ?>">출장 지역</a>
      <a href="<?php echo esc_url(home_url('/blog/')); ?>">시공 기록</a>
      <a class="nav-call" href="tel:01045888709">전화 상담</a>
    </nav>
  </div>
</header>
