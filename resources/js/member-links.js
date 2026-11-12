import './jquery';

/* Shorten form: post with AJAX, then show the short link with a copy button or the first error */
$(function () {
  var $form = $('#shorten');

  if (!$form.length) {
    return;
  }

  var $result = $('.add-link-result');
  var $submit = $form.find('.btn-submit');

  function showError(message) {
    $result.empty().append($('<div class="alert alert-danger"></div>').text(message));
  }

  function showSuccess(shortUrl) {
    var $input = $('<input type="text" class="form-control" readonly>').val(shortUrl).on('focus', function () {
      this.select();
    });
    var $copy = $('<button type="button" class="btn btn-default js-copy" title="Copy"><i class="fa fa-clone"></i> Copy</button>').data('copy', shortUrl);
    var $group = $('<div class="input-group"></div>')
      .append($input)
      .append($('<span class="input-group-btn"></span>').append($copy));

    $result.empty().append($('<div class="alert alert-success"></div>').append($group));
  }

  $form.on('submit', function (e) {
    e.preventDefault();
    $submit.prop('disabled', true);

    $.ajax({
      url: $form.attr('action'),
      type: 'POST',
      data: $form.serialize(),
      dataType: 'json',
    }).done(function (response) {
      showSuccess(response.data.short_url);
      $('#url').val('');
      $('#alias').val('');
    }).fail(function (xhr) {
      var message = xhr.responseJSON && xhr.responseJSON.message;

      showError(message || 'Something went wrong. Please try again.');
    }).always(function () {
      $submit.prop('disabled', false);
    });
  });
});
