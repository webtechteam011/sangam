document.addEventListener('DOMContentLoaded', function () {

  /* ---------- Mobile menu toggle ---------- */
  const hamburger = document.getElementById('hamburger');
  const navLinks = document.getElementById('navLinks');
  const navOverlay = document.getElementById('navOverlay');

  function openMenu() {
    hamburger.classList.add('active');
    navLinks.classList.add('active');
    navOverlay.classList.add('active');
    hamburger.setAttribute('aria-expanded', 'true');
    document.body.style.overflow = 'hidden';
  }

  function closeMenu() {
    hamburger.classList.remove('active');
    navLinks.classList.remove('active');
    navOverlay.classList.remove('active');
    hamburger.setAttribute('aria-expanded', 'false');
    document.body.style.overflow = '';
    
    // Optional: Close open mobile dropdowns when closing the menu
    document.querySelectorAll('.nav-links .dropdown.open').forEach(function(drop) {
      drop.classList.remove('open');
    });
  }

  if (hamburger && navLinks && navOverlay) {
    hamburger.addEventListener('click', function () {
      const isActive = navLinks.classList.contains('active');
      isActive ? closeMenu() : openMenu();
    });

    navOverlay.addEventListener('click', closeMenu);

    /* ---------- Mobile Dropdown & Link Handling ---------- */
    const dropdownToggle = navLinks.querySelector('.dropdown > a');

    navLinks.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', function (e) {
        // If it's the dropdown toggle on mobile, open/close accordion instead of closing menu
        if (window.innerWidth <= 992 && link === dropdownToggle) {
          e.preventDefault();
          const parentLi = link.parentElement;
          parentLi.classList.toggle('open');
          return; // Stop execution so it doesn't call closeMenu()
        }

        // For all other links (Home, About, Sub-links, etc.), close the menu normally
        closeMenu();
      });
    });

    // Close menu when resize back to desktop
    window.addEventListener('resize', function () {
      if (window.innerWidth > 992) {
        closeMenu();
      }
    });

    // Close on Escape key
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeMenu();
    });
  }

  /* ---------- Booking modal ---------- */
  const bookingOverlay = document.getElementById('bookingOverlay');
  const bookingClose = document.getElementById('bookingClose');
  const bookingForm = document.getElementById('bookingForm');
  const openBookingBtns = document.querySelectorAll('.open-booking');

  function openBooking() {
    closeMenu();
    bookingOverlay.classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  function closeBooking() {
    bookingOverlay.classList.remove('active');
    document.body.style.overflow = '';
  }

  if (bookingOverlay && bookingClose) {
    openBookingBtns.forEach(function (btn) {
      btn.addEventListener('click', openBooking);
    });

    bookingClose.addEventListener('click', closeBooking);

    // Close when clicking outside the modal card
    bookingOverlay.addEventListener('click', function (e) {
      if (e.target === bookingOverlay) closeBooking();
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeBooking();
    });

    // Form submission is handled by assets/js/enquiry.js
  }

  /* ---------- Hero slider ---------- */
  if (!document.querySelector('.heroSwiper')) return;
  const heroSwiper = new Swiper('.heroSwiper', {
    loop: true,
    speed: 1000,
    effect: 'fade',
    fadeEffect: { crossFade: true },
    grabCursor: true,
    autoplay: {
      delay: 5000,
      disableOnInteraction: false,
    },
    navigation: {
      nextEl: '.swiper-button-next',
      prevEl: '.swiper-button-prev',
    },
    pagination: {
      el: '.swiper-pagination',
      clickable: true,
    },
    observer: true,
    observeParents: true,
  });

  // Force the text-entrance animation to replay cleanly on every transition
  heroSwiper.on('slideChangeTransitionStart', function () {
    const activeSlide = heroSwiper.slides[heroSwiper.activeIndex];
    if (!activeSlide) return;
    const animatedEls = activeSlide.querySelectorAll('.badge, .headline, .slide-text, .hero-buttons');
    animatedEls.forEach(function (el) {
      el.style.animation = 'none';
      void el.offsetWidth; // force reflow so the animation restarts
      el.style.animation = '';
    });
  });
});

/* Google Ads: count every WhatsApp link click as a "WhatsApp Click" conversion.
   Same event as the gtag_report_conversion() snippet in the header, but sent without
   redirecting, so links that open in a new tab keep working. */
document.addEventListener('click', function (e) {
  var a = e.target.closest ? e.target.closest('a[href*="wa.me"], a[href*="api.whatsapp.com"]') : null;
  if (!a || typeof gtag !== 'function') return;
  gtag('event', 'conversion', { 'send_to': 'AW-18497672685/2PluCIuOopodEO2rsPRE', 'value': 1.0, 'currency': 'INR' });
});
