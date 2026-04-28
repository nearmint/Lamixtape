// Pass `jQuery` explicitly and alias as `$` inside the closure: stays
// safe regardless of whether `$` is bound globally (jQuery noConflict
// behaves differently depending on whether a CDN jQuery is loaded
// alongside the WP-bundled one).
jQuery(function ($) {
    // Like button AJAX logic
    $(document).ready(function() {
        $('.like__btn').on('click', function() {
            var $btn = $(this);
            $.ajax({
                url: lmtData.site_url + '/wp-json/social/v2/likes/' + postid,
                type: 'POST',
                beforeSend: function (xhr) {
                    if (lmtData.nonce) {
                        xhr.setRequestHeader('X-WP-Nonce', lmtData.nonce);
                    }
                },
                success: function (response) {
                    $btn.attr('disabled', true).addClass('tada');
                    // Server returns the new count; trust it over optimistic UI.
                    $('.like__number').html(response);
                },
                error: function (xhr) {
                    if (xhr.status === 429) {
                        // Already liked from this IP within the hour.
                        $btn.attr('disabled', true).attr('title', 'Already liked');
                    } else {
                        console.log('like failed:', xhr.status);
                    }
                }
            });
        });
    });

    // =============================
    // BURGER MENU (Mobile Navigation)
    // =============================
    $(function() {
        var $burgerBtn = $('#burger-btn');
        var $mobileMenu = $('#mobile-menu-overlay');
        var $closeBtn = $('#close-mobile-menu');
        function openMenu() {
            $mobileMenu.fadeIn(200, function() {
                $mobileMenu.css({'pointer-events': 'auto', 'opacity': 1});
            });
            $('body').css('overflow', 'hidden');
        }
        function closeMenu() {
            $mobileMenu.fadeOut(200, function() {
                $mobileMenu.css({'pointer-events': 'none', 'opacity': 0});
            });
            $('body').css('overflow', '');
        }
        $burgerBtn.on('click', function(e) {
            e.preventDefault();
            openMenu();
        });
        $closeBtn.on('click', function(e) {
            e.preventDefault();
            closeMenu();
        });
        // Close on ESC key
        $(document).on('keydown', function(e) {
            if ($mobileMenu.is(':visible') && e.key === 'Escape') {
                closeMenu();
            }
        });
        // Optional: Close when clicking outside menu
        $mobileMenu.on('click', function(e) {
            if (e.target === this) closeMenu();
        });
        // Close menu when any link inside the overlay is tapped or clicked (for iOS/Safari reliability)
        $mobileMenu.find('a').on('click touchend', function() {
            closeMenu();
        });
    });

    // =============================
    // MENU OVERLAY ENTRANCE ANIMATIONS
    // =============================
    var $mobileMenu = $('#mobile-menu-overlay');
    function animateMenuItems() {
        $mobileMenu.find('.menu-fade-in').each(function(i, el) {
            setTimeout(function() {
                $(el).addClass('visible');
            }, 100 + i * 100);
        });
    }
    function resetMenuItems() {
        $mobileMenu.find('.menu-fade-in').removeClass('visible');
    }
    // Animate on menu open
    $('#burger-btn').on('click', function() {
        setTimeout(animateMenuItems, 200); // after menu fade in
    });
    // Reset on menu close
    $('#close-mobile-menu').on('click', resetMenuItems);
    $mobileMenu.on('click', function(e) {
        if (e.target === this) resetMenuItems();
    });

    // =============================
    // HOMEPAGE ENTRANCE ANIMATIONS
    // =============================
    $(function() {
        $(".fade-in").each(function(i, el) {
            setTimeout(function() {
                $(el).addClass("visible");
            }, 100 + i * 120);
        });
    });

    // =============================
    // PLAYER SLIDE-UP ANIMATION
    // =============================
    $(function() {
        setTimeout(function() {
            $('#footer-player').addClass('visible');
        }, 300);
    });
});

// Smooth scrolling
// TODO Phase 5: replace placeholder href="#" with <button> for a11y (cf. A11Y-002).
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
  anchor.addEventListener('click', function (e) {
    const href = this.getAttribute('href');
    if (!href || href === '#') return; // skip placeholder links (modal toggles, etc.)
    const target = document.querySelector(href);
    if (target) {
      e.preventDefault();
      target.scrollIntoView({ behavior: 'smooth' });
    }
  });
});