class browserDetect {
	constructor() {
		var ua = navigator.userAgent.toLowerCase();
		this.isiOS = /(iPad|iPhone|iPod)/.test(navigator.userAgent);
		this.isAppleWebKit = (ua.indexOf("webkit") !== -1);
		this.isKonqueror = (ua.indexOf("konqueror") !== -1);
		this.isOpera = (ua.indexOf("opera") !== -1);
		this.isFirefox = (ua.indexOf("firefox/") !== -1);
		this.isChrome = (ua.indexOf("chrome/") !== -1);
		this.isSafari = (ua.indexOf("safari") !== -1) && !this.isChrome;

		var h = document.getElementsByTagName("html")[0];
		var c = h.className;
		if (this.isOpera)
			h.className = ("opera " + c);

		else if (this.isKonqueror)
			h.className = ("konqueror " + c);

		else if (this.isChrome)
			h.className = ("webkit " + c);

		else if (this.isAppleWebKit)
			h.className = ("webkit " + c);
	}
}
