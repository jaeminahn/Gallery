<?php
/**
 * Plugin Name: Gallery Naver Blog Sync
 * Description: 갤러리조명 네이버 블로그 LED 카테고리 글을 선택해 워드프레스에 게시합니다.
 * Version: 1.0.0
 * Author: Gallery Lighting
 */

if (! defined('ABSPATH')) {
    exit;
}

final class Gallery_Naver_Blog_Sync
{
    private const BLOG_ID = 'luxurylusso';

    private const CATEGORY_NO = '1';

    private const CACHE_KEY = 'gallery_naver_led_posts_v2';

    private const THUMB_CACHE_KEY = 'gallery_naver_thumbnails_v1';

    private const LIST_OPTION = 'gallery_naver_led_posts_v3';

    private const LIST_PAGE_SIZE = 30;

    private const LIST_MAX_PAGES = 100;

    private const META_LOG_NO = '_gallery_naver_log_no';

    private const META_SOURCE_URL = '_gallery_naver_source_url';

    private const QUEUE_OPTION = 'gallery_naver_sync_queue';

    private const PROCESS_LOCK = 'gallery_naver_sync_process_lock';

    private const CANCEL_OPTION = 'gallery_naver_sync_cancel_requested';

    private const SETTINGS_OPTION = 'gallery_naver_sync_settings';

    private const META_REWRITTEN_AT = '_gallery_naver_rewritten_at';

    private const OPENAI_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    private const MAX_ATTEMPTS = 3;

    private const MIN_REQUEST_INTERVAL = 2.5;

    private const BLOCK_PAUSE = 30 * MINUTE_IN_SECONDS;

    private static float $last_request_at = 0.0;

    public static function boot(): void
    {
        add_action('admin_menu', [self::class, 'register_menu']);
        add_action('admin_post_gallery_naver_sync', [self::class, 'handle_sync']);
        add_action('admin_post_gallery_naver_refresh', [self::class, 'handle_refresh']);
        add_action('admin_enqueue_scripts', [self::class, 'admin_assets']);
        add_filter('upload_mimes', [self::class, 'keep_remote_images_only']);
        add_action('wp_ajax_gallery_naver_process_next', [self::class, 'handle_process_next']);
        add_action('wp_ajax_gallery_naver_cancel', [self::class, 'handle_cancel']);
        add_action('wp_ajax_gallery_naver_refresh_page', [self::class, 'handle_refresh_page']);
        add_action('admin_post_gallery_naver_save_settings', [self::class, 'handle_save_settings']);
        add_action('admin_post_gallery_naver_retry', [self::class, 'handle_retry']);
    }

    public static function register_menu(): void
    {
        add_menu_page(
            '네이버 글 동기화',
            '네이버 글 동기화',
            'manage_options',
            'gallery-naver-sync',
            [self::class, 'render_page'],
            'dashicons-update-alt',
            25,
        );
    }

    public static function admin_assets(string $hook): void
    {
        if ($hook !== 'toplevel_page_gallery-naver-sync') {
            return;
        }

        wp_register_style('gallery-naver-sync-admin', false, [], '1.0.0');
        wp_enqueue_style('gallery-naver-sync-admin');
        wp_add_inline_style('gallery-naver-sync-admin', self::admin_css());
        wp_add_inline_style('gallery-naver-sync-admin', self::admin_accessibility_css());
    }

    public static function keep_remote_images_only(array $mimes): array
    {
        return $mimes;
    }

    public static function render_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $result = self::get_remote_posts();
        $posts = $result['posts'];
        $thumbnails = self::get_rss_thumbnails();
        $queue_items = self::normalize_queue((array) get_option(self::QUEUE_OPTION, []));
        $queue_count = count(array_filter($queue_items, fn($item) => in_array($item['status'], ['pending', 'processing', 'cancelling'], true)));
        $failed_items = array_filter($queue_items, fn($item) => $item['status'] === 'failed');
        $is_syncing = $queue_count > 0;
        $published_map = self::get_published_map(array_column($posts, 'log_no'));
        $pending = [];
        $published = [];

        foreach ($posts as $post) {
            $post['queue_status'] = $queue_items[$post['log_no']]['status'] ?? '';
            if (! $post['thumbnail'] && isset($thumbnails[$post['log_no']])) {
                $post['thumbnail'] = $thumbnails[$post['log_no']];
            }
            if (isset($published_map[$post['log_no']])) {
                $post['wp_post_id'] = $published_map[$post['log_no']];
                if (! $post['thumbnail']) {
                    $first_image = get_post_meta($post['wp_post_id'], '_gallery_naver_first_image', true);
                    if ($first_image) {
                        $post['thumbnail'] = $first_image;
                    }
                }
                $published[] = $post;
            } else {
                $pending[] = $post;
            }
        }

        $per_page = 50;
        $pending_total = count($pending);
        $published_total = count($published);
        $pending_page = max(1, absint($_GET['pending_page'] ?? 1));
        $published_page = max(1, absint($_GET['published_page'] ?? 1));
        $pending = array_slice($pending, ($pending_page - 1) * $per_page, $per_page);
        $published = array_slice($published, ($published_page - 1) * $per_page, $per_page);

        $notice = get_transient('gallery_naver_sync_notice_' . get_current_user_id());
        delete_transient('gallery_naver_sync_notice_' . get_current_user_id());
        ?>
        <div class="wrap gallery-sync-admin <?php echo $is_syncing ? 'is-syncing' : ''; ?>">
            <?php $settings = self::get_settings();
        $env_key = self::env_api_key(); ?>
            <div class="gallery-sync-notices">
            <?php if (is_array($notice)) : ?>
                <div class="gallery-sync-alert gallery-sync-alert-<?php echo $notice['errors'] ? 'warning' : 'success'; ?>" role="<?php echo $notice['errors'] ? 'alert' : 'status'; ?>"><p><?php echo esc_html($notice['message']); ?></p></div>
            <?php endif; ?>
            </div>

            <div class="gallery-sync-hero">
                <div>
                    <h1>네이버 LED 글 동기화</h1>
                </div>
                <?php if ($is_syncing) : ?>
                    <span class="button gallery-refresh disabled" aria-disabled="true">작성 중에는 새로고침할 수 없음</span>
                <?php else : ?>
                    <a class="button gallery-refresh" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=gallery_naver_refresh'), 'gallery_naver_refresh')); ?>">목록 새로고침</a>
                <?php endif; ?>
            </div>

            <section class="gallery-sync-section gallery-sync-settings">
                <div class="gallery-sync-heading"><div><h2>AI 재작성 설정</h2></div></div>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="gallery_naver_save_settings">
                    <?php wp_nonce_field('gallery_naver_save_settings'); ?>
                    <fieldset <?php disabled($is_syncing); ?>>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="gallery-openai-key">OpenAI API 키</label></th>
                            <td>
                                <input type="password" id="gallery-openai-key" name="openai_api_key" class="regular-text" value="" placeholder="<?php echo $env_key ? '서버 환경변수(OPENAI_API_KEY) 사용 중' : 'sk-...'; ?>" autocomplete="new-password">
                                <p class="description">
                                    <?php if ($env_key) : ?>
                                        서버 환경변수 <code>OPENAI_API_KEY</code>가 설정되어 있어 환경변수 값을 우선 사용합니다. 저장된 키가 있어도 무시됩니다.
                                    <?php elseif ($settings['api_key']) : ?>
                                        키가 저장되어 있습니다. 변경하려면 새 키를 입력하고, 삭제하려면 <code>--delete--</code>를 입력하세요. 브라우저와 게시글에 노출되지 않습니다.
                                    <?php else : ?>
                                        키는 서버 데이터베이스에만 암호 형태로 저장되며 브라우저와 게시글에 노출되지 않습니다.
                                    <?php endif; ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="gallery-openai-model">모델</label></th>
                            <td>
                                <select id="gallery-openai-model" name="openai_model">
                                    <?php foreach (['gpt-4o-mini', 'gpt-4o', 'gpt-4.1-mini', 'gpt-4.1', 'gpt-5-mini', 'gpt-5'] as $model) : ?>
                                        <option value="<?php echo esc_attr($model); ?>" <?php selected($settings['model'], $model); ?>><?php echo esc_html($model); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">기본 게시 상태</th>
                            <td>
                                <label><input type="radio" name="post_status" value="draft" <?php checked($settings['post_status'], 'draft'); ?>> 임시글</label>
                                &nbsp;&nbsp;
                                <label><input type="radio" name="post_status" value="publish" <?php checked($settings['post_status'], 'publish'); ?>> 공개</label>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('설정 저장'); ?>
                    </fieldset>
                </form>
            </section>

