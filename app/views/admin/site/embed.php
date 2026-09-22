<?php
use App\Csrf;
use App\OpenAi;
use App\Site;

$design = $site['design'];
$ai = $site['ai'];
?>
  <div class="card" style="max-width:760px">
    <h2>Embed code</h2>
    <p class="hint">Paste before <span class="mono">&lt;/head&gt;</span> on every page of the client site.</p>
    <pre class="code" id="embed-code"><?= e(Site::embedCode($site)) ?></pre>
    <div class="actions" style="margin-top:14px">
      <button class="btn" type="button" data-copy-embed>Copy code</button>
      <a class="btn secondary" target="_blank" rel="noopener" href="<?= e(admin_url('preview', ['id' => $site['id']])) ?>">Test it here</a>
    </div>

    <h2 style="margin-top:26px">WordPress</h2>
    <p class="hint">Appearance → Theme File Editor → <span class="mono">header.php</span>, or a header-scripts plugin.</p>

    <h2 style="margin-top:26px">Control it from your own page</h2>
    <pre class="code">Chatbot.open();   // open the window
Chatbot.close();  // close it
Chatbot.send('Do you ship to Germany?');
Chatbot.reset();  // clear this visitor's history</pre>

    <h2 style="margin-top:26px">Site key</h2>
    <p class="hint">Regenerating disables the current embed code.</p>
    <p class="mono"><?= e($site['site_key']) ?></p>
    <form method="post" action="<?= e(admin_url('site')) ?>" onsubmit="return confirm('Generate a new key? The current embed code will stop working until you replace it.');">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="regenerate_key">
      <input type="hidden" name="id" value="<?= (int)$site['id'] ?>">
      <button class="btn secondary" type="submit">Regenerate key</button>
    </form>
  </div>
