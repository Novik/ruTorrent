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

		// macOS is the platform where ctrl with the primary mouse button is the
		// secondary click. navigator.platform cannot answer that on its own:
		// iPadOS reports "MacIntel" as well, and ctrl is no secondary click
		// there. No Mac has a touchscreen, so a touch-capable "Mac" is an iPad.
		const uaPlatform = navigator.userAgentData?.platform;
		this.isMac = uaPlatform
			? (uaPlatform === "macOS")
			: (/Mac/.test(navigator.platform || "") && !this.isiOS && !(navigator.maxTouchPoints > 1));

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

	// True while a mouse event could still turn out to be either click: on
	// macOS the browser fires contextmenu for ctrl with the primary button,
	// between the mousedown and the mouseup, so neither of those two is known
	// to be a primary click until the gesture ends.
	maybeSecondaryClick(e) {
		const primaryButton = (e.which === 1) || (e.button === 0);
		return !!(
			this.isMac && e.ctrlKey && primaryButton &&
			((e.type === "mousedown") || (e.type === "mouseup"))
		);
	}
}
