/***************************************************
==================== JS INDEX ======================
****************************************************
01. PreLoader Js
02. Mobile Menu Js
03. Common Js
04. Menu Controls JS
05. Offcanvas Js
06. Search Js
07. cartmini Js
08. filter
09. Body overlay Js
10. Sticky Header Js
11. Theme Settings Js
12. Nice Select Js
13. Smooth Scroll Js
14. Slider Activation Area Start
15. Masonary Js
16. Wow Js
17. Counter Js
18. InHover Active Js
19. Line Animation Js
20. Video Play Js
21. Password Toggle Js
****************************************************/

(function ($) {
  "use strict";

  var windowOn = $(window);

  windowOn.on('load', function () {
    wowAnimation();
  });


  ////////////////////////////////////////////////////
  // 1. Preloader Js
  windowOn.on('load', function () {
    $("#loading").fadeOut(500);
  })


  ////////////////////////////////////////////////////
  // 2. Back-to-top Js
  var btn = $('#back-to-top');
  windowOn.scroll(function () {
    if (windowOn.scrollTop() > 300) {
      btn.addClass('show');
    } else {
      btn.removeClass('show');
    }
  });
  btn.on('click', function () {
    $('html, body').animate({ scrollTop: 0 }, '300');
  });


  ////////////////////////////////////////////////////
  // 3. Sticky Js
  windowOn.on('scroll', function () {
    var scroll = windowOn.scrollTop();
    if (scroll < 100) {
      $("#mt-header-sticky").removeClass("header-sticky");
    } else {
      $("#mt-header-sticky").addClass("header-sticky");
    }
  });


  ////////////////////////////////////////////////////
  // 4. Offcanvas bar  Js
  $(".mt-offcanvas-toogle").on('click', function () {
    $(".mt-offcanvas").addClass("mt-offcanvas-open");
    $(".mt-offcanvas-overlay").addClass("mt-offcanvas-overlay-open");
  });
  $(".mt-offcanvas-close-toggle,.mt-offcanvas-overlay").on('click', function () {
    $(".mt-offcanvas").removeClass("mt-offcanvas-open");
    $(".mt-offcanvas-overlay").removeClass("mt-offcanvas-overlay-open");
  });


  ////////////////////////////////////////////////////
  // 5. Mini Cart Js
  $('.mtcartmini-open-btn').on('click', function () {
    $('.mtcartmini__area').addClass('mtcartmini-opened');
    $('.mt-offcanvas-overlay').addClass('mt-offcanvas-overlay-open');
  });
  $('.mtcartmini-close-btn,.mt-offcanvas-overlay').on('click', function () {
    $('.mtcartmini__area').removeClass('mtcartmini-opened');
    $('.mt-offcanvas-overlay').removeClass('mt-offcanvas-overlay-open');
  });


  ////////////////////////////////////////////////////
  // 6. Show Category Toggle Js
  $('.mtheader__bottom-bar-wrap').on('click', function () {
    $('#mtheader__bottom-category-list').slideToggle(400);
  });


  ////////////////////////////////////////////////////
  // 7. Show Category Toggle offcanvas Js
  $('.offcanvas-cate').on('click', function () {
    $('#mtheader__bottom-category-offcanvas').slideToggle(400);
  });


  // data bg img 
  $("[data-background]").each(function () {
    $(this).css("background-image", "url(" + $(this).attr("data-background") + ")")
  });

  // data bg color
  $("[data-bg-color]").each(function () {
    $(this).css("background-color", $(this).attr("data-bg-color"))
  });

  // data bg color
  $("[data-color]").each(function () {
    $(this).css("color", $(this).attr("data-color"))
  });


  ////////////////////////////////////////////////////
  // 1. Swiper mt-hero-active slider 
  var swiper = new Swiper(".mt-hero-active", {
    slidesPerView: 1,
    loop: true,
    effect: "fade",
    autoplay: {
      delay: 3000,
    },
    keyboard: {
      enabled: true,
    },
    pagination: {
      el: ".mthero-pagination",
      clickable: true,
    },
  });


  ////////////////////////////////////////////////////
  // 2. Swiper mt-hero-2-active slider 
  var swiper = new Swiper(".mt-hero-2-active", {
    slidesPerView: 1,
    loop: true,
    // effect: "fade",
    autoplay: {
      delay: 3000,
    },
    keyboard: {
      enabled: true,
    },
    pagination: {
      el: ".mthero-2-pagination",
      clickable: true,
    },
  });


  ////////////////////////////////////////////////////
  // 3. Swiper mtshop__category_active slider 
  var swiper = new Swiper(".mtshop__category_active", {
    slidesPerView: 10,
    spaceBetween: 25,
    breakpoints: {
      1781: {
        slidesPerView: 10,
      },
      1780: {
        slidesPerView: 7,
      },
      1600: {
        slidesPerView: 6,
      },
      1300: {
        slidesPerView: 6,
      },
      991: {
        slidesPerView: 5,
      },
      888: {
        slidesPerView: 4,
      },
      767: {
        slidesPerView: 4,
      },
      575: {
        slidesPerView: 3,
      },
      439: {
        slidesPerView: 2,
      },
      320: {
        slidesPerView: 2,
      },
    },
    navigation: {
      nextEl: ".mtshop__category-arrow-left",
      prevEl: ".mtshop__category-arrow-right",
    },
  });



  ////////////////////////////////////////////////////
  // 4. Swiper mtshop__category_2_active slider 
  var swiper = new Swiper(".mtshop__category_2_active", {
    slidesPerView: 10,
    spaceBetween: 20,
    breakpoints: {
      1200: {
        slidesPerView: 5,
      },
      991: {
        slidesPerView: 4,
      },
      767: {
        slidesPerView: 2,
      },
      0: {
        slidesPerView: 1,
      },
    },
    navigation: {
      nextEl: ".mtshop__category-arrow-left",
      prevEl: ".mtshop__category-arrow-right",
    },
  });



  ////////////////////////////////////////////////////
  // 5. Swiper mtfeature_product_active slider 
  var swiper = new Swiper(".mtfeature_product_active", {
    slidesPerView: 2,
    spaceBetween: 16,
    breakpoints: {
      550: {
        slidesPerView: 2,
      },
      0: {
        slidesPerView: 1,
      },
    },
    navigation: {
      nextEl: ".mtfeature__product-arrow-left",
      prevEl: ".mtfeature__product-arrow-right",
    },
  });



  ////////////////////////////////////////////////////
  // 6. Swiper mtflash_product_active slider 
  var swiper = new Swiper(".mtflash_product_active", {
    slidesPerView: 7,
    spaceBetween: 25,
    breakpoints: {
      1499: {
        slidesPerView: 6,
      },
      1299: {
        slidesPerView: 5,
      },
      992: {
        slidesPerView: 4,
      },
      767: {
        slidesPerView: 2,
      },
      550: {
        slidesPerView: 2,
      },
      0: {
        slidesPerView: 1,
      }
    },
    navigation: {
      nextEl: ".mtfeature__product-arrow-left",
      prevEl: ".mtfeature__product-arrow-right",
    },
  });



  ////////////////////////////////////////////////////
  // 7. Swiper mttop_product_active slider 
  var swiper = new Swiper(".mttop_product_active", {
    slidesPerView: 4,
    spaceBetween: 16,
    breakpoints: {
      1200: {
        slidesPerView: 4,
      },
      992: {
        slidesPerView: 3,
      },
      768: {
        slidesPerView: 2,
      },
      550: {
        slidesPerView: 2,
      },
      0: {
        slidesPerView: 1,
      },
    },
    navigation: {
      nextEl: ".mttop__product-slider-left",
      prevEl: ".mttop__product-slider-right",
    },
  });


  ////////////////////////////////////////////////////
  // 8. Swiper mtpopular_product_2_active slider 
  var swiper = new Swiper(".mtpopular_product_2_active", {
    slidesPerView: 4,
    spaceBetween: 16,
    breakpoints: {
      1200: {
        slidesPerView: 5,
      },
      992: {
        slidesPerView: 3,
      },
      768: {
        slidesPerView: 2,
      },
      550: {
        slidesPerView: 2,
      },
      0: {
        slidesPerView: 1,
      },
    },
    navigation: {
      nextEl: ".mtpopular__product-slider-left1",
      prevEl: ".mtpopular__product-slider-right1",
    },
  });



  ////////////////////////////////////////////////////
  // 9. Swiper mthot__product_active_2 slider 
  var swiper = new Swiper(".mthot__product_active_2", {
    slidesPerView: 4,
    spaceBetween: 16,
    breakpoints: {
      1200: {
        slidesPerView: 5,
      },
      992: {
        slidesPerView: 3,
      },
      768: {
        slidesPerView: 2,
      },
      0: {
        slidesPerView: 1,
      },
    },
    navigation: {
      nextEl: ".mthot__product-slider-left1",
      prevEl: ".mthot__product-slider-right1",
    },
  });




  ////////////////////////////////////////////////////
  // 10. Swiper mtweek__product_active slider 
  var swiper = new Swiper(".mtweek__product_active", {
    slidesPerView: 4,
    spaceBetween: 15,
    breakpoints: {
      1600: {
        slidesPerView: 4,
      },
      1400: {
        slidesPerView: 4,
      },
      1200: {
        slidesPerView: 3,
      },
      992: {
        slidesPerView: 3,
      },
      768: {
        slidesPerView: 2,
      },
      0: {
        slidesPerView: 1,
      },
    },
    navigation: {
      nextEl: ".mtfeature__product-arrow-left",
      prevEl: ".mtfeature__product-arrow-right",
    },
  });




  ////////////////////////////////////////////////////
  // 11. Swiper mtflashsale_product_active slider 
  var swiper = new Swiper(".mtflashsale_product_active", {
    slidesPerView: 4,
    spaceBetween: 16,
    breakpoints: {
      1200: {
        slidesPerView: 4,
      },
      992: {
        slidesPerView: 3,
      },
      768: {
        slidesPerView: 2,
      },
      550: {
        slidesPerView: 2,
      },
      0: {
        slidesPerView: 1,
      },
    },
    navigation: {
      nextEl: ".mtflashsale__product-slider-left",
      prevEl: ".mtflashsale__product-slider-right",
    },
  });




  ////////////////////////////////////////////////////
  // 12. Swiper mt-testimonial-active slider 
  var swiper = new Swiper(".mt-testimonial-active", {
    slidesPerView: 1,
    loop: true,
    navigation: {
      nextEl: ".mttestimonial__arrow-next",
      prevEl: ".mttestimonial__arrow-prev",
    },
  });




  ////////////////////////////////////////////////////
  // 13. Swiper mt-testimonial-active-2 slider 
  var swiper = new Swiper(".mt-testimonial-active-2", {
    slidesPerView: 3,
    spaceBetween: 25,
    loop: true,
    breakpoints: {
      1200: {
        slidesPerView: 3,
      },
      992: {
        slidesPerView: 2,
      },
      768: {
        slidesPerView: 2,
      },
      0: {
        slidesPerView: 1,
      },
    },
    navigation: {
      nextEl: ".mttestimonial__arrow-next",
      prevEl: ".mttestimonial__arrow-prev",
    },
  });




  ////////////////////////////////////////////////////
  // 14. Swiper mt-blog-slider-active slider 
  var swiper = new Swiper(".mt-blog-slider-active", {
    slidesPerView: 4,
    spaceBetween: 25,
    loop: true,
    breakpoints: {
      1200: {
        slidesPerView: 4,
      },
      992: {
        slidesPerView: 3,
      },
      768: {
        slidesPerView: 2,
      },
      550: {
        slidesPerView: 2,
      },
      0: {
        slidesPerView: 1,
      },
    },
    navigation: {
      nextEl: ".mtblog__arrow-next",
      prevEl: ".mtblog__arrow-prev",
    },
  });



  ////////////////////////////////////////////////////
  // 15. Swiper mt-postbox-active slider 
  var swiper = new Swiper(".mt-postbox-active", {
    slidesPerView: 1,
    loop: true,
    navigation: {
      nextEl: ".mt-swiper-blog-button-next",
      prevEl: ".mt-swiper-blog-button-prev",
    },
  });




  ////////////////////////////////////////////////////
  // 16. Swiper mt-brand-title-active slider 
  var swiper = new Swiper(".mt-brand-title-active", {
    slidesPerView: 'auto',
    spaceBetween: 20,
    freemode: true,
    centeredSlides: true,
    loop: true,
    speed: 9000,
    allowTouchMove: false,
    autoplay: {
      delay: 1,
      disableOnInteraction: true,
    },
  });




  ////////////////////////////////////////////////////
  // 17. Swiper mtteam_active slider 
  var swiper = new Swiper(".mtteam_active", {
    slidesPerView: 4,
    spaceBetween: 16,
    breakpoints: {
      1200: {
        slidesPerView: 4,
      },
      992: {
        slidesPerView: 3,
      },
      768: {
        slidesPerView: 2,
      },
      575: {
        slidesPerView: 2,
      },
      0: {
        slidesPerView: 1,
      },
    },
    pagination: {
      el: ".mtteam__pagination",
      clickable: true,
    },
    navigation: {
      nextEl: ".mtteam__arrow-next",
      prevEl: ".mtteam__arrow-prev",
    },
  });




  // Quantity 
  function mt_ecommerce() {
    $('.mt-cart-minus').on('click', function () {
      var $input = $(this).parent().find('input');
      var count = parseInt($input.val()) - 1;
      count = count < 1 ? 1 : count;
      $input.val(count);
      $input.change();
      return false;
    });

    $('.mt-cart-plus').on('click', function () {
      var $input = $(this).parent().find('input');
      $input.val(parseInt($input.val()) + 1);
      $input.change();
      return false;
    });

    $("#slider-range").slider({
      range: true,
      min: 0,
      max: 500,
      values: [75, 300],
      slide: function (event, ui) {
        $("#amount").val("$" + ui.values[0] + " - $" + ui.values[1]);
      }
    });
    $("#amount").val("$" + $("#slider-range").slider("values", 0) +
      " - $" + $("#slider-range").slider("values", 1));



    $('.mt-checkout-payment-item label').on('click', function () {
      $(this).siblings('.mt-checkout-payment-desc').slideToggle(400);

    });

    ////////////////////////////////////////////////////
    // 18. Create An Account Toggle Js
    $('#cbox').on('click', function () {
      $('#cbox_info').slideToggle(900);
    });

    ////////////////////////////////////////////////////
    // 19. Shipping Box Toggle Js
    $('#ship-box').on('click', function () {
      $('#ship-box-info').slideToggle(1000);
    });
  }
  mt_ecommerce();



  ////////////////////////////////////////////////////
  // 24. Wow Js
  /* magnificPopup img view */
  $('.popup-image').magnificPopup({
    type: 'image'
  });

  /* magnificPopup video view */
  $('.popup-video').magnificPopup({
    type: 'iframe'
  });




  ////////////////////////////////////////////////////
  // 20. Wow Js
  function wowAnimation() {
    var wow = new WOW({
      boxClass: 'wow',
      animateClass: 'animated',
      offset: 0,
      mobile: false,
      live: true
    });
    wow.init();
  }



  ////////////////////////////////////////////////////
  // 21. jarallax Js
  if ($('.jarallax').length) {
    $('.jarallax').jarallax({
      speed: 0.2,
    });
  }


  ////////////////////////////////////////////////////
  // 22. jarallax Js
  $('select').niceSelect();



  ////////////////////////////////////////////////////
  // 23. Counter Js
  if ($('.PureCounter').length) {
    new PureCounter();
    new PureCounter({
      filesizing: true,
      selector: ".filesizecount",
      pulse: 2,
    });
  }



})(jQuery);