/* ---------- About Page Swiper Slider Initialization ---------- */
const aboutSwiper = new Swiper('.aboutSwiper', {
    loop: true,
    speed: 800,
    grabCursor: true,
    autoplay: {
        delay: 4000,
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
});
