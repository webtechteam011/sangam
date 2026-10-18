<?php /** @var string $route */ ?>
<?php require __DIR__ . '/_footer_body.php'; ?>

<script src="/vendor/swiper-bundle.min.js" defer></script>
<script src="/assets/js/site.js" defer></script>
<script src="/assets/js/main.js" defer></script>
<script src="/assets/js/enquiry.js" defer></script>
<?php if (!empty($pageJs) && is_file(__DIR__ . '/../assets/js/' . $pageJs)): ?>
<script src="/assets/js/<?= e($pageJs) ?>" defer></script>
<?php endif; ?>
<?php render_scripts('scripts_footer'); ?>
</body>
</html>
