import './jquery';

/* Builds the table of contents from the document headings and highlights the section being read */
$(function () {
  var $h = $('#doc h3[id]');
  $h.each(function () { $('#toc').append($('<a>').attr('href', '#' + this.id).text($(this).text())); });
  $('#toc a').on('click', function (e) {
    e.preventDefault();
    $('html, body').animate({ scrollTop: $($(this).attr('href')).offset().top - 80 }, 400);
  });
  function spy() {
    var y = $(window).scrollTop() + 120, cur = null;
    $h.each(function () { if ($(this).offset().top <= y) cur = this.id; });
    $('#toc a').removeClass('on').filter('[href="#' + cur + '"]').addClass('on');
  }
  $(window).on('scroll', spy); spy();
});
