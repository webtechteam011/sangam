<?php
declare(strict_types=1);

/**
 * Self-contained, branded error page (no DB, no layout includes) so it still
 * renders when the rest of the app is broken. Used for 403, 404, 500 and 503.
 */
const ERROR_COPY = [
    403 => ['Access denied', "You don't have permission to open this page.", 'locked'],
    404 => ['Page not found', "The page you're looking for has moved, been renamed or never existed.", 'compass'],
    500 => ['Something went wrong', "We hit an unexpected problem on our side. It's not you — please try again in a moment.", 'wrench'],
    503 => ['Back in a moment', 'The website is briefly unavailable. Please try again shortly.', 'wrench'],
];

function render_error_view(int $code): void
{
    $code = isset(ERROR_COPY[$code]) ? $code : 500;
    [$title, $text] = ERROR_COPY[$code];
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: text/html; charset=utf-8');
        header('X-Robots-Tag: noindex, nofollow');
        header('Cache-Control: no-store');
    }
    $h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $wa = 'https://wa.me/919999683311?text=' . rawurlencode('Hello, I would like to know more about staying at The Sangam House.');
    ?>
<!DOCTYPE html>
<html lang="en-IN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= $code ?> · <?= $h($title) ?> | The Sangam House</title>
<link rel="icon" href="/img/favicon.ico">
<style>
:root{--gold:#d4af37;--gold-d:#b8912f;--ink:#111;--cream:#f4f1ea}
*{box-sizing:border-box;margin:0;padding:0}
body{min-height:100vh;display:flex;flex-direction:column;font-family:'Poppins','Segoe UI',system-ui,sans-serif;color:var(--cream);
  background:radial-gradient(1200px 600px at 80% -10%,rgba(212,175,55,.18),transparent 60%),radial-gradient(900px 500px at -10% 110%,rgba(212,175,55,.10),transparent 55%),#0e0f12}
header{padding:20px 28px}
header a{display:inline-block}header img{height:54px;width:auto;display:block}
main{flex:1;display:flex;align-items:center;justify-content:center;padding:20px}
.card{max-width:680px;width:100%;text-align:center}
.code{font-family:'Playfair Display',Georgia,serif;font-size:clamp(96px,22vw,190px);font-weight:700;line-height:1;letter-spacing:.02em;
  background:linear-gradient(180deg,#f3dc8a,var(--gold) 45%,var(--gold-d));-webkit-background-clip:text;background-clip:text;color:transparent;position:relative}
.code svg{width:.62em;height:.62em;vertical-align:-.04em;stroke:var(--gold);fill:none;stroke-width:1.6;margin:0 .02em}
.river{display:block;margin:6px auto 22px;width:150px;height:14px;opacity:.9}
h1{font-family:'Playfair Display',Georgia,serif;font-size:clamp(26px,5vw,38px);font-weight:600;margin-bottom:12px}
p.lead{color:#c9c5ba;line-height:1.75;font-size:16px;max-width:520px;margin:0 auto 28px}
.btns{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-bottom:34px}
.btn{display:inline-flex;align-items:center;gap:8px;padding:13px 26px;border-radius:50px;font-weight:600;font-size:14px;text-decoration:none;border:2px solid var(--gold);cursor:pointer;transition:.25s;font-family:inherit}
.btn.primary{background:var(--gold);color:var(--ink)}.btn.primary:hover{background:var(--gold-d);border-color:var(--gold-d)}
.btn.ghost{background:transparent;color:var(--gold)}.btn.ghost:hover{background:var(--gold);color:var(--ink)}
.links{border-top:1px solid rgba(255,255,255,.1);padding-top:22px}
.links span{display:block;font-size:12px;letter-spacing:.16em;text-transform:uppercase;color:#8d8a80;margin-bottom:12px}
.links div{display:flex;flex-wrap:wrap;gap:8px;justify-content:center}
.links a{color:var(--cream);text-decoration:none;font-size:13.5px;padding:8px 16px;border:1px solid rgba(255,255,255,.16);border-radius:30px;transition:.2s}
.links a:hover{border-color:var(--gold);color:var(--gold)}
footer{text-align:center;padding:22px;font-size:13px;color:#8d8a80}
footer a{color:var(--gold);text-decoration:none}
</style>
</head>
<body>
<header><a href="/"><img src="/img/logo.png" alt="The Sangam House, Devprayag"></a></header>
<main>
  <div class="card">
    <div class="code" aria-label="Error <?= $code ?>"><?php
      $d = (string)$code;
      // Replace the middle digit with a small "confluence" glyph for a brand touch.
      echo $d[0]; ?><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4c4 3 6 6 9 9m9-9c-4 3-6 6-9 9m0 0v8"/><circle cx="12" cy="13" r="1.6"/></svg><?= $d[2] ?></div>
    <svg class="river" viewBox="0 0 150 14" aria-hidden="true"><path d="M0 7c12-8 25-8 37 0s25 8 38 0 25-8 37 0 25 8 38 0" fill="none" stroke="#d4af37" stroke-width="2" stroke-linecap="round"/></svg>
    <h1><?= $h($title) ?></h1>
    <p class="lead"><?= $h($text) ?></p>
    <div class="btns">
      <a class="btn primary" href="/">Back to Home</a>
      <?php if ($code >= 500): ?><button class="btn ghost" type="button" onclick="location.reload()">Try again</button>
      <?php else: ?><a class="btn ghost" href="/contact">Contact us</a><?php endif; ?>
      <a class="btn ghost" href="<?= $h($wa) ?>" target="_blank" rel="noopener">WhatsApp</a>
    </div>
    <nav class="links" aria-label="Popular pages">
      <span>Popular pages</span>
      <div>
        <a href="/accommodation">Rooms &amp; Stay</a>
        <a href="/hotels-in-devprayag">Hotels in Devprayag</a>
        <a href="/resorts-in-devprayag">Resorts in Devprayag</a>
        <a href="/stay-near-devprayag-sangam">Stay near Sangam</a>
        <a href="/activities">Things to do</a>
        <a href="/about">About</a>
      </div>
    </nav>
  </div>
</main>
<footer>Need help right now? Call <a href="tel:+919889882696">+91 98898 82696</a> · <a href="mailto:info@thesangamhouse.com">info@thesangamhouse.com</a></footer>
</body>
</html>
<?php
}
