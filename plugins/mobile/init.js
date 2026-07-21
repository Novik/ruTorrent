/*** Configurable Options ***/
plugin.enableAutodetect = true;
plugin.tabletsDetect = true;
plugin.eraseWithDataDefault = false;
plugin.sort = 'name'; /* 'name', 'status', 'size', 'uploaded', 'downloaded', 'done', 'eta', 'ul', 'dl', 'ratio', and with the seedingtime plugin 'addtime', 'seedingtime'. Add preceding negative for descending sort. */
plugin.accentColor = 'primary'; /* Bootstrap theme color for buttons, progress bars and highlights: 'primary' (blue), 'secondary' (gray), 'success' (green), 'danger' (red), 'warning' (yellow), 'info' (cyan) or 'dark' (near-black). */
/*** End Configurable Options ***/


/*** State ***/
plugin.filters = {status: [], label: [], tracker: []};
plugin.labelIds = {'': 0};
plugin.trackerIds = {};
plugin.nextLabelId = 1;
plugin.nextTrackerId = 1;
plugin.trackersCache = null;
plugin.rowsPrev = {};
plugin.labelList = [];
plugin.trackerList = [];
plugin.torrents = null;
plugin.torrent = undefined;
plugin.lastHref = "";
plugin.scrollTop = 0;
plugin.labelInEdit = false;
plugin.eraseWithDataLoaded = false;
plugin.ratioGroupsLoaded = false;
plugin.throttleLoaded = false;
plugin.seedingtimeLoaded = false;
plugin.dataDirLoaded = false;
plugin.files = undefined; /* file tree for the details Files tab */


/*** Static lookup tables ***/
var pageToHash = {
  'torrentsList': '',
  'torrentDetails': 'details',
  'globalSettings': 'settings',
  'torrentSort': 'sort',
  'torrentFilter': 'filter',
  'addTorrent': 'add',
  'confirmTorrentDelete': 'delete',
  'torrentDataDir': 'savepath',
  'getDirList': 'filesystem'
};

var detailsIdToLangId = {
  'status' : 'Status',
  'done' : 'Done',
  'downloaded' : 'Downloaded',
  'size' : 'Size',
  'timeElapsed' : 'Time_el',
  'remaining' : 'Remaining',
  'eta' : 'ETA',
  'ratio' : 'Ratio',
  'downloadSpeed' : 'Down_speed',
  'wasted' : 'Wasted',
  'uploaded' : 'Uploaded',
  'uploadSpeed' : 'Ul_speed',
  'seeds' : 'Seeds',
  'peers' : 'Peers',
  'label' : 'Label',
  'priority' : 'Priority',
  'trackerStatus' : 'Track_status',
  'trackerUrl' : 'Track_URL',
  'created' : 'Created_on',
  'savePath' : 'Save_path',
  'freeDiskSpace' : 'Free_Disk_Space',
  'hash' : 'Hash',
  'comment' : 'Comment'
};

var peersIdToLangId = {
  'country' : 'countryName',
  'address' : 'Address',
  'client' : 'ClientVersion',
  'flags' : 'Flags',
  'done' : 'Done',
  'downloaded' : 'Downloaded',
  'uploaded' : 'Uploaded',
  'dl' : 'DL',
  'ul' : 'UL',
  'peer_dl' : 'PeerDL',
  'peer_downloaded' : 'PeerDownloaded',
};

