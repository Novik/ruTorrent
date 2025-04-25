function stripTimestamp(line) {
        return line.replace(/\[[^\]]*\]\s*/, '');
}

function fetchLogLines(initialLoad = false) {
        fetch('php/getlog.php')
                .then(response => {
                        if (!response.ok) throw new Error('Network response was not ok');
                        return response.text();
                })
                .then(text => {
                        const lines = text.trim().split('\n');

                        lines.forEach(line => {
                                const cleanLine = stripTimestamp(line);
                                noty(cleanLine, "info");
                        });
                })
                .catch(err => {
                        noty("Log fetch error: " + err.message,"error");
                });
}

fetchLogLines(true);
