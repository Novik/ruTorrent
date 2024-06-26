class browserDetect {
	constructor() {
		var ua = navigator.userAgent.toLowerCase();
		this.isiOS = /(iPad|iPhone|iPod)/.test(navigator.userAgent);
		this.isGecko = (ua.indexOf("gecko") !== -1 && ua.indexOf("safari") === -1);
		this.isAppleWebKit = (ua.indexOf("webkit") !== -1);
		this.isKonqueror = (ua.indexOf("konqueror") !== -1);
		this.isOpera = (ua.indexOf("opera") !== -1);
		this.isIE = (ua.indexOf("msie") !== -1 && !this.isOpera && (ua.indexOf("webtv") === -1));
		this.isMozilla = (this.isGecko && ua.indexOf("gecko/") + 14 === ua.length);
		this.isFirefox = (ua.indexOf("firefox/") !== -1);
		this.isChrome = (ua.indexOf("chrome/") !== -1);
		this.isMidori = (ua.indexOf("midori/") !== -1);
		this.isSafari = (ua.indexOf("safari") !== -1) && !this.isChrome;
		this.versionMinor = parseFloat(navigator.appVersion);
		if (this.isGecko && !this.isMozilla && !this.isKonqueror)
			this.versionMinor = parseFloat(ua.substring(ua.indexOf("/", ua.indexOf("gecko/") + 6) + 1));

		else if (this.isMozilla)
			this.versionMinor = parseFloat(ua.substring(ua.indexOf("rv:") + 3));

		else if (this.isIE && this.versionMinor >= 4)
			this.versionMinor = parseFloat(ua.substring(ua.indexOf("msie ") + 5));

		else if (this.isKonqueror)
			this.versionMinor = parseFloat(ua.substring(ua.indexOf("konqueror/") + 10));

		else if (this.isSafari || this.isChrome)
			this.versionMinor = parseFloat(ua.substring(ua.lastIndexOf("safari/") + 7));

		else if (this.isOpera)
			this.versionMinor = parseFloat(ua.substring(ua.indexOf("opera") + 6));
		if (this.isIE && document.documentMode)
			this.versionMajor = document.documentMode;

		else
			this.versionMajor = parseInt(this.versionMinor);

		this.mode = document.compatMode ? document.compatMode : "BackCompat";
		this.isIE7x = (this.isIE && this.versionMajor === 7);
		this.isIE7up = (this.isIE && this.versionMajor >= 7);
		this.isIE8up = (this.isIE && this.versionMajor >= 8);
		this.isFirefox3x = (this.isFirefox && this.versionMajor === 3);

		var h = document.getElementsByTagName("html")[0];
		var c = h.className;
		if (this.isIE)
			h.className = "ie" + " ie" + this.versionMajor + " " + c;

		else if (this.isOpera)
			h.className = ("opera " + c);

		else if (this.isKonqueror)
			h.className = ("konqueror " + c);

		else if (this.isChrome)
			h.className = ("webkit chrome " + c);

		else if (this.isAppleWebKit)
			h.className = ("webkit safari " + c);

		else if (this.isGecko)
			h.className = ("gecko " + c);
	}
}