            <?php
        $paused = (bool) get_transient('gallery_naver_sync_paused');
        ?>
            <div id="gallery-sync-progress" class="gallery-sync-progress" hidden>
                <div class="gallery-sync-progress-bar"><span id="gallery-sync-progress-fill"></span></div>
                <div class="gallery-sync-progress-copy">
                    <p id="gallery-sync-progress-text"></p>
                    <button type="button" id="gallery-sync-cancel" class="button">작성 취소</button>
                </div>
            </div>
            <?php if ($paused) : ?>
                <div class="gallery-sync-alert gallery-sync-alert-warning" role="alert"><p>네이버가 요청을 일시 제한했습니다. 약 30분 후에 자동으로 재개됩니다.</p></div>
            <?php endif; ?>

            <?php if ($result['error']) : ?>
                <div class="gallery-sync-alert gallery-sync-alert-error" role="alert"><p><?php echo esc_html($result['error']); ?></p></div>
            <?php endif; ?>

            <div class="gallery-sync-summary">
                <div><strong><?php echo esc_html($pending_total); ?></strong><span>게시 전</span></div>
                <div><strong><?php echo esc_html($published_total); ?></strong><span>게시 완료</span></div>
                <div><strong><?php echo esc_html(count($posts)); ?></strong><span>불러온 LED 글</span></div>
            </div>

            <div id="gallery-list-progress" class="gallery-sync-progress" <?php echo $result['complete'] ? 'hidden' : ''; ?>>
                <div class="gallery-sync-progress-bar"><span id="gallery-list-progress-fill"></span></div>
                <p id="gallery-list-progress-text"><?php echo $posts ? esc_html('저장된 ' . count($posts) . '개 글을 먼저 표시했습니다. 나머지 목록을 불러오는 중입니다.') : '네이버 글 목록을 불러오는 중입니다.'; ?></p>
            </div>

            <section class="gallery-sync-section gallery-sync-pending">
                <div class="gallery-sync-heading"><div><h2>게시 전</h2></div></div>
                <?php if ($pending) : ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="gallery_naver_sync">
                        <?php wp_nonce_field('gallery_naver_sync'); ?>
                        <div class="gallery-sync-toolbar">
                            <label><input type="checkbox" data-gallery-select-all> 전체 선택</label>
                            <button class="button button-primary button-hero" type="submit" <?php disabled($is_syncing); ?>><?php echo $is_syncing ? 'AI 작성 진행 중' : '선택한 글 동기화'; ?></button>
                        </div>
                        <div class="gallery-sync-list">
                            <?php foreach ($pending as $post) : self::render_row($post, true); endforeach; ?>
                        </div>
                        <?php self::render_pagination($pending_total, $pending_page, $per_page, 'pending_page'); ?>
                    </form>
                <?php else : ?>
                    <div class="gallery-empty">현재 목록의 모든 글이 게시되었습니다.</div>
                <?php endif; ?>
            </section>

            <section class="gallery-sync-section gallery-published">
                <div class="gallery-sync-heading"><div><h2>게시 완료</h2></div></div>
                <?php if ($published) : ?>
                    <div class="gallery-sync-list">
                        <?php foreach ($published as $post) : self::render_row($post, false); endforeach; ?>
                    </div>
                    <?php self::render_pagination($published_total, $published_page, $per_page, 'published_page'); ?>
                <?php else : ?>
                    <div class="gallery-empty">아직 동기화된 글이 없습니다.</div>
                <?php endif; ?>
            </section>

