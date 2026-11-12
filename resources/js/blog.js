import './jquery';

/* JavaScript for the blog page: category filter and post popup */
$(function () {
  $('.chip').on('click', function () {
    var filter = $(this).data('filter');
    $('.chip').removeClass('on');
    $(this).addClass('on');
    $('.post').each(function () {
      var show = filter === 'all' || $(this).data('cat') === filter;
      $(this).toggle(show);
      if (show) $(this).addClass('in');
    });
  });

  function openPost($card) {
    $('#pMeta').text($card.data('meta'));
    $('#pTitle').text($card.find('h4').text());
    $('#pBody').html($card.find('template.post-body').html());
    $('#postModal').addClass('open');
    $('body').css('overflow', 'hidden');
  }

  function closePost() {
    $('#postModal').removeClass('open');
    $('body').css('overflow', '');
  }

  $('.blog-card').on('click keydown', function (e) {
    if (e.type === 'keydown' && e.key !== 'Enter') return;
    openPost($(this));
  });
  $('#postModal').on('click', function (e) {
    if (e.target === this || $(e.target).hasClass('close')) closePost();
  });
  $(document).on('keydown', function (e) {
    if (e.key === 'Escape') closePost();
  });
});
