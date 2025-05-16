function stripTimestamp(line) {
        return line.replace(/^\[[^\]]*\]\s*/, '');
}

function fetchLogLines(initialLoad = false) {
    fetch('plugins/log_history/action.php')
        .then(response => {
            if (response.status === 204) {
                console.log("Log is disabled by server (204 No Content).");
                return Promise.reject({ disabled: true });
            }
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            const logs = data.logs || data; // backward compatible
            const style = data.load_style || 'noty'; // default

            if (!logs.length) {
                console.log("Log is empty or not configured.");
                return;
            }

            logs.forEach(entry => {
                const rawMsg = entry.message || '';
                const cleanMsg = stripTimestamp(rawMsg);
                const status = entry.status || 'info';

                if (style === 'log') {
                    log(cleanMsg, false, 'std');
                } else {
                    noty(cleanMsg, status);
                }
            });
        })
        .catch(err => {
            if (err.disabled) return;
            if (typeof noty === 'function') {
                noty("Log fetch error: " + err.message, "error");
            } else {
                console.error("Log fetch error:", err.message);
            }
        });
}

function sendLogToServer(msg, status) {
    fetch('plugins/log_history/log_history.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `message=${encodeURIComponent(msg)}&status=${encodeURIComponent(status)}`
    })
        .then(response => {
            if (response.status === 204) return;
            return response.json();
        })
        .then(data => {
            if (data) console.log("Log saved to server:", data);
        })
        .catch(error => {
            console.log("Saving Log failed:", error);
        });
}

window._log_history_status = null;

plugin.init = function () {
    const originalLog = window.log;
    window.log = function(text, noTime, divClass, force) {
        originalLog(text, noTime, divClass, force);
        const status = window._log_history_status || divClass || 'info';
        sendLogToServer(text, status);
        window._log_history_status = null;
    };
    const originalNoty = window.noty;
    window.noty = function(msg, status, noTime) {
        window._log_history_status = status;

        if (typeof originalNoty === 'function') {
            originalNoty(msg, status, noTime);
        } else {
            log(msg, noTime, status);
        }
    };
    setTimeout(() => {
        fetchLogLines(true);
    }, 3000);

    plugin.markLoaded();
};

plugin.onRemove = function () {
};

plugin.init();
