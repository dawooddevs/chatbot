<?php use App\Csrf; ?>
<div class="page-head">
  <div>
    <h1>Users</h1>
  </div>
</div>

<?php foreach ($errors as $error): ?>
  <div class="alert error"><?= e($error) ?></div>
<?php endforeach; ?>

<div class="sites-layout">
  <div class="card">
    <h2>Admin users <span class="muted" style="font-weight:500;font-size:14px"><?= count($users) ?></span></h2>
    <div class="table-wrap"><table>
      <thead><tr><th>User</th><th>Last sign-in</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($users as $row): ?>
        <?php $id = (int)$row['id']; ?>
        <tr>
          <td>
            <strong><?= e($row['username']) ?></strong>
            <?php if ($id === $ownerId): ?><span class="badge">owner</span><?php endif; ?>
            <?php if ($id === $meId): ?><span class="badge green">you</span><?php endif; ?>
            <?php if ((int)$row['must_change_password'] === 1): ?><span class="badge amber">must change password</span><?php endif; ?>
            <?php if ($row['email']): ?><br><span class="muted"><?= e($row['email']) ?></span><?php endif; ?>
          </td>
          <td class="muted nowrap"><?= e($row['last_login_at'] ? time_ago($row['last_login_at']) : 'never') ?></td>
          <td class="right">
            <div class="row-actions">
              <details class="reset-password">
                <summary class="btn secondary small">Reset password</summary>
                <form method="post" action="<?= e(admin_url('users')) ?>" class="reset-form">
                  <?= Csrf::field() ?>
                  <input type="hidden" name="action" value="reset">
                  <input type="hidden" name="id" value="<?= $id ?>">
                  <input type="password" name="password" minlength="10" placeholder="New password" autocomplete="new-password" aria-label="New password for <?= e($row['username']) ?>" required>
                  <button class="btn small" type="submit">Save</button>
                </form>
              </details>
              <?php if ($id !== $ownerId && $id !== $meId): ?>
                <form class="inline-form" method="post" action="<?= e(admin_url('users')) ?>"
                      data-confirm="Delete <?= e($row['username']) ?>? They are signed out at once." data-confirm-action="Delete">
                  <?= Csrf::field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $id ?>">
                  <button class="btn danger small" type="submit">Delete</button>
                </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>

  <div class="card">
    <h2>Add a user</h2>
    <form method="post" action="<?= e(admin_url('users')) ?>" autocomplete="off">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="create">
      <div class="field">
        <label for="new-username">Username</label>
        <input type="text" id="new-username" name="username" value="<?= e((string)post('username', '')) ?>" pattern="[A-Za-z0-9._\-]{3,64}" required>
      </div>
      <div class="field">
        <label for="new-email">Email</label>
        <input type="email" id="new-email" name="email" value="<?= e((string)post('email', '')) ?>">
      </div>
      <div class="field">
        <label for="new-password">Password</label>
        <input type="password" id="new-password" name="password" minlength="10" autocomplete="new-password" required>
        <div class="help">At least 10 characters.</div>
      </div>
      <div class="field checkbox">
        <input type="checkbox" id="new-must-change" name="must_change" value="1" checked>
        <label for="new-must-change" style="margin:0">Ask them to change it at first sign-in</label>
      </div>
      <button class="btn" type="submit">Create user</button>
    </form>
  </div>
</div>
