function stripTimestamp(line) {
    return line.replace(/^\[[^\]]*\]\s*/, '');
}

function fetchLogLines() {
    fetch('plugins/log_history/action.php')
        .then(response => {
            if (response.status === 204) {
                return Promise.reject({ disabled: true });
            }
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            const logs = data.logs || data;

            if (!logs.length) {
                return;
            }

            plugin._replaying = true;
            var lcont = document.getElementById('lcont');
            if (lcont) {
                // Save current content (e.g. "WebUI started")
                var currentContent = lcont.innerHTML;
                // Clear and rebuild in order
                lcont.innerHTML = '';

                // Header
                var header = document.createElement('span');
                header.className = 'std';
                header.textContent = '========== restored log ==========';
                lcont.appendChild(header);

                // Restored entries
                logs.forEach(entry => {
                    var cleanMsg = stripTimestamp(entry.message || '');
                    var ts = entry.timestamp
                        ? '[' + theConverter.date(entry.timestamp) + ']'
                        : '';
                    var span = document.createElement('span');
                    span.className = 'std';
                    span.textContent = ts + ' ' + cleanMsg;
                    lcont.appendChild(span);
                });

                // Footer
                var footer = document.createElement('span');
                footer.className = 'std';
                footer.textContent = '========== restored log ==========';
                lcont.appendChild(footer);

                // Re-append current session content
                lcont.innerHTML += currentContent;

                lcont.scrollTop = lcont.scrollHeight;
            }
            plugin._replaying = false;
        })
        .catch(err => {
            if (err.disabled) return;
            console.error("Log fetch error:", err.message);
        });
}

function sendLogToServer(msg, status) {
    var timestamp = Math.floor(Date.now() / 1000);
    fetch('plugins/log_history/log_history.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'message=' + encodeURIComponent(msg)
            + '&status=' + encodeURIComponent(status)
            + '&timestamp=' + timestamp
    })
    .catch(error => {
        console.log("Saving Log failed:", error);
    });
}

plugin._replaying = false;

plugin.init = function () {
    plugin._originalNoty = window.noty;
    const originalNoty = window.noty;

    window.noty = function(msg, status, noTime) {
        originalNoty(msg, status, noTime);
        if (!plugin._replaying) {
            sendLogToServer(msg, status || 'info');
        }
    };

    fetchLogLines();
    plugin.markLoaded();
};

plugin.onRemove = function () {
};

plugin.init();
