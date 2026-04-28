$(document).ready(function() {
    // Newsletter AJAX subscription for both forms
    ajaxMailChimpForm($("#subscribe-form"), $("#subscribe-result"));
    ajaxMailChimpForm($("#subscribe-form-2"), $("#subscribe--result"));

    // Handles MailChimp AJAX form submission
    function submitSubscribeForm($form, $resultElement) {
        $.ajax({
            type: "GET",
            url: $form.attr("action"),
            data: $form.serialize(),
            cache: false,
            dataType: "jsonp",
            jsonp: "c",
            contentType: "application/json; charset=utf-8",
            error: function(error) {
                // Optionally log error
            },
            success: function(data) {
                if (data.result != "success") {
                    var message = data.msg || "⚠️ Sorry. Unable to subscribe. Please try again later.";
                    $resultElement.css("color", "white");
                    if (data.msg && data.msg.indexOf("already subscribed") >= 0) {
                        message = "You're already subscribed!✌🏻";
                        $resultElement.css("color", "white");
                    }
                    $resultElement.html(message);
                } else {
                    $resultElement.css("color", "white");
                    $resultElement.html("Thanks for subscribing!✌🏻");
                }
            }
        });
    }

    // Bind AJAX MailChimp form
    function ajaxMailChimpForm($form, $resultElement) {
        $form.submit(function(e) {
            e.preventDefault();
            if (!isValidEmail($form)) {
                var error = "⚠️ A valid email address must be provided.";
                $resultElement.html(error);
                $resultElement.css("color", "white");
            } else {
                $resultElement.css("color", "white");
                $resultElement.html("Subscribing...");
                submitSubscribeForm($form, $resultElement);
            }
        });
    }

    // Simple email validation
    function isValidEmail($form) {
        var email = $form.find("input[type='email']").val();
        if (!email || !email.length) {
            return false;
        } else if (email.indexOf("@") == -1) {
            return false;
        }
        return true;
    }

    // Like button AJAX logic
    $(document).ready(function() {
        $('.like__btn').on('click', function() {
            var $btn = $(this);
            $.ajax({
                url: bloginfo.site_url + '/wp-json/social/v2/likes/' + postid,
                type: 'POST',
                beforeSend: function (xhr) {
                    if (bloginfo.nonce) {
                        xhr.setRequestHeader('X-WP-Nonce', bloginfo.nonce);
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

document.querySelectorAll('a[href^="#"]').forEach(anchor => {
  anchor.addEventListener('click', function (e) {
    const target = document.querySelector(this.getAttribute('href'));
    if (target) {
      e.preventDefault();
      target.scrollIntoView({ behavior: 'smooth' });
    }
  });
});