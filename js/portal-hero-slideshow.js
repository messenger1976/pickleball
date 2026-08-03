/* Hero background slideshow (home.php) */
(function() {
    'use strict';

    var hero = document.getElementById('portalHero');
    if (!hero) {
        return;
    }

    var slides = hero.querySelectorAll('.portal-hero-slide');
    var dots = hero.querySelectorAll('.portal-hero-dot');
    var total = slides.length;

    if (total <= 1) {
        return;
    }

    var current = 0;
    var timer = null;
    var intervalMs = 5500;
    var prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function goTo(index) {
        if (index === current || index < 0 || index >= total) {
            return;
        }

        slides[current].classList.remove('is-active');
        if (dots[current]) {
            dots[current].classList.remove('is-active');
        }

        current = index;

        slides[current].classList.add('is-active');
        if (dots[current]) {
            dots[current].classList.add('is-active');
        }
    }

    function next() {
        goTo((current + 1) % total);
    }

    function start() {
        stop();
        if (!prefersReducedMotion && !document.hidden) {
            timer = window.setInterval(next, intervalMs);
        }
    }

    function stop() {
        if (timer) {
            window.clearInterval(timer);
            timer = null;
        }
    }

    Array.prototype.forEach.call(dots, function(dot) {
        dot.addEventListener('click', function() {
            var index = parseInt(dot.getAttribute('data-slide'), 10);
            if (!isNaN(index)) {
                goTo(index);
                start();
            }
        });
    });

    // Pause only while interacting with the controls — not the whole hero
    // (full-viewport heroes would never rotate with mouseenter on the section).
    var dotsWrap = hero.querySelector('.portal-hero-dots');
    if (dotsWrap) {
        dotsWrap.addEventListener('mouseenter', stop);
        dotsWrap.addEventListener('mouseleave', start);
    }

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stop();
        } else {
            start();
        }
    });

    start();
})();
