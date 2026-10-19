<?php
declare(strict_types=1);

/**
 * Page registry: route => SEO + rendering data. Drives <head> tags, the
 * sitemap, breadcrumbs and the router, so each page is declared once.
 *
 * type: 'static'  => pages/<file>.php
 *       'landing' => pages/_landing.php, content from includes/landing.php
 */
return [
    '' => [
        'file' => 'index', 'type' => 'static', 'rooms' => ['deluxe', 'dorm'], 'nav' => 'home', 'priority' => '1.0', 'changefreq' => 'weekly',
        'title' => 'Hotel & Resort in Devprayag | The Sangam House',
        'description' => 'Book The Sangam House, a peaceful hotel & resort in Devprayag beside the Alaknanda–Bhagirathi sangam. King-size AC rooms, dormitory, free parking, pure veg food.',
        'keywords' => 'hotel in devprayag, hotels in devprayag, resort in devprayag, best hotel in devprayag, best resort in devprayag, devprayag hotel booking, hotel near devprayag sangam, The Sangam House',
        'image' => '/img/slider-1.jpg', 'breadcrumb' => 'Home',
    ],
    'about' => [
        'file' => 'about', 'type' => 'static', 'ptype' => 'AboutPage', 'nav' => 'about', 'priority' => '0.8', 'changefreq' => 'monthly',
        'title' => 'About Us | Best Stay in Devprayag – The Sangam House',
        'description' => 'Know The Sangam House: 20 deluxe rooms and 2 dormitories beside the holy Devprayag sangam, with a multi-cuisine restaurant, free parking and private darshan help.',
        'keywords' => 'about The Sangam House, accommodation in devprayag, places to stay in devprayag, devprayag stay, hotel near devprayag sangam',
        'image' => '/img/about.jpg', 'breadcrumb' => 'About Us',
    ],
    'accommodation' => [
        'file' => 'accommodation', 'type' => 'static', 'ptype' => 'CollectionPage', 'rooms' => ['deluxe', 'dorm'], 'nav' => 'accommodation', 'priority' => '0.9', 'changefreq' => 'monthly',
        'title' => 'Rooms & Accommodation in Devprayag | The Sangam House',
        'description' => 'Deluxe mountain-view rooms (₹4,999) and a 40-pax Himalayan dormitory (₹999/bed) in Devprayag. Compare accommodation and enquire for the best direct rate.',
        'keywords' => 'accommodation in devprayag, devprayag rooms, devprayag dormitory, luxury stay in devprayag, devprayag hotel booking, places to stay in devprayag',
        'image' => '/img/accomdtion.jpg', 'breadcrumb' => 'Accommodation',
    ],
    'deluxe-mountain-view-room' => [
        'file' => 'deluxe-mountain-view-room', 'type' => 'static', 'rooms' => ['deluxe'], 'nav' => 'accommodation', 'priority' => '0.8', 'changefreq' => 'monthly',
        'title' => 'Deluxe Mountain View Room in Devprayag | Sangam House',
        'description' => 'Deluxe room in Devprayag with king-size bed, 43" smart TV, AC, hot water and Himalayan views — 2 adults + 1 child. Free parking, veg food. Enquire for dates.',
        'keywords' => 'deluxe room devprayag, hotel room in devprayag, luxury stay in devprayag, hotel booking devprayag, mountain view room devprayag',
        'image' => '/img/roomone.jpg', 'breadcrumb' => 'Deluxe Mountain View Room', 'parent' => 'accommodation',
    ],
    'luxury-himalayan-dormitory' => [
        'file' => 'luxury-himalayan-dormitory', 'type' => 'static', 'rooms' => ['dorm'], 'nav' => 'accommodation', 'priority' => '0.8', 'changefreq' => 'monthly',
        'title' => 'Dormitory in Devprayag for Groups (40 Pax) | Sangam House',
        'description' => 'Clean bunk-bed dormitory in Devprayag for yatra groups, trekkers and rafting squads — 40 beds, individual lockers, from ₹999/bed. Enquire for group rates.',
        'keywords' => 'dormitory in devprayag, group stay devprayag, budget stay in devprayag, rafting group stay devprayag, devprayag dorm',
        'image' => '/img/room12.jpg', 'breadcrumb' => 'Luxury Himalayan Dormitory', 'parent' => 'accommodation',
    ],
    'activities' => [
        'file' => 'activities', 'type' => 'static', 'ptype' => 'CollectionPage', 'attractions' => true, 'nav' => 'activities', 'priority' => '0.7', 'changefreq' => 'monthly',
        'title' => 'Things to Do in Devprayag | Rafting, Bungee, Sangam',
        'description' => 'Plan your Devprayag trip: sangam darshan, Raghunathji Temple, Chandrabadani, Dasrathshila, bungee jumping and Ganga river rafting — all near The Sangam House.',
        'keywords' => 'things to do in devprayag, devprayag sangam, devprayag rafting, devprayag bungee jumping, devprayag weekend getaway, raghunath temple devprayag',
        'image' => '/img/advanchure.jpg', 'breadcrumb' => 'Activities',
    ],
    'contact' => [
        'file' => 'contact', 'type' => 'static', 'ptype' => 'ContactPage', 'preload' => '/img/room5.jpg', 'nav' => 'contact', 'priority' => '0.8', 'changefreq' => 'yearly',
        'title' => 'Contact & Book Your Stay | The Sangam House, Devprayag',
        'description' => 'Call +91 98898 82696, WhatsApp or send an enquiry to book The Sangam House, Devprayag. Get quick replies on rooms, dormitory, dates and best direct rates.',
        'keywords' => 'devprayag hotel booking, devprayag resort booking, contact The Sangam House, hotel enquiry devprayag',
        'image' => '/img/about.jpg', 'breadcrumb' => 'Contact',
    ],

    // ---- SEO landing pages (content in includes/landing.php) ----
    'hotels-in-devprayag' => [
        'type' => 'landing', 'nav' => 'accommodation', 'priority' => '0.9', 'changefreq' => 'monthly',
        'title' => 'Best Hotels in Devprayag | Hotel Booking – Sangam House',
        'description' => 'Looking for hotels in Devprayag? The Sangam House offers comfortable AC rooms beside the sangam, free parking, veg food and direct booking at the best rate.',
        'keywords' => 'hotels in devprayag, hotel in devprayag, best hotel in devprayag, accommodation in devprayag, devprayag in hotel booking',
        'image' => '/img/slider-1.jpg', 'breadcrumb' => 'Hotels in Devprayag',
    ],
    'resorts-in-devprayag' => [
        'type' => 'landing', 'nav' => 'accommodation', 'priority' => '0.9', 'changefreq' => 'monthly',
        'title' => 'Best Resort in Devprayag | Luxury Stay & Booking',
        'description' => 'Resort-style luxury stay in Devprayag: king-size rooms, Himalayan views, multi-cuisine restaurant and sangam darshan help. Book direct at The Sangam House.',
        'keywords' => 'resort in devprayag, resorts in devprayag, best resort in devprayag, luxury stay in devprayag, devprayag resort booking',
        'image' => '/img/slider-2.jpg', 'breadcrumb' => 'Resorts in Devprayag',
    ],
    'stay-near-devprayag-sangam' => [
        'type' => 'landing', 'nav' => 'accommodation', 'priority' => '0.9', 'changefreq' => 'monthly',
        'title' => 'Hotel & Stay Near Devprayag Sangam | Sangam House',
        'description' => 'Stay steps from the Devprayag sangam where Alaknanda meets Bhagirathi. Rooms and dormitory with parking, veg food and darshan guidance at The Sangam House.',
        'keywords' => 'hotel near devprayag sangam, stay near devprayag sangam, places to stay in devprayag, devprayag sangam hotel',
        'image' => '/img/davparyag.jpg', 'breadcrumb' => 'Stay Near Devprayag Sangam',
    ],
    'devprayag-weekend-getaway' => [
        'type' => 'landing', 'nav' => 'activities', 'priority' => '0.9', 'changefreq' => 'monthly',
        'title' => 'Devprayag Weekend Getaway from Rishikesh | Hotel Near',
        'description' => 'Plan a Devprayag weekend getaway from Rishikesh, Haridwar or Delhi. Hotel near Rishikesh–Devprayag route with rafting, temples and a riverside sangam stay.',
        'keywords' => 'devprayag weekend gateway, devprayag weekend getaway, hotel near rishikesh devprayag, weekend trip from rishikesh, devprayag trip',
        'image' => '/img/riverrafting.jpg', 'breadcrumb' => 'Devprayag Weekend Getaway',
    ],
    'blog' => [
        'file' => 'blog', 'type' => 'blog', 'ptype' => 'CollectionPage', 'nav' => 'blog', 'priority' => '0.8', 'changefreq' => 'daily',
        'title' => 'Devprayag Travel Guide & Blog | The Sangam House',
        'description' => 'Plan your Devprayag trip: sangam darshan, temples, rafting, weekend getaways from Rishikesh and stay tips — written by the team at The Sangam House.',
        'keywords' => 'devprayag travel blog, devprayag travel guide, places to visit in devprayag, devprayag weekend getaway, things to do in devprayag, devprayag tourism',
        'image' => '/img/davparyag.jpg', 'breadcrumb' => 'Blog',
    ],
    'privacy-policy' => [
        'file' => 'privacy-policy', 'type' => 'static', 'nav' => '', 'priority' => '0.3', 'changefreq' => 'yearly',
        'title' => 'Privacy Policy | The Sangam House, Devprayag',
        'description' => 'How The Sangam House collects, uses and protects the information you share when you send an enquiry on this website.',
        'keywords' => '', 'image' => '/img/logo.png', 'breadcrumb' => 'Privacy Policy',
    ],
    'terms-of-service' => [
        'file' => 'terms-of-service', 'type' => 'static', 'nav' => '', 'priority' => '0.3', 'changefreq' => 'yearly',
        'title' => 'Terms of Service | The Sangam House, Devprayag',
        'description' => 'Terms of use for The Sangam House website and bookings in Devprayag: reservations, check-in and check-out, guest conduct, pricing and liability.',
        'keywords' => '', 'image' => '/img/logo.png', 'breadcrumb' => 'Terms of Service',
    ],
    'cancellation-policy' => [
        'file' => 'cancellation-policy', 'type' => 'static', 'nav' => '', 'priority' => '0.3', 'changefreq' => 'yearly',
        'title' => 'Cancellation & Refund Policy | The Sangam House',
        'description' => 'How cancellations, date changes, no-shows and refunds work for bookings at The Sangam House, Devprayag. Contact us for dates and group bookings.',
        'keywords' => '', 'image' => '/img/logo.png', 'breadcrumb' => 'Cancellation Policy',
    ],
];
