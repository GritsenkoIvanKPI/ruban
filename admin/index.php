<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
require __DIR__ . '/storage.php';

$loginErrorMessages = [
    'invalid_password' => 'Falsches Passwort.',
    'too_many_attempts' => 'Zu viele Fehlversuche. Bitte kurz warten.',
];
$loginErrorCode = isset($_GET['login_error']) ? (string)$_GET['login_error'] : null;
$loginErrorText = $loginErrorCode ? ($loginErrorMessages[$loginErrorCode] ?? 'Falsches Passwort.') : null;

$initialState = null;
if (is_logged_in()) {
    $metaPath = __DIR__ . '/field-meta.json';
    $initialState = [
        'csrf' => csrf_token(),
        'content' => read_content(),
        'fieldMeta' => file_exists($metaPath) ? json_decode((string)file_get_contents($metaPath), true) : null,
    ];
}
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Ruban Marine — Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  @font-face {
    font-family: 'Inter';
    font-style: normal;
    font-weight: 100 900;
    font-display: swap;
    src: url('../fonts/inter-latin.woff2') format('woff2');
  }
  :root { --border: oklch(28% .018 240 / .6); }
  * { border-color: var(--border); }
  body {
    background-color: oklch(14% .012 240);
    color: oklch(97% .005 240);
    font-family: 'Inter', system-ui, sans-serif;
    -webkit-font-smoothing: antialiased;
  }
  .surface { background: oklch(18% .014 240); border: 1px solid var(--border); border-radius: 1rem; }
  .surface-2 { background: oklch(22% .014 240); border: 1px solid var(--border); border-radius: .75rem; }
  .gold { color: oklch(82% .14 85); }
  .btn { display: inline-flex; align-items: center; justify-content: center; gap: .5rem; padding: .625rem 1.1rem; border-radius: 999px; font-size: .875rem; font-weight: 600; transition: transform .15s ease, opacity .15s ease; cursor: pointer; border: 1px solid transparent; }
  .btn:active { transform: scale(.97); }
  .btn-gold { background: linear-gradient(135deg, oklch(88% .12 90), oklch(78% .15 80)); color: oklch(16% .012 240); }
  .btn-gold:hover { opacity: .92; }
  .btn-gold:disabled { opacity: .5; cursor: default; }
  .btn-outline { border-color: var(--border); color: oklch(90% .01 240); }
  .btn-outline:hover { background: oklch(24% .016 240); }
  .btn-danger { color: oklch(75% .16 25); }
  .btn-danger:hover { background: oklch(30% .06 25 / .25); }
  .field-input, .field-textarea {
    width: 100%; background: oklch(15% .012 240); border: 1px solid var(--border); border-radius: .6rem;
    padding: .6rem .75rem; font-size: .875rem; color: oklch(97% .005 240);
  }
  .field-input:focus, .field-textarea:focus { outline: none; border-color: oklch(82% .14 85 / .6); }
  .field-textarea { resize: vertical; min-height: 5.5rem; font-family: ui-monospace, monospace; line-height: 1.5; }
  .lang-tab { padding: .4rem .9rem; border-radius: 999px; font-size: .8rem; font-weight: 600; border: 1px solid var(--border); color: oklch(70% .015 240); cursor: pointer; }
  .lang-tab[data-active="true"] { background: oklch(82% .14 85); color: oklch(16% .012 240); border-color: transparent; }
  .side-link { display: block; padding: .5rem .75rem; border-radius: .5rem; font-size: .8rem; color: oklch(70% .015 240); }
  .side-link:hover { background: oklch(22% .014 240); color: oklch(97% .005 240); }
  .side-link.active { background: oklch(24% .05 85 / .18); color: oklch(88% .12 90); }
  ::-webkit-scrollbar { width: 10px; height: 10px; }
  ::-webkit-scrollbar-thumb { background: oklch(30% .018 240); border-radius: 999px; }
</style>
</head>
<body class="min-h-screen">

