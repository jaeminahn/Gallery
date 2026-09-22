<?php get_header(); ?>
<main><?php while (have_posts()) : the_post(); ?>
  <article class="article">
    <div class="wrap">
      <nav class="article-nav" aria-label="글 탐색">
        <a class="btn-link" href="<?php echo esc_url(home_url('/blog/')); ?>"><span aria-hidden="true">&larr;</span> 시공 기록 목록</a>
        <span class="article-nav-label">갤러리조명 시공 기록</span>
      </nav>
      <header class="article-header">
        <div class="article-meta"><?php echo esc_html(get_the_date('Y.m.d')); ?></div>
        <h1><?php the_title(); ?></h1>
      </header>
      <div class="article-body"><?php the_content(); ?></div>
      <footer class="article-footer">
        <div class="article-share">
          <span>이 글이 도움이 되었다면 주변에 알려주세요.</span>
          <button type="button" class="btn btn-share" data-share-url="<?php echo esc_url(get_permalink()); ?>" data-share-title="<?php echo esc_attr(get_the_title()); ?>">링크 복사</button>
        </div>
        <nav class="article-pager" aria-label="이전/다음 글">
          <?php
            $prev = get_previous_post();
    $next = get_next_post();
    ?>
          <div class="article-pager-item">
            <?php if ($prev) : ?><span class="article-pager-label">이전 글</span><a href="<?php echo esc_url(get_permalink($prev)); ?>"><?php echo esc_html(get_the_title($prev)); ?></a>
            <?php else : ?><span class="article-pager-label">이전 글</span><span class="article-pager-empty">가장 오래된 글입니다</span><?php endif; ?>
          </div>
          <div class="article-pager-item article-pager-next">
            <?php if ($next) : ?><span class="article-pager-label">다음 글</span><a href="<?php echo esc_url(get_permalink($next)); ?>"><?php echo esc_html(get_the_title($next)); ?></a>
            <?php else : ?><span class="article-pager-label">다음 글</span><span class="article-pager-empty">가장 최근 글입니다</span><?php endif; ?>
          </div>
        </nav>
        <div class="article-cta">
          <h2>비슷한 고민이 있으신가요?</h2>
          <p>사진과 지역을 문자로 본내주시면 빠르게 상담해 드립니다.</p>
          <a class="btn btn-light" href="tel:01045888709">010-4588-8709 전화하기</a>
        </div>
      </footer>
    </div>
  </article>
<?php endwhile; ?></main>
<?php get_footer(); ?>
