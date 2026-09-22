<div class="page-head">
  <div>
    <h1>Conversation #<?= (int)$conversation['id'] ?></h1>
    <p><?= e($conversation['site_name']) ?> · started <?= e(time_ago($conversation['created_at'])) ?> ·
      <?= (int)$conversation['message_count'] ?> messages</p>
  </div>
  <a class="btn secondary" href="<?= e(admin_url('conversations')) ?>">Back to list</a>
</div>

<div class="grid cols-2">
  <div class="card">
    <h2>Transcript</h2>
    <div class="transcript">
      <?php foreach ($messages as $message): ?>
        <div class="bubble <?= $message['role'] === 'user' ? 'user' : 'assistant' ?>">
          <?= nl2br(e($message['content'])) ?>
          <?php if (!empty($message['attachment_id']) && !empty($message['original_name'])): ?>
            <a href="<?= e(admin_url('attachment', ['id' => $message['attachment_id']])) ?>" target="_blank" rel="noopener"
               style="display:inline-block;margin-top:8px;color:inherit;font-size:12.5px">
              📎 <?= e($message['original_name']) ?> (<?= e(format_bytes((int)$message['size_bytes'])) ?>)
            </a>
          <?php endif; ?>
          <span class="time"><?= e(date('j M Y, H:i', strtotime((string)$message['created_at']))) ?></span>
        </div>
      <?php endforeach; ?>
      <?php if (!$messages): ?><p class="muted">No messages stored.</p><?php endif; ?>
    </div>
  </div>

  <div class="card">
    <h2>Visitor</h2>
    <div class="table-wrap"><table>
      <tbody>
        <tr><th>Page</th><td class="mono"><?= e((string)$conversation['page_url'] ?: '—') ?></td></tr>
        <tr><th>Referrer</th><td class="mono"><?= e((string)$conversation['referrer'] ?: '—') ?></td></tr>
        <tr><th>Browser</th><td class="muted"><?= e(str_limit((string)$conversation['user_agent'], 70)) ?></td></tr>
        <tr><th>Visitor ID</th><td class="mono"><?= e((string)$conversation['visitor_id']) ?></td></tr>
        <tr><th>Started</th><td><?= e((string)$conversation['created_at']) ?></td></tr>
        <tr><th>Last activity</th><td><?= e((string)$conversation['last_activity_at']) ?></td></tr>
      </tbody>
    </table></div>
    <p class="hint" style="margin-top:14px">IP addresses are stored only as a salted hash, used for rate limiting.</p>
  </div>
</div>
