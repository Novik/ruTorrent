/*
 * _noty2 -- (c) 2013 Davor Babic <davor@davor.se>
 * A drop-in replacement for the rutorrent plugin _noty.
 *
 * https://github.com/davorb/_noty2
 *
 * Licensed under the MIT licenses:
 * http://www.opensource.org/licenses/mit-license.php
 */
(function($) {
  $.noty = function(options) {
    var message, options;
    message = options.text ? options.text : options;
    options = {
      icon: 'plugins/_noty2/icon.png',
      body: message
    };

    if ('Notification' in window) {
      if (Notification.permission === 'granted' ||
          (('webkitNotifications' in window &&
            window.webkitNotifications.checkPermission() === 0))) {
        new Notification('rutorrent', options); // 'plugins/_noty2/icon.png',
      } else if (Notification.permission !== 'denied') {
        Notification.requestPermission(function(permission) {
          if (!('permission' in Notification)) { // for Chrome
            Notification.permission = permission;
          }

          if (permission === 'granted') {
            new Notification('rutorrent', options);
          }
        });
      }
    }
  };
})(jQuery);
