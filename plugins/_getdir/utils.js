function filterDir(e) {
	if (e.key === "Escape") {
		e.target.value = "";
	}
	let keyword = e.currentTarget.value.toUpperCase();
	dirs = document.querySelector(".rmenuobj").querySelectorAll("div");
	for (let i = 0; i < dirs.length; i++) {
		dir = dirs[i]
    if (dir) {
      txtValue = dir.textContent || dir.innerText;
      if (txtValue.toUpperCase().indexOf(keyword) > -1) {
        dirs[i].style.display = "";
      } else {
        dirs[i].style.display = "none";
      }
    }
	}
}

document.querySelector("input.filter-dir").addEventListener("keyup", filterDir);
