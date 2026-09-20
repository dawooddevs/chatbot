<div class="page-head">
  <div>
    <h1>Dashboard</h1>
    <p>An overview of your chatbots and recent visitor activity.</p>
  </div>
  <a class="btn" href="<?= e(admin_url('sites')) ?>">+ Add website</a>
</div>

<?php if (!$hasKey): ?>
  <div class="alert warning">
    No OpenAI API key is configured yet, so the chatbots will only reply with their fallback message.
    <a href="<?= e(admin_url('settings')) ?>">Add your key in Settings</a>.
  </div>
<?php endif; ?>

<div class="grid cols-4" style="margin-bottom:18px">
  <div class="stat"><div class="label">Websites</div><div class="value"><?= count($sites) ?></div></div>
  <div class="stat"><div class="label">Conversations</div><div class="value"><?= (int)$stats['conversations'] ?></div></div>
  <div class="stat"><div class="label">Messages</div><div class="value"><?= (int)$stats['messages'] ?></div></div>
  <div class="stat"><div class="label">Messages today</div><div class="value"><?= (int)$stats['today'] ?></div></div>
</div>

<div class="grid cols-2">
  <div class="card">
    <h2>Your websites</h2>
    <p class="hint">Each website has its own knowledge base, design and embed code.</p>
    <?php if (!$sites): ?>
      <div class="empty">
        <h3>No websites yet</h3>
        <p>Add your first website to generate an embed code.</p>
        <a class="btn" href="<?= e(admin_url('sites')) ?>">Add website</a>
      </div>
    <?php else: ?>
      <table>
        <tbody>
        <?php foreach ($sites as $site): ?>
          <tr>
            <td>
              <strong><?= e($site['name']) ?></strong><br>
              <span class="muted mono"><?= e(implode(', ', $site['domains']) ?: 'any domain') ?></span>
            </td>
            <td class="right nowrap">
              <span class="badge <?= $site['status'] === 'active' ? 'green' : 'grey' ?>"><?= e($site['status']) ?></span>
              <a class="btn secondary small" href="<?= e(admin_url('site', ['id' => $site['id']])) ?>">Manage</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>Recent conversations</h2>
    <p class="hint">The latest visitor chats across all of your websites.</p>
    <?php if (!$recent): ?>
      <div class="empty"><p>No conversations yet. They appear here as soon as visitors start chatting.</p></div>
    <?php else: ?>
      <table>
        <tbody>
        <?php foreach ($recent as $row): ?>
          <tr>
            <td>
              <a href="<?= e(admin_url('conversation', ['id' => $row['id']])) ?>"><?= e($row['site_name']) ?></a><br>
              <span class="muted"><?= (int)$row['message_count'] ?> messages</span>
            </td>
            <td class="right muted nowrap"><?= e(time_ago($row['last_activity_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
