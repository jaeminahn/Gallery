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

    private const META_LOG_NO = '_gallery_naver_log_no';

    private const META_SOURCE_URL = '_gallery_naver_source_url';

    private const QUEUE_OPTION = 'gallery_naver_sync_queue';

    private const CRON_HOOK = 'gallery_naver_sync_queue_cron';

    private const MIN_REQUEST_INTERVAL = 2.5;

    private const BATCH_SIZE = 3;

    private const BLOCK_PAUSE = 30 * MINUTE_IN_SECONDS;

    private static float $last_request_at = 0.0;

    public static function boot(): void
    {
        add_action('admin_menu', [self::class, 'register_menu']);
        add_action('admin_post_gallery_naver_sync', [self::class, 'handle_sync']);
        add_action('admin_post_gallery_naver_refresh', [self::class, 'handle_refresh']);
        add_action('admin_enqueue_scripts', [self::class, 'admin_assets']);
        add_filter('upload_mimes', [self::class, 'keep_remote_images_only']);
        add_action(self::CRON_HOOK, [self::class, 'process_queue']);
        add_filter('cron_schedules', [self::class, 'register_cron_schedule']);
        add_action('init', [self::class, 'schedule_cron']);
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
        $published_map = self::get_published_map(array_column($posts, 'log_no'));
        $pending = [];
        $published = [];

        foreach ($posts as $post) {
            if (isset($published_map[$post['log_no']])) {
                $post['wp_post_id'] = $published_map[$post['log_no']];
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
        <div class="wrap gallery-sync-admin">
            <div class="gallery-sync-hero">
                <div>
                    <span class="gallery-sync-kicker">갤러리조명 콘텐츠 관리</span>
                    <h1>네이버 LED 글 동기화</h1>
                    <p>게시할 글을 선택하고 한 번에 동기화하세요. 본문 이미지는 네이버 원본 URL을 사용하며 현재 서버에는 저장하지 않습니다.</p>
                </div>
                <a class="button gallery-refresh" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=gallery_naver_refresh'), 'gallery_naver_refresh')); ?>">목록 새로고침</a>
            </div>

            <?php
            $queue_count = count((array) get_option(self::QUEUE_OPTION, []));
        $paused = (bool) get_transient('gallery_naver_sync_paused');
        ?>
            <?php if ($queue_count > 0) : ?>
                <div class="notice notice-info"><p>동기화 대기 중인 글이 <?php echo esc_html($queue_count); ?>개 있습니다. 2분 간격으로 자동 게시됩니다.</p></div>
            <?php endif; ?>
            <?php if ($paused) : ?>
                <div class="notice notice-warning"><p>네이버가 요청을 일시 제한했습니다. 약 30분 후에 자동으로 재개됩니다.</p></div>
            <?php endif; ?>

            <?php if (is_array($notice)) : ?>
                <div class="notice <?php echo $notice['errors'] ? 'notice-warning' : 'notice-success'; ?> is-dismissible"><p><?php echo esc_html($notice['message']); ?></p></div>
            <?php endif; ?>

            <?php if ($result['error']) : ?>
                <div class="notice notice-error"><p><?php echo esc_html($result['error']); ?></p></div>
            <?php endif; ?>

            <div class="gallery-sync-summary">
                <div><strong><?php echo esc_html($pending_total); ?></strong><span>게시 전</span></div>
                <div><strong><?php echo esc_html($published_total); ?></strong><span>게시 완료</span></div>
                <div><strong><?php echo esc_html(count($posts)); ?></strong><span>불러온 LED 글</span></div>
            </div>

            <section class="gallery-sync-section">
                <div class="gallery-sync-heading"><div><h2>게시 전</h2><p>체크한 글만 워드프레스에 즉시 공개됩니다.</p></div></div>
                <?php if ($pending) : ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="gallery_naver_sync">
                        <?php wp_nonce_field('gallery_naver_sync'); ?>
                        <div class="gallery-sync-toolbar">
                            <label><input type="checkbox" data-gallery-select-all> 전체 선택</label>
                            <button class="button button-primary button-hero" type="submit">선택한 글 동기화</button>
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
                <div class="gallery-sync-heading"><div><h2>게시 완료</h2><p>이미 동기화된 글은 다시 게시되지 않습니다.</p></div></div>
                <?php if ($published) : ?>
                    <div class="gallery-sync-list">
                        <?php foreach ($published as $post) : self::render_row($post, false); endforeach; ?>
                    </div>
                    <?php self::render_pagination($published_total, $published_page, $per_page, 'published_page'); ?>
                <?php else : ?>
                    <div class="gallery-empty">아직 동기화된 글이 없습니다.</div>
                <?php endif; ?>
            </section>
        </div>
        <script>
        document.querySelector('[data-gallery-select-all]')?.addEventListener('change', function () {
            document.querySelectorAll('input[name="post_ids[]"]').forEach((input) => input.checked = this.checked);
        });
        </script>
        <?php
    }

    private static function render_row(array $post, bool $selectable): void
    {
        $source_url = self::source_url($post['log_no']);
        ?>
        <article class="gallery-sync-row">
            <?php if ($selectable) : ?>
                <label class="gallery-sync-check"><input type="checkbox" name="post_ids[]" value="<?php echo esc_attr($post['log_no']); ?>"><span class="screen-reader-text"><?php echo esc_html($post['title']); ?> 선택</span></label>
            <?php else : ?>
                <span class="gallery-sync-status">완료</span>
            <?php endif; ?>
            <div class="gallery-sync-thumb">
                <?php if ($post['thumbnail']) : ?><img src="<?php echo esc_url($post['thumbnail']); ?>" alt="" referrerpolicy="no-referrer"><?php else : ?><span class="dashicons dashicons-format-image"></span><?php endif; ?>
            </div>
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
        delete_transient(self::CACHE_KEY);
        wp_safe_redirect(self::admin_url());
        exit;
    }

    public static function schedule_cron(): void
    {
        if (! wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 120, 'gallery_naver_every_2min', self::CRON_HOOK);
        }
    }

    public static function register_cron_schedule($schedules)
    {
        $schedules['gallery_naver_every_2min'] = [
            'interval' => 120,
            'display' => '네이버 동기화 2분 간격',
        ];

        return $schedules;
    }

    public static function process_queue(): void
    {
        if (get_transient('gallery_naver_sync_paused')) {
            return;
        }

        $queue = array_values(array_filter((array) get_option(self::QUEUE_OPTION, [])));
        if (! $queue) {
            return;
        }

        $done = 0;
        foreach ($queue as $index => $log_no) {
            if ($done >= self::BATCH_SIZE) {
                break;
            }

            if (self::find_existing_post($log_no)) {
                unset($queue[$index]);
                continue;
            }

            $result = self::sync_post($log_no);
            unset($queue[$index]);
            $done++;

            if (is_wp_error($result) && self::is_blocked_error($result)) {
                set_transient('gallery_naver_sync_paused', time(), self::BLOCK_PAUSE);
                break;
            }
        }

        update_option(self::QUEUE_OPTION, array_values($queue), false);
    }

    private static function is_blocked_error(WP_Error $error): bool
    {
        return in_array($error->get_error_code(), ['blocked', 'http_error'], true);
    }

    public static function handle_sync(): void
    {
        self::assert_admin_request('gallery_naver_sync');
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

        $queue = array_values(array_unique(array_merge(
            (array) get_option(self::QUEUE_OPTION, []),
            $selected,
        )));
        update_option(self::QUEUE_OPTION, $queue, false);
        self::schedule_cron();

        $message = sprintf('%d개 글을 동기화 대기열에 추가했습니다. 한 번에 %d개씩, 약 2분 간격으로 자동 게시됩니다.', count($selected), self::BATCH_SIZE);
        self::set_notice($message, 0);
        wp_safe_redirect(self::admin_url());
        exit;
    }

    public static function sync_post(string $log_no)
    {
        if (! preg_match('/^\d{8,20}$/', $log_no)) {
            return new WP_Error('invalid_log_no', '올바르지 않은 네이버 글 번호입니다.');
        }

        if (self::find_existing_post($log_no)) {
            return new WP_Error('duplicate', '이미 게시된 글입니다.');
        }

        $remote = self::fetch_remote_post($log_no);
        if (is_wp_error($remote)) {
            return $remote;
        }

        $post_id = wp_insert_post([
            'post_title' => $remote['title'],
            'post_content' => $remote['content'],
            'post_excerpt' => $remote['excerpt'],
            'post_status' => 'publish',
            'post_type' => 'post',
            'post_date' => $remote['post_date'],
            'meta_input' => [
                self::META_LOG_NO => $log_no,
                self::META_SOURCE_URL => self::source_url($log_no),
                '_gallery_naver_first_image' => $remote['first_image'],
                '_gallery_naver_synced_at' => current_time('mysql'),
            ],
        ], true);

        return $post_id;
    }

    public static function get_remote_posts(bool $force = false): array
    {
        if (! $force) {
            $cached = get_transient(self::CACHE_KEY);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $posts = [];
        $error = '';

        for ($page = 1; $page <= 10; $page++) {
            $page_posts = self::fetch_list_page($page);
            if (is_wp_error($page_posts)) {
                $error = $page_posts->get_error_message();
                break;
            }

            foreach ($page_posts as $remote_post) {
                $log_no = preg_replace('/\D/', '', (string) ($remote_post['logNo'] ?? ''));
                $title = self::clean_text(urldecode((string) ($remote_post['title'] ?? '')));
                if (! $log_no || ! $title || isset($posts[$log_no])) {
                    continue;
                }
                $posts[$log_no] = [
                    'log_no' => $log_no,
                    'title' => mb_substr($title, 0, 180),
                    'date' => self::clean_text((string) ($remote_post['addDate'] ?? '')),
                    'thumbnail' => '',
                ];
            }

            if (count($page_posts) < 30) {
                break;
            }
        }

        $result = ['posts' => array_values($posts), 'error' => $error];
        if ($posts) {
            set_transient(self::CACHE_KEY, $result, 30 * MINUTE_IN_SECONDS);
        }

        return $result;
    }

    private static function fetch_list_page(int $page)
    {
        $url = add_query_arg([
            'blogId' => self::BLOG_ID,
            'categoryNo' => self::CATEGORY_NO,
            'currentPage' => $page,
            'countPerPage' => 30,
        ], 'https://blog.naver.com/PostTitleListAsync.naver');
        $response = self::request($url);
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

    private static function request(string $url)
    {
        self::throttle();

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

    private static function throttle(): void
    {
        if (self::$last_request_at === 0.0) {
            self::$last_request_at = (float) get_transient('gallery_naver_last_request');
        }

        $elapsed = microtime(true) - self::$last_request_at;
        if ($elapsed < self::MIN_REQUEST_INTERVAL) {
            usleep((int) ((self::MIN_REQUEST_INTERVAL - $elapsed) * 1_000_000));
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

    private static function normalize_image_url(string $url): string
    {
        $url = html_entity_decode(trim($url));
        if (! preg_match('#^https://(?:postfiles|blogfiles|storep-phinf)\.pstatic\.net/#i', $url)) {
            return '';
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
        return '.gallery-sync-admin{max-width:1200px}.gallery-sync-hero{display:flex;justify-content:space-between;gap:32px;align-items:flex-end;margin:28px 0;padding:32px;border-radius:18px;background:#171712;color:#fff}.gallery-sync-hero h1{margin:8px 0 6px;color:#fff;font-size:34px}.gallery-sync-hero p{max-width:680px;margin:0;color:#babaae}.gallery-sync-kicker{color:#d7ff57;font-weight:700}.gallery-sync-hero .gallery-refresh{border-color:#d7ff57;background:#d7ff57;color:#171712}.gallery-sync-summary{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin:22px 0}.gallery-sync-summary div{display:flex;align-items:baseline;gap:12px;padding:22px;border:1px solid #dcdcda;border-radius:14px;background:#fff}.gallery-sync-summary strong{font-size:30px}.gallery-sync-summary span{color:#64645d}.gallery-sync-section{margin-top:22px;padding:26px;border:1px solid #dcdcda;border-radius:18px;background:#fff}.gallery-sync-heading h2{margin:0;font-size:24px}.gallery-sync-heading p{margin:5px 0 0;color:#6b6b64}.gallery-sync-toolbar{display:flex;justify-content:space-between;align-items:center;margin:22px 0 12px;padding:12px 16px;border-radius:10px;background:#f5f5f1}.gallery-sync-list{border-top:1px solid #e4e4df}.gallery-sync-row{display:grid;grid-template-columns:34px 90px minmax(0,1fr) auto;gap:16px;align-items:center;padding:16px 6px;border-bottom:1px solid #e4e4df}.gallery-sync-check input{width:18px;height:18px}.gallery-sync-thumb{width:90px;height:68px;overflow:hidden;border-radius:8px;background:#efefe9}.gallery-sync-thumb img{width:100%;height:100%;object-fit:cover}.gallery-sync-thumb .dashicons{display:grid;width:100%;height:100%;place-items:center;color:#aaa}.gallery-sync-info h3{margin:0 0 7px;font-size:15px}.gallery-sync-meta{display:flex;gap:14px;color:#77776f;font-size:12px}.gallery-sync-actions{display:flex;gap:10px;white-space:nowrap}.gallery-sync-status{display:inline-flex;justify-content:center;padding:4px 7px;border-radius:999px;background:#eaffaa;color:#314000;font-size:11px;font-weight:700}.gallery-empty{margin-top:20px;padding:30px;border-radius:12px;background:#f6f6f2;color:#777;text-align:center}.gallery-sync-pagination{display:flex;flex-wrap:wrap;gap:5px;margin-top:20px}.gallery-sync-pagination .page-numbers{display:grid;min-width:34px;height:34px;padding:0 9px;place-items:center;border:1px solid #dcdcda;border-radius:7px;text-decoration:none}.gallery-sync-pagination .current{border-color:#171712;background:#171712;color:#fff}@media(max-width:782px){.gallery-sync-hero{display:block}.gallery-sync-hero .button{margin-top:18px}.gallery-sync-summary{grid-template-columns:1fr}.gallery-sync-row{grid-template-columns:28px 70px 1fr}.gallery-sync-thumb{width:70px;height:54px}.gallery-sync-actions{grid-column:3}.gallery-sync-meta{display:block}}';
    }
}

Gallery_Naver_Blog_Sync::boot();
