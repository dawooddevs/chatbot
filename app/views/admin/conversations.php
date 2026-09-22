<?php use App\Csrf; ?>
<div class="page-head">
  <div>
    <h1>Conversations</h1>
  </div>
  <form method="get" action="<?= e(base_url('index.php')) ?>" class="actions">
    <input type="hidden" name="r" value="conversations">
    <select name="site" onchange="this.form.submit()">
      <option value="0">All websites</option>
      <?php foreach ($sites as $site): ?>
        <option value="<?= (int)$site['id'] ?>" <?= $filterSite === (int)$site['id'] ? 'selected' : '' ?>><?= e($site['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <noscript><button class="btn secondary" type="submit">Filter</button></noscript>
  </form>
</div>

<div class="card">
  <?php if (!$conversations): ?>
    <div class="empty"><h3>No conversations yet</h3></div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>Website</th><th>First question</th><th>Messages</th><th>Page</th><th>Last activity</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($conversations as $conversation): ?>
        <tr>
          <td><strong><?= e($conversation['site_name']) ?></strong></td>
          <td><a href="<?= e(admin_url('conversation', ['id' => $conversation['id']])) ?>"><?= e(str_limit((string)($conversation['first_message'] ?? '(no message)'), 60)) ?></a></td>
          <td><?= (int)$conversation['message_count'] ?></td>
          <td class="muted"><?= e(str_limit((string)$conversation['page_url'], 40)) ?></td>
          <td class="muted nowrap"><?= e(time_ago($conversation['last_activity_at'])) ?></td>
          <td class="right nowrap">
            <a class="btn secondary small" href="<?= e(admin_url('conversation', ['id' => $conversation['id']])) ?>">Open</a>
            <form class="inline-form" method="post" action="<?= e(admin_url('conversations')) ?>" onsubmit="return confirm('Delete this conversation?');">
              <?= Csrf::field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="conversation_id" value="<?= (int)$conversation['id'] ?>">
              <button class="btn danger small" type="submit">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>

    <?php if ($pages > 1): ?>
      <div class="actions" style="margin-top:16px">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
          <a class="btn <?= $i === $page ? '' : 'secondary' ?> small"
             href="<?= e(admin_url('conversations', ['site' => $filterSite, 'page' => $i])) ?>"><?= $i ?></a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
