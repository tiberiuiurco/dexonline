var Alphabet = 'a-záàäåăâçèéëìíïĭîòóöșțşţùúüŭ';
var letter = '[' + Alphabet + ']';
var nonLetter = '[^' + Alphabet + ']';
var wwwRoot = getWwwRoot();

/**
 * Shuffles an array in place. Welcome to 1975, where this function is not built in.
 * @param Array a
 */
function shuffle(a) {
  for (var i = a.length - 1; i > 0; i--) {
    var j = Math.floor(Math.random() * (i + 1));
    [ a[i], a[j] ] = [ a[j], a[i] ]; // swap
  }
}

$(function() {
  $('.def').click(searchClickedWord);
  $('#typoModal').on('shown.bs.modal', shownTypoModal);

  $('#searchField').select().focus();
  $('#searchClear').click(function() {
    $('#searchField').val('').focus();
    $(this).hide();
  });
  $('#searchField').on('input', function() {
    if ($(this).val()) {
      // Bootstrap's d-none comes with !important, so it takes precedence over show().
      $('#searchClear').removeClass('d-none').show();
    } else {
      $('#searchClear').hide();
    }
  });

  // Prevent submitting forms twice. Forms can request permission to resubmit
  // by calling removeData('submitted').
  $('form').submit(function(e) {
    if ($(this).data('submitted')) {
      e.preventDefault();
    } else {
      $(this).data('submitted', true);
      return true;
    }
  });

  $('li.disabled a').click(function() {
    return false;
  });

  $('.doubleText').click(function() {
    var tmp = $(this).text();
    $(this).text($(this).attr('data-other-text'));
    $(this).attr('data-other-text', tmp);
  });

  // abbreviation hover
  $('.abbrev[data-bs-toggle="popover"]').each(function() {
    new bootstrap.Popover(this, {
      html: true,
      trigger: 'hover',
    });
  });

});

function formatSource(item) {
  return $('<span>' +
           item.text.replace(/(\(([^)]+)\))/, '<strong>$2</strong>') +
           '</span>');
}

/**
 * config must have the following structure
 *   * url: URL of asyncjs.php
 *   * id: value of data-revive-id in the invocation code
 *   * maxHeight (float, 0...1): how much of the screen height the banner is
       allowed to occupy
 *   * sizes: an array of [ width, height, zoneId ] listing the available
 *     banner sizes (this should match the Revive setup). The widths should be
 *     in decreasing order. We use the first line from the array having:
 *     - "width" < real screen width (JS width x JS device pixel ratio);
 *     - an acceptable height;
 *
 * Once the banner is rendered, we shrink it by the dpr.
 */
function reviveInit(config) {

  var dpr = window.devicePixelRatio,
      w = $(window).width() * dpr,
      h = $(window).height() * dpr;

  var i = 0;
  while ((i < config.sizes.length) &&
         ((config.sizes[i][0] > w) ||
          (config.sizes[i][1] > h * config.maxHeight))) {
    i++;
  }

  if (i == config.sizes.length) {
    return; // cannot accommodate any banner
  }

  var zoneId = config.sizes[i][2];

  // ask to be notified when the image is inserted
  $(document).on('DOMNodeInserted', '.banner-section ins', function() {
    var img = $('.banner-section img').first();
    if (img.length && dpr > 1) {
      img.attr('height', img.attr('height') / dpr);
      img.attr('width', img.attr('width') / dpr);
    }
  });

  $('#revive-container').attr('data-revive-zoneid', zoneId);
  $.getScript(config.url);

}

function getWidth() {
  if (self.innerWidth) {
    return self.innerWidth;
  }

  if (document.documentElement && document.documentElement.clientWidth) {
    return document.documentElement.clientWidth;
  }

  if (document.body) {
    return document.body.clientWidth;
  }
}

function loadAjaxContent(url, elid) {
  $.get(url, function(data) {
    $(elid).html(data);
  });
  return false;
}

function searchSubmit() {
  // Avoid server hit on empty query
  if (!document.frm.cuv.value) {
    return false;
  }

  // Friendly redirect
  action = document.frm.text.checked ? 'text' : 'definitie';
  source = document.frm.source.value;
  sourcePart = source ? '-' + source : '';
  window.location = wwwRoot + action + sourcePart + '/' + encodeURIComponent(document.frm.cuv.value);
  return false;
}

function getWwwRoot() {
  var pos = window.location.href.indexOf('/www/');
  if (pos == -1) {
    return '/';
  } else {
    return window.location.href.substr(0, pos + 5);
  }
}

function shownTypoModal(event) {
  var link = $(event.relatedTarget); // link that triggered the modal
  var defId = link.data('definitionId');
  $('input[name="definitionId"]').val(defId);
  $('#typoTextarea').focus();
  $('#typoSubmit').removeData('submitted'); // allow clicking the button again
}

