    <footer class="footer-section">
        <div class="footer-container">
            
            <!-- Top Trust Badges / Highlights -->
            <div class="footer-trust-grid footer-animate">
                <div class="footer-trust-card">
                    <div class="footer-trust-icon">
                        <i class="fa-solid fa-mountain-sun"></i>
                    </div>
                    <div>
                        <h4 class="footer-trust-title">Prime Sangam View</h4>
                        <p class="footer-trust-desc">Overlooking the holy river confluence</p>
                    </div>
                </div>

                <div class="footer-trust-card">
                    <div class="footer-trust-icon">
                        <i class="fa-solid fa-concierge-bell"></i>
                    </div>
                    <div>
                        <h4 class="footer-trust-title">24/7 Concierge</h4>
                        <p class="footer-trust-desc">Dedicated hospitality & guidance</p>
                    </div>
                </div>

                <div class="footer-trust-card">
                    <div class="footer-trust-icon">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                    <div>
                        <h4 class="footer-trust-title">Safe &amp; Sanitized</h4>
                        <p class="footer-trust-desc">Premium standards for spiritual peace</p>
                    </div>
                </div>
            </div>

            <!-- Main Footer Navigation Grid -->
            <div class="footer-main-grid footer-animate">
                
                <!-- Col 1: Brand & Bio -->
                <div class="footer-col">
                    <a href="/" class="footer-brand-logo">
                       <img src="/img/logoold.png" alt="The Sangam House logo" loading="lazy" />
                    </a>
                    <p class="footer-text">
                        Your premier ultra-luxury retreat in Devprayag. Experience peace, spirituality, and Himalayan grandeur right where the holy Ganga begins.
                    </p>
                 
                </div>

                <!-- Col 2: Quick Links -->
                <div class="footer-col">
                    <h4 class="footer-heading">Quick Navigation</h4>
                    <ul class="footer-links-list">
                        <li><a href="/" class="footer-link">Home</a></li>
                        <li><a href="/about" class="footer-link">About Us</a></li>
                        <li><a href="/accommodation" class="footer-link">Accommodation</a></li>
                        <li><a href="/hotels-in-devprayag" class="footer-link">Hotels in Devprayag</a></li>
                        <li><a href="/resorts-in-devprayag" class="footer-link">Resorts in Devprayag</a></li>
                        <li><a href="/activities" class="footer-link">Activities</a></li>
                        <li><a href="/blog" class="footer-link">Blog</a></li>
                        <li><a href="/contact" class="footer-link">Contact</a></li>
                    </ul>
                </div>

                <!-- Col 3: Attractions Guide -->
                <div class="footer-col">
                    <h4 class="footer-heading">Devprayag Guide</h4>
                    <ul class="footer-links-list">
                        <li><a href="/stay-near-devprayag-sangam" class="footer-link">Stay Near Devprayag Sangam</a></li>
                        <li><a href="/activities" class="footer-link">Raghunathji Temple</a></li>
                        <li><a href="/activities" class="footer-link">Bungee Jumping &amp; Rafting</a></li>
                        <li><a href="/devprayag-weekend-getaway" class="footer-link">Weekend Getaway</a></li>
                        <li><a href="/activities" class="footer-link">Dasrathshila</a></li>
                    </ul>
                </div>

                <!-- Col 4: Newsletter & Desk Info -->
                <div class="footer-col">
                    <h4 class="footer-heading">Follow &amp; Connect</h4>
                      <div class="footer-socials">
                        <a href="https://wa.me/919999683311?text=Hello%2C%20I%20would%20like%20to%20know%20more%20about%20staying%20at%20The%20Sangam%20House." target="_blank" rel="noopener" class="footer-social-link" title="WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
                        <a href="https://www.instagram.com/thesangamhouse/" target="_blank" rel="noopener" class="footer-social-link" title="Instagram"><i class="fa-brands fa-instagram"></i></a>


                    </div>
                    
                    <div class="footer-hours-box">
                        <i class="fa-regular fa-clock"></i> Desk Hours: <span>Open 24 / 7 Daily</span>
                    </div>

                 
                </div>

            </div>

            <!-- Bottom Sub-Footer Bar -->
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> The Sangam House, Devprayag. All Rights Reserved.</p>
                <div class="footer-bottom-links">
                    <a href="/privacy-policy" class="footer-bottom-link">Privacy Policy</a>
                    <a href="/terms-of-service" class="footer-bottom-link">Terms of Service</a>
                    <a href="/cancellation-policy" class="footer-bottom-link">Cancellation Policy</a>
                </div>
            </div>

        </div>
    </footer>

  <!-- BOOKING MODAL -->
  <div class="booking-overlay" id="bookingOverlay">
    <div class="booking-modal" role="dialog" aria-modal="true" aria-labelledby="bookingTitle">
      <button class="booking-close" id="bookingClose" aria-label="Close">&times;</button>

      <span class="booking-eyebrow">RESERVATION ENQUIRY</span>
      <h2 class="booking-title" id="bookingTitle">Book Your Stay at The Sangam House</h2>

      <form class="booking-form" id="bookingForm" data-enquiry data-source="booking-modal" method="post" action="/enquiry">
        <div class="form-row">
          <div class="form-group">
            <label for="checkin">CHECK-IN DATE</label>
            <input type="date" id="checkin" name="checkin" required>
          </div>
          <div class="form-group">
            <label for="checkout">CHECK-OUT DATE</label>
            <input type="date" id="checkout" name="checkout" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="roomType">SELECT ROOM / BED</label>
            <select id="roomType" name="room">
              <option value="Deluxe Mountain View Room">Deluxe Mountain View Room (₹4,999)</option>
              <option value="Luxury Himalayan Dormitory">Luxury Himalayan Dormitory Bed (₹999)</option>
            </select>
          </div>
          <div class="form-group">
            <label for="guests">GUESTS COUNT</label>
            <input type="number" id="guests" name="guests" min="1" max="60" value="2" required>
          </div>
        </div>

        <div class="form-group full">
          <label for="fullName">FULL NAME</label>
          <input type="text" id="fullName" name="name" maxlength="120" placeholder="Enter your full name" required>
        </div>

        <div class="form-group full">
          <label for="phone">PHONE / WHATSAPP</label>
          <input type="tel" id="phone" name="phone" maxlength="30" placeholder="Your mobile / WhatsApp number" required>
        </div>

        <div class="form-group full">
          <label for="bookEmail">EMAIL (FOR CONFIRMATION)</label>
          <input type="email" id="bookEmail" name="email" maxlength="160" placeholder="you@example.com">
        </div>

        <div class="enq-hp" aria-hidden="true"><label>Leave empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        <div class="enq-status" role="status" aria-live="polite"></div>
        <button type="submit" class="booking-submit">SEND ENQUIRY</button>
      </form>
    </div>
  </div>
	
