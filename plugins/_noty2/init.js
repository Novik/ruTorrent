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
      function showNotification() {
        var notification = new Notification('rutorrent', options);
        // Close the notification in Chrome after 3s
        setTimeout(function() {
          notification.close();
        }, 3000);
      }

      options.icon = 'images/favicon.ico';
      options.body = options.text ? options.text : options;

      if (Notification.permission === 'granted' ||
          (('webkitNotifications' in window &&
            window.webkitNotifications.checkPermission() === 0))) {
        showNotification();
      } else if (Notification.permission !== 'denied') {
        Notification.requestPermission(function(permission) {
          if (!('permission' in Notification)) { // for Chrome
            Notification.permission = permission;
          }

          if (permission === 'granted') {
            showNotification();
          }
        });
      }
    };
  }
})(jQuery);
