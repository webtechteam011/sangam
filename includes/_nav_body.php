	<div id="fixed-social">
		 <div>
    <a href="https://wa.me/919999683311?text=Hello%2C%20I%20would%20like%20to%20know%20more%20about%20staying%20at%20The%20Sangam%20House." class="fixed-gplus" target="_blank"><i class=""><img src="/img/wts.png" alt="WhatsApp The Sangam House"> </i> <span>WhatsApp Us</span></a>
  </div>
  <div>
    <a href="tel:+919889882696" class="fixed-facebook"><i class=""><img src="/img/phone.png" alt="Call The Sangam House"></i> <span>Call Us</span></a>
  </div>
  </div>

  <!-- FIXED COMPACT HEADER -->
  <header class="site-header">
    <!-- TOP INFO BAR -->
    <div class="topbar">
      <div class="topbar-left">
        <span>&#9993; info@thesangamhouse.com</span>
        <span>&#128222; +91 9889882696, +91 9999683311</span>
      </div>
      <div class="topbar-right">
    <span class="location">Devprayag, Uttarakhand — Where the Ganga Begins</span>
    
</div>
    </div>

    <!-- MAIN NAVBAR -->
    <nav class="navbar">
      <div class="brand">
       <a href="/"><img src="/img/logo.png" alt="The Sangam House, Devprayag" /> </a>
      </div>

      <ul class="nav-links" id="navLinks">
        <li><a <?= nav_cls('home') ?> href="/">Home</a></li>
        <li><a <?= nav_cls('about') ?> href="/about">About Us</a></li>
        
        <!-- Dropdown Properly Nested -->
        <li class="dropdown">
            <a href="/accommodation" class="dropdown-toggle<?= $nav === 'accommodation' ? ' active' : '' ?>">Accommodation <span class="arrow">&#9662;</span></a>
            <ul class="dropdown-menu">
               <li><a href="/deluxe-mountain-view-room">Deluxe Mountain View Room</a></li>
               <li><a href="/luxury-himalayan-dormitory">Luxury Himalayan Dormitory</a></li>
               <li><a href="/hotels-in-devprayag">Hotels in Devprayag</a></li>
            </ul>
        </li>

        <li><a <?= nav_cls('activities') ?> href="/activities">Activities</a></li>
        <li><a <?= nav_cls('contact') ?> href="/contact">Contact</a></li>
        <li class="mobile-book"><a class="book-btn" href="https://www.secure-booking-engine.com/accounts/N7SAVY4IvU12gH563XN2NQ/properties/uK9uqJbfQJjEr9POldilkw/booking-engine/web/source/4wsctBw6Oq6j-g9XuxeRzQ/" target="_blank" rel="noopener">BOOK STAY</a></li>
      </ul>


      <div class="navbar-actions">
        <a class="book-btn desktop-book" href="https://www.secure-booking-engine.com/accounts/N7SAVY4IvU12gH563XN2NQ/properties/uK9uqJbfQJjEr9POldilkw/booking-engine/web/source/4wsctBw6Oq6j-g9XuxeRzQ/" target="_blank" rel="noopener">BOOK STAY</a>
        <button class="hamburger" id="hamburger" aria-label="Toggle menu" aria-expanded="false" aria-controls="navLinks">
          <span></span>
          <span></span>
          <span></span>
        </button>
      </div>
    </nav>
  </header>
  <div class="nav-overlay" id="navOverlay"></div>
