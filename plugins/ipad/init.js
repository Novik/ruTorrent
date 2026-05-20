$.extend($.support,
{
	touchable: 'createTouch' in document
});

let target = null;
let startPos = null;
let longPressTimer = null;
let suppressNextClick = false;
let suppressSafetyTimer = null;

const clearLongPress = () => {
	if (longPressTimer) {
		clearTimeout(longPressTimer);
		longPressTimer = null;
	}
};

const armSuppress = () => {
	suppressNextClick = true;
	if (suppressSafetyTimer) clearTimeout(suppressSafetyTimer);
	suppressSafetyTimer = setTimeout(() => {
		suppressNextClick = false;
		suppressSafetyTimer = null;
	}, 500);
};

const onTouchStart = (e) => {
	if (e.touches.length !== 1) {
		clearLongPress();
		return;
	}
	const t = e.touches[0];
	target = t.target;
	startPos = { x: t.clientX, y: t.clientY };
	longPressTimer = setTimeout(() => {
		longPressTimer = null;
		if (!target) return;
		target.dispatchEvent(new MouseEvent("contextmenu", {
			bubbles: true, cancelable: true, view: window, button: 2,
			screenX: t.screenX, screenY: t.screenY,
			clientX: t.clientX, clientY: t.clientY,
		}));
		armSuppress();
	}, 600);
};

const onTouchMove = (e) => {
	if (!startPos) return;
	const t = e.touches[0];
	if (Math.abs(t.clientX - startPos.x) > 8 || Math.abs(t.clientY - startPos.y) > 8)
		clearLongPress();
};

const onTouchEnd = () => {
	clearLongPress();
	target = null;
	startPos = null;
};

const suppressIfFlagged = (e, clearFlag) => {
	if (suppressNextClick) {
		if (clearFlag) {
			suppressNextClick = false;
			if (suppressSafetyTimer) {
				clearTimeout(suppressSafetyTimer);
				suppressSafetyTimer = null;
			}
		}
		e.preventDefault();
		e.stopImmediatePropagation();
	}
};

if ($.support.touchable && browser.isSafari) {
	document.addEventListener("touchstart", onTouchStart, { passive: true });
	document.addEventListener("touchmove",  onTouchMove,  { passive: true });
	document.addEventListener("touchend",   onTouchEnd,   { passive: true });
	document.addEventListener("mousedown", (e) => suppressIfFlagged(e, false), true);
	document.addEventListener("mouseup",   (e) => suppressIfFlagged(e, false), true);
	document.addEventListener("click",     (e) => suppressIfFlagged(e, true),  true);
}