            <?php if ($failed_items) : ?>
                <section class="gallery-sync-section gallery-sync-failed">
                    <div class="gallery-sync-heading"><div><h2>처리 실패</h2><p>AI 재작성이 실패한 글입니다. 재시도하면 대기열 맨 앞에 다시 올라갑니다.</p></div></div>
                    <div class="gallery-sync-list">
                        <?php foreach ($failed_items as $item) : ?>
                            <article class="gallery-sync-row">
                                <span class="gallery-sync-status gallery-sync-status-failed">실패</span>
                                <div></div>
                                <div class="gallery-sync-info">
                                    <h3>원문 번호 <?php echo esc_html($item['log_no']); ?></h3>
                                    <div class="gallery-sync-meta"><span><?php echo esc_html($item['error']); ?> (시도 <?php echo esc_html($item['attempts']); ?>/<?php echo esc_html(self::MAX_ATTEMPTS); ?>)</span></div>
                                </div>
                                <div class="gallery-sync-actions">
                                    <?php if ($item['attempts'] < self::MAX_ATTEMPTS) : ?>
                                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=gallery_naver_retry&log_no=' . $item['log_no']), 'gallery_naver_retry')); ?>">재시도</a>
                                    <?php endif; ?>
                                    <a href="<?php echo esc_url(self::source_url($item['log_no'])); ?>" target="_blank" rel="noopener">네이버 원문</a>
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=gallery_naver_retry&log_no=' . $item['log_no'] . '&remove=1'), 'gallery_naver_retry')); ?>">목록에서 제거</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
        <script>
        (function () {
            const selectAll = document.querySelector('[data-gallery-select-all]');
            selectAll?.addEventListener('change', function () {
                document.querySelectorAll('input[name="post_ids[]"]').forEach((input) => input.checked = this.checked);
            });

            const progress = document.getElementById('gallery-sync-progress');
            const fill = document.getElementById('gallery-sync-progress-fill');
            const text = document.getElementById('gallery-sync-progress-text');
            const nonce = <?php echo wp_json_encode(wp_create_nonce('gallery_naver_process')); ?>;
            const cancelNonce = <?php echo wp_json_encode(wp_create_nonce('gallery_naver_cancel')); ?>;
            const cancelButton = document.getElementById('gallery-sync-cancel');
            const listNonce = <?php echo wp_json_encode(wp_create_nonce('gallery_naver_refresh_page')); ?>;
            const listComplete = <?php echo $result['complete'] ? 'true' : 'false'; ?>;
            const listProgress = document.getElementById('gallery-list-progress');
            const listFill = document.getElementById('gallery-list-progress-fill');
            const listText = document.getElementById('gallery-list-progress-text');
            let total = <?php echo esc_js($queue_count); ?>;
            let processed = 0;
            let running = false;
            let cancelRequested = false;

            function markNextAsProcessing() {
                const row = document.querySelector('[data-queue-status="pending"]');
                if (!row) return;
                row.dataset.queueStatus = 'processing';
                const badge = row.querySelector('.gallery-sync-status-pending');
                if (badge) {
                    badge.className = 'gallery-sync-status gallery-sync-status-processing';
                    badge.textContent = '게시 중';
                }
            }

            function markResult(logNo, status) {
                const row = document.querySelector('[data-log-no="' + logNo + '"]');
                if (!row) return;
                const badge = row.querySelector('.gallery-sync-status');
                if (!badge) return;
                row.dataset.queueStatus = status;
                badge.className = 'gallery-sync-status gallery-sync-status-' + status;
                badge.textContent = status === 'failed' ? '실패' : '완료';
            }

            async function tick() {
                if (document.hidden || running || cancelRequested) return;
                running = true;
                markNextAsProcessing();
                try {
                    const res = await fetch(ajaxurl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ action: 'gallery_naver_process_next', _ajax_nonce: nonce }),
                    });
                    const raw = await res.text();
                    let json;
                    try {
                        json = JSON.parse(raw);
                    } catch (error) {
                        throw new Error('서버가 올바른 응답을 보내지 않았습니다. HTTP ' + res.status);
                    }
                    if (!res.ok || !json.success) {
                        throw new Error(json.data?.message || '서버 요청에 실패했습니다. HTTP ' + res.status);
                    }

                    const data = json.data;
                    if (data.status === 'empty') {
                        if (processed > 0) window.location.reload();
                        return;
                    }
                    if (data.status === 'paused') {
                        text.textContent = '네이버가 요청을 일시 제한했습니다. 약 30분 후에 이 페이지를 새로고침하면 이어서 진행됩니다.';
                        fill.style.width = '100%';
                        return;
                    }
                    if (data.status === 'cancelled') {
                        cancelRequested = true;
                        fill.style.width = '0';
                        text.textContent = '자동 작성을 취소했습니다. 작성 중이던 글은 게시되지 않았습니다.';
                        setTimeout(() => window.location.reload(), 900);
                        return;
                    }
                    if (data.status === 'busy') {
                        text.textContent = '다른 창에서 AI 작성이 진행 중입니다. 완료 상태를 기다리고 있습니다.';
                        setTimeout(tick, 1500);
                        return;
                    }

                    processed++;
                    const done = total - data.remaining;
                    progress.hidden = false;
                    fill.style.width = (total ? Math.round(done / total * 100) : 100) + '%';
                    const label = { done: '게시 완료', updated: '기존 글 갱신', skipped: '이미 게시됨', error: '실패' }[data.status] || data.status;
                    text.textContent = label + ' (' + done + '/' + total + ')' + (data.message ? ' — ' + data.message : '');
                    markResult(data.log_no, data.status === 'error' ? 'failed' : 'done');

                    if (data.remaining > 0) {
                        setTimeout(tick, 500);
                    } else {
                        window.location.reload();
                    }
                } catch (error) {
                    progress.hidden = false;
                    progress.classList.add('gallery-sync-progress-error');
                    text.textContent = '자동 작성이 중단되었습니다. ' + error.message + ' 페이지를 새로고침하면 저장된 대기열에서 다시 확인할 수 있습니다.';
                } finally {
                    running = false;
                }
            }

            if (total > 0) {
                progress.hidden = false;
                text.textContent = 'AI 재작성과 게시를 진행합니다. 글당 수십 초가 걸릴 수 있으니 탭을 열어 둔 상태로 두세요.';
                tick();
                document.addEventListener('visibilitychange', tick);
            }

            cancelButton?.addEventListener('click', async function () {
                if (cancelRequested || !window.confirm('현재 작성과 남은 대기 작업을 모두 취소할까요?')) return;
                cancelRequested = true;
                this.disabled = true;
                this.textContent = '취소 요청 중';
                text.textContent = '취소 요청을 전달하고 있습니다. 현재 AI 응답이 끝나면 글을 저장하지 않고 중단합니다.';
                try {
                    const res = await fetch(ajaxurl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ action: 'gallery_naver_cancel', _ajax_nonce: cancelNonce }),
                    });
                    const raw = await res.text();
                    const json = JSON.parse(raw);
                    if (!res.ok || !json.success) throw new Error(json.data?.message || '취소 요청에 실패했습니다.');
                    text.textContent = json.data.message;
                    if (json.data.status === 'cancelled') {
                        setTimeout(() => window.location.reload(), 900);
                    }
                } catch (error) {
                    cancelRequested = false;
                    this.disabled = false;
                    this.textContent = '작성 취소';
                    text.textContent = error.message;
                }
            });

            async function refreshListPage() {
                if (document.hidden) return;
                try {
                    const res = await fetch(ajaxurl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ action: 'gallery_naver_refresh_page', _ajax_nonce: listNonce }),
                    });
                    const json = await res.json();
                    if (!json.success) {
                        listText.textContent = json.data?.message || '목록을 불러오지 못했습니다. 새로고침을 눌러 다시 시도해 주세요.';
                        return;
                    }

                    const data = json.data;
                    const estimatedPages = Math.max(data.page, Math.ceil(data.total / <?php echo self::LIST_PAGE_SIZE; ?>));
                    listProgress.hidden = false;
                    listFill.style.width = Math.min(95, Math.round(data.page / estimatedPages * 100)) + '%';
                    listText.textContent = data.total + '개 글을 불러왔습니다. 네이버 목록 ' + data.page + '페이지 처리 완료';

                    if (data.complete) {
                        listFill.style.width = '100%';
                        listText.textContent = '전체 ' + data.total + '개 글을 불러왔습니다. 목록을 갱신합니다.';
                        window.location.reload();
                        return;
                    }
                    setTimeout(refreshListPage, 250);
                } catch (error) {
                    listText.textContent = '목록 연결이 잠시 끊겼습니다. 페이지를 새로 열면 이어서 불러옵니다.';
                }
            }

            if (!listComplete) {
                refreshListPage();
                document.addEventListener('visibilitychange', refreshListPage, { once: true });
            }
        })();
        </script>
        <?php
    }

    private static function render_row(array $post, bool $selectable): void
    {
        $source_url = self::source_url($post['log_no']);
        $queue_status = (string) ($post['queue_status'] ?? '');
        ?>
        <article class="gallery-sync-row" data-log-no="<?php echo esc_attr($post['log_no']); ?>" data-queue-status="<?php echo esc_attr($queue_status); ?>">
            <?php if ($selectable && ! in_array($queue_status, ['pending', 'processing', 'cancelling'], true)) : ?>
                <label class="gallery-sync-check"><input type="checkbox" name="post_ids[]" value="<?php echo esc_attr($post['log_no']); ?>"><span class="screen-reader-text"><?php echo esc_html($post['title']); ?> 선택</span></label>
            <?php elseif ($queue_status === 'processing') : ?>
                <span class="gallery-sync-status gallery-sync-status-processing">게시 중</span>
            <?php elseif ($queue_status === 'cancelling') : ?>
                <span class="gallery-sync-status gallery-sync-status-cancelling">취소 중</span>
            <?php elseif ($queue_status === 'pending') : ?>
                <span class="gallery-sync-status gallery-sync-status-pending">대기 중</span>
            <?php else : ?>
                <span class="gallery-sync-status">완료</span>
            <?php endif; ?>
            <div class="gallery-sync-info">
                <h3><?php echo esc_html($post['title']); ?></h3>
                <div class="gallery-sync-meta">
                    <?php if ($post['date']) : ?><span><?php echo esc_html($post['date']); ?></span><?php endif; ?>
                    <span>원문 번호 <?php echo esc_html($post['log_no']); ?></span>
                </div>
            </div>
            <div class="gallery-sync-actions">
                <a href="<?php echo esc_url($source_url); ?>" target="_blank" rel="noopener">네이버 원문</a>
                <?php if (! $selectable) : ?>
                    <a href="<?php echo esc_url(get_edit_post_link($post['wp_post_id'])); ?>">편집</a>
                    <a href="<?php echo esc_url(get_permalink($post['wp_post_id'])); ?>" target="_blank" rel="noopener">보기</a>
                <?php endif; ?>
            </div>
        </article>
        <?php
    }

    private static function render_pagination(int $total, int $current, int $per_page, string $parameter): void
    {
        $total_pages = (int) ceil($total / $per_page);
        if ($total_pages <= 1) {
            return;
        }

        $base_url = remove_query_arg($parameter);
        $links = paginate_links([
            'base' => add_query_arg($parameter, '%#%', $base_url),
            'format' => '',
            'current' => $current,
            'total' => $total_pages,
            'type' => 'array',
            'prev_text' => '이전',
            'next_text' => '다음',
        ]);

        if ($links) {
            echo '<nav class="gallery-sync-pagination" aria-label="목록 페이지">' . wp_kses_post(implode('', $links)) . '</nav>';
        }
    }

    public static function handle_refresh(): void
    {
        self::assert_admin_request('gallery_naver_refresh');
        if (self::has_active_sync()) {
            self::set_notice('AI 작성이 끝난 후 목록을 새로고침할 수 있습니다.', 1);
            wp_safe_redirect(self::admin_url());
            exit;
        }
        delete_transient(self::CACHE_KEY);
        delete_transient(self::THUMB_CACHE_KEY);
        delete_option(self::LIST_OPTION);
        wp_safe_redirect(self::admin_url());
        exit;
    }

    public static function handle_refresh_page(): void
    {
        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => '권한이 없습니다.'], 403);
        }
        check_ajax_referer('gallery_naver_refresh_page');

        $state = self::get_remote_posts();
        if ($state['complete']) {
            wp_send_json_success([
                'complete' => true,
                'total' => count($state['posts']),
                'page' => max(1, (int) $state['next_page'] - 1),
            ]);
        }

        $page = max(1, min(self::LIST_MAX_PAGES, (int) $state['next_page']));
        $page_posts = self::fetch_list_page($page);
        if (is_wp_error($page_posts)) {
            $state['error'] = $page_posts->get_error_message();
            update_option(self::LIST_OPTION, $state, false);
            wp_send_json_error(['message' => $state['error']], 502);
        }

        $posts = [];
        foreach ($state['posts'] as $post) {
            $posts[(string) $post['log_no']] = $post;
        }
        foreach ($page_posts as $remote_post) {
            $normalized = self::normalize_list_post($remote_post);
            if ($normalized) {
                $posts[$normalized['log_no']] = $normalized;
            }
        }

        $complete = count($page_posts) < self::LIST_PAGE_SIZE || $page >= self::LIST_MAX_PAGES;
        $state = [
            'posts' => array_values($posts),
            'error' => '',
            'complete' => $complete,
            'next_page' => $page + 1,
            'updated_at' => time(),
        ];
        update_option(self::LIST_OPTION, $state, false);

        wp_send_json_success([
            'complete' => $complete,
            'total' => count($posts),
            'page' => $page,
        ]);
    }

    public static function handle_process_next(): void
    {
        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => '권한이 없습니다.'], 403);
        }
        check_ajax_referer('gallery_naver_process');

        if (get_transient('gallery_naver_sync_paused')) {
            wp_send_json_success(['status' => 'paused', 'remaining' => self::pending_count()]);
        }
        if (get_transient(self::PROCESS_LOCK)) {
            wp_send_json_success(['status' => 'busy', 'remaining' => self::pending_count()]);
        }

        $queue = self::normalize_queue((array) get_option(self::QUEUE_OPTION, []));
        $log_no = '';
        foreach ($queue as $candidate => $item) {
            if ($item['status'] === 'pending') {
                $log_no = (string) $candidate;
                break;
            }
        }
        if ($log_no === '') {
            wp_send_json_success(['status' => 'empty', 'remaining' => 0]);
        }

        set_transient(self::PROCESS_LOCK, $log_no, 5 * MINUTE_IN_SECONDS);
        $queue[$log_no]['status'] = 'processing';
        $queue[$log_no]['started_at'] = time();
        update_option(self::QUEUE_OPTION, array_values($queue), false);

        $result = self::sync_post($log_no);
        delete_transient(self::PROCESS_LOCK);
        $queue = self::normalize_queue((array) get_option(self::QUEUE_OPTION, []));

        if (is_wp_error($result) && $result->get_error_code() === 'cancelled') {
            unset($queue[$log_no]);
            delete_option(self::CANCEL_OPTION);
            update_option(self::QUEUE_OPTION, array_values($queue), false);
            wp_send_json_success([
                'status' => 'cancelled',
                'log_no' => $log_no,
                'remaining' => 0,
            ]);
        }

        if (is_wp_error($result) && self::is_blocked_error($result)) {
            $queue[$log_no]['status'] = 'pending';
            update_option(self::QUEUE_OPTION, array_values($queue), false);
            set_transient('gallery_naver_sync_paused', time(), self::BLOCK_PAUSE);
            wp_send_json_success(['status' => 'paused', 'remaining' => self::pending_count()]);
        }

        if (is_wp_error($result)) {
            $queue[$log_no]['status'] = 'failed';
            $queue[$log_no]['attempts'] = (int) $queue[$log_no]['attempts'] + 1;
            $queue[$log_no]['error'] = $result->get_error_message();
            unset($queue[$log_no]['started_at']);
            update_option(self::QUEUE_OPTION, array_values($queue), false);
            wp_send_json_success([
                'status' => 'error',
                'log_no' => $log_no,
                'message' => $result->get_error_message(),
                'remaining' => self::pending_count(),
            ]);
        }

        unset($queue[$log_no]);
        update_option(self::QUEUE_OPTION, array_values($queue), false);
        wp_send_json_success([
            'status' => $result['updated'] ? 'updated' : 'done',
            'log_no' => $log_no,
            'post_id' => $result['post_id'],
            'edit_url' => get_edit_post_link($result['post_id'], 'raw'),
            'remaining' => self::pending_count(),
        ]);
    }

    public static function handle_cancel(): void
    {
        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => '권한이 없습니다.'], 403);
        }
        check_ajax_referer('gallery_naver_cancel');

        $queue = self::normalize_queue((array) get_option(self::QUEUE_OPTION, []));
        $processing_log_no = '';
        $remaining = [];
        foreach ($queue as $log_no => $item) {
            if (in_array($item['status'], ['processing', 'cancelling'], true)) {
                $processing_log_no = (string) $log_no;
                $item['status'] = 'cancelling';
                $remaining[$log_no] = $item;
            } elseif ($item['status'] === 'failed') {
                $remaining[$log_no] = $item;
            }
        }

        if ($processing_log_no !== '') {
            update_option(self::CANCEL_OPTION, $processing_log_no, false);
            update_option(self::QUEUE_OPTION, array_values($remaining), false);
            wp_send_json_success([
                'status' => 'cancelling',
                'message' => '취소 요청을 받았습니다. 현재 AI 응답이 끝나면 글을 저장하지 않고 중단합니다.',
            ]);
        }

        delete_option(self::CANCEL_OPTION);
        update_option(self::QUEUE_OPTION, array_values($remaining), false);
        wp_send_json_success([
            'status' => 'cancelled',
            'message' => '대기 중인 자동 작성 작업을 취소했습니다.',
        ]);
    }

    private static function pending_count(): int
    {
        return count(array_filter(
            self::normalize_queue((array) get_option(self::QUEUE_OPTION, [])),
            fn($item) => in_array($item['status'], ['pending', 'processing', 'cancelling'], true),
        ));
    }

    private static function has_active_sync(): bool
    {
        return self::pending_count() > 0 || (bool) get_transient(self::PROCESS_LOCK);
    }

    private static function normalize_queue(array $queue): array
    {
        $normalized = [];
        foreach ($queue as $item) {
            if (is_string($item)) {
                $item = ['log_no' => $item, 'attempts' => 0, 'status' => 'pending', 'error' => ''];
            }
            $item = wp_parse_args($item, ['log_no' => '', 'attempts' => 0, 'status' => 'pending', 'error' => '', 'started_at' => 0]);
            if ($item['status'] === 'processing' && (int) $item['started_at'] < time() - (10 * MINUTE_IN_SECONDS)) {
                $item['status'] = 'pending';
                $item['started_at'] = 0;
            }
            if ($item['log_no'] !== '' && ! isset($normalized[$item['log_no']])) {
                $normalized[$item['log_no']] = $item;
            }
        }

        return $normalized;
    }

    private static function is_blocked_error(WP_Error $error): bool
    {
        return in_array($error->get_error_code(), ['blocked', 'http_error'], true);
    }

    public static function handle_sync(): void
    {
        self::assert_admin_request('gallery_naver_sync');
        if (self::has_active_sync()) {
            self::set_notice('이미 AI 작성이 진행 중입니다. 완료된 후 다음 글을 선택해 주세요.', 1);
            wp_safe_redirect(self::admin_url());
            exit;
        }
        delete_option(self::CANCEL_OPTION);
        $selected = isset($_POST['post_ids']) ? (array) wp_unslash($_POST['post_ids']) : [];
        $selected = array_values(array_unique(array_filter(array_map(
            static fn($id): string => preg_replace('/\D/', '', (string) $id),
            $selected,
        ))));

        if (! $selected) {
            self::set_notice('동기화할 글을 한 개 이상 선택해 주세요.', 1);
            wp_safe_redirect(self::admin_url());
            exit;
        }

        if (get_transient('gallery_naver_sync_paused')) {
            self::set_notice('네이버가 요청을 일시 차단한 상태입니다. 약 30분 후에 자동으로 재개됩니다.', 1);
            wp_safe_redirect(self::admin_url());
            exit;
        }

        $queue = self::normalize_queue((array) get_option(self::QUEUE_OPTION, []));
        $queued = 0;
        foreach ($selected as $log_no) {
            if (isset($queue[$log_no]) && $queue[$log_no]['status'] === 'pending') {
                continue;
            }
            $queue[$log_no] = ['log_no' => $log_no, 'attempts' => 0, 'status' => 'pending', 'error' => ''];
            $queued++;
        }
        update_option(self::QUEUE_OPTION, array_values($queue), false);

        $message = sprintf('%d개 글을 동기화 대기열에 추가했습니다. 이 페이지를 열어 둔 동안 AI 재작성과 게시가 진행됩니다.', $queued);
        self::set_notice($message, 0);
        wp_safe_redirect(self::admin_url());
        exit;
    }

    public static function handle_retry(): void
    {
        self::assert_admin_request('gallery_naver_retry');
        if (self::has_active_sync()) {
            self::set_notice('현재 AI 작성이 끝난 후 실패한 글을 재시도해 주세요.', 1);
            wp_safe_redirect(self::admin_url());
            exit;
        }
        $log_no = preg_replace('/\D/', '', (string) ($_GET['log_no'] ?? ''));

        $queue = self::normalize_queue((array) get_option(self::QUEUE_OPTION, []));
        if ($log_no && isset($queue[$log_no])) {
            if (! empty($_GET['remove'])) {
                unset($queue[$log_no]);
                self::set_notice('대기열에서 제거했습니다.', 0);
            } else {
                $queue[$log_no]['status'] = 'pending';
                $queue[$log_no]['error'] = '';
                self::set_notice('재시도 대기열에 올렸습니다. 페이지를 열어 두면 처리됩니다.', 0);
            }
            update_option(self::QUEUE_OPTION, array_values($queue), false);
        }
        wp_safe_redirect(self::admin_url());
        exit;
    }

    public static function handle_save_settings(): void
    {
        self::assert_admin_request('gallery_naver_save_settings');
        if (self::has_active_sync()) {
            self::set_notice('AI 작성 중에는 설정을 변경할 수 없습니다.', 1);
            wp_safe_redirect(self::admin_url());
            exit;
        }

        $settings = self::get_settings();
        $key = trim((string) wp_unslash($_POST['openai_api_key'] ?? ''));

        if ($key === '--delete--') {
            $settings['api_key'] = '';
        } elseif ($key !== '') {
            $settings['api_key'] = $key;
        }

        $model = sanitize_key((string) ($_POST['openai_model'] ?? ''));
        if ($model !== '') {
            $settings['model'] = $model;
        }

        $status = (string) ($_POST['post_status'] ?? 'draft');
        $settings['post_status'] = $status === 'publish' ? 'publish' : 'draft';

        update_option(self::SETTINGS_OPTION, $settings, false);
        self::set_notice('설정을 저장했습니다.', 0);
        wp_safe_redirect(self::admin_url());
        exit;
    }

    public static function sync_post(string $log_no)
    {
        if (! preg_match('/^\d{8,20}$/', $log_no)) {
            return new WP_Error('invalid_log_no', '올바르지 않은 네이버 글 번호입니다.');
        }

        if (! self::resolve_api_key()) {
            return new WP_Error('no_api_key', 'OpenAI API 키가 설정되지 않았습니다. 페이지 상단의 설정에서 키를 입력하거나 서버 환경변수 OPENAI_API_KEY를 설정하세요.');
        }

        if (self::is_cancel_requested($log_no)) {
            return new WP_Error('cancelled', '사용자가 자동 작성을 취소했습니다.');
        }

        $remote = self::fetch_remote_post($log_no);
        if (is_wp_error($remote)) {
            return $remote;
        }

        if (self::is_cancel_requested($log_no)) {
            return new WP_Error('cancelled', '사용자가 자동 작성을 취소했습니다.');
        }

        $rewritten = self::rewrite_with_ai($remote);
        if (is_wp_error($rewritten)) {
            return $rewritten;
        }

        if (self::is_cancel_requested($log_no)) {
            return new WP_Error('cancelled', '사용자가 자동 작성을 취소했습니다.');
        }

        $settings = self::get_settings();
        $existing_id = self::find_existing_post($log_no);
        $post_data = [
            'post_title' => $rewritten['title'],
            'post_content' => $rewritten['content'],
            'post_excerpt' => $rewritten['excerpt'],
            'meta_input' => [
                self::META_SOURCE_URL => self::source_url($log_no),
                '_gallery_naver_first_image' => $remote['first_image'],
                self::META_REWRITTEN_AT => current_time('mysql'),
            ],
        ];

        if ($existing_id) {
            $post_data['ID'] = $existing_id;
            $post_id = wp_update_post($post_data, true);
        } else {
            $post_data['post_status'] = $settings['post_status'];
            $post_data['post_type'] = 'post';
            $post_data['post_date'] = $remote['post_date'];
            $post_data['meta_input'][self::META_LOG_NO] = $log_no;
            $post_data['meta_input']['_gallery_naver_synced_at'] = current_time('mysql');
            $post_id = wp_insert_post($post_data, true);
        }

        if (is_wp_error($post_id)) {
            return new WP_Error('wp_insert_failed', '워드프레스 게시글 저장에 실패했습니다: ' . $post_id->get_error_message());
        }

        return ['post_id' => (int) $post_id, 'updated' => (bool) $existing_id];
    }

    private static function is_cancel_requested(string $log_no): bool
    {
        $requested = (string) get_option(self::CANCEL_OPTION, '');

        return $requested !== '' && hash_equals($requested, $log_no);
    }

    private static function rewrite_with_ai(array $remote)
    {
        $content = $remote['content'];

        // Replace <img> tags with placeholders so the AI keeps their positions
        // but can never touch the URLs, order, or attributes.
        $images = [];
        $content = preg_replace_callback('/<img\b[^>]*>/i', function ($match) use (&$images) {
            $token = '<!--gallery-sync-img-' . count($images) . '-->';
            $images[] = $match[0];

            return $token;
        }, $content);

        $response = wp_safe_remote_post(self::OPENAI_ENDPOINT, [
            'timeout' => 180,
            'headers' => [
                'Authorization' => 'Bearer ' . self::resolve_api_key(),
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'model' => self::get_settings()['model'],
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => self::system_prompt()],
                    ['role' => 'user', 'content' => self::user_prompt($remote['title'], $content)],
                ],
            ]),
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('openai_network', 'OpenAI 연결 실패: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $message = $body['error']['message'] ?? ('HTTP ' . $code);

            return new WP_Error('openai_error', 'OpenAI 재작성 실패: ' . $message);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $text = $body['choices'][0]['message']['content'] ?? '';
        $decoded = json_decode($text, true);
        if (! is_array($decoded) || empty($decoded['title']) || empty($decoded['content'])) {
            return new WP_Error('openai_parse', 'OpenAI 응답을 해석하지 못했습니다. 다시 시도해 주세요.');
        }

        // Restore images in their original order and exact original markup.
        $rewritten_content = self::normalize_ai_punctuation((string) $decoded['content']);
        foreach ($images as $i => $img) {
            $rewritten_content = preg_replace(
                '/<!--\s*gallery-sync-img-' . $i . '\s*-->/i',
                $img,
                $rewritten_content,
                1,
            );
        }
        // Any placeholder the model dropped or mangled gets appended back so no image is lost.
        foreach ($images as $i => $img) {
            if (stripos($rewritten_content, $img) === false) {
                $rewritten_content .= "\n" . $img;
            }
        }

        $rewritten_content = wp_kses($rewritten_content, self::allowed_html());
        $plain_text = self::clean_text(wp_strip_all_tags($rewritten_content));

        return [
            'title' => self::clean_text(self::normalize_ai_punctuation((string) $decoded['title'])),
            'content' => $rewritten_content,
            'excerpt' => wp_trim_words($plain_text, 32, '...'),
        ];
    }

    private static function system_prompt(): string
    {
        return <<<'PROMPT'
당신은 울산 갤러리조명 블로그의 전문 카피라이터입니다. 네이버 블로그에 쓴 원문 글을 갤러리조명 홈페이지 블로그용으로 재작성합니다.

절대 원칙:
- 원문에 담긴 사실과 작업 내용은 정확하게 유지한다.
- 현장명, 지역명, 건축명, 제품명, 브랜드명, 조명 종류, 규격, 수량, 시공 방식 등 중요한 정보는 임의로 변경하거나 삭제하지 않는다.
- 원문에 없는 시공 사례, 제품 사양, 인증, 가격, 효과를 만들어내지 않는다.
- 갤러리조명 블로그 특유의 친근하고 현장감 있는 어투를 유지한다.
- 단순히 단어만 바꾸지 말고 문장 구조와 설명 순서를 자연스럽게 다듬는다.
- 검색엔진에서 원문을 그대로 복제한 글로 보이지 않도록 독립적인 글로 작성한다.
- 지역, 공간 유형, 조명 종류, 시공 목적이 자연스럽게 드러나게 작성한다.
- 독자가 시공 과정과 결과를 쉽게 이해할 수 있도록 문단을 정리한다.
- 과장된 홍보 문구와 반복적인 키워드 사용은 피한다.
- en dash, em dash, 중간점 문자는 사용하지 않는다.
- 원문에 없는 이모지는 새로 추가하지 않는다.
- 전화번호와 업체 정보가 원문에 포함된 경우 정확하게 유지한다.
- 과장되지 않는 한도 내에서 원문의 핵심 검색 의도를 유지한다.

이미지 규칙:
- 원문 본문의 <!--gallery-sync-img-N--> 마커는 이미지 위치를 나타낸다.
- 마커를 삭제하거나, 이동하지 말고, 마커 사이의 텍스트만 재작성한다.
- 마커를 제외한 어떤 이미지 URL도 만들어내거나 수정하지 않는다.

출력 규칙:
- 반드시 JSON 하나만 출력한다: {"title": "새 제목", "content": "재작성된 본문 HTML"}
- 본문은 기본적인 HTML 태그(p, h2, h3, ul, ol, li, strong, em, blockquote, br)를 사용한다.
- 게시글 내용만 출력하고 작업 설명이나 의견은 포함하지 않는다.
PROMPT;
    }

    private static function user_prompt(string $title, string $content): string
    {
        $reference = self::style_reference();
        $reference_block = $reference
            ? "\n\n[문체 참고용 최근 글 예시. 문장이나 내용을 현재 글에 섞어 넣지 말고 문장만 참고할 것]\n" . $reference
            : '';

        return '[원문 제목]\n' . $title . '\n\n[원문 본문]\n' . $content . $reference_block;
    }

    private static function normalize_ai_punctuation(string $text): string
    {
        return str_replace(['–', '—', '·'], ['-', '-', ','], $text);
    }

    private static function style_reference(): string
    {
        $posts = get_posts([
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => 2,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_key' => self::META_LOG_NO,
        ]);

        $samples = [];
        foreach ($posts as $post) {
            $text = self::clean_text(wp_strip_all_tags($post->post_content));
            $samples[] = '제목: ' . self::clean_text($post->post_title) . "\n" . mb_substr($text, 0, 400);
        }

        return implode("\n\n", $samples);
    }

    private static function get_settings(): array
    {
        return wp_parse_args((array) get_option(self::SETTINGS_OPTION, []), [
            'api_key' => '',
            'model' => 'gpt-4o-mini',
            'post_status' => 'draft',
        ]);
    }

    private static function env_api_key(): string
    {
        $key = getenv('OPENAI_API_KEY');

        return $key !== false ? trim($key) : '';
    }

    private static function resolve_api_key(): string
    {
        $env = self::env_api_key();
        if ($env !== '') {
            return $env;
        }

        return (string) self::get_settings()['api_key'];
    }

    public static function get_remote_posts(bool $force = false): array
    {
        if ($force) {
            delete_option(self::LIST_OPTION);
            delete_transient(self::CACHE_KEY);
        }

        $stored = get_option(self::LIST_OPTION, null);
        if (is_array($stored) && isset($stored['posts'])) {
            return wp_parse_args($stored, [
                'posts' => [],
                'error' => '',
                'complete' => false,
                'next_page' => 1,
                'updated_at' => 0,
            ]);
        }

        // Preserve the old transient immediately, then continue from the next
        // page through AJAX. This makes the admin page open without a remote request.
        $legacy = get_transient(self::CACHE_KEY);
        $legacy_posts = is_array($legacy) && isset($legacy['posts']) && is_array($legacy['posts'])
            ? array_values($legacy['posts'])
            : [];
        $state = [
            'posts' => $legacy_posts,
            'error' => '',
            'complete' => false,
            'next_page' => max(1, (int) floor(count($legacy_posts) / self::LIST_PAGE_SIZE) + 1),
            'updated_at' => 0,
        ];
        update_option(self::LIST_OPTION, $state, false);

        return $state;
    }

    private static function get_rss_thumbnails(): array
    {
        $cached = get_transient(self::THUMB_CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $response = wp_safe_remote_get('https://rss.blog.naver.com/' . self::BLOG_ID . '.xml', [
            'timeout' => 20,
            'headers' => ['User-Agent' => 'Mozilla/5.0'],
        ]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return [];
        }

        $xml = wp_remote_retrieve_body($response);
        if ($xml === '') {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (! $document) {
            return [];
        }

        $map = [];
        foreach ($document->channel->item ?? [] as $item) {
            $link = (string) $item->link;
            if (! preg_match('/\/(\d{8,20})(?:[?\/]|$)/', $link, $match)) {
                continue;
            }
            $description = (string) $item->description;
            if (! preg_match('#(https?://[^"\'\s]+?pstatic\.net/[^"\'\s]+?\.(?:jpe?g|png|gif)[^"\'\s]*)#i', $description, $image)) {
                continue;
            }
            $map[$match[1]] = self::normalize_image_url(html_entity_decode($image[1]));
        }

        if ($map) {
            set_transient(self::THUMB_CACHE_KEY, $map, 6 * HOUR_IN_SECONDS);
        }

        return $map;
    }

    private static function normalize_list_post(array $remote_post): ?array
    {
        $log_no = preg_replace('/\D/', '', (string) ($remote_post['logNo'] ?? ''));
        $title = self::clean_text(urldecode((string) ($remote_post['title'] ?? '')));
        if (! $log_no || ! $title) {
            return null;
        }

        return [
            'log_no' => $log_no,
            'title' => mb_substr($title, 0, 180),
            'date' => self::clean_text((string) ($remote_post['addDate'] ?? '')),
            'thumbnail' => '',
        ];
    }

    private static function fetch_list_page(int $page)
    {
        $url = add_query_arg([
            'blogId' => self::BLOG_ID,
            'categoryNo' => self::CATEGORY_NO,
            'currentPage' => $page,
            'countPerPage' => self::LIST_PAGE_SIZE,
        ], 'https://blog.naver.com/PostTitleListAsync.naver');
        $response = self::request($url, 0.35);
        if (is_wp_error($response)) {
            return $response;
        }
        $body = wp_remote_retrieve_body($response);
        if (! preg_match('/"postList":(\[.*?\]),"countPerPage"/s', $body, $match)) {
            return new WP_Error('list_parse_error', '네이버 목록 ' . $page . '페이지를 해석하지 못했습니다.');
        }
        $post_list = json_decode($match[1], true);
        if (! is_array($post_list)) {
            return new WP_Error('list_parse_error', '네이버 목록 ' . $page . '페이지를 해석하지 못했습니다.');
        }

        return $post_list;
    }

    public static function fetch_remote_post(string $log_no)
    {
        $response = self::request(self::source_url($log_no));
        if (is_wp_error($response)) {
            return $response;
        }

        $document = self::load_document(wp_remote_retrieve_body($response));
        if (! $document) {
            return new WP_Error('parse_error', '네이버 본문을 해석하지 못했습니다.');
        }

        $xpath = new DOMXPath($document);
        $main = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " se-main-container ")]')->item(0);
        if (! $main) {
            return new WP_Error('content_missing', '네이버 글 본문을 찾지 못했습니다.');
        }

        $title = self::meta_content($xpath, 'og:title');
        $title = preg_replace('/\s*:\s*네이버 블로그\s*$/u', '', $title);
        if (! $title) {
            $title_node = $xpath->query('//*[contains(@class, "se-title-text")]')->item(0);
            $title = $title_node ? self::clean_text($title_node->textContent) : '네이버 블로그 글 ' . $log_no;
        }

        foreach (iterator_to_array($xpath->query('.//script|.//style|.//button', $main)) as $node) {
            $node->parentNode?->removeChild($node);
        }

        $first_image = '';
        foreach ($xpath->query('.//img', $main) as $image) {
            $source = self::image_source($image);
            if (! $source) {
                $image->parentNode?->removeChild($image);
                continue;
            }

            $image->setAttribute('src', $source);
            $image->setAttribute('loading', 'lazy');
            $image->setAttribute('decoding', 'async');
            $image->setAttribute('referrerpolicy', 'no-referrer');
            foreach (['id', 'data-lazy-src', 'data-width', 'data-height', 'class', 'style'] as $attribute) {
                $image->removeAttribute($attribute);
            }
            if (! $first_image && str_contains($source, 'postfiles.pstatic.net')) {
                $first_image = $source;
            }
        }

        foreach ($xpath->query('.//*[@onclick] | .//*[@data-linkdata] | .//*[@data-linktype] | .//*[@area-hidden] | .//*[@data-module-v2]') as $node) {
            foreach (['onclick', 'data-linkdata', 'data-linktype', 'area-hidden', 'data-module-v2'] as $attribute) {
                $node->removeAttribute($attribute);
            }
        }

        $content = '<div class="naver-post-content">' . self::inner_html($main) . '</div>';
        $content = wp_kses($content, self::allowed_html());
        $plain_text = self::clean_text(wp_strip_all_tags($content));
        $date_node = $xpath->query('//*[contains(@class, "se_publishDate")]')->item(0);
        $post_date = self::parse_date($date_node ? $date_node->textContent : '');

        return [
            'title' => $title,
            'content' => $content,
            'excerpt' => wp_trim_words($plain_text, 32, '...'),
            'first_image' => $first_image,
            'post_date' => $post_date,
        ];
    }

    private static function get_published_map(array $log_numbers): array
    {
        if (! $log_numbers) {
            return [];
        }

        $query = new WP_Query([
            'post_type' => 'post',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [[
                'key' => self::META_LOG_NO,
                'value' => $log_numbers,
                'compare' => 'IN',
            ]],
        ]);
        $map = [];
        foreach ($query->posts as $post_id) {
            $map[(string) get_post_meta($post_id, self::META_LOG_NO, true)] = (int) $post_id;
        }

        return $map;
    }

    private static function find_existing_post(string $log_no): int
    {
        $posts = get_posts([
            'post_type' => 'post',
            'post_status' => 'any',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_key' => self::META_LOG_NO,
            'meta_value' => $log_no,
        ]);

        return isset($posts[0]) ? (int) $posts[0] : 0;
    }

    private static function request(string $url, float $minimum_interval = self::MIN_REQUEST_INTERVAL)
    {
        self::throttle($minimum_interval);

        $response = wp_safe_remote_get($url, [
            'timeout' => 25,
            'redirection' => 3,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
                'Referer' => 'https://blog.naver.com/' . self::BLOG_ID,
                'Accept-Language' => 'ko-KR,ko;q=0.9',
            ],
        ]);

        self::$last_request_at = microtime(true);
        set_transient('gallery_naver_last_request', self::$last_request_at, 10 * MINUTE_IN_SECONDS);

        if (is_wp_error($response)) {
            return new WP_Error('network_error', '네이버 연결 실패: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code === 429 || $code === 403) {
            set_transient('gallery_naver_sync_paused', time(), self::BLOCK_PAUSE);

            return new WP_Error('blocked', '네이버가 요청을 제한했습니다(HTTP ' . $code . '). 약 30분 후에 자동으로 재개됩니다.');
        }
        if ($code !== 200) {
            return new WP_Error('http_error', '네이버가 요청을 처리하지 못했습니다(HTTP ' . $code . '). 잠시 후 다시 시도해 주세요.');
        }

        return $response;
    }

    private static function throttle(float $minimum_interval): void
    {
        if (self::$last_request_at === 0.0) {
            self::$last_request_at = (float) get_transient('gallery_naver_last_request');
        }

        $elapsed = microtime(true) - self::$last_request_at;
        if ($elapsed < $minimum_interval) {
            usleep((int) (($minimum_interval - $elapsed) * 1_000_000));
        }
    }

    private static function load_document(string $html): ?DOMDocument
    {
        if ($html === '' || ! class_exists('DOMDocument')) {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument('1.0', 'UTF-8');
        $loaded = $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded ? $document : null;
    }

    private static function image_source(DOMElement $image): string
    {
        $source = '';
        $parent = $image->parentNode;
        if ($parent instanceof DOMElement && $parent->hasAttribute('data-linkdata')) {
            $data = json_decode(html_entity_decode($parent->getAttribute('data-linkdata')), true);
            $source = is_array($data) ? (string) ($data['src'] ?? '') : '';
        }
        if (! $source) {
            $source = $image->getAttribute('data-lazy-src') ?: $image->getAttribute('src');
        }

        return self::normalize_image_url($source);
    }

    private static function fetch_remote_thumbnail(string $log_no): string
    {
        $response = self::request(self::source_url($log_no), 0.35);
        if (is_wp_error($response)) {
            return '';
        }

        $body = wp_remote_retrieve_body($response);
        if (! preg_match('/<meta\s+property=["\']og:image["\']\s+content=["\']([^"\']+)["\']/i', $body, $match)
            && ! preg_match('/<meta\s+content=["\']([^"\']+)["\']\s+property=["\']og:image["\']/i', $body, $match)) {
            return '';
        }

        return self::normalize_image_url(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private static function normalize_image_url(string $url): string
    {
        $url = html_entity_decode(trim($url));
        if (! preg_match('#^https://(?:postfiles|blogfiles|storep-phinf|blogthumb)\.pstatic\.net/#i', $url)) {
            return '';
        }
        if (str_contains(parse_url($url, PHP_URL_HOST) ?: '', 'blogthumb')) {
            return $url;
        }
        $url = preg_replace('/[?&]type=[^&]+/', '', $url);

        return $url . '?type=w966';
    }

    private static function parse_date(string $value): string
    {
        if (preg_match('/(\d{4})\.\s*(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{1,2}):(\d{2})/', $value, $match)) {
            return sprintf('%04d-%02d-%02d %02d:%02d:00', $match[1], $match[2], $match[3], $match[4], $match[5]);
        }
        if (preg_match('/(\d{4})\.\s*(\d{1,2})\.\s*(\d{1,2})\./', $value, $match)) {
            return sprintf('%04d-%02d-%02d 09:00:00', $match[1], $match[2], $match[3]);
        }

        return current_time('mysql');
    }

    private static function meta_content(DOMXPath $xpath, string $property): string
    {
        $node = $xpath->query('//meta[@property="' . $property . '"]/@content')->item(0);

        return $node ? self::clean_text($node->nodeValue) : '';
    }

    private static function clean_text(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    private static function inner_html(DOMNode $node): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument->saveHTML($child);
        }

        return $html;
    }

    private static function allowed_html(): array
    {
        $allowed = wp_kses_allowed_html('post');
        $allowed['div']['class'] = true;
        $allowed['div']['style'] = true;
        $allowed['span']['class'] = true;
        $allowed['span']['style'] = true;
        $allowed['p']['class'] = true;
        $allowed['p']['style'] = true;
        $allowed['img'] = [
            'src' => true,
            'alt' => true,
            'title' => true,
            'width' => true,
            'height' => true,
            'loading' => true,
            'decoding' => true,
            'referrerpolicy' => true,
        ];
        $allowed['a']['class'] = true;
        $allowed['figure'] = ['class' => true, 'style' => true];
        $allowed['figcaption'] = ['class' => true];

        return $allowed;
    }

    private static function source_url(string $log_no): string
    {
        return add_query_arg([
            'blogId' => self::BLOG_ID,
            'logNo' => $log_no,
            'categoryNo' => self::CATEGORY_NO,
        ], 'https://blog.naver.com/PostView.naver');
    }

    private static function assert_admin_request(string $action): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('이 작업을 실행할 권한이 없습니다.');
        }
        check_admin_referer($action);
    }

    private static function set_notice(string $message, int $errors): void
    {
        set_transient('gallery_naver_sync_notice_' . get_current_user_id(), compact('message', 'errors'), MINUTE_IN_SECONDS);
    }

    private static function admin_url(): string
    {
        return admin_url('admin.php?page=gallery-naver-sync');
    }

    private static function admin_css(): string
    {
        return '.gallery-sync-admin{max-width:1200px}.gallery-sync-hero{display:flex;justify-content:space-between;gap:32px;align-items:flex-end;margin:28px 0;padding:32px;border-radius:18px;background:#171712;color:#fff}.gallery-sync-hero h1{margin:8px 0 6px;color:#fff;font-size:34px}.gallery-sync-hero p{max-width:680px;margin:0;color:#babaae}.gallery-sync-kicker{color:#d7ff57;font-weight:700}.gallery-sync-hero .gallery-refresh{border-color:#d7ff57;background:#d7ff57;color:#171712}.gallery-sync-summary{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin:22px 0}.gallery-sync-summary div{display:flex;align-items:baseline;gap:12px;padding:22px;border:1px solid #dcdcda;border-radius:14px;background:#fff}.gallery-sync-summary strong{font-size:30px}.gallery-sync-summary span{color:#64645d}.gallery-sync-settings{margin-top:22px}.gallery-sync-settings .form-table th{width:160px}.gallery-sync-settings .form-table td{padding:12px 10px}.gallery-sync-failed{border-color:#f0caca;background:#fffafa}.gallery-sync-status-failed{background:#f6d5d5;color:#7a1f1f}.gallery-sync-section{margin-top:22px;padding:26px;border:1px solid #dcdcda;border-radius:18px;background:#fff}.gallery-sync-heading h2{margin:0;font-size:24px}.gallery-sync-heading p{margin:5px 0 0;color:#6b6b64}.gallery-sync-toolbar{display:flex;justify-content:space-between;align-items:center;margin:22px 0 12px;padding:12px 16px;border-radius:10px;background:#f5f5f1}.gallery-sync-list{border-top:1px solid #e4e4df}.gallery-sync-row{display:grid;grid-template-columns:34px minmax(0,1fr) auto;gap:16px;align-items:center;padding:16px 6px;border-bottom:1px solid #e4e4df}.gallery-sync-check input{width:18px;height:18px}.gallery-sync-info h3{margin:0 0 7px;font-size:15px}.gallery-sync-meta{display:flex;gap:14px;color:#77776f;font-size:12px}.gallery-sync-actions{display:flex;gap:10px;white-space:nowrap}.gallery-sync-status{display:inline-flex;justify-content:center;padding:4px 7px;border-radius:999px;background:#eaffaa;color:#314000;font-size:11px;font-weight:700}.gallery-sync-progress{margin:22px 0;padding:22px;border:1px solid #dcdcda;border-radius:14px;background:#fff}.gallery-sync-progress-bar{height:8px;overflow:hidden;border-radius:999px;background:#efefe9}.gallery-sync-progress-bar span{display:block;height:100%;width:0;border-radius:999px;background:#171712;transition:width .4s ease}.gallery-sync-progress p{margin:12px 0 0;color:#6b6b64}.gallery-empty{margin-top:20px;padding:30px;border-radius:12px;background:#f6f6f2;color:#777;text-align:center}.gallery-sync-pagination{display:flex;flex-wrap:wrap;gap:5px;margin-top:20px}.gallery-sync-pagination .page-numbers{display:grid;min-width:34px;height:34px;padding:0 9px;place-items:center;border:1px solid #dcdcda;border-radius:7px;text-decoration:none}.gallery-sync-pagination .current{border-color:#171712;background:#171712;color:#fff}@media(max-width:782px){.gallery-sync-hero{display:block}.gallery-sync-hero .button{margin-top:18px}.gallery-sync-summary{grid-template-columns:1fr}.gallery-sync-row{grid-template-columns:28px 1fr}.gallery-sync-actions{grid-column:3}.gallery-sync-meta{display:block}}';
    }

    private static function admin_accessibility_css(): string
    {
        return <<<'CSS'
.gallery-sync-admin { color: #1d2327; }
.gallery-sync-admin .gallery-sync-row { grid-template-columns: 64px 90px minmax(0, 1fr) auto; }
.gallery-sync-admin h1,
.gallery-sync-admin h2,
.gallery-sync-admin h3,
.gallery-sync-settings .form-table th,
.gallery-sync-settings label { color: #1d2327; }
.gallery-sync-admin .gallery-sync-hero h1 { color: #fff; }
.gallery-sync-notices { position: relative; z-index: 1; margin-top: 24px; }
.gallery-sync-alert { margin: 0 0 18px; padding: 13px 18px; border: 1px solid #dcdcda; border-left: 4px solid #646970; border-radius: 10px; background: #fff; color: #1d2327; box-shadow: none; }
.gallery-sync-alert p { margin: 0; color: inherit; }
.gallery-sync-alert-success { border-left-color: #008a20; background: #f0f8f1; color: #145523; font-weight: 600; }
.gallery-sync-alert-warning { border-left-color: #b26200; background: #fff8e5; color: #6e4600; }
.gallery-sync-alert-error { border-left-color: #d63638; background: #fcf0f1; color: #8a2424; }
.gallery-sync-hero .gallery-refresh,
.gallery-sync-hero .gallery-refresh:hover,
.gallery-sync-hero .gallery-refresh:focus { border-color: #d7ff57; background: #d7ff57; color: #171712; font-weight: 700; }
.gallery-sync-toolbar label { display: inline-flex; align-items: center; gap: 8px; min-height: 36px; color: #1d2327; font-weight: 600; cursor: pointer; }
.gallery-sync-check { display: grid; width: 34px; height: 44px; place-items: center; cursor: pointer; }
.gallery-sync-row { transition: background-color .18s ease; }
.gallery-sync-row:hover,
.gallery-sync-row:focus-within { background: #f7f8f5; }
.gallery-sync-actions a { display: inline-flex; min-height: 34px; align-items: center; padding: 0 10px; border: 1px solid #c3c4c7; border-radius: 7px; background: #fff; color: #135e96 !important; font-weight: 600; text-decoration: none !important; }
.gallery-sync-actions a:hover,
.gallery-sync-actions a:focus { border-color: #135e96; background: #f0f6fc; color: #0a4b78 !important; }
.gallery-sync-pagination .page-numbers { background: #fff; color: #135e96 !important; font-weight: 600; }
.gallery-sync-pagination .page-numbers:hover,
.gallery-sync-pagination .page-numbers:focus { border-color: #135e96; background: #f0f6fc; text-decoration: none !important; }
.gallery-sync-pagination .current { border-color: #171712; background: #171712; color: #fff !important; }
.gallery-sync-settings input[type="password"],
.gallery-sync-settings select { min-height: 42px; }
.gallery-sync-settings .description { color: #50575e; }
.gallery-sync-settings fieldset { min-width: 0; margin: 0; padding: 0; border: 0; }
.gallery-sync-settings fieldset:disabled { opacity: .58; }
.gallery-sync-admin.is-syncing .gallery-sync-settings,
.gallery-sync-admin.is-syncing .gallery-sync-pending { pointer-events: none; opacity: .65; }
.gallery-sync-status-pending { background: #fff3cd; color: #664d03; }
.gallery-sync-status-processing { min-width: 52px; background: #135e96; color: #fff; animation: gallery-sync-pulse 1.4s ease-in-out infinite; }
.gallery-sync-status-cancelling { min-width: 52px; background: #b32d2e; color: #fff; }
.gallery-sync-status-done { background: #eaffaa; color: #314000; }
.gallery-sync-row:has(.gallery-sync-status-processing) { background: #f0f6fc; box-shadow: inset 3px 0 #135e96; }
.gallery-sync-progress-error { border-left-color: #b32d2e; background: #fff5f5; }
@keyframes gallery-sync-pulse { 50% { opacity: .62; } }
.gallery-sync-progress { border-left: 4px solid #135e96; }
.gallery-sync-progress-copy { display: flex; align-items: center; justify-content: space-between; gap: 18px; margin-top: 12px; }
.gallery-sync-progress-copy p { margin: 0; }
#gallery-sync-cancel { flex: 0 0 auto; border-color: #b32d2e; color: #b32d2e; }
#gallery-sync-cancel:hover, #gallery-sync-cancel:focus { border-color: #b32d2e; background: #b32d2e; color: #fff; }
.gallery-sync-progress-bar { background: #dcdcda; }
.gallery-sync-progress-bar span { background: #135e96; }
.gallery-sync-admin .notice { color: #1d2327; }
@media (max-width: 782px) {
  .gallery-sync-admin { margin-right: 12px; }
  .gallery-sync-hero, .gallery-sync-section { padding: 20px; border-radius: 12px; }
  .gallery-sync-hero h1 { font-size: 28px; }
  .gallery-sync-toolbar { align-items: stretch; flex-direction: column; gap: 12px; }
  .gallery-sync-toolbar .button { width: 100%; }
  .gallery-sync-actions { flex-wrap: wrap; white-space: normal; }
  .gallery-sync-settings .form-table th { width: auto; padding-bottom: 4px; }
  .gallery-sync-settings .form-table td { padding-left: 0; }
  .gallery-sync-settings input.regular-text, .gallery-sync-settings select { width: 100%; max-width: none; }
  .gallery-sync-admin .gallery-sync-row { grid-template-columns: 60px 70px minmax(0, 1fr); }
  .gallery-sync-progress-copy { align-items: stretch; flex-direction: column; }
}
CSS;
    }
}

Gallery_Naver_Blog_Sync::boot();
