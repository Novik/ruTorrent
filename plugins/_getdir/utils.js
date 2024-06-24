function filterDir(e) {
	if (e.key === "Escape") {
		e.target.value = "";
	}
	let keyword = e.currentTarget.value.toUpperCase();
	dirs = document.querySelector(".rmenuobj").querySelectorAll("div");
	for (let i = 0; i < dirs.length; i++) {
		dir = dirs[i];
		if (dir) {
			txtValue = dir.textContent || dir.innerText;
			dirs[i].style.display = txtValue.toUpperCase().indexOf(keyword) > -1 ? "" : "none";
		}
	}
}

document.querySelector("input.filter-dir").addEventListener("keyup", filterDir);
