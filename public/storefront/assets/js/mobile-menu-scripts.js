(function ($) {
  "use strict";

  // mobile menu 
  var mtMenuWrap = $('.mt-mobile-menu-active > ul').clone();
  var mtSideMenu = $('.mt-offcanvas-menu nav');
  mtSideMenu.append(mtMenuWrap);
  if ($(mtSideMenu).find('.sub-menu, .mt-mega-menu').length != 0) {
    $(mtSideMenu).find('.sub-menu, .mt-mega-menu').parent().append('<button class="mt-menu-close"><i class="fas fa-chevron-right"></i></button>');
  }

  var sideMenuList = $('.mt-offcanvas-menu nav > ul > li button.mt-menu-close, .mt-offcanvas-menu nav > ul li.has-dropdown > a');
  $(sideMenuList).on('click', function (e) {
    console.log(e);
    e.preventDefault();
    if (!($(this).parent().hasClass('active'))) {
      $(this).parent().addClass('active');
      $(this).siblings('.sub-menu, .mt-mega-menu').slideDown();
    } else {
      $(this).siblings('.sub-menu, .mt-mega-menu').slideUp();
      $(this).parent().removeClass('active');
    }
  });


})(jQuery);