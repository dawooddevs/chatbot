<?php
use App\Csrf;

$statusBadges = [
    'ready' => ['green', 'indexed'],
    'pending' => ['amber', 'pending'],
    'keyword_only' => ['amber', 'keyword only'],
    'empty' => ['grey', 'empty'],
];
?>
<div class="kb-summary muted"><?= (int)$stats['documents'] ?> documents · <?= (int)$stats['chunks'] ?> chunks
  (<?= (int)$stats['embedded'] ?> with embeddings)
  <form class="inline-form" method="post" action="<?= e(admin_url('knowledge')) ?>">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="reindex">
    <input type="hidden" name="id" value="<?= (int)$site['id'] ?>">
    <button class="btn secondary small" type="submit">Re-index all</button>
  </form>
</div>

<?php
$lastSync = $store['last_sync_at'] ? time_ago(date('Y-m-d H:i:s', strtotime($store['last_sync_at']))) : null;
?>
<div class="card" id="store-sync" data-site="<?= (int)$site['id'] ?>">
  <h2>Website sync</h2>
  <form id="store-sync-form" data-js-form>
    <div class="sync-row">
      <input type="text" name="url" value="<?= e($store['url']) ?>" placeholder="https://example.com" aria-label="Website address" required>
      <label class="checkbox"><input type="checkbox" name="pages" value="1" <?= $store['pages'] ? 'checked' : '' ?>> Pages</label>
      <label class="checkbox"><input type="checkbox" name="posts" value="1" <?= $store['posts'] ? 'checked' : '' ?>> Posts</label>
      <label class="checkbox"><input type="checkbox" name="products" value="1" <?= $store['products'] ? 'checked' : '' ?>> Products</label>
      <button class="btn" type="submit" data-sync-button>Sync now</button>
    </div>
  </form>
  <div class="sync-progress" hidden>
    <div class="sync-bar"><span></span></div>
    <div class="sync-label muted"></div>
  </div>
  <p class="sync-status muted">
    <?php if ($lastSync): ?>
      Last sync <?= e($lastSync) ?> ·
      <?= number_format((int)$store['stats']['products']) ?> products ·
      <?= number_format((int)$store['stats']['pages']) ?> pages ·
      <?= number_format((int)$store['stats']['posts']) ?> posts
    <?php else: ?>
      Not synced yet.
    <?php endif; ?>
  </p>
  <?php if (!empty($store['last_error'])): ?>
    <p class="sync-error"><?= e($store['last_error']) ?></p>
  <?php endif; ?>
  <p class="hint cron-line">Auto-sync (cPanel cron): <span class="mono">php <?= e(APP_ROOT) ?>/bin/sync.php</span></p>
</div>

<?php
$groups = ['pages' => [], 'posts' => [], 'other' => []];
foreach ($documents as $document) {
    $groups[['wp_page' => 'pages', 'wp_post' => 'posts'][$document['source_type']] ?? 'other'][] = $document;
}
$counts = [
    'pages' => count($groups['pages']),
    'posts' => count($groups['posts']),
    'products' => $productCount,
    'other' => count($groups['other']),
];
$openTab = 'pages';
foreach (['pages', 'posts', 'products', 'other'] as $candidate) {
    if ($counts[$candidate] > 0) {
        $openTab = $candidate;
        break;
    }
}
if (!empty($editing)) {
    $openTab = 'other';
}

