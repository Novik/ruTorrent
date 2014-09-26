/*
 * _noty2 -- (c) 2013 Davor Babic <davor@davor.se>
 * A drop-in replacement for the rutorrent plugin _noty.
 *
 * Licensed under the MIT licenses:
 * http://www.opensource.org/licenses/mit-license.php
 */
(function($) {
  if ('Notification' in window) {
    $.noty = function(options) {
      options.icon = 'favicon.ico';
      options.body = options.text ? options.text : options;

      if (Notification.permission === 'granted' ||
          (('webkitNotifications' in window &&
            window.webkitNotifications.checkPermission() === 0))) {
        new Notification('rutorrent', options);
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
    };
  }
})(jQuery);