<?php if (!is_logged_in()): ?>

  <div class="min-h-screen flex items-center justify-center p-6">
    <form id="login-form" method="post" action="login.php" class="surface w-full max-w-sm p-8">
      <p class="text-xs tracking-[.25em] uppercase font-semibold gold mb-2">Ruban Marine</p>
      <h1 class="text-2xl font-semibold mb-6">Admin-Login</h1>
      <label class="block text-xs text-white/60 mb-2" for="password">Passwort</label>
      <input id="password" name="password" type="password" required autofocus
        class="field-input mb-4" placeholder="••••••••">
      <p id="login-error" class="text-sm text-red-400 mb-4<?php echo $loginErrorText ? '' : ' hidden'; ?>"><?php echo $loginErrorText ? htmlspecialchars($loginErrorText, ENT_QUOTES) : ''; ?></p>
      <button type="submit" class="btn btn-gold w-full">Anmelden</button>
    </form>
  </div>

  <script>
    // The form above has a plain action="login.php" as a no-JS fallback (the
    // request still reaches the server and login.php redirects back with the
    // outcome). When JS is available, intercept the submit and use fetch
    // instead for a snappier, no-full-reload experience.
    document.getElementById('login-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const password = document.getElementById('password').value;
      const errEl = document.getElementById('login-error');
      errEl.classList.add('hidden');
      try {
        const res = await fetch('login.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ password }),
        });
        if (res.ok) {
          location.reload();
        } else {
          const data = await res.json().catch(() => ({}));
          errEl.textContent = data.error === 'too_many_attempts'
            ? 'Zu viele Fehlversuche. Bitte kurz warten.'
            : 'Falsches Passwort.';
          errEl.classList.remove('hidden');
        }
      } catch (err) {
        // Network/JS failure — fall back to a real form submission so the
        // request still goes through instead of failing silently.
        document.getElementById('login-form').submit();
      }
    });
  </script>

<?php else: ?>

  <div id="app" class="pb-24">
    <header class="sticky top-0 z-30 backdrop-blur border-b border-white/10" style="background: oklch(14% .012 240 / .92);">
      <div class="max-w-[1400px] mx-auto px-5 py-3 flex items-center gap-4 flex-wrap">
        <div>
          <p class="text-[10px] tracking-[.25em] uppercase font-semibold gold">Ruban Marine</p>
          <h1 class="text-lg font-semibold leading-tight">Admin</h1>
        </div>
        <div class="flex gap-2 ml-2" id="lang-tabs"></div>
        <div class="flex-1"></div>
        <span id="save-status" class="text-xs text-white/50"></span>
        <button id="save-btn" class="btn btn-gold">Speichern</button>
        <button id="password-btn" class="btn btn-outline">Passwort ändern</button>
        <button id="logout-btn" class="btn btn-outline">Abmelden</button>
      </div>
    </header>

    <div class="max-w-[1400px] mx-auto px-5 py-8 grid lg:grid-cols-[220px_1fr] gap-8">
      <nav id="side-nav" class="hidden lg:block sticky top-24 self-start max-h-[80vh] overflow-y-auto space-y-1"></nav>
      <main id="sections" class="space-y-8 min-w-0"></main>
    </div>
  </div>

  <dialog id="password-dialog" class="surface p-0 max-w-sm w-full backdrop:bg-black/70">
    <form id="password-form" class="p-6">
      <h2 class="text-lg font-semibold mb-4">Passwort ändern</h2>
      <label class="block text-xs text-white/60 mb-1">Aktuelles Passwort</label>
      <input id="pw-current" type="password" required class="field-input mb-3">
      <label class="block text-xs text-white/60 mb-1">Neues Passwort (min. 8 Zeichen)</label>
      <input id="pw-new" type="password" required minlength="8" class="field-input mb-4">
      <p id="pw-error" class="text-sm text-red-400 mb-3 hidden"></p>
      <div class="flex gap-3 justify-end">
        <button type="button" id="pw-cancel" class="btn btn-outline">Abbrechen</button>
        <button type="submit" class="btn btn-gold">Speichern</button>
      </div>
    </form>
  </dialog>

  <script>
    window.__INITIAL__ = <?php echo json_encode($initialState, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
  </script>
  <script src="app.js"></script>

<?php endif; ?>

</body>
</html>
