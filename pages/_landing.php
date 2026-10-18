<?php
/** @var array $content landing content  @var array $page  @var string $route */
$pages = require __DIR__ . '/../includes/pages.php';
$related = array_filter($pages, fn($p, $k) => $p['type'] === 'landing' && $k !== $route, ARRAY_FILTER_USE_BOTH);
?>
<main>
  <section class="seo-hero" style="--seo-hero:url('<?= e($page['image']) ?>')">
    <span class="seo-kicker"><?= e($content['kicker']) ?></span>
    <h1><?= e($content['h1']) ?></h1>
    <p><?= e($content['lead']) ?></p>
    <div class="seo-cta">
      <button type="button" class="seo-btn primary open-booking"><i class="fa-solid fa-calendar-check"></i> Enquire Now</button>
      <a class="seo-btn ghost" href="tel:+919889882696"><i class="fa-solid fa-phone"></i> Call +91 98898 82696</a>
      <a class="seo-btn ghost" href="https://wa.me/<?= SITE_WHATSAPP ?>?text=<?= rawurlencode('Hello, I would like to book a stay at The Sangam House, Devprayag.') ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>
    </div>
  </section>

  <section class="seo-body">
    <div class="seo-wrap">
      <nav class="seo-crumbs" aria-label="Breadcrumb"><a href="/">Home</a> &rsaquo; <span><?= e($page['breadcrumb']) ?></span></nav>

      <?php foreach ($content['sections'] as $s): ?>
        <div class="seo-section seo-animate">
          <h2><?= e($s['h']) ?></h2>
          <?php foreach ($s['p'] ?? [] as $para): ?><p><?= e($para) ?></p><?php endforeach; ?>
          <?php if (!empty($s['list'])): ?><ul><?php foreach ($s['list'] as $li): ?><li><?= e($li) ?></li><?php endforeach; ?></ul><?php endif; ?>
        </div>
      <?php endforeach; ?>

      <div class="seo-section seo-faq seo-animate">
        <h2>Frequently Asked Questions</h2>
        <?php foreach ($content['faq'] as [$q, $a]): ?>
          <details><summary><?= e($q) ?></summary><p><?= e($a) ?></p></details>
        <?php endforeach; ?>
      </div>

      <div class="seo-section">
        <h2>Explore more</h2>
        <div class="seo-related">
          <a href="/accommodation">Rooms &amp; Accommodation</a>
          <a href="/deluxe-mountain-view-room">Deluxe Mountain View Room</a>
          <a href="/luxury-himalayan-dormitory">Himalayan Dormitory</a>
          <a href="/activities">Things to do in Devprayag</a>
          <?php foreach ($related as $k => $p): ?><a href="/<?= e($k) ?>"><?= e($p['breadcrumb']) ?></a><?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>

  <section class="seo-enquire" id="enquire">
    <div class="seo-wrap">
      <h2>Send an Enquiry</h2>
      <p class="sub">Tell us your dates and we will reply with availability and our best direct rate.</p>
      <form class="seo-form" data-enquiry data-source="landing:<?= e($route) ?>" method="post" action="/enquiry">
        <div class="row">
          <input type="text" name="name" placeholder="Your name" required maxlength="120" autocomplete="name">
          <input type="tel" name="phone" placeholder="Mobile / WhatsApp" required maxlength="30" autocomplete="tel">
        </div>
        <div class="row">
          <input type="date" name="checkin" aria-label="Check-in date">
          <input type="date" name="checkout" aria-label="Check-out date">
        </div>
        <div class="row">
          <select name="room" aria-label="Room type">
            <option value="Deluxe Mountain View Room">Deluxe Mountain View Room</option>
            <option value="Luxury Himalayan Dormitory">Luxury Himalayan Dormitory</option>
            <option value="General Enquiry">General Enquiry</option>
          </select>
          <input type="number" name="guests" min="1" max="60" placeholder="Guests" aria-label="Number of guests">
        </div>
        <textarea name="message" rows="3" maxlength="2000" placeholder="Any message (optional)"></textarea>
        <div class="enq-hp" aria-hidden="true"><label>Leave empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        <div class="enq-status" role="status" aria-live="polite"></div>
        <button type="submit" class="seo-btn primary" style="justify-content:center"><i class="fa-solid fa-paper-plane"></i> Send Enquiry</button>
      </form>
    </div>
  </section>
</main>