$documentTable = static function (array $rows, bool $synced) use ($site, $statusBadges): void {
    if (!$rows) {
        echo '<div class="empty"><h3>Nothing here yet</h3></div>';
        return;
    }
    ?>
    <div class="table-wrap"><table>
      <thead><tr><th>Title</th><th>Status</th><th>Chunks</th><th>Updated</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $document): ?>
        <?php [$badge, $label] = $statusBadges[$document['status']] ?? ['grey', $document['status']]; ?>
        <tr>
          <td>
            <?php if ($synced && $document['source_url']): ?>
              <a class="doc-title" href="<?= e($document['source_url']) ?>" target="_blank" rel="noopener"><?= e($document['title']) ?></a>
            <?php else: ?>
              <strong><?= e($document['title']) ?></strong>
            <?php endif; ?>
            <br><span class="muted"><?= e(str_limit((string)$document['content'], 90)) ?></span>
          </td>
          <td>
            <span class="badge <?= e($badge) ?>"><?= e($label) ?></span>
            <?php if (!empty($document['error'])): ?>
              <br><span class="muted" title="<?= e($document['error']) ?>"><?= e(str_limit((string)$document['error'], 40)) ?></span>
            <?php endif; ?>
          </td>
          <td><?= (int)$document['chunk_count'] ?></td>
          <td class="muted nowrap"><?= e(time_ago($document['updated_at'])) ?></td>
          <td class="right">
            <div class="row-actions">
            <?php if (!$synced): ?>
              <a class="btn secondary small" href="<?= e(admin_url('site', ['id' => $site['id'], 'tab' => 'knowledge', 'edit' => $document['id']])) ?>">Edit</a>
            <?php endif; ?>
            <form class="inline-form" method="post" action="<?= e(admin_url('knowledge')) ?>">
              <?= Csrf::field() ?>
              <input type="hidden" name="action" value="reindex">
              <input type="hidden" name="id" value="<?= (int)$site['id'] ?>">
              <input type="hidden" name="document_id" value="<?= (int)$document['id'] ?>">
              <button class="btn secondary small" type="submit">Re-index</button>
            </form>
            <?php if (!$synced): ?>
            <form class="inline-form" method="post" action="<?= e(admin_url('knowledge')) ?>" data-confirm="Delete this document?" data-confirm-action="Delete">
              <?= Csrf::field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$site['id'] ?>">
              <input type="hidden" name="document_id" value="<?= (int)$document['id'] ?>">
              <button class="btn danger small" type="submit">Delete</button>
            </form>
            <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php
};
$tabLabels = ['pages' => 'Pages', 'posts' => 'Posts', 'products' => 'Products', 'other' => 'Other'];
?>
<div class="card kb-card">
  <div class="seg-tabs" role="tablist">
    <?php foreach ($tabLabels as $key => $label): ?>
      <button type="button" role="tab" class="seg <?= $openTab === $key ? 'active' : '' ?>" data-kb-tab="<?= $key ?>"
              aria-selected="<?= $openTab === $key ? 'true' : 'false' ?>">
        <?= $label ?> <span class="count"><?= number_format($counts[$key]) ?></span>
      </button>
    <?php endforeach; ?>
  </div>

  <div class="kb-pane" data-kb-pane="pages" <?= $openTab === 'pages' ? '' : 'hidden' ?>><?php $documentTable($groups['pages'], true); ?></div>
  <div class="kb-pane" data-kb-pane="posts" <?= $openTab === 'posts' ? '' : 'hidden' ?>><?php $documentTable($groups['posts'], true); ?></div>
  <div class="kb-pane" data-kb-pane="products" id="products-card" data-site="<?= (int)$site['id'] ?>" <?= $openTab === 'products' ? '' : 'hidden' ?>>
  <div class="card-head">
    <span class="muted"><span class="product-total"><?= number_format($productCount) ?></span> products</span>
    <input type="search" class="product-search" placeholder="Search name, SKU or brand" aria-label="Search products" <?= $productCount ? '' : 'hidden' ?>>
  </div>
  <div class="product-list">
    <?php foreach ($products as $product): ?>
      <div class="product-row">
        <div class="product-name">
          <?php if ($product['permalink']): ?>
            <a href="<?= e($product['permalink']) ?>" target="_blank" rel="noopener"><?= e($product['name']) ?></a>
          <?php else: ?>
            <?= e($product['name']) ?>
          <?php endif; ?>
          <?php if ($product['sku']): ?><span class="mono muted"><?= e($product['sku']) ?></span><?php endif; ?>
        </div>
        <div class="product-price"><?= e(App\ProductSearch::price($product)) ?></div>
        <span class="badge <?= $product['in_stock'] ? 'green' : 'grey' ?>"><?= e($product['stock_text'] ?: ($product['in_stock'] ? 'In stock' : 'Out of stock')) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
  <p class="muted product-empty" <?= $productCount ? 'hidden' : '' ?>>No products yet.</p>

  <form class="csv-import" method="post" action="<?= e(admin_url('knowledge')) ?>" enctype="multipart/form-data">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="import_products_csv">
    <input type="hidden" name="id" value="<?= (int)$site['id'] ?>">
    <span class="muted">WooCommerce CSV</span>
    <input type="file" name="file" accept=".csv" required>
    <button class="btn secondary small" type="submit">Import CSV</button>
  </form>
  </div>
  <div class="kb-pane" data-kb-pane="other" <?= $openTab === 'other' ? '' : 'hidden' ?>><?php $documentTable($groups['other'], false); ?></div>
</div>

