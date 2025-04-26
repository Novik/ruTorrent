function stripTimestamp(line) {
        return line.replace(/^\[[^\]]*\]\s*/, '');
}

function fetchLogLines(initialLoad = false) {
        fetch('php/log_history.php')
        .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                        return response.json();
                })
        .then(data => {
                if (data.length === 0) {
                        console.log("Log file is empty.");
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
                noty("Log fetch error: " + err.message, "error");
        });
}

setTimeout(() => {
    fetchLogLines(true);
}, 3000);
