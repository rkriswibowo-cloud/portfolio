(function ($) {

  "use strict";

    // COLOR MODE
    var $colorModeToggles = $('.color-mode-toggle');

    function setColorMode(isDark) {
        $('html').toggleClass('dark-mode', isDark);
        $('body').toggleClass('dark-mode', isDark);
        $('.color-mode-icon').toggleClass('active', isDark);
        $colorModeToggles.attr('aria-pressed', isDark ? 'true' : 'false');

        try {
            localStorage.setItem('color-mode', isDark ? 'dark' : 'light');
        } catch (error) {
            // localStorage can be unavailable in strict browser privacy modes.
        }
    }

    try {
        if (localStorage.getItem('color-mode') === 'dark') {
            setColorMode(true);
        }
    } catch (error) {
        // Keep the default light mode when saved preference cannot be read.
    }

    $colorModeToggles.on('click', function(event) {
        event.preventDefault();
        setColorMode(!$('body').hasClass('dark-mode'));
    });

    $('.color-mode').on('keydown', function(event) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            $(this).trigger('click');
        }
    });

    // HEADER
    if ($.fn.headroom) {
        $(".navbar").headroom();
    }

    // PROJECT CAROUSEL
    if ($.fn.owlCarousel) {
        $('.owl-carousel').owlCarousel({
            items: 1,
            loop: true,
            margin: 10,
            nav: true
        });
    }

    // SMOOTHSCROLL
    $(function() {
      $('.navbar-nav .nav-link, .custom-btn-link').not('[data-toggle="tab"]').on('click', function(event) {
        var href = $(this).attr('href');

        if (!href || href.charAt(0) !== '#' || href === '#') {
          return;
        }

        var targetEl = $(href);
        if (targetEl.length) {
          $('html, body').stop().animate({
              scrollTop: targetEl.offset().top - 49
          }, 1000);
          event.preventDefault();
        }
      });
    });  

    // ACADEMIC TABS NO JUMP
    $('.academic-nav-tabs .nav-link').on('click', function(event) {
      event.preventDefault();
      $(this).tab('show');
    });

    // TOOLTIP
    $('.social-links a').tooltip();

})(jQuery);
