import './images';
import './jquery';
import './member-links';

/* Member area behaviour: AdminLTE sidebar (push menu and treeview), user dropdown, shorten box and copy buttons */
$(function () {
  var $body = $('body');
  var SCREEN_SM = 768;
  var SLIDE_SPEED = 500;

  $body.removeClass('hold-transition');

  /* Stretch the content area so the dark sidebar background never shows below it */
  function fixLayout() {
    var neg = $('.main-header').outerHeight() + $('.main-footer').outerHeight();
    var windowHeight = $(window).height();
    var sidebarHeight = $('.sidebar').height();

    $('.content-wrapper').css('min-height', windowHeight >= sidebarHeight ? windowHeight - neg : sidebarHeight);
  }

  fixLayout();
  $(window).on('resize', fixLayout);

  /* Push menu: collapse to icons on desktop, slide in and out on mobile */
  $('.sidebar-toggle').on('click', function (e) {
    e.preventDefault();

    if ($(window).width() >= SCREEN_SM) {
      $body.toggleClass('sidebar-collapse');
    } else if ($body.hasClass('sidebar-open')) {
      $body.removeClass('sidebar-open sidebar-collapse');
    } else {
      $body.addClass('sidebar-open');
    }
  });

  $('.content-wrapper').on('click', function () {
    if ($(window).width() < SCREEN_SM && $body.hasClass('sidebar-open')) {
      $body.removeClass('sidebar-open');
    }
  });

  /* Treeview: open one submenu at a time */
  $('.sidebar').on('click', 'li > a', function (e) {
    var $link = $(this);
    var $submenu = $link.next('.treeview-menu');

    if (!$submenu.length) {
      return;
    }

    e.preventDefault();

    var $item = $link.parent('li');

    if ($submenu.is(':visible') && !$body.hasClass('sidebar-collapse')) {
      $submenu.slideUp(SLIDE_SPEED, function () {
        $submenu.removeClass('menu-open');
        fixLayout();
      });
      $item.removeClass('active');

      return;
    }

    var $openSiblings = $item.siblings('.treeview').children('.treeview-menu:visible');
    $openSiblings.slideUp(SLIDE_SPEED).removeClass('menu-open');
    $openSiblings.parent('li').removeClass('active');

    $submenu.slideDown(SLIDE_SPEED, function () {
      $submenu.addClass('menu-open');
      fixLayout();
    });
    $item.addClass('active');
  });

  /* User dropdown in the top bar */
  $(document).on('click', '[data-toggle="dropdown"]', function (e) {
    e.preventDefault();
    e.stopPropagation();

    var $parent = $(this).parent();
    $('.dropdown.open').not($parent).removeClass('open');
    $parent.toggleClass('open');
  });

  $(document).on('click', function (e) {
    if (!$(e.target).closest('.dropdown-menu').length) {
      $('.dropdown.open').removeClass('open');
    }
  });

  /* Sidebar "Shorten a new link" button shows the shorten form */
  $('.shorten-button').on('click', function () {
    $('.box-short').slideToggle(200);
    $body.removeClass('sidebar-open');
    $('#url').trigger('focus');
  });

  /* Copy buttons: <button class="js-copy" data-copy="text"> or data-copy-target="#input" */
  function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(text);
    }

    return new Promise(function (resolve, reject) {
      var $area = $('<textarea>').val(text).css({ position: 'fixed', top: 0, left: 0, opacity: 0 }).appendTo('body');
      $area[0].select();

      try {
        document.execCommand('copy') ? resolve() : reject();
      } catch (err) {
        reject(err);
      } finally {
        $area.remove();
      }
    });
  }

  $(document).on('click', '.js-copy', function (e) {
    e.preventDefault();

    var $button = $(this);
    var target = $button.data('copy-target');
    var text = target ? $(target).val() || $(target).text() : $button.data('copy');

    if (!text) {
      return;
    }

    copyText(String(text)).then(function () {
      var original = $button.data('original-html') || $button.html();
      $button.data('original-html', original).attr('title', 'Copied').html('<i class="fa fa-check"></i> Copied');

      setTimeout(function () {
        $button.html(original).attr('title', 'Copy');
      }, 1500);
    }, function () {
      window.alert('Unable to copy. Please copy it manually.');
    });
  });
});
