<?php
declare(strict_types=1);

use App\Auth;
use App\Database;
use App\Faq;
use App\KnowledgeBase;
use App\OpenAi;
use App\Session;
use App\Site;
use App\Uploads;
use App\View;

$userId = Auth::id();
$siteId = (int)(query('id') ?? post('id') ?? 0);
$site = Site::find($siteId, $userId);

if (!$site) {
    Session::flash('error', 'That website was not found.');
    redirect(admin_url('sites'));
}

$tab = (string)(query('tab') ?? 'general');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');

    if ($action === 'save_general') {
        Database::run(
            'UPDATE sites SET name = ?, allowed_domains = ?, status = ?, updated_at = NOW() WHERE id = ? AND user_id = ?',
            [
                (string)post('name', $site['name']),
                implode("\n", Site::domains((string)post('allowed_domains', ''))),
                post('status') === 'paused' ? 'paused' : 'active',
                $siteId,
                $userId,
            ]
        );
        Session::flash('success', 'Website settings saved.');
        redirect(admin_url('site', ['id' => $siteId, 'tab' => 'general']));
    }

    if ($action === 'save_design') {
        $design = $site['design'];
        $design['title'] = mb_substr((string)post('title', $design['title']), 0, 60);
        $design['subtitle'] = mb_substr((string)post('subtitle', ''), 0, 80);
        $design['welcome_message'] = mb_substr((string)post('welcome_message', ''), 0, 400);
        $design['placeholder'] = mb_substr((string)post('placeholder', ''), 0, 60);
        $design['launcher_label'] = mb_substr((string)post('launcher_label', ''), 0, 30);
        $design['launcher_icon'] = in_array(post('launcher_icon'), ['chat', 'question', 'sparkle', 'support'], true)
            ? (string)post('launcher_icon') : 'chat';
        $design['launcher_badge'] = max(0, min(99, (int)post('launcher_badge', '0')));
        $design['launcher_radius'] = max(8, min(27, (int)post('launcher_radius', '26')));
        $design['badge_color'] = color_or((string)post('badge_color', ''), '#ef4444');
        $design['primary_color'] = color_or((string)post('primary_color', ''), $design['primary_color']);
        $design['text_on_primary'] = color_or((string)post('text_on_primary', ''), '#ffffff');
        $design['bubble_color'] = color_or((string)post('bubble_color', ''), $design['primary_color']);
        $design['background'] = color_or((string)post('background', ''), '#ffffff');
        $design['agent_bubble'] = color_or((string)post('agent_bubble', ''), '#f1f5f9');
        $design['user_bubble'] = color_or((string)post('user_bubble', ''), $design['primary_color']);
        $design['theme'] = post('theme') === 'dark' ? 'dark' : 'light';
        $design['font'] = in_array(post('font'), ['system', 'inter', 'georgia', 'mono'], true) ? (string)post('font') : 'system';
        $design['position'] = post('position') === 'left' ? 'left' : 'right';
        $design['offset_x'] = max(0, min(200, (int)post('offset_x', '20')));
        $design['offset_y'] = max(0, min(200, (int)post('offset_y', '20')));
        $design['radius'] = max(0, min(30, (int)post('radius', '16')));
        $design['avatar_url'] = filter_var((string)post('avatar_url', ''), FILTER_VALIDATE_URL) ? (string)post('avatar_url') : '';
        if (!empty($_FILES['avatar_file']['name'])) {
            try {
                $design['avatar_url'] = Uploads::storeAvatar($_FILES['avatar_file']);
            } catch (Throwable $e) {
                Session::flash('error', $e->getMessage());
            }
        }
        if (post('remove_avatar')) {
            $design['avatar_url'] = '';
        }
        $design['show_status_dot'] = post('show_status_dot') ? 1 : 0;
        $design['show_attachments'] = post('show_attachments') ? 1 : 0;
        $design['show_voice'] = post('show_voice') ? 1 : 0;
        $design['show_reset'] = post('show_reset') ? 1 : 0;
        $design['show_branding'] = post('show_branding') ? 1 : 0;
        $design['auto_open'] = post('auto_open') ? 1 : 0;
        $design['auto_open_delay'] = max(0, min(120, (int)post('auto_open_delay', '5')));
        $design['suggestions'] = array_values(array_filter(array_map(
            static fn (string $line): string => trim(mb_substr($line, 0, 80)),
            preg_split('/\r?\n/', (string)post('suggestions', '')) ?: []
        )));

        Database::run('UPDATE sites SET design = ?, updated_at = NOW() WHERE id = ? AND user_id = ?', [
            json_encode($design, JSON_UNESCAPED_UNICODE),
            $siteId,
            $userId,
        ]);
        Session::flash('success', 'Design saved. Reload the client site to see it.');
        redirect(admin_url('site', ['id' => $siteId, 'tab' => 'design']));
    }

    if ($action === 'save_ai') {
        $ai = $site['ai'];
        $ai['model'] = array_key_exists((string)post('model'), OpenAi::CHAT_MODELS) ? (string)post('model') : $ai['model'];
        $ai['embedding_model'] = array_key_exists((string)post('embedding_model'), OpenAi::EMBEDDING_MODELS)
            ? (string)post('embedding_model') : $ai['embedding_model'];
        $ai['temperature'] = max(0.0, min(1.5, (float)post('temperature', '0.3')));
        $ai['max_tokens'] = max(100, min(2000, (int)post('max_tokens', '600')));
        $ai['history_turns'] = max(0, min(20, (int)post('history_turns', '6')));
        $ai['top_k'] = max(1, min(12, (int)post('top_k', '5')));
        $ai['min_score'] = max(0.0, min(0.9, (float)post('min_score', '0.15')));
        $ai['persona'] = mb_substr((string)post('persona', ''), 0, 1500);
        $ai['fallback'] = mb_substr((string)post('fallback', ''), 0, 500);
        $ai['strict_knowledge'] = post('strict_knowledge') ? 1 : 0;
        $ai['rate_limit_per_hour'] = max(5, min(1000, (int)post('rate_limit_per_hour', '60')));

        $embeddingChanged = $ai['embedding_model'] !== $site['ai']['embedding_model'];

        Database::run('UPDATE sites SET ai = ?, updated_at = NOW() WHERE id = ? AND user_id = ?', [
            json_encode($ai, JSON_UNESCAPED_UNICODE),
            $siteId,
            $userId,
        ]);
        Session::flash(
            'success',
            $embeddingChanged
                ? 'AI settings saved. The embedding model changed — re-index your documents from the Knowledge tab.'
                : 'AI settings saved.'
        );
        redirect(admin_url('site', ['id' => $siteId, 'tab' => 'ai']));
    }

    if ($action === 'regenerate_key') {
        Database::run('UPDATE sites SET site_key = ?, updated_at = NOW() WHERE id = ? AND user_id = ?', [
            Site::newKey(),
            $siteId,
            $userId,
        ]);
        Session::flash('warning', 'A new key was generated. Replace the embed code on the client website.');
        redirect(admin_url('site', ['id' => $siteId, 'tab' => 'embed']));
    }
}

