<?php get_header(); ?>
<main><?php while (have_posts()) : the_post(); ?>
  <article class="article"><header class="wrap article-header"><div class="article-meta"><?php echo esc_html(get_the_date('Y.m.d')); ?>, 갤러리조명 시공 기록</div><h1><?php the_title(); ?></h1></header><div class="article-body"><?php the_content(); ?></div></article>
<?php endwhile; ?></main>
<?php get_footer(); ?>