function submitTypoForm() {
  var text = $('#typoTextarea').val();
  var defId = $('input[name="definitionId"]').val();
  $.post(wwwRoot + 'ajax/typo.php',
         { definitionId: defId, text: text, submit: 1 },
         function() {
           $('#typoModal').modal('hide');
           $('#typoTextarea').val('');
           var confModal = new bootstrap.Modal($('#typoConfModal'));
           confModal.show();
         });
  return false;
}

function toggle(id) {
  $('#' + id).stop().slideToggle();
  return false;
}

function addProvider(url) {
  try {
    window.external.AddSearchProvider(url);
  } catch (e) {
    alert('Aveți nevoie de Firefox 2.0 sau Internet Explorer 7 ' +
          'pentru a adăuga dexonline la lista motoarelor de căutare.');
  }
}

function startsWith(str, sub) {
  return str.substr(0, sub.length) == sub;
}

function endsWith(str, sub) {
  return str.substr(str.length - sub.length) == sub;
}

/* adapted from http://stackoverflow.com/questions/7563169/detect-which-word-has-been-clicked-on-within-a-text */
function searchClickedWord(event) {
  if ($(event.target).is('abbr')) return false;

  // Gets clicked on word (or selected text if text is selected)
  var word = '';
  if (window.getSelection && (sel = window.getSelection()).modify) {
    // Webkit, Gecko
    var s = window.getSelection();
    if (s.isCollapsed) { // Do not redirect when the user is trying to select text
      s.modify('move', 'forward', 'character');
      s.modify('move', 'backward', 'word');
      s.modify('extend', 'forward', 'word');
      word = s.toString();
      s.modify('move', 'forward', 'character'); // clear selection
    }
  } else if ((sel = document.selection) && sel.type != 'Control') {
    // IE 4+
    var textRange = sel.createRange();
    if (!textRange.text) {
      textRange.expand('word');
      while (/\s$/.test(textRange.text)) {
        textRange.moveEnd('character', -1);
      }
      word = textRange.text;
    }
  }

  // Trim trailing dots
  var regex = new RegExp(nonLetter + '$', 'i');
  while (word && regex.test(word)) {
    word = word.substr(0, word.length - 1);
  }

  var source = $('#source-field-hidden').length
      ? $('#source-field-hidden').val()
      : '';
  if (source) {
    source = '-' + source;
  }

  if (word) {
    window.location = wwwRoot + 'definitie' + source + '/' + encodeURIComponent(word);
  }
}

function installFirefoxSpellChecker(evt) {
  var params = {
    'ortoDEX': { URL: evt.target.href,
                 toString : function() { return this.URL; }
    }
  }
  InstallTrigger.install(params);
  return false;
}

$(function() {
  $('.mention').hover(mentionHoverIn, mentionHoverOut);

  function mentionHoverIn() {
    var elem = $(this);

    if (elem.data('loaded')) {
      $(this).popover('show');
    } else {
      var meaningId = elem.attr('title');
      $.getJSON(wwwRoot + 'ajax/getMeaningById', { id: meaningId })
        .done(function(resp) {
          elem.removeAttr('title');
          elem.data('loaded', 1);
          var p = new bootstrap.Popover(elem, {
            content: resp.html,
            html: true,
            title: resp.description + ' (' + resp.breadcrumb + ')',
          });
          p.show();
        });
    }
  }

  function mentionHoverOut() {
    $(this).popover('hide');
  }
});

/****************** „Read more” link for long sections ******************/

$(function() {
  const BTN_HTML =
        '<button class="read-more-btn btn btn-sm">' +
        '<span class="material-icons">expand_more</span>' +
        _('expand') +
        '</btn>';

  $('.read-more').each(function() {
    var realHeight = $(this).prop('scrollHeight');
    var lineHeight = parseInt($(this).css('line-height')); // ignore the 'px' suffix
    var lines = $(this).data('readMoreLines');

    // If the whole thing isn't much larger than the proposed visible area,
    // don't hide anything
    if (realHeight / lineHeight > lines * 1.33) {
      $(this).css('max-height', (lines * lineHeight) + 'px');
      $(this).append(BTN_HTML);
    }
  });

  $(document).on('click', '.read-more-btn', function() {
    var p = $(this).closest('.read-more')
    var realHeight = p.prop('scrollHeight');

    p.animate({ maxHeight: realHeight }, 1000);
    $(this).animate({ opacity: 0 }, 1000);
  });
});

/************************ light/dark mode toggle ************************/

$(function() {
  $('.light-mode-toggle, .dark-mode-toggle').click(function() {
    setColorScheme($(this).data('mode'));
    $(this).hide();
    $(this).siblings().css('display', 'block');
    return false;
  });
});

/***************************** autocomplete *****************************/