$tab = in_array($tab, ['general', 'design', 'ai', 'embed', 'knowledge'], true) ? $tab : 'general';
$site = Site::find($siteId, $userId);

$data = ['site' => $site, 'tab' => $tab];

if ($tab === 'knowledge') {
    $data['faqs'] = Faq::forSite($siteId);
    // The FAQ document is generated from the list above it, so it is not
    // offered for editing here.
    $data['documents'] = Database::all(
        "SELECT * FROM documents WHERE site_id = ? AND source_type <> 'faq' ORDER BY updated_at DESC",
        [$siteId]
    );
    $data['stats'] = KnowledgeBase::stats($siteId);
    $data['editing'] = query('edit')
        ? Database::first('SELECT * FROM documents WHERE id = ? AND site_id = ?', [(int)query('edit'), $siteId])
        : null;
    $test = Session::get('kb_test');
    Session::forget('kb_test');
    $data['testResults'] = $test['results'] ?? null;
    $data['testQuestion'] = $test['question'] ?? null;
}

$panel = View::render('admin.site.' . $tab, $data, null);

// The tab strip fetches this same URL with partial=1 and swaps just the panel.
if (query('partial') === '1') {
    header('Content-Type: text/html; charset=utf-8');
    echo $panel;
    exit;
}

View::display('admin.site', $data + [
    'panel' => $panel,
    'kbStats' => $tab === 'knowledge' ? $data['stats'] : KnowledgeBase::stats($siteId),
]);
