<?php get_header(); ?>
<main class="blog-main">
  <section class="blog-hero"><div class="wrap"><p class="eyebrow">Field journal</p><h1>현장에서 기록한<br>조명 이야기</h1><p class="section-copy">지역과 공간, 작업 내용을 구체적으로 담습니다. 비슷한 현장의 조명 선택에 도움이 되길 바랍니다.</p></div></section>
  <section class="section"><div class="wrap"><div class="post-grid">
    <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
      <article class="post-card" data-reveal><a href="<?php the_permalink(); ?>"><div class="post-thumb"><?php if (has_post_thumbnail()) {
          the_post_thumbnail('large');
      } else {
          echo '<img src="' . esc_url(get_template_directory_uri() . '/assets/images/barrisol-hero.png') . '" alt="">';
      } ?></div><div class="post-meta"><?php echo esc_html(get_the_date('Y.m.d')); ?></div><h3><?php the_title(); ?></h3><p><?php echo esc_html(get_the_excerpt()); ?></p></a></article>
    <?php endwhile;
else : ?><p>새로운 시공 기록을 준비하고 있습니다.</p><?php endif; ?>
  </div><?php the_posts_pagination(); ?></div></section>
</main>
<?php get_footer(); ?>