/**
 * Note: <datalist>'s are tempting, but they are not aware of diacriticals.
 * Hence, a search for "saptamana" will not by default include the result
 * "săptămână", even if we add the <option> explicitly from Javascript.
 */

$(function() {

  const COMPACT_FORMS_URL = 'https://dexonline.ro/static/download/compact-forms/*.txt';

  var cache = {}; // map of first letter -> expanded forms
  var delayTimer; // delay before processing input
  var dropdown;
  var limit;      // number of results to display
  var minChars;   // when to kick in

  var d = $('#search-autocomplete');
  if (d.length) {
    limit = d.data('limit');
    minChars = d.data('minChars');
    dropdown = new bootstrap.Dropdown($('#searchField')[0]);

    $('#searchField').on('input', onInput);
    $('#searchField').on('show.bs.dropdown', onDropdownShow);
    $('#search-autocomplete').on('click', 'a', onSelect);
  }

  function onInput() {
    clearTimeout(delayTimer);
    delayTimer = setTimeout(doAutocomplete, 100);
  }

  /* prevents showing an empty dropdown */
  function onDropdownShow() {
    return $('#search-autocomplete li').length > 0;
  }

  function onSelect(e) {
    $('#searchField').val($(this).text());
    $('#searchForm').submit();
  }

  /* removes diacriticals, returning an ASCII string */
  function translit(s) {
    return s.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  }

  /* does the opposite of lib/Str.php:compactForms() */
  function expand(data) {
    var lines = data.split('\n');
    var result = [];

    var prev = '';
    for (var i = 0; i < lines.length; i++) {
      var common = lines[i][0] - '0';
      prev = prev.substr(0, common) + lines[i].substr(1);
      result.push(prev);
    }

    return result;
  }

  /**
   * Scans the word list (with or without diacriticals, based on whether the
   * query contains diacriticals). Returns up to @limit matches. Assumes the
   * necessary list is cached.
   */
  function match(term, trans) {
    var result = [];

    var lists = cache[trans[0]];
    var field = (term == trans) ? 1 : 0;
    var i = 0;
    while ((i < lists[0].length) && (result.length < limit)) {
      if (startsWith(lists[field][i].toLowerCase(), term)) {
        result.push(lists[0][i]);
      }
      i++;
    }
    return result;
  }

  /**
   * Builds the dropdown and shows or hides it as needed.
   */
  function output(term, trans) {
    var data = match(term, trans);
    var div = $('#search-autocomplete').empty();

    if (data.length) {
      for (var i = 0; i < data.length; i++) {
        div.append($('<li><a class="dropdown-item" href="#">' +  data[i] + '</a></li>'));
      }

      dropdown.show();
    } else {
      dropdown.hide();
    }
  }

  /**
   * Fetches the necessary word list if needed, then runs the autocomplete.
   */
  function doAutocomplete() {
    var term = $('#searchField').val().toLowerCase().trim();
    if (term.length < minChars) {
      dropdown.hide();
      return;
    }
    var trans = translit(term);
    var first = trans[0];

    if (!first.match(/[a-z]/i)) {
      dropdown.hide();
      return;
    }

    if (first in cache) {
      output(term, trans);
      return;
    }

    var url = COMPACT_FORMS_URL.replace('*', first);
    $.get(url)
      .done(function(data) {
        // cache the original data as well as the transliterated data
        data = expand(data);
        var transData = [];
        for (var i = 0; i < data.length; i++) {
          transData.push(translit(data[i].toLowerCase()));
        }
        cache[first] = [data, transData];
        output(term, trans);
      });
  }

});

/********************** source field in search form **********************/

$(function() {
  $('#source-field a.dropdown-item').click(function(e) {
    // copy the visible HTML
    $(this).closest('.dropdown').find('.dropdown-toggle').html($(this).html());

    // set the hidden field value
    $('#source-field-hidden').val($(this).data('value'));

    // prevent the # from being appended to the URL
    e.preventDefault();
  });

  $('#source-field').on('shown.bs.dropdown', function () {
    // make sure all the menu options are visible
    $('#source-field a.dropdown-item').show();

    // focus the filter
    $('#source-field input').val('').focus();
  });

  $('#source-field').on('hidden.bs.dropdown', function () {
    // go back to the main search field
    $('#searchField').focus();
  });

  $('#source-field input').on('keyup paste', function(e) {
    var isDownArrow = (e.keyCode == 40);

    if (isDownArrow) {
      // highlight the first visible option
      $('#source-field a.dropdown-item:visible').first().focus();
    } else {
      // perform the filtering
      var val = $(this).val().toLowerCase();
      $('#source-field a.dropdown-item').each(function() {
        var visible = $(this).text().toLowerCase().indexOf(val) !== -1;
        $(this).toggle(visible);
      });
    }
  });

});
