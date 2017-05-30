injectScript(plugin.path+"piecon.min.js");

(function() {
  setInterval(function() {
    var progress = parseFloat($('.selected .meter-text').text().replace('%',''));

    if (!isNaN(progress)) {
      Piecon.setOptions({
        color: '#00853e',
        background: '#fff',
        shadow: '#fff',
        fallback: false
      });

      Piecon.setProgress(progress);
    } else {
      try {
        Piecon.reset();
      } catch (e) {
        console.log('piecon error: problem resetting the icon');
      }
    }
  }, 1400);
})();
