(function () {
  var bar = document.querySelector('.bl-progress i');
  var article = document.getElementById('bl-article');
  if (bar && article) {
    var tick = false;
    var update = function () {
      var r = article.getBoundingClientRect();
      var total = r.height - window.innerHeight * 0.6;
      var done = Math.min(1, Math.max(0, -r.top / Math.max(total, 1)));
      bar.style.width = (done * 100).toFixed(1) + '%';
      tick = false;
    };
    window.addEventListener('scroll', function () { if (!tick) { tick = true; requestAnimationFrame(update); } }, { passive: true });
    update();
  }

  document.querySelectorAll('[data-copy]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var url = btn.getAttribute('data-copy');
      var label = btn.querySelector('span');
      var done = function () { if (label) { label.textContent = 'Copied!'; setTimeout(function () { label.textContent = 'Copy link'; }, 2000); } };
      if (navigator.clipboard) { navigator.clipboard.writeText(url).then(done, function () { window.prompt('Copy this link', url); }); }
      else { window.prompt('Copy this link', url); }
    });
  });

  // Highlight the current section in the sticky table of contents.
  var links = document.querySelectorAll('.bl-side .bl-toc a');
  if (links.length && 'IntersectionObserver' in window) {
    var map = {};
    links.forEach(function (a) { map[a.getAttribute('href').slice(1)] = a; });
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting && map[en.target.id]) {
          links.forEach(function (a) { a.classList.remove('on'); });
          map[en.target.id].classList.add('on');
        }
      });
    }, { rootMargin: '-90px 0px -70% 0px' });
    Object.keys(map).forEach(function (id) { var el = document.getElementById(id); if (el) { io.observe(el); } });
  }
})();
