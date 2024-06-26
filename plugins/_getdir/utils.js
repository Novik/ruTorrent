function filterDir(e) {
	if (e.key === "Escape") {
		e.target.value = "";
	}
	let keyword = e.currentTarget.value.toUpperCase();
	let dirs = document.querySelector(".rmenuobj").querySelectorAll("div");
	for (let i = 0; i < dirs.length; i++) {
		if (dirs[i]) {
			let txtValue = dirs[i].textContent || dirs[i].innerText;
			dirs[i].style.display = txtValue.toUpperCase().indexOf(keyword) > -1 ? "" : "none";
		}
	}
}

document.getElementById("dir-search-bar").addEventListener("keyup", filterDir);
document.addEventListener("readystatechange", () => {
	if (document.readyState === "complete") {
		const browser = new browserDetect();
		if (browser.isFirefox) {
			// A `setTimeout()` function is used here for browser compatability,
			// as a plain `focus()` wouldn't work on FireFox.
			setTimeout(() => {
				document.getElementById("dir-search-bar").focus();
			}, 100); 
		} else {
			// This has been tested on Chrome and MS Edge, both working fine.
			document.getElementById("dir-search-bar").focus();
		}
	}
});
