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
                var currentNodes = [];
				while (lcont.firstChild) {
					currentNodes.push(lcont.firstChild);
					lcont.removeChild(lcont.firstChild);
				}

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
                currentNodes.forEach(node => lcont.appendChild(node));

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
    plugin._originalLog = window.log;
    const originalLog = window.log;

    window.log = function(text, noTime, divClass, force) {
        originalLog(text, noTime, divClass, force);
        if (!plugin._replaying) {
            sendLogToServer(text, status || 'info');
        }
    };

    fetchLogLines();
    plugin.markLoaded();
};

plugin.onLangLoaded = function () {
    this.registerTopMenu(20, theUILang.ruLogHistoryMenuName, theWebUI.showLogHistory);

    const container = $("<div>").css({
        width: "100%",
        "max-width": "900px",
        height: "600px",
        "overflow-y": "hidden",
        "display": "flex",
        "flex-direction": "column"
    }).attr("id", "logHistoryContainer");

    const buttonBar = $("<div>").attr("id", "action-buttons").addClass("action-buttons").css({
        "padding": "8px",
        "flex-shrink": "0"
    });

    const clearBtn = $("<button>")
        .attr("id", "clearLogBtn")
        .text(theUILang.ruLogHistoryClear || "Clear Log")
        .css({
        });

    buttonBar.append(clearBtn);
    container.append(buttonBar);

    theDialogManager.make("dlgLogHistory", theUILang.ruLogHistoryMenuName, [container], false);
};

theWebUI.showLogHistory = function () {
    theDialogManager.show("dlgLogHistory");

    setTimeout(() => {
        const dlg = document.getElementById("dlgLogHistory");
        if (!dlg) {
            console.error("Log History dialog not found.");
            return;
        }

        const container = $("#logHistoryContainer", dlg);
        if (container.length === 0) {
            console.error("logHistoryContainer not found in dialog.");
            return;
        }

        // Lisa nupule sündmus (kui pole veel lisatud)
        const clearBtn = container.find("#clearLogBtn");
        if (clearBtn.length > 0 && !clearBtn.data("bound")) {
            clearBtn.on("click", () => clearLog(container));
            clearBtn.data("bound", true);
        }

        // Lae logid
        loadLogHistory(container);

    }, 100);
};

function loadLogHistory(container) {
    // Leia või loo logi ala AINULT KORRA
    let logArea = container.find("#logContent");
    if (logArea.length === 0) {
        logArea = $("<div>").attr("id", "logContent").css({
            "margin-top": "10px",
            "flex": "1",
            "overflow-y": "auto"
        });
        container.append(logArea); // Lisa nupu alla
    }

    // KUSTUTA AINULT LOGI SISU, MITTE KOGU CONTAINERIT
    logArea.empty().text("Loading...");

    fetch('plugins/log_history/log_history.php?all=1')
        .then(r => {
            if (r.status === 204) throw new Error("Log is disabled");
            if (!r.ok) throw new Error('Network error');
            return r.json();
        })
        .then(data => {
            const logs = data.logs || data;
            logArea.empty(); // Ainult siin kustutame

            if (!logs || logs.length === 0) {
                logArea.text("Log is empty.");
                return;
            }

            logs.forEach((entry, index) => {
                const msg = entry.message || '';
                const status = (entry.status || 'info').toUpperCase();
                const num = String(index + 1).padStart(3, '0');

                const line = $("<div>").css({
                    "margin-bottom": "4px",
                    "border-radius": "2px",
                    "display": "flex",
                    "align-items": "flex-start"
                });

                const dotSpan = $("<span>").text("●").css({
                    "color": getStatusColor(status),
					"display": "flex",
                    "font-size": "2.0em",
					"line-height": "1",
					"align-items": "center",
					"justify-content": "center",
					"padding": "0 0 0 5px"
                });
				
				const numSpan = $("<span>").text("["+ num + "] ").css({
                    "flex": "0 0 30px",
                    "padding": "3px 0 3px 5px"
                });

                const msgSpan = $("<span>").text(msg).css({
                    "flex": "1",
                    "padding": "3px 8px 3px 0",
                    "white-space": "pre-wrap",
                    "word-break": "break-word"
                });

                line.append(dotSpan, numSpan, msgSpan);
                logArea.append(line);
            });

            setTimeout(() => container.scrollTop(container[0].scrollHeight), 50);
        })
        .catch(err => {
            logArea.text("Error: " + err.message).css("color", "red");
        });
}

function getStatusColor(status) {
    const colors = {
        ERROR:   'red',
        WARNING: 'blue',
        SUCCESS: 'green',
        INFO:    'black'
    };
    return colors[status] || 'black';
}

function clearLog(container) {
    if (!confirm(theUILang.ruLogHistoryConfirmClear || "Clear entire log?")) return;

    fetch('plugins/log_history/log_history.php?clear=1')
        .then(r => r.ok ? r.json() : Promise.reject("HTTP " + r.status))
        .then(() => {
            loadLogHistory(container);
        })
        .catch(err => alert("Failed to clear: " + err.message));
}

plugin.onRemove = function () {
	theDialogManager.hide("dlgLogHistory");
};

plugin.onRemove = function () {
};

plugin.init();
