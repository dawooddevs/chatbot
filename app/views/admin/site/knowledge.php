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

    <form class="faq-form" id="faq-form">
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
        <span class="faq-status muted"></span>
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

<div class="card">
  <h2>Documents</h2>
  <?php if (!$documents): ?>
    <div class="empty"><h3>No documents yet</h3></div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>Title</th><th>Source</th><th>Status</th><th>Chunks</th><th>Updated</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($documents as $document): ?>
        <?php [$badge, $label] = $statusBadges[$document['status']] ?? ['grey', $document['status']]; ?>
        <tr>
          <td>
            <strong><?= e($document['title']) ?></strong><br>
            <span class="muted"><?= e(str_limit((string)$document['content'], 90)) ?></span>
          </td>
          <td class="muted"><?= e($document['source_type']) ?></td>
          <td>
            <span class="badge <?= e($badge) ?>"><?= e($label) ?></span>
            <?php if (!empty($document['error'])): ?>
              <br><span class="muted" title="<?= e($document['error']) ?>"><?= e(str_limit((string)$document['error'], 40)) ?></span>
            <?php endif; ?>
          </td>
          <td><?= (int)$document['chunk_count'] ?></td>
          <td class="muted nowrap"><?= e(time_ago($document['updated_at'])) ?></td>
          <td class="right nowrap">
            <a class="btn secondary small" href="<?= e(admin_url('site', ['id' => $site['id'], 'tab' => 'knowledge', 'edit' => $document['id']])) ?>">Edit</a>
            <form class="inline-form" method="post" action="<?= e(admin_url('knowledge')) ?>">
              <?= Csrf::field() ?>
              <input type="hidden" name="action" value="reindex">
              <input type="hidden" name="id" value="<?= (int)$site['id'] ?>">
              <input type="hidden" name="document_id" value="<?= (int)$document['id'] ?>">
              <button class="btn secondary small" type="submit">Re-index</button>
            </form>
            <form class="inline-form" method="post" action="<?= e(admin_url('knowledge')) ?>" onsubmit="return confirm('Delete this document?');">
              <?= Csrf::field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$site['id'] ?>">
              <input type="hidden" name="document_id" value="<?= (int)$document['id'] ?>">
              <button class="btn danger small" type="submit">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