/*** Small helpers ***/
// Fallback copy of the tracklabels plugin's theWebUI.getTrackerName
// (announce URL to display name), for when tracklabels isn't installed
if(!$type(theWebUI.getTrackerName))
{
  theWebUI.getTrackerName = function(announce)
  {
    var domain = '';
    if(announce)
    {
      var parts = announce.match(/^(?:([^:/?#]+):)?(?:\/\/((?:(([^:@]*):?([^:@]*))?@)?([^:/?#]*)(?::(\d*))?))?((((?:[^?#/]*\/)*)([^?#]*))(?:\?([^#]*))?(?:#(.*))?)/);
      if(parts && (parts.length>6))
      {
        domain = parts[6];
        if(!domain.match(/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/))
        {
          parts = domain.split(".");
          if(parts.length>2)
          {
            if($.inArray(parts[parts.length-2]+"", ["co", "com", "net", "org"])>=0 ||
            $.inArray(parts[parts.length-1]+"", ["uk"])>=0)
            parts = parts.slice(parts.length-3);
            else
            parts = parts.slice(parts.length-2);
            domain = parts.join(".");
          }
        }
      }
    }
    return(domain);
  }
}

// Nudge the scroll position when a form field loses focus, so iOS Safari
// re-lays-out the fixed bars after the on-screen keyboard closes
$(document).on('blur', 'input, select, textarea', function() {
  setTimeout(function() {
    $(window).scrollTop($(window).scrollTop()+1);
  }, 0);
});

// Shallow-compare two row snapshots (see plugin.rowSnapshot); both sides
// always have the same keys
var isEqual = function (a, b) {
  for (var prop in a) {
    if (a[prop] !== b[prop]) {
      return false;
    }
  }
  return true;
};

plugin.toggleDisplay = function(s) {
  if (s.css('display') == 'none') {
    s.css('display', '')
  } else{
    s.css('display', 'none');
  }
};

/*** Page navigation ***/
// Every page is a .mainContainer div in mobile.html; showPage() switches
// between them and mirrors the current page into the URL hash so the
// browser's back button navigates the UI (see backListener)
plugin.backListener = function() {
  if (this.lastHref != window.location.href) {
    if (window.location.hash == '#details') {
      if (this.torrent != undefined) {
        this.showDetails(this.torrent.hash);
      }
    } else if (window.location.hash == '#settings') {
      this.showSettings();
    } else if (window.location.hash == '#add') {
      this.addTorrent();
    } else if (window.location.hash == '#sort') {
      this.showSort();
    } else if (window.location.hash == '#filter') {
      this.showFilter();
    } else if (window.location.hash == '#delete') {
      if (this.torrent != undefined) {
        this.delete();
      }
    } else if (window.location.hash == '#savepath') {
      if (this.torrent != undefined) {
        this.showDataDir();
      }
    } else {
      this.showList();
    }
  }
};

plugin.request = function(url, func) {
  theWebUI.requestWithTimeout(url, function(d){if (func != undefined) func(d);}, function(){}, function(){});
};

plugin.setHash = function(page) {
  window.location.hash = pageToHash[page];
  this.lastHref = window.location.href;
};

plugin.showAlert = function(message,alerttype) {
  $('#alert_placeholder').append('<div id="alertdiv" class="alert alert-dismissible fade show navbar-fixed-top '+ alerttype +'" role="alert">'+ escapeHTML(message) +'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
  setTimeout(function() {
    $('#alertdiv').removeClass('show');
    setTimeout(function() {
      $("#alertdiv").remove();
    }, 1500);
  }, 5000);
};

plugin.createiFrame = function() {
  $('#addTorrent').prepend('<iframe id="uploadFrame" name="uploadFrame" style="visibility: hidden; width: 0; height: 0; line-height: 0; font-size: 0; border: 0;"></iframe>')
  $('#uploadFrame').on('load', function() {
    var d = (this.contentDocument || this.contentWindow.document);

    if(d && (d.location.href != "about:blank")) {
      var matchedRegex = d.body.innerHTML.match(/noty\(".*"\+(.*),"(.*)"/);
      if (matchedRegex != null) {
        var message = '';
        try {message = eval(matchedRegex[1]);} catch(e) { }
        if (message != '') {
          if(matchedRegex[2] == "success") {
            plugin.showAlert(message,"alert-success");
            // Clear the add forms so the next torrent can be added;
            // on error the inputs are kept so they can be corrected
            $('#url').val('');
            $('#torrent_file').val('');
          } else if (matchedRegex[2] == "error") {
            plugin.showAlert(message,"alert-danger");
          }
        }
      }
    }
    $('#addUrl').prop('disabled', $.trim($('#url').val()) === '');
    $('#torrentFileSend').prop('disabled', $('#torrent_file')[0].files.length === 0);
    $('#uploadFrame').remove();
    plugin.update(true);
  });
};

plugin.showPage = function(page) {
  if (window.location.hash == "" && page != 'torrentsList') {
    this.scrollTop = $(window).scrollTop();
  }
  $('.mainContainer').css('display', 'none');
  $('.torrentControl').css('display', 'none');
  $('#' + page).css('display', '');
  if (page == 'torrentsList') {
    window.scrollTo(0,this.scrollTop);
  }
  else {
    window.scrollTo(0,0);
  }
  this.setHash(page);
};

plugin.showList = function() {
  this.showPage('torrentsList');
};

/*** Filter page ***/
plugin.statusFilterOptions = [
  {value: 'all', langId: 'All', countClass: '.torrentBlock', stateId: 'pstate_all', icon: 'bi-asterisk'},
  {value: 'downloading', langId: 'Downloading', countClass: '.statusDownloading', stateId: '-_-_-dls-_-_-', icon: 'bi-download'},
  {value: 'completed', langId: 'Finished', countClass: '.statusCompleted', stateId: '-_-_-com-_-_-', icon: 'bi-check-lg'},
  {value: 'stopped', langId: 'Stopped', countClass: '.statusStopped', stateId: '-_-_-wfa-_-_-', icon: 'bi-stop-fill'},
  {value: 'active', langId: 'Active', countClass: '.stateActive', stateId: '-_-_-act-_-_-', icon: 'bi-arrow-down-up'},
  {value: 'inactive', langId: 'Inactive', countClass: '.stateInactive', stateId: '-_-_-iac-_-_-', icon: 'bi-pause-fill'},
  {value: 'error', langId: 'Error', countClass: '.errorYes', stateId: '-_-_-err-_-_-', icon: 'bi-exclamation-triangle-fill'}
];

// Read status option text from the desktop state panel instead of theUILang:
// the desktop DOM is translated once at startup, before plugins (e.g. history)
// can override core lang keys like theUILang.Finished.
plugin.statusOptionText = function(opt) {
  try {
    var attribs = theWebUI.categoryList.panelLabelAttribs.pstate.get(opt.stateId);
    if (attribs && attribs.text) {
      return attribs.text;
    }
  } catch (e) {}
  return theUILang[opt.langId];
};

// value -> row-class selector, derived from statusFilterOptions
plugin.statusFilterClasses = {};
$.each(plugin.statusFilterOptions, function(i, opt) {
  if (opt.value != 'all') {
    plugin.statusFilterClasses[opt.value] = opt.countClass;
  }
});

plugin.applyFilters = function() {
  // Drop filters whose label/tracker no longer exists in the torrent list
  // (label/tracker ids are persistent, so check the current lists instead)
  this.filters.label = $.grep(this.filters.label, function(name) {
    return $.grep(plugin.labelList, function(o) {return o.name === name;}).length > 0;
  });
  this.filters.tracker = $.grep(this.filters.tracker, function(name) {
    return $.grep(plugin.trackerList, function(o) {return o.name === name;}).length > 0;
  });

  // Selections within a category are ORed, categories are ANDed
  // (like the desktop panels), so the selector is the cartesian
  // product of the per-category class lists
  var statusSels = this.filters.status.length ?
    $.map(this.filters.status, function(s) {return plugin.statusFilterClasses[s];}) : [''];
  var labelSels = this.filters.label.length ?
    $.map(this.filters.label, function(name) {return '.label' + plugin.labelIds[name];}) : [''];
  var trackerSels = this.filters.tracker.length ?
    $.map(this.filters.tracker, function(name) {return '.tracker' + plugin.trackerIds[name];}) : [''];

  var sels = [];
  $.each(statusSels, function(i, s) {
    $.each(labelSels, function(j, l) {
      $.each(trackerSels, function(k, t) {
        sels.push('.torrentBlock' + s + l + t);
      });
    });
  });
  var sel = sels.join(', ');

  $('.torrentBlock').css('display', 'none');
  $(sel).css('display', '');
  this.updateFilterButton($(sel));
};

plugin.selectionSize = function(elements) {
  var size = 0;
  if (this.torrents) {
    elements.each(function() {
      var t = plugin.torrents[this.id];
      if (t) {
        size += iv(t.size);
      }
    });
  }
  return size;
};

plugin.updateFilterButton = function(matched) {
  var parts = [];
  if (this.filters.status.length) {
    parts.push($.map(this.filters.status, function(s) {
      var opt = $.grep(plugin.statusFilterOptions, function(o) {return o.value == s;})[0];
      return plugin.statusOptionText(opt);
    }).join('+'));
  }
  if (this.filters.label.length) {
    parts.push($.map(this.filters.label, function(name) {
      return (name == '') ? theUILang.No_label : name;
    }).join('+'));
  }
  if (this.filters.tracker.length) {
    parts.push(this.filters.tracker.join('+'));
  }
  var text = parts.length ? parts.join(' · ') : theUILang.All;
  var info = matched.length;
  var size = this.selectionSize(matched);
  if (size > 0) {
    info += ' / ' + theConverter.bytes(size, 'catlist');
  }
  $('#filterText').text(text);
  $('#filterCount').text('(' + info + ')');
};

plugin.setFilter = function(type, value) {
  if (value === null || value === 'all') {
    // The All rows clear their category
    this.filters[type] = [];
  } else {
    // Tapping an option toggles it, like the desktop panels
    var idx = $.inArray(value, this.filters[type]);
    if (idx >= 0) {
      this.filters[type].splice(idx, 1);
    } else {
      this.filters[type].push(value);
    }
  }
  this.applyFilters();
  this.renderFilterPage();
};

plugin.clearFilters = function() {
  this.filters = {status: [], label: [], tracker: []};
  this.applyFilters();
  this.renderFilterPage();
};

plugin.makeFilterItem = function(text, count, isSelected, type, value, icon) {
  var item = $('<a href="javascript://void();" class="list-group-item list-group-item-action"></a>');
  if (isSelected) {
    item.addClass('active');
  }
  item.append($('<span class="badge text-bg-secondary rounded-pill"></span>').text(count));
  if (icon && icon.img) {
    item.append($('<img class="filter-icon" alt=""/>').attr('src', icon.img));
  } else {
    item.append($('<i></i>').addClass('bi ' + icon));
  }
  item.append(document.createTextNode(text));
  item.click(function() {mobile.setFilter(type, value);});
  return item;
};

plugin.renderFilterPage = function() {
  var total = $('.torrentBlock').length;

  var statusList = $('#filterStatusList').empty();
  $.each(this.statusFilterOptions, function(i, opt) {
    var isSelected = (opt.value == 'all') ? !plugin.filters.status.length : ($.inArray(opt.value, plugin.filters.status) >= 0);
    statusList.append(plugin.makeFilterItem(plugin.statusOptionText(opt), $(opt.countClass).length, isSelected, 'status', opt.value, opt.icon));
  });

  // The tracklabels plugin serves the same per-label icons and tracker
  // favicons the desktop panels show
  var trackLabels = thePlugins.get('tracklabels');

  var labelsList = $('#filterLabelsList').empty();
  labelsList.append(plugin.makeFilterItem(theUILang.All, total, !plugin.filters.label.length, 'label', null, 'bi-asterisk'));
  $.each(this.labelList, function(i, l) {
    var icon = (l.name != '' && trackLabels) ? {img: 'plugins/tracklabels/action.php?label=' + encodeURIComponent(l.name)} : 'bi-tag';
    labelsList.append(plugin.makeFilterItem((l.name == '') ? theUILang.No_label : l.name, l.count, $.inArray(l.name, plugin.filters.label) >= 0, 'label', l.name, icon));
  });

  var trackersList = $('#filterTrackersList').empty();
  trackersList.append(plugin.makeFilterItem(theUILang.All, total, !plugin.filters.tracker.length, 'tracker', null, 'bi-asterisk'));
  $.each(this.trackerList, function(i, t) {
    var icon = trackLabels ? {img: 'plugins/tracklabels/action.php?tracker=' + encodeURIComponent(t.name)} : 'bi-globe2';
    trackersList.append(plugin.makeFilterItem(t.name, t.count, $.inArray(t.name, plugin.filters.tracker) >= 0, 'tracker', t.name, icon));
  });
};

plugin.showFilter = function() {
  this.renderFilterPage();
  this.showPage('torrentFilter');
};

/*** Settings page ***/
plugin.showSettings = function() {
  this.request("?action=gettotal", function(total) {
    // 327625 KiB/s is the core's "unlimited" sentinel (see js/webui.js)
    var fillLimitSelect = function(sel, speedList, current) {
      sel.empty();
      $.each(speedList.split(","), function(i, s) {
        var spd = s * 1024;
        sel.append('<option' + (spd == current ? ' selected' : '') + ' value="' + spd + '">' + theConverter.speed(spd) + '</option>');
      });
      sel.append('<option' + ((current <= 0 || current >= 327625*1024) ? ' selected' : '') + ' value="' + 327625*1024 + '">' + theUILang.unlimited + '</option>');
    };
    fillLimitSelect($('#dlLimit'), theWebUI.settings["webui.speedlistdl"], total.rateDL);
    fillLimitSelect($('#ulLimit'), theWebUI.settings["webui.speedlistul"], total.rateUL);

    plugin.loadServerInfo();
    plugin.showPage('globalSettings');
  });
};

plugin.currentAccent = 'primary';

plugin.applyAccentColor = function(color) {
  if (color == 'primary') {
    document.documentElement.style.removeProperty('--mobile-accent');
    document.documentElement.style.removeProperty('--mobile-accent-rgb');
  } else {
    document.documentElement.style.setProperty('--mobile-accent', 'var(--bs-' + color + ')');
    document.documentElement.style.setProperty('--mobile-accent-rgb', 'var(--bs-' + color + '-rgb)');
  }
  if (color != plugin.currentAccent) {
    $('.btn-' + plugin.currentAccent).addClass('btn-' + color).removeClass('btn-' + plugin.currentAccent);
  }
  plugin.currentAccent = color;
};

plugin.renderOpenStatus = function(d) {
  if (d && d.http > -1) {
    $('#openStatus').css('display', '');
    $('#openStatus td:last').text(d.http + ' http | ' + d.sock + ' sock | ' + ((d.fd > -1) ? (d.fd + ' fd') : ''));
  }
};

plugin.accentColors = [
  ['primary', 'Blue'], ['secondary', 'Gray'], ['success', 'Green'], ['danger', 'Red'],
  ['warning', 'Yellow'], ['info', 'Cyan'], ['dark', 'Near-black']
];

// Try-before-you-buy accent switcher; not persisted, a reload reverts
// to the configured plugin.accentColor
plugin.loadAccentSelect = function() {
  var sel = $('#accentSelect').empty();
  $.each(this.accentColors, function(i, c) {
    // The colored swatch shows in the option list where the browser
    // supports styled options (iOS's native picker does not)
    sel.append($('<option></option>').attr('value', c[0])
      .css('color', 'var(--bs-' + c[0] + ')')
      .text('\u25cf ' + c[1] + ' (' + c[0] + ')'));
  });
  sel.val(this.currentAccent);
  sel.css('color', 'var(--bs-' + this.currentAccent + ')');
};

// Server details akin to the desktop status bar
plugin.loadServerInfo = function() {
  this.loadAccentSelect();
  $('#verRutorrent td:last').text('v' + theWebUI.version);
  var rt = theWebUI.systemInfo.rTorrent;
  $('#verRtorrent td:last').text(rt.started ? (rt.version + '/' + rt.libVersion) : '?');

  if (rt.iVersion >= 0x907) {
    // The core engine polls getopen every update cycle into theWebUI.stopen
    // when the open-status setting is on; reuse that data (see processTorrents
    // for the live refresh). Otherwise fetch once for this page view.
    if (theWebUI.settings['webui.show_open_status'] && theWebUI.stopen.http > -1) {
      this.renderOpenStatus(theWebUI.stopen);
    } else {
      this.request('?action=getopen', function(d) {
        plugin.renderOpenStatus(d);
      });
    }
  }

  if (thePlugins.get('check_port')) {
    this.request('?action=initportcheck', function(d) {
      $.each([['ipv4', '#portIpv4'], ['ipv6', '#portIpv6']], function(i, p) {
        var proto = p[0];
        var row = $(p[1]);
        var status = parseInt(d[proto + '_status']);
        if (isNaN(status) || status == -1 || !d[proto] || d[proto] == '-') {
          row.css('display', 'none');
          return;
        }
        row.css('display', '');
        var text = ((proto == 'ipv6') ? ('[' + d[proto] + ']') : d[proto]) + ':' + d[proto + '_port'] + ' ';
        row.find('td:last').text(text).append(
          $('<span></span>')
          .addClass((status == 2) ? 'text-success' : ((status == 1) ? 'text-danger' : 'text-muted'))
          .text(theUILang.portStatus[status]));
      });
    });
  }

  if (thePlugins.get('diskspace')) {
    $.ajax({
      url: 'plugins/diskspace/action.php',
      dataType: 'json',
      cache: false,
      success: function(d) {
        if (d && d.total > 0) {
          var used = d.total - d.free;
          // Truncate like the desktop diskspace plugin (iv() floors),
          // so both UIs show the same percentage
          var pct = Math.min(100, iv(used / d.total * 100));
          $('#diskSpace').css('display', '');
          // Same green-to-red gradient the desktop status bar meter uses
          $('#diskSpaceBar .progress-bar')
            .css('width', pct + '%')
            .css('background-color', (new RGBackground()).setGradient(new RGBackground('#99D699'), new RGBackground('#E69999'), pct).getColor());
          $('#diskSpaceBar .progress-label').css('width', pct + '%').text(pct + '%');
          // Same free-space alarm threshold as the desktop plugin
          var notifyLimit = thePlugins.get('diskspace').notifySpaceLimit;
          if (notifyLimit && d.free < notifyLimit) {
            plugin.showAlert(theUILang.diskNotification, 'alert-danger');
          }
          $('#diskSpaceText').text((theUILang.diskUsage || '%USED%/%TOTAL% (%FREE% free)')
            .replace('%USED%', theConverter.bytes(used, 2))
            .replace('%TOTAL%', theConverter.bytes(d.total, 2))
            .replace('%FREE%', theConverter.bytes(d.free, 2)));
        }
      }
    });
  }
};

/*** Sort page ***/
plugin.showSort = function() {
  $('#sortOption option').prop('selected', false);
  $('#sort_asc').prop('checked', false);
  $('#sort_desc').prop('checked', false);

  var sort = '';
  if(plugin.sort[0] === "-") {
    sort = plugin.sort.substr(1);
    $('#sort_desc').prop('checked', true);
  } else {
    sort = plugin.sort;
    $('#sort_asc').prop('checked', true);
  }

  var sortHtml = '<option value="name">' + theUILang.Name + '</option>' +
              '<option value="status">' + theUILang.Status + '</option>' +
              '<option value="size">' + theUILang.Size + '</option>' +
              '<option value="uploaded">' + theUILang.Uploaded + '</option>' +
              '<option value="downloaded">' + theUILang.Downloaded + '</option>' +
              '<option value="done">' + theUILang.Done + '</option>' +
              '<option value="eta">' + theUILang.ETA + '</option>' +
              '<option value="ul">' + theUILang.Ul_speed + '</option>' +
              '<option value="dl">' + theUILang.Down_speed + '</option>' +
              '<option value="ratio">' + theUILang.Ratio + '</option>';

  if (this.seedingtimeLoaded) {
    sortHtml += '<option value="addtime">' + theUILang.addTime + '</option>' +
                '<option value="seedingtime">' + theUILang.seedingTime + '</option>'
  }
  $('#sortOption').html(sortHtml);
  $('#sortOption option[value=' + sort + ']').prop('selected', true);

  plugin.showPage('torrentSort');
};

plugin.refresh = function() {
  window.location.reload(true);
};

plugin.setDLLimit = function() {
  theWebUI.setDLRate($('#dlLimit').val());
};

plugin.setULLimit = function() {
  theWebUI.setULRate($('#ulLimit').val());
};

plugin.setSort = function() {
  var sort = $('#sortOption').val();
  if($('#sort_desc').prop('checked')) {
    sort = '-' + sort
  }
  plugin.sort = sort;
  plugin.update(true);
  history.go(-1);
};

/*** Add-torrent page ***/
plugin.addTorrent = function() {
  this.returningFromGetDir = false;
  // Mirror the desktop dialog's beforeShow handler (js/content.js)
  var sel = $('#tadd_label_select');
  sel.empty();
  sel.append($('<option selected></option>').text(theUILang.No_label));
  sel.append($('<option></option>').text(theUILang.newLabel));
  for (const [torrentLabel] of theWebUI.categoryList.torrentLabelTree.torrentLabels) {
    sel.append($('<option></option>').text(torrentLabel));
  }
  $('#tadd_label').val('').hide();
  $('#taddReturnSelect').hide();
  sel.show();

  $('#torrent_file').val('');
  $('#torrentFileSend').prop('disabled', true);
  $('#addUrl').prop('disabled', $.trim($('#url').val()) === '');

  $.each(['not_add_path', 'torrents_start_stopped', 'fast_resume', 'randomize_hash'], function(i, id) {
    $('#' + id).prop('checked', !!theWebUI.settings['webui.' + id]);
  });

  this.showPage('addTorrent');
  var used = ($('#dir_edit').outerWidth(true) - $('#dir_edit').width()) + $('#showGetDir').outerWidth(true) + 1;
  $('#dir_edit').width($('#addTorrentFile').outerWidth(true) - used);
};

/*** Torrent details: General tab ***/
plugin.fillLabel = function(label) {
  if (this.labelInEdit) {
    return;
  }

  $('#torrentDetails #label td:last').text(label + ' ').append('<button class="btn btn-outline-secondary btn-sm" type="button" onclick="mobile.editLabel();"><i class="bi bi-pencil-square .icon-black"></i></button>');
};

plugin.fillDetails = function(d) {
  $('#torrentName').text(d.name);

  var percent = d.done / 10.0;
  $('#torrentProgress .progress-bar').removeClass('progress-bar-animated');
  if (d.done != 1000) {
    $('#torrentProgress .progress-bar').addClass('progress-bar-animated');
  }
  $('#torrentProgress .progress-bar').css('width', percent + '%');
  $('#torrentProgress .progress-label').css('width', percent + '%').text(percent + '% ' + theUILang.of + ' ' + theConverter.bytes(d.size,2));

  $('#torrentDetails #status td:last').text(theWebUI.getStatusIcon(d)[1] + ' ').append('<button class="btn btn-outline-secondary btn-sm" type="button" onclick="mobile.recheck();"><i class="bi bi-arrow-clockwise .icon-black"></i></button>');
  $('#torrentPriority option').prop('selected', false);
  $('#torrentPriority option[value=' + d.priority + ']').prop('selected', true);
  if (this.ratioGroupsLoaded) {
    $('#torrentRatioGrp option').prop('selected', false);
    if (d.ratiogroup) {
      $('#torrentRatioGrp option[value=' + d.ratiogroup.replace(/.*rat_/,'') + ']').prop('selected', true);
    } else {
      $('#torrentRatioGrp option[value=-1]').prop('selected', true);
    }
  }
  if (this.throttleLoaded) {
    $('#torrentChannel option').prop('selected', false);
    if (d.throttle) {
      $('#torrentChannel option[value=' + d.throttle.replace('thr_','') + ']').prop('selected', true);
    } else {
      $('#torrentChannel option[value=-1]').prop('selected', true);
    }
  }
  this.fillLabel(d.label);
  $('#torrentDetails #done td:last').text(percent + '%');
  $('#torrentDetails #downloaded td:last').text(theConverter.bytes(d.downloaded,2));
  $('#torrentDetails #size td:last').text(theConverter.bytes(d.size,2));
  $('#torrentDetails #remaining td:last').text(theConverter.bytes(d.remaining,2));
  $('#torrentDetails #timeElapsed td:last').text(theConverter.time(Math.floor((new Date().getTime()-theWebUI.deltaTime)/1000-iv(d.state_changed)),true));
  $('#torrentDetails #created td:last').text((d.created>3600*24*365) ? theConverter.date(iv(d.created)+theWebUI.deltaTime/1000) : "");
  if (this.seedingtimeLoaded) {
    $('#torrentDetails #seedtime td:last').text((d.seedingtime>3600*24*365) ? theConverter.time(new Date().getTime()/1000-(iv(d.seedingtime)+theWebUI.deltaTime/1000),true) : "");
    $('#torrentDetails #dateAdded td:last').text((d.addtime>3600*24*365) ? theConverter.date(iv(d.addtime)+theWebUI.deltaTime/1000) : "");
  }
  $('#torrentDetails #eta td:last').html((d.eta == -1) ? "&#8734;" : theConverter.time(d.eta));
  $('#torrentDetails #ratio td:last').html((d.ratio == -1) ? "&#8734;" : theConverter.round(d.ratio/1000,3));
  $('#torrentDetails #downloadSpeed td:last').text(theConverter.speed(d.dl));
  $('#torrentDetails #wasted td:last').text(theConverter.bytes(d.skip_total,2));
  $('#torrentDetails #uploaded td:last').text(theConverter.bytes(d.uploaded,2));
  $('#torrentDetails #uploadSpeed td:last').text(theConverter.speed(d.ul));
  $('#torrentDetails #seeds td:last').text(d.seeds_actual + " " + theUILang.of + " " + d.seeds_all + " " + theUILang.connected);
  $('#torrentDetails #peers td:last').text(d.peers_actual + " " + theUILang.of + " " + d.peers_all + " " + theUILang.connected);
  $('#torrentDetails #savePath td:last').text(d.save_path + ' ');
  if (this.dataDirLoaded) {
    $('#torrentDetails #savePath td:last').append('<button class="btn btn-outline-secondary btn-sm" type="button" onclick="mobile.showDataDir();"><i class="bi bi-pencil-square .icon-black"></i></button>');
  }
  $('#torrentDetails #freeDiskSpace td:last').text((d.free_diskspace == '0') ? '' : theConverter.bytes(d.free_diskspace,2));
  $('#torrentDetails #hash td:last').text(d.hash);
  $('#torrentDetails #comment td:last').text(d.comment);
  $('#torrentDetails #trackerUrl td:last').text((this.detailTrackers && this.detailTrackers.hash == d.hash && this.detailTrackers.list.length) ? (this.detailTrackers.list[0].name + ((this.detailTrackers.list.length > 1) ? (' ' + theUILang.of + ' ' + d.tracker_size) : '')) : d.tracker_size);
  $('#torrentDetails #trackerStatus td:last').text(d.msg);
};

plugin.changePriority = function() {
  this.request('?action=dsetprio&v=' + $('#torrentPriority').val() + '&hash=' + this.torrent.hash);
};

plugin.changeRatioGrp = function() {
  this.request('?action=setratio&v=' + $('#torrentRatioGrp').val() + '&hash=' + this.torrent.hash);
};

plugin.changeChannel = function() {
  this.request('?action=setthrottle&v=' + $('#torrentChannel').val() + '&hash=' + this.torrent.hash);
};

plugin.setTorrentLabel = function(newLabel) {
  plugin.labelInEdit = false;
  plugin.fillLabel(newLabel);

  if (plugin.torrent.label != newLabel) {
    plugin.torrent.label = newLabel;
    plugin.torrents[plugin.torrent.hash].label = newLabel;

    plugin.request('?action=setlabel&hash=' + plugin.torrent.hash + '&s=label&v=' + encodeURIComponent(newLabel));
  }
};

// Mirrors the desktop label menu: No Label / New Label... / existing labels
plugin.editLabel = function() {
  plugin.labelInEdit = true;

  var sel = $('<select class="form-select"></select>');
  sel.append($('<option></option>').text(theUILang.No_label));
  sel.append($('<option></option>').text(theUILang.newLabel));
  var currentIndex = 0;
  var index = 2;
  for (const [torrentLabel] of theWebUI.categoryList.torrentLabelTree.torrentLabels) {
    sel.append($('<option></option>').text(torrentLabel));
    if (torrentLabel === plugin.torrent.label) {
      currentIndex = index;
    }
    index++;
  }
  sel.prop('selectedIndex', currentIndex);
  sel.css('max-width', $('#torrentPriority').css('max-width'));

  sel.change(function() {
    if (this.selectedIndex == 1) {
      sel.off('blur');
      plugin.editLabelText();
    } else {
      plugin.setTorrentLabel((this.selectedIndex == 0) ? '' : this.options[this.selectedIndex].value);
    }
  });
  sel.blur(function() {
    // Dismissed without picking anything
    if (plugin.labelInEdit) {
      plugin.setTorrentLabel(plugin.torrent.label);
    }
  });

  $('#torrentDetails #label td:last').empty().append(sel);
  sel.focus();
};

plugin.editLabelText = function() {
  $('#torrentDetails #label td:last')
  .html('<div class="input-append">' +
  '<input class="form-control" id="labelEdit" type="text"/>' +
  '<button class="btn btn-outline-secondary btn-sm"><i class="bi bi-check-lg icon-black"></i></button></div>');
  $('#labelEdit').val(plugin.torrent.label);
  $('#labelEdit').focus();
  $('#labelEdit').blur(function() {
    plugin.setTorrentLabel($('#labelEdit').val());
  });
};

/*** Torrent details: page and tab switching ***/
plugin.showDetails = function(e) {
  this.torrent = this.torrents[e];
  if (this.torrent == undefined)
  return;

  this.torrent.hash = e;
  var d = this.torrent;

  this.fillDetails(d);
  this.loadDetailTrackers();

  this.showPage('torrentDetails');
  setTimeout(function() {
    var totalWidth = $('#torrentDetails').width();
    var combinedWidth = $('#detailsDetailsPage #priority td:nth-child(1)').outerWidth(true);
    var tdExcess = $('#detailsDetailsPage #priority td:nth-child(2)').outerWidth(true) - $('#detailsDetailsPage #priority td:nth-child(2)').width();
    var diffWidth = totalWidth - combinedWidth - tdExcess;
    $('#torrentDetails select').css('max-width',diffWidth);
  }, 0);
  $('.torrentControl').css('display', '');
  this.showDetailsInDetails();
};

// Fetch the tracker list for the General tab's Tracker URL field,
// like the desktop's showDetails does
plugin.loadDetailTrackers = function() {
  var hash = this.torrent.hash;
  if (this.detailTrackers && this.detailTrackers.hash == hash) {
    return;
  }
  this.request('?action=gettrackers&hash=' + hash, function(data) {
    if (mobile.torrent != undefined && hash == mobile.torrent.hash) {
      plugin.detailTrackers = {hash: hash, list: data[hash] || []};
      plugin.fillDetails(mobile.torrent);
    }
  });
};

plugin.showDetailsInDetails = function() {
  $('.detailsPage').css('display', 'none');
  $('#detailsDetailsPage').css('display', '');
  $('#detailsNav li').removeClass('active');
  $('#detailsDetailsTab').addClass('active');
};

plugin.showTrackersInDetails = function() {
  $('.detailsPage').css('display', 'none');
  $('#detailsTrackersPage').css('display', '');
  $('#detailsNav li').removeClass('active');
  $('#detailsTrackers').addClass('active');
  this.loadTrackers();
}

plugin.showFilesInDetails = function() {
  $('.detailsPage').css('display', 'none');
  $('#detailsFilesPage').css('display', '');
  $('#detailsNav li').removeClass('active');
  $('#detailsFiles').addClass('active');
  this.loadFiles();
}

plugin.showPeersInDetails = function() {
  $('.detailsPage').css('display', 'none');
  $('#detailsPeersPage').css('display', '');
  $('#detailsNav li').removeClass('active');
  $('#detailsPeers').addClass('active');
  // Same gating as the desktop peer menu (js/webui.js createPeerMenu)
  $('#peerAddBlock').toggle((theWebUI.systemInfo.rTorrent.iVersion >= 0x804) && (this.torrent.private == 0));
  $('#peerRowActions').toggle(theWebUI.systemInfo.rTorrent.iVersion >= 0x807);
  this.loadPeers();
}

/*** Torrent details: Peers tab ***/
plugin.selectPeer = function(row) {
  var wasSelected = $(row).hasClass('info');
  $('#peersTable tbody tr').removeClass('info');
  this.selectedPeer = null;
  if (!wasSelected) {
    $(row).addClass('info');
    this.selectedPeer = {id: $(row).data('pid'), snubbed: !!$(row).data('snubbed')};
  }
  $('#peerBanBtn, #peerKickBtn, #peerSnubBtn').prop('disabled', this.selectedPeer === null);
  $('#peerSnubBtn').text((this.selectedPeer && this.selectedPeer.snubbed) ? theUILang.peerUnsnub : theUILang.peerSnub);
};

plugin.peerAction = function(cmd, arg) {
  var self = this;
  this.request('?action=' + cmd + '&hash=' + this.torrent.hash + '&f=' + encodeURIComponent(arg), function() {
    self.loadPeers();
  });
};

plugin.loadPeers = function() {
  if (this.torrent != undefined) {
    var hash = this.torrent.hash;
    this.request('?action=getpeers&hash=' + hash, function(data) {
      var peers = data;
      var pid = Object.keys(peers);
      if (hash == mobile.torrent.hash) {
        var tableHeight = $(window).height() - $('#mainNavbar').outerHeight(true) - ($('#torrentDetails .nav').outerHeight(true) + $('#torrentDetailsHeader').outerHeight(true) + ($('#torrentDetailsHeader #torrentProgress').outerHeight(true) - $('#torrentDetailsHeader #torrentProgress').outerHeight()));
        $('div.tableFixHead').css("max-height", tableHeight + "px");

        var selected = plugin.selectedPeer;

        // The geoip plugin's getpeers hook annotates peers with a country
        // code; render the same flag images the desktop table shows
        var geoip = thePlugins.get('geoip');
        var geoipActive = !!(geoip && geoip.enabled && geoip.retrieveCountry);
        $('#peersTable').toggleClass('no-country', !geoipActive);

        var peersHtml = '';

        for (var i = 0; i < pid.length; i++) {
          var flagHtml = '';
          if (peers[pid[i]].country) {
            // The country value is e.g. "AU (Sydney)"; translate the code
            // and keep the city suffix, like the desktop column
            var cc = peers[pid[i]].country.substr(0, 2);
            var countryText = ((theUILang.country && theUILang.country[cc]) || cc) + peers[pid[i]].country.substr(2);
            flagHtml = '<img class="peer-flag" src="plugins/geoip/flags/' + cc.toLowerCase() + '.gif" alt="' + escapeHTML(cc) + '"/> ' + escapeHTML(countryText);
          }
          peersHtml += '<tr data-pid="' + pid[i] + '" data-snubbed="' + (peers[pid[i]].snubbed ? 1 : 0) + '">' +
          '<td class="country-cell">' + flagHtml + '</td>' +
          '<td>' + peers[pid[i]].ip + ':' +  peers[pid[i]].port + '</td>' +
          '<td>' + escapeHTML(peers[pid[i]].version) + '</td>' +
          '<td>' + escapeHTML(peers[pid[i]].flags) + '</td>' +
          '<td>' + peers[pid[i]].done + '%</td>' +
          '<td>' + theConverter.bytes(peers[pid[i]].downloaded,2) + '</td>' +
          '<td>' + theConverter.bytes(peers[pid[i]].uploaded,2) + '</td>' +
          '<td>' + theConverter.speed(peers[pid[i]].dl) + '</td>' +
          '<td>' + theConverter.speed(peers[pid[i]].ul) + '</td>' +
          '<td>' + theConverter.speed(peers[pid[i]].peerdl) + '</td>' +
          '<td>' + theConverter.bytes(peers[pid[i]].peerdownloaded,2) + '</td>' +
          '</tr>';
        }

        $('#peersTable tbody').html(peersHtml);

        // Restore the selection across periodic refreshes; drop it only if
        // the peer is gone from the list (e.g. after a ban/kick)
        plugin.selectedPeer = null;
        if (selected && peers[selected.id] != undefined) {
          $('#peersTable tbody tr').each(function() {
            if ($(this).data('pid') == selected.id) {
              $(this).addClass('info');
              plugin.selectedPeer = {id: selected.id, snubbed: !!peers[selected.id].snubbed};
            }
          });
        }
        $('#peerBanBtn, #peerKickBtn, #peerSnubBtn').prop('disabled', plugin.selectedPeer === null);
        $('#peerSnubBtn').text((plugin.selectedPeer && plugin.selectedPeer.snubbed) ? theUILang.peerUnsnub : theUILang.peerSnub);
      }
    });
  }
};

/*** Torrent details: Trackers tab ***/
plugin.loadTrackers = function() {
  if (this.torrent != undefined) {
    var hash = this.torrent.hash;
    this.request('?action=gettrackers&hash=' + hash, function(data) {
      var trackers = data[hash];
      if (hash == mobile.torrent.hash) {
        plugin.detailTrackers = {hash: hash, list: trackers};
        var openPanel = $('#trackersAccordion .accordion-collapse.show').attr('id');

        var trackersHtml = '';
        if (theWebUI.systemInfo.rTorrent.iVersion >= 0x809) {
          trackersHtml += '<button id="updateTrackersBtn" class="btn btn-outline-secondary">' + theUILang.updateTracker + '</button>';
        }
        trackersHtml += '<div class="accordion" id="trackersAccordion">';

        for (var i = 0; i < trackers.length; i++) {
          trackersHtml +=
          '<div class="accordion-item"><h2 class="accordion-header">' +
          '<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#tracker' + i + '">' +
          escapeHTML(trackers[i].name) + '</button></h2>' +
          '<div id="tracker' + i + '" class="accordion-collapse collapse" data-bs-parent="#trackersAccordion"><div class="accordion-body">' +
          '<table class=" table table-striped"><tbody>' +
          '<tr><td>' + theUILang.Type + '</td><td>' + theFormatter.trackerType(trackers[i].type) + '</td></tr>' +
          '<tr><td>' + theUILang.Enabled + '</td><td>' + theFormatter.yesNo(trackers[i].enabled) +
          ' <button class="btn btn-outline-secondary btn-sm trackerToggle" data-index="' + i + '" data-state="' + (trackers[i].enabled ? 0 : 1) + '">' +
          (trackers[i].enabled ? theUILang.DisableTracker : theUILang.EnableTracker) + '</button></td></tr>' +
          '<tr><td>' + theUILang.Group + '</td><td>' + trackers[i].group + '</td></tr>' +
          '<tr><td>' + theUILang.Seeds + '</td><td>' + trackers[i].seeds + '</td></tr>' +
          '<tr><td>' + theUILang.Peers + '</td><td>' + trackers[i].peers + '</td></tr>' +
          '<tr><td>' + theUILang.scrapeDownloaded + '</td><td>' + trackers[i].downloaded + '</td></tr>' +
          '<tr><td>' + theUILang.scrapeUpdate + '</td><td>' +
          (trackers[i].last ? theConverter.time($.now() / 1000 - trackers[i].last - theWebUI.deltaTime / 1000, true) : '') +
          '</td></tr>' +
          '<tr><td>' + theUILang.trkInterval + '</td><td>' + theConverter.time(trackers[i].interval) + '</td></tr>' +
          '<tr><td>' + theUILang.trkPrivate + '</td><td>' + theFormatter.yesNo(theWebUI.trkIsPrivate(trackers[i].name)) + '</td></tr>' +
          '</tbody></table></div></div></div>';
        }

        trackersHtml += '</div>';
        $('#detailsTrackersPage').html(trackersHtml);
        if (openPanel) {
          $('#' + openPanel).addClass('show');
          $('#trackersAccordion [data-bs-target="#' + openPanel + '"]').removeClass('collapsed');
        }

        $('#updateTrackersBtn').click(function() {
          $(this).prop('disabled', true);
          plugin.request('?action=updateTracker&hash=' + hash, function() {
            plugin.loadTrackers();
          });
        });
        $('#detailsTrackersPage .trackerToggle').click(function() {
          plugin.request('?action=settrackerstate&hash=' + hash + '&p=' + $(this).data('state') + '&f=' + $(this).data('index'), function() {
            plugin.loadTrackers();
          });
        });
      }
    });
  }
};

// Refresh the changing tracker values in place (no rebuild, so the open
// accordion panel and toggle buttons are left alone). Row indexes must
// match the table built in loadTrackers.
plugin.refreshTrackers = function() {
  if (this.torrent == undefined) {
    return;
  }
  var hash = this.torrent.hash;
  this.request('?action=gettrackers&hash=' + hash, function(data) {
    if (mobile.torrent == undefined || hash != mobile.torrent.hash || !$('#detailsTrackersPage').is(':visible')) {
      return;
    }
    var trackers = data[hash];
    plugin.detailTrackers = {hash: hash, list: trackers};
    for (var i = 0; i < trackers.length; i++) {
      var rows = $('#tracker' + i + ' tbody tr');
      if (!rows.length) {
        continue;
      }
      rows.eq(3).find('td:last').text(trackers[i].seeds);
      rows.eq(4).find('td:last').text(trackers[i].peers);
      rows.eq(5).find('td:last').text(trackers[i].downloaded);
      rows.eq(6).find('td:last').text(trackers[i].last ? theConverter.time($.now() / 1000 - trackers[i].last - theWebUI.deltaTime / 1000, true) : '');
      rows.eq(7).find('td:last').text(theConverter.time(trackers[i].interval));
    }
  });
};

/*** Torrent details: Files tab ***/
plugin.getDir = function(p) {
  var path = p.split('/');
  if ((path[0] == '') && (path.length == 1)) {
    path = [];
  }

  var dir = plugin.files;
  var realPath = '';
  for (var i = 0; i < path.length; i++) {
    if (path[i] == '') {
      continue;
    }

    if (dir.container[path[i]] != undefined) {
      dir = dir.container[path[i]];
      realPath += '/' + path[i];
      if (!dir.directory) {
        break;
      }
    } else {
      break;
    }
  }

  realPath = realPath.substr(1);
  return [realPath, dir];
}

plugin.getFilesList = function(s) {
  var ret = '';

  for (var name in s) {
    if (s[name].directory) {
      ret += this.getFilesList(s[name].container);
    } else {
      ret += '&v=' + s[name].id;
    }
  }

  return ret;
}

// File and directory names come from the torrent, so build this page with
// DOM nodes and .text()/bound handlers (never string-concatenated HTML or
// inline onclick attributes)
plugin.drawFiles = function(p) {
  var vars = this.getDir(p);
  var realPath = vars[0];
  var dir = vars[1];

  var container = $('#detailsFilesPage').empty();

  if (!dir.root) {
    var i = realPath.lastIndexOf('/');
    if (i < 0) {
      i = 0;
    }
    var upperDir = realPath.substr(0, i);
    container.append(
      $('<a href="javascript://void();"><i class="bi bi-folder2-open icon-black"></i> ..</a>')
        .click(function() {mobile.drawFiles(upperDir);}),
      $('<hr>'));
  }

  $.each(dir.container, function(name, entry) {
    var path = realPath + '/' + name;
    var block = $('<div></div>');

    block.append(
      $('<button class="btn btn-outline-secondary btn-sm pull-right"><i class="bi bi-list-ul icon-black"></i></button>')
        .click(function() {mobile.toggleDisplay($(this).parent().find('.prioritySelect'));}));

    if (entry.directory) {
      block.append(
        $('<a href="javascript://void();"><i class="bi bi-folder2-open icon-black"></i>&nbsp;</a>')
          .append(document.createTextNode(name))
          .click(function() {mobile.drawFiles(path);}));
    } else {
      var filePercent = (entry.size > 0) ? Math.min(100, Math.round(entry.done / entry.size * 1000) / 10) : 100;
      var info = $('<div style="display:none;" id="file' + entry.id + '">' +
        '<table class="table table-striped"><tbody>' +
        '<tr><td>' + theUILang.Done + '</td><td>' + theConverter.bytes(entry.done) + ' (' + filePercent + '%)</td></tr>' +
        '<tr><td>' + theUILang.Size + '</td><td>' + theConverter.bytes(entry.size) + '</td></tr>' +
        '</tbody></table></div>');
      block.append(
        $('<a href="javascript://void();"><i class="bi bi-file-earmark icon-black"></i>&nbsp;</a>')
          .append(document.createTextNode(name))
          .click(function() {mobile.toggleDisplay(info);}),
        info);
    }

    block.append($('<select class="prioritySelect" style="display:none;">' +
      '<option disabled ' + ((entry.priority == -1) ? 'selected' : '') + '></option>' +
      '<option value="2" ' + ((entry.priority == 2) ? 'selected' : '') + '>' + theUILang.High_priority + '</option>' +
      '<option value="1" ' + ((entry.priority == 1) ? 'selected' : '') + '>' + theUILang.Normal_priority + '</option>' +
      '<option value="0" ' + ((entry.priority == 0) ? 'selected' : '') + '>' + theUILang.Dont_download + '</option>' +
      '</select>').data('path', path));

    container.append(block, $('<hr/>'));
  });

  $('#detailsFilesPage select').change(function() {
    var newValue = $(this).val();
    if (newValue < 0) {
      return;
    }

    var vars = plugin.getDir($(this).data('path'));

    var filesList = '';
    if (!vars[1].directory) {
      filesList = vars[1].id;
    } else {
      filesList = plugin.getFilesList(vars[1].container);
    }

    plugin.request('?action=setprio&hash=' + plugin.torrent.hash  + '&v=' +filesList + '&s=' + newValue);
  });
}

plugin.fillDirectoriesPriority = function(p) {
  var priority = -2;
  for (var name in p.container) {
    if (p.container[name].directory) {
      this.fillDirectoriesPriority(p.container[name]);
    }
    if (priority == -2) {
      priority = p.container[name].priority;
    } else if (priority != p.container[name].priority) {
      priority = -1;
    }
  }
  p.priority = priority;
}

plugin.loadFiles = function() {
  if (this.torrent != undefined) {
    var hash = this.torrent.hash;
    $('#detailsFilesPage').html('');
    this.request('?action=getfiles&hash=' + hash, function(data) {
      var rawFiles = data[hash];
      var files = {root: true, directory: true, priority: -1, container: {}};

      for (var i = 0; i < rawFiles.length; i++) {
        var path = rawFiles[i].name.replace(/^\/|\/$/g, '').split('/');
        var currDir = files;
        for (var j = 0; j < path.length -1; j++) {
          if (currDir.container[path[j]] == undefined) {
            currDir.container[path[j]] = {directory: true, root: false, container: {}, priority: -2};
          }
          currDir = currDir.container[path[j]];
        }
        currDir.container[path[path.length - 1]] = {root: false,
          directory: false,
          size: rawFiles[i].size,
          done: rawFiles[i].done,
          priority: rawFiles[i].priority,
          id: i
        };
      }

      plugin.fillDirectoriesPriority(files);
      mobile.files = files;
      mobile.filesDownloaded = mobile.torrent.downloaded;
      mobile.drawFiles('');
    });
  }
}

// Refresh the per-file done values in place (no DOM rebuild, so expanded
// rows and open priority selects are left alone)
plugin.refreshFiles = function() {
  if (this.torrent == undefined) {
    return;
  }
  var hash = this.torrent.hash;
  this.filesDownloaded = this.torrent.downloaded;
  this.request('?action=getfiles&hash=' + hash, function(data) {
    if (mobile.torrent == undefined || hash != mobile.torrent.hash || !$('#detailsFilesPage').is(':visible')) {
      return;
    }
    var rawFiles = data[hash];
    for (var i = 0; i < rawFiles.length; i++) {
      // Update the visible Done cell, if this file's row is rendered
      var cell = $('#file' + i + ' tr:first td:last');
      if (cell.length) {
        var pct = (rawFiles[i].size > 0) ? Math.min(100, Math.round(rawFiles[i].done / rawFiles[i].size * 1000) / 10) : 100;
        cell.text(theConverter.bytes(rawFiles[i].done) + ' (' + pct + '%)');
      }
      // Keep the in-memory tree fresh for navigation
      var node = plugin.getDir(rawFiles[i].name.replace(/^\/|\/$/g, ''))[1];
      if (node && !node.directory) {
        node.done = rawFiles[i].done;
      }
    }
  });
}

/*** Torrent actions (bottom bar and delete page) ***/
plugin.start = function() {
  if (this.torrent != undefined) {
    var status = this.torrent.state;

    if ((!(status & dStatus.started) || (status & dStatus.paused) && !(status & dStatus.checking) && !(status & dStatus.hashing))) {
      this.request('?action=start&hash=' + this.torrent.hash);
    }
  }
};

plugin.stop = function() {
  if (this.torrent != undefined) {
    var status = this.torrent.state;

    if ((status & dStatus.started) || (status & dStatus.hashing) || (status & dStatus.checking)) {
      this.request('?action=stop&hash=' + this.torrent.hash);
    }
  }
};

plugin.pause = function() {
  if (this.torrent != undefined) {
    var status = this.torrent.state;

    if (((status & dStatus.started) && !(status & dStatus.paused) && !(status & dStatus.checking) && !(status & dStatus.hashing))) {
      this.request('?action=pause&hash=' + this.torrent.hash);
    } else if (((status & dStatus.paused) && !(status & dStatus.checking) && !(status & dStatus.hashing))) {
      this.request('?action=unpause&hash=' + this.torrent.hash);
    }
  }
};

plugin.recheck = function() {
  if (this.torrent != undefined) {
    var status = this.torrent.state;

    if (!(status & dStatus.checking) && !(status & dStatus.hashing)) {
      this.request('?action=recheck&hash=' + this.torrent.hash);
    }
  }
};

plugin.delete = function() {
  if (this.torrent == undefined) {
    this.showList();
  } else {

    if ((this.eraseWithDataLoaded) && (this.eraseWithDataDefault != undefined)) {
      $('#deleteWithData input').prop('checked', this.eraseWithDataDefault);
    }
    if (theWebUI.settings["webui.confirm_when_deleting"]) {
      $('#confirmTorrentDelete h5').html('<span id="confirmText">' + theUILang.Rem_torrents_prompt + '</span><hr />' + escapeHTML(this.torrent.name));
      this.showPage('confirmTorrentDelete');
    } else {
      this.deleteConfirmed();
    }
  }
};

plugin.deleteConfirmed = function() {
  if ((this.eraseWithDataLoaded) && ($('#deleteWithData input').prop('checked'))) {
    this.request('?action=removewithdata&hash=' + this.torrent.hash);
  } else {
    this.request('?action=remove&hash=' + this.torrent.hash);
  }
  this.torrent = undefined;
  this.showList();
};

/*** Directory browser and save-path pages ***/
plugin.chooseGetDir = function(path) {
  $(plugin.getDirTarget || '#dir_edit').val(path);
  history.go(-1);
}

plugin.drawGetDir = function(path, first) {
  $.ajax({
    url: 'plugins/_getdir/listdir.php',
    data: {
      'dir': path,
      'time': ((new Date()).getTime())
    },
    dataType: 'json',

    success: function(data) {
      var container = $('#getDirList').empty();
      container.append($('<h5></h5>').text(data.path));
      container.append($('<button class="btn btn-' + plugin.currentAccent + '"></button>').text(theUILang.ok).click(function() {mobile.chooseGetDir(data.path);}));
      container.append($('<button class="btn btn-outline-secondary"></button>').text(theUILang.Cancel).click(function() {history.go(-1);}));

      var tbody = $('<tbody></tbody>');
      $.each(data.directories, function(i, name) {
        var cell = $('<td><i class="bi bi-folder2-open icon-black"></i> </td>').append(document.createTextNode(name));
        tbody.append($('<tr></tr>').append(cell).click(function() {mobile.drawGetDir(data.path + name);}));
      });
      container.append($('<table class="table table-striped"></table>').append(tbody));

      if (first === true) {
        mobile.showPage('getDirList');
      }
    }
  });
};

plugin.showGetDir = function(target) {
  this.getDirTarget = target || '#dir_edit';
  this.returningFromGetDir = true;
  this.drawGetDir('', true);
};

plugin.showDataDir = function() {
  // Keep the form state when coming back from the directory browser
  if (this.returningFromGetDir) {
    this.returningFromGetDir = false;
    this.showPage('torrentDataDir');
    return;
  }

  var d = this.torrent;
  var fill = function(savepath) {
    $('#datadir_edit').val(savepath);
    // Same rules as the desktop dialog (plugins/datadir/init.js):
    // path can't be ignored for single-file torrents, fast resume needs a completed torrent
    $('#datadir_not_add_path').prop('disabled', String(d.multi_file).trim() === '0').prop('checked', false);
    $('#datadir_move').prop('checked', true);
    $('#datadir_fastresume').prop('disabled', String(d.done).trim() !== '1000').prop('checked', false);
    $('#dataDirOk').prop('disabled', false);
    plugin.showPage('torrentDataDir');
    var used = ($('#datadir_edit').outerWidth(true) - $('#datadir_edit').width()) + $('#showGetDirDataDir').outerWidth(true) + 1;
    $('#datadir_edit').width($('#torrentDataDir').width() - used);
  };

  var savePath = $.trim(d.save_path);
  if (savePath.length) {
    fill(savePath);
  } else {
    // Torrent is not open; the datadir plugin's getsavepath stub fetches its base path
    this.request('?action=getsavepath&hash=' + d.hash, function(r) {
      fill($.trim(r.savepath));
    });
  }
};

plugin.sendDataDir = function() {
  $('#dataDirOk').prop('disabled', true);
  $.ajax({
    type: 'POST',
    url: 'plugins/datadir/action.php',
    dataType: 'json',
    contentType: 'application/x-www-form-urlencoded',
    processData: false,
    // action.php uses rawurldecode, which doesn't map '+' back to a space,
    // so encode the same way as the desktop setdatadir stub
    data: 'hash=' + this.torrent.hash +
      '&datadir=' + encodeURIComponent($('#datadir_edit').val()) +
      '&move_addpath=' + ($('#datadir_not_add_path').prop('checked') ? '0' : '1') +
      '&move_datafiles=' + ($('#datadir_move').prop('checked') ? '1' : '0') +
      '&move_fastresume=' + ($('#datadir_fastresume').prop('checked') ? '1' : '0'),
    success: function(d) {
      $('#dataDirOk').prop('disabled', false);
      if (d && d.errors && d.errors.length) {
        for (var i = 0; i < d.errors.length; i++) {
          var s = '';
          try {s = eval(d.errors[i].desc);} catch(e) {s = d.errors[i].desc;}
          if (d.errors[i].prm) {
            s += ' (' + d.errors[i].prm + ')';
          }
          plugin.showAlert(s, 'alert-danger');
        }
      } else {
        history.go(-1);
        plugin.update(true);
      }
    },
    error: function() {
      $('#dataDirOk').prop('disabled', false);
    }
  });
};

/*** Optional plugin integrations (ratio, throttle, seedingtime) ***/
// These only add the extra rows to the General tab. The ?action=setratio /
// ?action=setthrottle requests are handled by the ratio and throttle
// plugins' own rTorrentStub methods (both plugins stay enabled on mobile,
// see the keepEnabled list in disableOthers).
plugin.loadRatio = function () {
  var ratio = thePlugins.get("ratio");
  if (ratio.allStuffLoaded) {
    $('#priority').after('<tr id="ratiogrp"><td></td><td><select id="torrentRatioGrp"></select></td></tr>');
    $('#torrentRatioGrp').change(function(){mobile.changeRatioGrp()});
    var ratioHTML = '<option value="-1">' + theUILang.mnuRatioUnlimited + '</option>'
    $.each(theWebUI.ratios, function(i, v) {
      ratioHTML += '<option value="' + i + '">' + v.name + '</option>';
    });
    $('#torrentRatioGrp').html(ratioHTML);
    $('#ratiogrp').children('td:first').text(theUILang.ratio);
  } else {
    setTimeout(function(){plugin.loadRatio()}, 1000);
  }
};

plugin.loadThrottle = function () {
  var throttle = thePlugins.get("throttle");
  if (throttle.allStuffLoaded) {
    $('#priority').after('<tr id="throttle"><td></td><td><select id="torrentChannel"></select></td></tr>');
    $('#torrentChannel').change(function(){mobile.changeChannel()});
    var throttleHTML = '<option value="-1">' + theUILang.mnuUnlimited + '</option>';
    $.each(theWebUI.throttles, function(i, v) {
      throttleHTML += '<option value="' + i + '">' + v.name + '</option>';
    });
    $('#torrentChannel').html(throttleHTML);
    $('#throttle').children('td:first').text(theUILang.throttle);
  } else {
    setTimeout(function(){plugin.loadThrottle()}, 1000);
  }
};

plugin.loadSeedingTime = function () {
  var seedingtime = thePlugins.get("seedingtime");
  if (seedingtime.allStuffLoaded) {
    $('#created').after('<tr id="seedtime"><td></td><td></td></tr>');
    $('#created').after('<tr id="dateAdded"><td></td><td></td></tr>');
    $('#dateAdded').children('td:first').text(theUILang.addTime);
    $('#seedtime').children('td:first').text(theUILang.seedingTime);
  } else {
    setTimeout(function(){plugin.loadSeedingTime()}, 1000);
  }
};

/*** Torrent list rendering and updates ***/
plugin.dynamicSort = function (property) {
  var sortOrder = 1;
  if(property[0] === "-") {
    sortOrder = -1;
    property = property.substr(1);
  }
  return function (a,b) {
    var result;
    if (typeof a[property] == 'string' || a[property] instanceof String) {
      if (parseInt(a[property])) {
        if (parseInt(b[property])) {
          result = (parseInt(a[property]) < parseInt(b[property])) ? -1 : (parseInt(a[property]) > parseInt(b[property])) ? 1 : 0;
        } else {
          result = -1;
        }
      } else if (parseInt(b[property])) {
        result = 1;
      } else {
        result = (a[property].toLowerCase() < b[property].toLowerCase()) ? -1 : (a[property].toLowerCase() > b[property].toLowerCase()) ? 1 : 0;
      }
    } else {
      result = (a[property] < b[property]) ? -1 : (a[property] > b[property]) ? 1 : 0;
    }
    if (result == 0 && property != 'name') {
      // Tie-break by name (always ascending), so e.g. sorting by status
      // lists same-status torrents alphabetically like the desktop does
      return (a.name.toLowerCase() < b.name.toLowerCase()) ? -1 : (a.name.toLowerCase() > b.name.toLowerCase()) ? 1 : 0;
    }
    return result * sortOrder;
  }
}

// Only the fields the torrent row rendering depends on; comparing these
// (instead of whole torrent objects) avoids redrawing rows whose visible
// content didn't change
plugin.rowSnapshot = function(v) {
  return {name: v.name, size: v.size, label: v.label, state: v.state, done: v.done,
    ul: v.ul, dl: v.dl, eta: v.eta, ratio: v.ratio, msg: v.msg};
};

plugin.processTorrents = function(torrents, singleUpdate) {
  plugin.torrents = torrents;
  var torrentArray = [];
  var tul = 0;
  var tdl = 0;

  plugin.labelList = [{name: '', count: theWebUI.categoryList.panelLabelAttribs.plabel.get("-_-_-nlb-_-_-").count}];

  for (const l of theWebUI.categoryList.panelLabelAttribs.plabel.keys()) {
    if (l.startsWith("clabel")) {
      const labelProper = l.replace('clabel__', '');
      if (plugin.labelIds[labelProper] == undefined) {
        plugin.labelIds[labelProper] = plugin.nextLabelId++;
      }

      plugin.labelList.push({name: labelProper, count: theWebUI.categoryList.panelLabelAttribs.plabel.get(l).count});
    }
  }

  var listHtml = $('#torrentsList #list table tbody');
  var listHtmlString = '';

  $.each(plugin.torrents, function(n, v){
    v.hash = n;
    v.status = theWebUI.getStatusIcon(v)[1];
    torrentArray.push(v);
  });
  torrentArray.sort(plugin.dynamicSort(plugin.sort));

  var process = function(trackerData, trackersRefetched) {
    var trackersCount = {};
    var trackersMap = {};
    var listChanged = !!singleUpdate;
    var changedIds = [];
    var rowsNow = {};

    $.each(torrentArray, function(n, v){
      // Mirrors the desktop state panel grouping (see js/category-list.js),
      // except that any transfer counts as active (the desktop ignores
      // rates below 1 KiB/s)
      var statusClass = (v.done >= 1000) ? 'Completed' : ((v.state & dStatus.paused) || !(v.state & dStatus.started)) ? 'Stopped' : 'Downloading';
      var stateClass = (v.state & dStatus.started) ? ((v.dl > 0 || v.ul > 0) ? 'Active' : 'Inactive') : 'None';
      var errorClass = (v.state & dStatus.error) ? 'Yes' : 'No';
      var percent = v.done / 10;

      // The parts shared by row creation and in-place update
      var rowClass = 'torrentBlock status' + statusClass + ' state' + stateClass + ' error' + errorClass + ' label' + plugin.labelIds[v.label];
      var statusHtml = v.status +
        ((v.ul) ? ' ↑' + theConverter.speed(v.ul) : '') +
        ((v.dl) ? ' ↓' + theConverter.speed(v.dl) : '') +
        ' | ' + ((statusClass == 'Downloading')
          ? (theUILang.ETA + ' ' + ((v.eta == -1) ? "&#8734;" : theConverter.time(v.eta)))
          : (theUILang.Ratio + ' ' + ((v.ratio == -1) ? "&#8734;" : theConverter.round(v.ratio/1000,3)))) +
        ((v.msg) ? ' | <i class="text-danger">' + escapeHTML(v.msg) + '</i>' : '');
      var progressLabel = percent + '% ' + theUILang.of + ' ' + theConverter.bytes(v.size,2);

      tul += iv(v.ul);
      tdl += iv(v.dl);
      rowsNow[v.hash] = plugin.rowSnapshot(v);

      var row = $('#' + v.hash);
      if ( ! row.length || singleUpdate) {
        listHtmlString +=
        '<tr id="' + v.hash + '" class="' + rowClass + '" onclick="mobile.showDetails(this.id);"><td>' +
        '<h5>' + escapeHTML(v.name) + '</h5>' +
        '<span>' + statusHtml + '</span>' +
        '<div class="progress">' +
        '<div class="progress-bar progress-bar-striped' + ((v.done == 1000) ? '' : ' progress-bar-animated') + '" style="width: ' + percent + '%;"></div>' +
        '<span class="progress-label" style="width: ' + percent + '%;">' + progressLabel + '</span>' +
        '</div>' +
        '</td></tr>';
        listChanged = true;
        changedIds.push(v.hash);
      } else if ( ! plugin.rowsPrev[v.hash] || ! isEqual(rowsNow[v.hash], plugin.rowsPrev[v.hash]) ) {
        row.removeClass();
        row.addClass(rowClass);
        row.find('span').html(statusHtml);
        var progressBar = row.find('.progress-bar');
        progressBar.removeClass('progress-bar-animated');
        if (v.done != 1000) {
          progressBar.addClass('progress-bar-animated');
        }
        progressBar.css('width', percent + '%');
        row.find('.progress-label').css('width', percent + '%').text(progressLabel);
        listChanged = true;
        changedIds.push(v.hash);
      }

      var trackers = trackerData[v.hash] || [];
      var uniqueTrackers = [];
      for (var i = 0; i < trackers.length; i++) {
        var trackerName = theWebUI.getTrackerName(trackers[i].name);
        if (trackerName) {
          if (trackerName in trackersCount) {
            if ($.inArray(trackerName, uniqueTrackers) == -1) {
              trackersCount[trackerName]++;
            }
          } else {
            trackersCount[trackerName] = 1;
          }
          if (plugin.trackerIds[trackerName] == undefined) {
            plugin.trackerIds[trackerName] = plugin.nextTrackerId++;
          }
          if ($.inArray(trackerName, uniqueTrackers) == -1) {
            uniqueTrackers.push(trackerName);
          }
        }
      }
      trackersMap[v.hash] = uniqueTrackers;
    });

    plugin.trackerList = [];
    Object.keys(trackersCount).sort().forEach(function(t) {
      plugin.trackerList.push({name: t, count: trackersCount[t]});
    });

    $.each(plugin.rowsPrev, function(n, v){
      if ( ! plugin.torrents[n] ) {
        $('#' + n).remove();
        listChanged = true;
      }
    });

    if ( listHtmlString ) {
      if (singleUpdate) {
        listHtml.html(listHtmlString);
      } else {
        listHtml.append(listHtmlString);
      }
    }

    // Tracker classes only need a full pass when the torrent set changed
    // (fresh tracker data) or the list was rebuilt; otherwise the unchanged
    // rows already carry them
    if (trackersRefetched || singleUpdate) {
      $.each(trackersMap, function(id, ns) {
        $.each(ns, function(i, n) {
          $('#'+id).addClass("tracker"+plugin.trackerIds[n]);
        });
      });
    } else {
      $.each(changedIds, function(i, id) {
        $.each(trackersMap[id] || [], function(j, n) {
          $('#'+id).addClass("tracker"+plugin.trackerIds[n]);
        });
      });
    }

    // Re-filtering shows/hides every row, so skip it while nothing changed
    if (listChanged) {
      plugin.applyFilters();
      if ($('#torrentFilter').is(':visible')) {
        plugin.renderFilterPage();
      }
    }

    if (plugin.torrent != undefined) {
      if (plugin.torrents[plugin.torrent.hash] != undefined) {
        plugin.torrent = plugin.torrents[plugin.torrent.hash];
        plugin.fillDetails(plugin.torrent);
        if ($('#detailsPeersPage').is(':visible')) {
          plugin.loadPeers();
        }
        // Refresh file percentages while data is arriving, like the desktop
        if ($('#detailsFilesPage').is(':visible') && plugin.filesDownloaded != plugin.torrent.downloaded) {
          plugin.refreshFiles();
        }
        if ($('#detailsTrackersPage').is(':visible')) {
          plugin.refreshTrackers();
        }
      } else {
        plugin.showList();
      }
    }

    // The core engine refreshes theWebUI.stopen every cycle when the
    // open-status setting is on; mirror it live like the desktop status bar
    if (theWebUI.settings['webui.show_open_status'] && $('#globalSettings').is(':visible')) {
      plugin.renderOpenStatus(theWebUI.stopen);
    }

    $('#upspeed').text(theConverter.speed(tul));
    $('#downspeed').text(theConverter.speed(tdl));

    plugin.rowsPrev = rowsNow;
  };

  // The full tracker list only changes when torrents come or go,
  // so don't refetch it on every cycle
  var trackersKey = Object.keys(plugin.torrents).sort().join(' ');
  if (plugin.trackersCache && plugin.trackersCache.key == trackersKey) {
    process(plugin.trackersCache.data, false);
  } else {
    mobile.request('?action=getalltrackers', function(data) {
      plugin.trackersCache = {key: trackersKey, data: data};
      process(data, true);
    });
  }
};

plugin.update = function(singleUpdate) {
  theWebUI.requestWithTimeout("?list=1&getmsg=1", function(data) {
    plugin.processTorrents(data.torrents, singleUpdate);
  }, function() {}, function() {});
};

/*** Bootstrapping: device detection, plugin takeover and initialization ***/
plugin.disableOthers = function() {
  var start = (window.location.href.indexOf('mobile=1') > 0);

  if ((!start) && this.enableAutodetect) {
    start = jQuery.browser.mobile;
  }

  if (start) {
    dxSTable.prototype.renameColumn = function(no,name) { }

    dxSTable.prototype.Sort = function(e) { }

    dxSTable.prototype.createRow = function(cols, sId, icon, attr) { }

    dxSTable.prototype.addRow = function (cols, sId, icon, attr) { }

    dxSTable.prototype.addRowById = function (ids, sId, icon, attr) { }

    dxSTable.prototype.refreshRows = function( height, fromScroll ) { }

    dxSTable.prototype.getAttr = function (row, attrName) { }

    dxSTable.prototype.setAttr = function(row, attr) { }

    dxSTable.prototype.setIcon = function(row, icon) { }

    theWebUI.filterByLabel = function() { }

    theWebUI.loadTorrents = function() { }

    // Plugins whose client side the mobile UI integrates with (see the
    // optional plugins list in the README); all others are disabled while
    // the mobile UI is active. Plugins the mobile UI only talks to through
    // their PHP side (diskspace, check_port, tracklabels) need not be here.
    // The RPC backend is pluggable and flags itself as un-shutdownable
    // (plugin.may_be_shutdowned: 0), so rather than hardcoding backend
    // names, keep every plugin the server marks that way.
    var keepEnabled = ['_getdir', 'throttle', 'ratio', 'erasedata', 'seedingtime', 'datadir', 'geoip', 'mobile'];
    $.each(thePlugins.list, function(i, v) {
      if ($.inArray(v.name, keepEnabled) == -1 && v.canShutdown()) {
        v.disable();
      }
    });

    // disable() only flags a plugin off; it doesn't undo hooks or timers
    // the plugin already installed, and the desktop engine keeps running
    // underneath the mobile UI. In particular, theWebUI.config wraps and
    // already-scheduled callbacks of disabled plugins still run, so stub
    // the entry points through which they would keep polling the server
    // or start timers that never end. (More status-bar cleanup that has
    // to wait until after the takeover lives in plugin.init.)

    // The rss plugin's self-rescheduling update loop and request callbacks
    // would keep refreshing a category panel the mobile UI doesn't
    // maintain (same reason loadTorrents/filterByLabel are stubbed above).
    theWebUI.loadRSS = function() { }
    theWebUI.addRSSItems = function() { }

    // ...and its config wrap calls start(), which would fire a
    // getrsssettings request and start two perpetual timers.
    var rssPlugin = thePlugins.get('rss');
    if (rssPlugin) {
      rssPlugin.start = function() { }
    }

    // history and _task poll their PHP side from wait loops kicked off by
    // their config wraps; history's never even terminates while disabled
    // because its custom langLoaded doesn't set allStuffLoaded.
    var historyPlugin = thePlugins.get('history');
    if (historyPlugin) {
      historyPlugin.renameHistoryStuff = function() { }
    }
    var taskPlugin = thePlugins.get('_task');
    if (taskPlugin) {
      taskPlugin.renameTasksStuff = function() { }
    }

    // trafic starts a getratios poll loop at load time (before this code
    // runs); stubbing the entry point ends it at the next reschedule.
    var traficPlugin = thePlugins.get('trafic');
    if (traficPlugin) {
      traficPlugin.startRatios = function() { }
    }

    // Defense in depth: the trafic graph is created in onLangLoaded, which
    // core skips for disabled plugins, but if it ever exists shut its Flot
    // plot down — a plot whose placeholder was destroyed throws "Invalid
    // dimensions for plot" from its resize handler.
    if (theWebUI.trafGraph && theWebUI.trafGraph.plot) {
      theWebUI.trafGraph.plot.shutdown();
    }

    plugin.config = theWebUI.config;
    theWebUI.config = function(data)
    {
      plugin.config.call(this,data);
      plugin.init();
    }
  } else {
    this.disable();
  }
};

plugin.init = function() {

  this.lastHref = window.location.href;

  // The desktop engine keeps running underneath the mobile UI and can
  // auto-save the whole webui.* settings snapshot without user interaction
  // (e.g. categoryList pruning a vanished label selection during the update
  // loop). A long-lived mobile session holds a stale snapshot, so such a
  // save would revert settings (like the speed lists) changed from the
  // desktop in the meantime. Cut the auto-save hook on mobile.
  if (theWebUI.categoryList) {
    theWebUI.categoryList.onConfigChangeFn = function() {};
  }

  // The ipad plugin (a touch-to-mouse shim for the desktop UI) attaches
  // document-level touch handlers that preventDefault() every touch, which
  // breaks scrolling and tapping in the mobile UI. Detach them; the mobile
  // UI needs native touch behavior. This runs after all plugins are loaded.
  var ipad = thePlugins.get('ipad');
  if (ipad && ipad.touchStart) {
    document.removeEventListener('touchstart', ipad.touchStart, false);
    document.removeEventListener('touchend', ipad.touchEnd, false);
  }

  $(window).on('hashchange', function() {plugin.backListener();});

  $.ajax({
    type: 'GET',
    url: this.path + 'mobile.html',
    processData: false,

    success: function(data, textStatus) {
      $('body').html(data);

      // The desktop engine keeps polling ?list=1&getmsg=1 on its own
      // schedule underneath the mobile UI. Instead of running a second,
      // identical polling loop, piggyback on the data it already fetched.
      plugin.desktopAddTorrents = theWebUI.addTorrents;
      theWebUI.addTorrents = function(listData) {
        plugin.desktopAddTorrents.call(this, listData);
        if (listData && listData.torrents) {
          plugin.processTorrents(listData.torrents);
        }
      };

      // Keep the core's Bootstrap 5 stylesheet (and its JS bundle, already
      // loaded); drop the desktop-specific stylesheets
      $('link[rel=stylesheet]').filter(function() {
        return this.href.indexOf('css/bootstrap.min.css') === -1;
      }).remove();

      // cpuload and diskspace build their status-bar meters from unguarded
      // retry loops that wait for their stylesheets, so they may have come
      // alive between disableOthers and this point. Dismantle whatever
      // exists; with their stylesheets gone (removed just above) the retry
      // loops can never create anything again.
      var cpuloadPlugin = thePlugins.get('cpuload');
      if (cpuloadPlugin && cpuloadPlugin.graph) {
        // Unbind Flot's internal handlers and no-op the plugin's own
        // window-resize handler — resizing a plot whose placeholder was
        // destroyed throws "Invalid dimensions for plot".
        cpuloadPlugin.graph.plot.shutdown();
        cpuloadPlugin.graph.resize = function() { };
        // Stop re-fetching the CPU load on every torrent-list poll
        theRequestManager.removeRequest('ttl', cpuloadPlugin.reqId);
      }
      var diskspacePlugin = thePlugins.get('diskspace');
      if (diskspacePlugin) {
        // End its self-rescheduling free-space poll (and the duplicate
        // low-space noty it could raise); the mobile UI does its own
        // diskspace request for the dashboard meter.
        if (diskspacePlugin.diskTimeout) {
          window.clearTimeout(diskspacePlugin.diskTimeout);
          diskspacePlugin.diskTimeout = null;
        }
        diskspacePlugin.check = function() { };
      }
      // The mobile UI is designed for the light scheme
      $('html').attr('data-bs-theme', 'light');
      // Apply the configured accent color (see plugin.accentColor at the top)
      plugin.applyAccentColor(plugin.accentColor);
      plugin.loadLang();
      plugin.loadMainCSS();
      $('head').append('<meta name="apple-mobile-web-app-capable" content="yes" />');
      // viewport-fit=cover is required for env(safe-area-inset-*) to report
      // the home-indicator inset on iOS
      $('meta[name=viewport]').attr('content', 'width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, viewport-fit=cover');

      $('#mainNavbar').addClass('navbar-fixed-bottom');
      $('.nav-tabs').addClass('navbar-fixed-top');

      $('.torrentControl').css('display', 'none');

      $('#dlLimit').change(function(){plugin.setDLLimit();});
      $('#ulLimit').change(function(){plugin.setULLimit();});
      $('#accentSelect').change(function(){
        plugin.applyAccentColor(this.value);
        $(this).css('color', 'var(--bs-' + this.value + ')');
      });

      $('input[id=torrent_file]').change(function() {
        $('#torrentFileName').val($(this).val());
      });

      $('#notAddPath').append(' ' + theUILang.Dont_add_tname);
      $('#startStopped').append(' ' + theUILang.Dnt_start_down_auto);
      $('#fastResume').append(' ' + theUILang.doFastResume);
      $('#randomizeHash').append(' ' + theUILang.doRandomizeHash);
      $('#torrentFileSend').text(theUILang.add_button);

      $('#torrentPriority').html(
        '<option value="3">' + theUILang.High_priority + '</option>' +
        '<option value="2">' + theUILang.Normal_priority + '</option>' +
        '<option value="1">' + theUILang.Low_priority + '</option>' +
        '<option value="0">' + theUILang.Dont_download + '</option>'
      );
      $('#torrentPriority').change(function(){mobile.changePriority()});

      var makeAddRequest = function(frm)
      {
        var s = theURLs.AddTorrentURL+"?";
        if($("#torrents_start_stopped").prop("checked")) {
          s += 'torrents_start_stopped=1&';
        }
        if($("#fast_resume").prop("checked")) {
          s += 'fast_resume=1&';
        }
        if($("#not_add_path").prop("checked")) {
          s += 'not_add_path=1&';
        }
        if($("#randomize_hash").prop("checked")) {
          s += 'randomize_hash=1&';
        }
        var dir = $.trim($("#dir_edit").val());
        if(dir.length) {
          s += ('dir_edit='+encodeURIComponent(dir)+'&');
        }
        var lbl = $.trim($("#tadd_label").val());
        if(lbl.length) {
          s += ('label='+encodeURIComponent(lbl));
        }
        frm.action = s;

        // Persist add-options like the desktop dialog's beforeHide handler.
        // Re-fetch the stored settings first and merge them in, so this
        // long-lived mobile session doesn't overwrite newer settings saved
        // from the desktop UI (its own snapshot may be stale).
        theWebUI.requestWithoutTimeout('?action=getuisettings', function(stored) {
          if (stored) {
            $.extend(theWebUI.settings, stored);
          }
          $.each(['not_add_path', 'torrents_start_stopped', 'fast_resume', 'randomize_hash'], function(i, id) {
            theWebUI.settings['webui.' + id] = $('#' + id).prop('checked');
          });
          theWebUI.save();
        }, true);

        return(true);
      }
      $("#addTorrentFile").submit(function()
      {
        var files = $("#torrent_file")[0].files;
        for (var i = 0; i < files.length; i++) {
          if (!files[i].name.match(/\.torrent$/i)) {
            plugin.showAlert(theUILang.Not_torrent_file,"alert-danger");
            return(false);
          }
        }
        $('#torrentFileSend').prop('disabled', true);
        plugin.createiFrame();
        return(makeAddRequest(this));
      });
      $("#addTorrentUrl").submit(function() {
        $('#addUrl').prop('disabled', true);
        plugin.createiFrame();
        return(makeAddRequest(this));
      });

      $('#tadd_label_select').change(function() {
        var index = this.selectedIndex;
        if (index == 1) {
          $(this).hide();
          $('#tadd_label').show();
          $('#taddReturnSelect').show();
        } else if (index == 0) {
          $('#tadd_label').val('');
        } else {
          $('#tadd_label').val(this.options[index].value);
        }
      });
      $('#taddReturnSelect').click(function() {
        $(this).hide();
        $('#tadd_label').val('').hide();
        $('#tadd_label_select').prop('selectedIndex', 0).show();
      });
      $('#torrent_file').change(function() {
        $('#torrentFileSend').prop('disabled', this.files.length === 0);
      });
      $('#url').on('input', function() {
        $('#addUrl').prop('disabled', $.trim($(this).val()) === '');
      });

      $('#peersTable tbody').on('click', 'tr', function() {
        plugin.selectPeer(this);
      });
      $('#peerAddBtn').click(function() {
        var ip = $.trim($('#peerIp').val());
        if (ip.length) {
          $('#peerIp').val('');
          plugin.peerAction('addpeer', ip);
        }
      });
      $('#peerBanBtn').click(function() {
        if (plugin.selectedPeer) {
          plugin.peerAction('ban', plugin.selectedPeer.id);
        }
      });
      $('#peerKickBtn').click(function() {
        if (plugin.selectedPeer) {
          plugin.peerAction('kick', plugin.selectedPeer.id);
        }
      });
      $('#peerSnubBtn').click(function() {
        if (plugin.selectedPeer) {
          plugin.peerAction(plugin.selectedPeer.snubbed ? 'unsnub' : 'snub', plugin.selectedPeer.id);
        }
      });

      if (thePlugins.isInstalled('erasedata')) {
        $('#confirmTorrentDelete h5').after(
          '<div class="checkbox"><label id="deleteWithData">' +
          '<input type="checkbox"> ' + theUILang.Delete_data + '</label></div>');
        plugin.eraseWithDataLoaded = true;
      }

      if (thePlugins.isInstalled('throttle')) {
        plugin.throttleLoaded = true;
        plugin.loadThrottle();
      }

      if (thePlugins.isInstalled('ratio')) {
        plugin.ratioGroupsLoaded = true;
        plugin.loadRatio();
      }

      if (thePlugins.isInstalled('_getdir')) {
        $('#dirEditBlock').append('<input type="button" class="btn btn-outline-secondary btn-sm" id="showGetDir" onclick="mobile.showGetDir();" value="...">');
        $('#dataDirEditBlock').append('<input type="button" class="btn btn-outline-secondary btn-sm" id="showGetDirDataDir" onclick="mobile.showGetDir(\'#datadir_edit\');" value="...">');
      }

      if (thePlugins.isInstalled('datadir')) {
        plugin.dataDirLoaded = true;
      }

      if (thePlugins.isInstalled('seedingtime')) {
        plugin.seedingtimeLoaded = true;
        plugin.loadSeedingTime();
      }

      // One-shot fetch for the initial paint; periodic refreshes arrive
      // through the theWebUI.addTorrents hook above
      plugin.update(true);
    }
  });
};

plugin.onLangLoaded = function() {
  $('#filterStatusHeader').text(theUILang.Status);
  $('#filterLabelsHeader').text(theUILang.Labels);
  $('#filterTrackersHeader').text(theUILang.Trackers);
  $('#filterDone').text(theUILang.ok);
  $('#filterClear').text(theUILang.ClearButton);
  plugin.updateFilterButton($('.torrentBlock'));

  $('#detailsDetailsTab a').text(theUILang.General);
  $('#detailsTrackers a').text(theUILang.Trackers);
  $('#detailsFiles a').text(theUILang.Files);
  $('#detailsPeers a').text(theUILang.Peers);

  $('#torrentDetails table tr').each(function(n, v) {
    $(v).children('td:first').text(theUILang[detailsIdToLangId[v.id]]);
  });

  $('#dlLimit').parent().children('label').children('h5').text(theUILang.Glob_max_downl);
  $('#ulLimit').parent().children('label').children('h5').text(theUILang.Global_max_upl);
  $('#accentSelect').parent().children('label').children('h5').text('Accent color');
  $('#serverInfoHeader').text('Server');
  $('#verRutorrent td:first').text('ruTorrent');
  $('#verRtorrent td:first').text('rTorrent');
  $('#portIpv4 td:first').text('IPv4');
  $('#portIpv6 td:first').text('IPv6');
  $('#openStatus td:first').text('Open');
  $('#diskSpace td:first').text(theUILang.Free_Disk_Space);
  $('#speedLimitsOk').text(theUILang.ok);
  $('#speedLimitsCancel').text(theUILang.Cancel);

  $('#torrentFile').text(theUILang.Torrent_file+':');
  $('#addUrl').text(theUILang.add_url);
  $('#dir_edit').attr('placeholder', theUILang.Base_directory);
  $('#tadd_label').attr('placeholder', theUILang.Label);
  $('#url').attr('placeholder', theUILang.Torrent_URL_multiline);
  $('#taddReturnSelect').text(theUILang.Return_select_label);

  $('#deleteOk').text(theUILang.ok);
  $('#deleteCancel').text(theUILang.Cancel);

  $('#peerIp').attr('placeholder', theUILang.peerAddLabel);
  $('#peerAddBtn').text(theUILang.peerAdd);
  $('#peerBanBtn').text(theUILang.peerBan);
  $('#peerKickBtn').text(theUILang.peerKick);
  $('#peerSnubBtn').text(theUILang.peerSnub);

  $('#dataDirHeader').text(theUILang.datadirDlgCaption || theUILang.Save_path);
  $('#dataDirAddPath').append(' ' + theUILang.Dont_add_tname);
  $('#dataDirMove').append(' ' + (theUILang.DataDirMove || ''));
  $('#dataDirFastResume').append(' ' + theUILang.doFastResume);
  $('#dataDirOk').text(theUILang.ok);
  $('#dataDirCancel').text(theUILang.Cancel);

  $('#sortAsc').append(' ' + theUILang.acs);
  $('#sortDesc').append(' ' + theUILang.decs);
  $('#sortOption').parent().children('label').children('h5').text(theUILang.SortTorrents);
  $('#sortOk').text(theUILang.ok);
  $('#sortCancel').text(theUILang.Cancel);

  $('#peersTable th').each(function(n, v) {
    $(v).text(theUILang[peersIdToLangId[v.id]]);
  });
};

/**
* jQuery.browser.mobile will be true if the browser is a mobile device
**/
jQuery.browser = jQuery.browser || {};
// Phones: explicit engine hint where available (Chromium), otherwise the
// MDN-recommended user agent tokens, which cover all current phone browsers
jQuery.browser.mobile =
  (navigator.userAgentData && navigator.userAgentData.mobile === true) ||
  /Mobi|iP(hone|od)|Opera Mini/i.test(navigator.userAgent);

if ((plugin.tabletsDetect) && (!jQuery.browser.mobile)) {
  jQuery.browser.mobile =
    // Legacy tablet user agents
    /android|ipad|playbook|silk/i.test(navigator.userAgent) ||
    // Modern iPadOS reports a desktop Macintosh user agent; real Macs
    // report maxTouchPoints of 0, iPads report 5
    (/Macintosh/i.test(navigator.userAgent) && navigator.maxTouchPoints > 1) ||
    // Touch-primary devices without hover (excludes touch-screen laptops,
    // whose primary pointer is a fine, hover-capable mouse)
    (!!window.matchMedia && window.matchMedia('(pointer: coarse) and (hover: none)').matches);
}

// Global alias used by the inline handlers in mobile.html and generated markup
window.mobile = plugin;
plugin.disableOthers();
