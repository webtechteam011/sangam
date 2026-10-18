/* Shared page behaviour: room sliders + scroll-reveal animations. */
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.room-slider').forEach(function (slider) {
    var slides = slider.querySelectorAll('.slide');
    var prevBtn = slider.querySelector('.prev-btn');
    var nextBtn = slider.querySelector('.next-btn');
    if (!slides.length || !prevBtn || !nextBtn) return;
    var current = 0;
    function show(i) {
      slides.forEach(function (s) { s.classList.remove('active'); });
      slides[i].classList.add('active');
    }
    nextBtn.addEventListener('click', function () { current = (current + 1) % slides.length; show(current); });
    prevBtn.addEventListener('click', function () { current = (current - 1 + slides.length) % slides.length; show(current); });
  });

  var selectors = ['.amenities-animate', '.dining-animate', '.adventure-animate', '.cinematic-animate',
    '.contact-animate', '.footer-animate', '.roomsection-animate', '.seo-animate'].join(', ');
  var els = document.querySelectorAll(selectors);
  if (!('IntersectionObserver' in window)) {
    els.forEach(function (el) { el.classList.add('is-visible'); });
    return;
  }
  var io = new IntersectionObserver(function (entries, obs) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) { entry.target.classList.add('is-visible'); obs.unobserve(entry.target); }
    });
  }, { threshold: 0.15 });
  els.forEach(function (el) { io.observe(el); });
});