<div class="card faq-card" data-faq-site="<?= (int)$site['id'] ?>">
    <h2>FAQs</h2>

    <div class="faq-list" id="faq-list">
      <?php foreach ($faqs as $faq): ?>
        <div class="faq-item" data-faq-id="<?= (int)$faq['id'] ?>">
          <div class="faq-head">
            <strong class="faq-question"><?= e($faq['question']) ?></strong>
            <?php if ((int)$faq['show_as_chip'] === 1): ?><span class="badge">chip</span><?php endif; ?>
            <span class="spacer"></span>
            <button type="button" class="btn secondary small" data-faq-edit>Edit</button>
            <button type="button" class="btn danger small" data-faq-delete>Delete</button>
          </div>
          <div class="faq-answer muted"><?= nl2br(e($faq['answer'])) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="muted faq-empty" <?= $faqs ? 'hidden' : '' ?>>No FAQs yet.</p>

    <form class="faq-form" id="faq-form" data-js-form>
      <input type="hidden" name="faq_id" value="">
      <div class="field">
        <label for="faq-question">Question</label>
        <input type="text" id="faq-question" name="question" maxlength="255" required>
      </div>
      <div class="field">
        <label for="faq-answer">Answer</label>
        <textarea id="faq-answer" name="answer" rows="3"  required></textarea>
      </div>
      <div class="field checkbox">
        <input type="checkbox" id="faq-chip" name="show_as_chip" value="1" checked>
        <label for="faq-chip" style="margin:0">Show as a chat chip (first <?= App\Faq::MAX_CHIPS ?>)</label>
      </div>
      <div class="actions">
        <button class="btn" type="submit" data-faq-submit>Add FAQ</button>
        <button class="btn secondary" type="button" data-faq-cancel hidden>Cancel</button>
      </div>
    </form>
  </div>

<div class="grid cols-2">
  <div class="card">
    <h2><?= $editing ? 'Edit document' : 'Add knowledge' ?></h2>
    <form method="post" action="<?= e(admin_url('knowledge')) ?>">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="save_text">
      <input type="hidden" name="id" value="<?= (int)$site['id'] ?>">
      <?php if ($editing): ?><input type="hidden" name="document_id" value="<?= (int)$editing['id'] ?>"><?php endif; ?>
      <div class="field">
        <label for="title">Title</label>
        <input type="text" id="title" name="title" value="<?= e($editing['title'] ?? '') ?>" placeholder="Shipping &amp; returns" required>
      </div>
      <div class="field">
        <label for="content">Content</label>
        <textarea id="content" name="content" style="min-height:220px" required><?= e($editing['content'] ?? '') ?></textarea>
        <div class="help">Long documents are split automatically.</div>
      </div>
      <div class="actions">
        <button class="btn" type="submit"><?= $editing ? 'Save and re-index' : 'Save and index' ?></button>
        <?php if ($editing): ?>
          <a class="btn secondary" href="<?= e(admin_url('site', ['id' => $site['id'], 'tab' => 'knowledge'])) ?>">Cancel</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <div>
    <div class="card">
      <h2>Import a web page</h2>
      <form method="post" action="<?= e(admin_url('knowledge')) ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="import_url">
        <input type="hidden" name="id" value="<?= (int)$site['id'] ?>">
        <div class="field">
          <label for="url">Page URL</label>
          <input type="url" id="url" name="url" placeholder="https://acme.com/faq" required>
        </div>
        <button class="btn" type="submit">Import page</button>
      </form>
    </div>

    <div class="card">
      <h2>Upload a file</h2>
      <p class="hint">TXT, MD, CSV, HTML or JSON · 2 MB max.</p>
      <form method="post" action="<?= e(admin_url('knowledge')) ?>" enctype="multipart/form-data">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="upload">
        <input type="hidden" name="id" value="<?= (int)$site['id'] ?>">
        <div class="field">
          <label for="file">File</label>
          <input type="file" id="file" name="file" accept=".txt,.md,.markdown,.csv,.html,.htm,.json" required>
        </div>
        <button class="btn" type="submit">Upload and index</button>
      </form>
    </div>

    <div class="card">
      <h2>Test retrieval</h2>
      <form method="post" action="<?= e(admin_url('knowledge')) ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="test">
        <input type="hidden" name="id" value="<?= (int)$site['id'] ?>">
        <div class="field">
          <label for="question">Question</label>
          <input type="text" id="question" name="question" value="<?= e($testQuestion ?? '') ?>" placeholder="Do you offer refunds?" required>
        </div>
        <button class="btn secondary" type="submit">Run test</button>
      </form>
      <?php if ($testResults !== null): ?>
        <?php if (!$testResults): ?>
          <div class="alert warning" style="margin-top:14px">No passage scored above the minimum match score, so the bot would use its fallback message.</div>
        <?php else: ?>
          <table style="margin-top:14px">
            <thead><tr><th>Score</th><th>Document</th><th>Passage</th></tr></thead>
            <tbody>
            <?php foreach ($testResults as $result): ?>
              <tr>
                <td class="mono"><?= e((string)$result['score']) ?></td>
                <td><?= e($result['title']) ?></td>
                <td class="muted"><?= e(str_limit($result['content'], 130)) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table></div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
