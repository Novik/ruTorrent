function stripTimestamp(line) {
        return line.replace(/^\[[^\]]*\]\s*/, '');
}

function fetchLogLines(initialLoad = false) {
        fetch('plugins/log_history/log_history.php')
        .then(response => {
		if (response.status === 204) {
			console.log("Log is disabled by server (204 No Content).");
			return Promise.reject({disabled: true});
		}
                if (!response.ok) throw new Error('Network response was not ok');
                        return response.json();
                })
        .then(data => {
		if (!data || data.length === 0) {
			console.log("Log is disabled or empty.");
			return;
		}
                console.log('Received data:', data);
                data.forEach(entry => {
                        const rawMsg = entry.message || '';
                        const cleanMsg = stripTimestamp(rawMsg);
                        const status = entry.status || 'info';
                        noty(cleanMsg, status);
                });
        })
        .catch(err => {
		if (err.disabled) {
			return;
		}
                noty("Log fetch error: " + err.message, "error");
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

plugin.init = function () {
	setTimeout(() => {
		fetchLogLines(true);
	}, 3000);
	
	const originalLog = window.log;
    window.log = function(text, noTime, divClass, force) {
        originalLog(text, noTime, divClass, force);
        sendLogToServer(text, divClass || 'info');
    };
	
    plugin.markLoaded();
};

plugin.onRemove = function () {
};

plugin.init();
