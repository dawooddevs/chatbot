<?php use App\Csrf; ?>
<div class="page-head">
  <div>
    <h1>Websites</h1>
    <p>One entry per client website. Each gets its own key, knowledge base and embed snippet.</p>
  </div>
</div>

<?php foreach ($errors as $error): ?>
  <div class="alert error"><?= e($error) ?></div>
<?php endforeach; ?>

<div class="sites-layout">
  <div class="card">
    <h2>Your websites</h2>
    <p class="hint"><?= count($sites) ?> website(s).</p>
    <?php if (!$sites): ?>
      <div class="empty"><h3>Nothing here yet</h3><p>Create your first website with the form beside this panel.</p></div>
    <?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr><th>Website</th><th>Knowledge</th><th>Chats</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($sites as $site): ?>
          <tr>
            <td>
              <strong><?= e($site['name']) ?></strong>
              <span class="badge <?= $site['status'] === 'active' ? 'green' : 'grey' ?>"><?= e($site['status']) ?></span><br>
              <span class="muted mono"><?= e(implode(', ', $site['domains']) ?: 'any domain (not restricted)') ?></span>
            </td>
            <td><?= (int)($counts[$site['id']]['documents'] ?? 0) ?> docs</td>
            <td><?= (int)($counts[$site['id']]['conversations'] ?? 0) ?></td>
            <td class="right">
              <div class="row-actions">
              <a class="btn secondary small" href="<?= e(admin_url('site', ['id' => $site['id'], 'tab' => 'knowledge'])) ?>">Knowledge</a>
              <a class="btn secondary small" href="<?= e(admin_url('site', ['id' => $site['id']])) ?>">Manage</a>
              <form class="inline-form" method="post" action="<?= e(admin_url('sites')) ?>"
                    onsubmit="return confirm('Delete <?= e(addslashes($site['name'])) ?> and every document and conversation it has?');">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$site['id'] ?>">
                <button class="btn danger small" type="submit">Delete</button>
              </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>Add a website</h2>
    <p class="hint">You can change everything later.</p>
    <form method="post" action="<?= e(admin_url('sites')) ?>">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="create">
      <div class="field">
        <label for="name">Business Name</label>
        <input type="text" id="name" name="name" placeholder="Acme Ltd" required>
      </div>
      <div class="field">
        <label for="allowed_domains">Allowed domains</label>
        <textarea id="allowed_domains" name="allowed_domains" placeholder="acme.com&#10;www.acme.com"></textarea>
        <div class="help">One per line. The widget only answers on these domains (subdomains included). Leave empty to allow any domain.</div>
      </div>
      <button class="btn" type="submit">Create website</button>
    </form>
  </div>
</div>
