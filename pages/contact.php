  
<main class="ts-contact-page-wrapper">

    <!-- 1. Contact Page Full-Width Hero Section -->
    <section class="ts-room-hero-section ts-dark-theme ts-contact-hero">
        <div class="ts-room-hero-container">
            <div class="ts-room-hero-content">
                <span class="ts-room-subtitle">The Sangam House • Get in Touch</span>
                <h1 class="ts-room-main-heading">Contact Us</h1>
                <p class="ts-room-tagline">We are here to assist with your stay, group bookings, or pilgrimage inquiries right beside the holy confluence.</p>
            </div>
        </div>
    </section>

    <!-- 2. Main Contact Section (Info Cards Left, Form Right) -->
    <section class="ts-contact-section ts-light-theme">
        <div class="ts-room-content-container ts-contact-grid-wrapper">
            
            <!-- Left Side: Contact Information Cards -->
            <div class="ts-contact-info-col">
                <div class="adventure-header" style="text-align: left; margin-bottom: 30px;">
                    <span class="adventure-tagline">Reach Out</span>
                    <h2 class="adventure-title" style="font-size: 2rem;">We’d Love to Hear From You</h2>
                    <p class="adventure-description" style="margin: 10px 0 0; text-align: left;">
                        Whether you're planning a spiritual yatra, family vacation, or group trek, our team at The Sangam House is ready to help you plan your stay.
                    </p>
                </div>

                <div class="ts-contact-cards-stack">
                    <!-- Phone Card -->
                    <a href="tel:9889882696" class="ts-contact-card-item">
                        <div class="ts-contact-icon-box">
                            <i class="fa-solid fa-phone"></i>
                        </div>
                        <div class="ts-contact-card-text">
                            <h4>Phone Number</h4>
                            <p>+91 9889882696, +91 9999683311</p>
                        </div>
                    </a>

                    <!-- WhatsApp Card -->
                    <a href="https://wa.me/919999683311?text=Hello%2C%20I%20would%20like%20to%20know%20more%20about%20staying%20at%20The%20Sangam%20House." target="_blank" class="ts-contact-card-item">
                        <div class="ts-contact-icon-box wa-icon">
                            <i class="fa-brands fa-whatsapp"></i>
                        </div>
                        <div class="ts-contact-card-text">
                            <h4>WhatsApp Chat</h4>
                            <p>9999683311 (Instant Response)</p>
                        </div>
                    </a>

                    <!-- Email Card -->
                    <a href="mailto:info@thesangamhouse.com" class="ts-contact-card-item">
                        <div class="ts-contact-icon-box">
                            <i class="fa-solid fa-envelope"></i>
                        </div>
                        <div class="ts-contact-card-text">
                            <h4>Email Address</h4>
                            <p>info@thesangamhouse.com</p>
                        </div>
                    </a>

                    <!-- Location Card -->
                    <a href="https://maps.app.goo.gl/oBABLFd3XfHZSdLr5" target="_blank" class="ts-contact-card-item">
                        <div class="ts-contact-icon-box">
                            <i class="fa-solid fa-location-dot"></i>
                        </div>
                        <div class="ts-contact-card-text">
                            <h4>Location</h4>
                            <p>The Sangam House, Devprayag, Uttarakhand (Beside Alaknanda & Bhagirathi Confluence)</p>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Right Side: Interactive Inquiry Form -->
            <div class="ts-contact-form-col">
                <div class="ts-booking-widget ts-contact-form-card">
                    <div class="ts-widget-header" style="text-align: left; padding: 0 0 20px 0;">
                        <span class="ts-widget-tag">Quick Inquiry</span>
                        <h4>Send Us a Message</h4>
                        <p>Fill out the form below and our team will get back to you shortly.</p>
                    </div>

                    <form class="ts-inquiry-form" data-enquiry data-source="contact-page" method="post" action="/enquiry">
                        <div class="ts-form-group">
                            <label for="contact-name">Your Full Name</label>
                            <input type="text" id="contact-name" name="name" maxlength="120" placeholder="Enter your name" required class="ts-form-input">
                        </div>

                        <div class="ts-form-group">
                            <label for="contact-phone">Phone Number / WhatsApp</label>
                            <input type="tel" id="contact-phone" name="phone" maxlength="30" placeholder="Enter your mobile number" required class="ts-form-input">
                        </div>

                        <div class="ts-form-group">
                            <label for="contact-email">Email (for confirmation)</label>
                            <input type="email" id="contact-email" name="email" maxlength="160" placeholder="you@example.com" class="ts-form-input">
                        </div>

                        <div class="ts-form-group">
                            <label for="contact-room">Select Accommodation / Interest</label>
                            <select id="contact-room" name="room" class="ts-form-input">
                                <option value="Deluxe Mountain View Room">Deluxe Mountain View Room (2+1 Guests)</option>
                                <option value="Luxury Himalayan Dormitory">Luxury Himalayan Dormitory (Group / 40 Pax)</option>
                                <option value="General Enquiry">General Inquiry</option>
                            </select>
                        </div>

                        <div class="ts-form-group">
                            <label for="contact-msg">Your Message / Dates</label>
                            <textarea id="contact-msg" name="message" maxlength="2000" rows="4" placeholder="Let us know your travel dates or specific questions..." class="ts-form-input"></textarea>
                        </div>

                        <div class="enq-hp" aria-hidden="true"><label>Leave empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                        <div class="enq-status" role="status" aria-live="polite"></div>
                        <button type="submit" class="ts-cta-btn phone-btn" style="width: 100%; border: none; cursor: pointer; justify-content: center; margin-top: 10px;">
                            <i class="fa-solid fa-paper-plane"></i> Send Inquiry Now
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </section>

    <!-- 3. Google Maps Embed Section -->
    <section class="ts-map-section">
        <div class="ts-map-container">
<iframe src="https://www.google.com/maps/embed?pb=!1m14!1m8!1m3!1d431.2746357608238!2d78.6049213!3d30.1457808!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3909090017dcf57f%3A0x26c75dd2c58647b8!2sTHE%20SANGAM%20HOUSE%20-DEVPRAYAG!5e0!3m2!1sen!2sin!4v1792195422052!5m2!1sen!2sin" width="100%" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>
        </div>
    </section>

</main>
