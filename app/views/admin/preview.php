<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Preview · <?= e($site['name']) ?></title>
<style>
  body { margin: 0; font: 16px/1.6 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #0f172a; background: #f8fafc; }
  .bar { background: #0f172a; color: #e2e8f0; padding: 12px 20px; font-size: 14px; display: flex; gap: 14px; align-items: center; justify-content: space-between; flex-wrap: wrap; }
  .bar a { color: #93c5fd; }
  .page { max-width: 760px; margin: 0 auto; padding: 50px 20px 120px; }
  .page h1 { font-size: 30px; margin-bottom: 8px; }
  .page p { color: #475569; }
  .block { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 22px; margin-top: 18px; }
</style>
</head>
<body>
<div class="bar">
  <span>Preview of <strong><?= e($site['name']) ?></strong> — this page loads the real embed code and talks to the live bot.</span>
  <a href="<?= e(admin_url('site', ['id' => $site['id'], 'tab' => 'design'])) ?>">← Back to design</a>
</div>
<div class="page">
  <h1>A sample client page</h1>
  <p>This stand-in page exists only so you can see the widget exactly as a visitor will. Open the bubble and ask something from the knowledge base.</p>
  <div class="block">
    <h2>Tips</h2>
    <ul>
      <li>Design changes appear here after you save and reload (the embed is cached for 5 minutes).</li>
      <li>Messages sent here are stored as real conversations and use your OpenAI credits.</li>
      <li>Use <span style="font-family:monospace">Chatbot.reset()</span> in the browser console to clear this visitor's history.</li>
    </ul>
  </div>
</div>
<script src="<?= e(base_url('embed.php')) ?>?k=<?= e($site['site_key']) ?>" async></script>
</body>
</html>
