$.extend($.support,
{
	touchable: 'createTouch' in document
});

plugin.holdMouse = { x:0, y: 0 };

plugin.emulateRightClick = function()
{
	if(( (Math.abs(plugin.rightClick.screenX - plugin.holdMouse.x)<8) &&
		(Math.abs(plugin.rightClick.screenY - plugin.holdMouse.y)<8)))
	{
		var mouseEvent = document.createEvent("MouseEvent");
		mouseEvent.initMouseEvent("contextmenu", true, true, window, 1,
			plugin.rightClick.screenX + 20, plugin.rightClick.screenY + 5,
			plugin.rightClick.clientX + 20, plugin.rightClick.clientY + 5,
			false, false, false, false, 2, null);
		plugin.rightClick.target.dispatchEvent(mouseEvent);
		plugin.cancelMouseUp = true;
	}
	plugin.rightClick = null;
}

plugin.cancelHold = function()
{
	if(plugin.rightClick)
	{
		window.clearTimeout(plugin.holdTimeout);
		plugin.rightClick = null;
	}
}

plugin.startHold = function(touch)
{
	if(!plugin.rightClick)
	{
		plugin.holdMouse = { x: touch.screenX, y: touch.screenY };
		plugin.rightClick = touch;
		plugin.holdTimeout = window.setTimeout(plugin.emulateRightClick, 600);
	}
}

// Safari turns a tap into mousedown/mouseup/click and a double tap into
// dblclick by itself. The listeners below sit on document, where touchstart is
// passive, so the preventDefault() that used to suppress those native events
// was ignored and the mouse events this plugin synthesized arrived on top of
// them: every tap became a double click, and a double tap opened two folders --
// the second landing on whatever row the first navigation had moved under the
// finger. Only the long press is still ours: Safari has no gesture for a right
// click.
plugin.touchStart = function(event)
{
	if(event.changedTouches.length)
	{
		var touch = event.changedTouches[0];
		if($(touch.target).is("select") || $(touch.target).is("input") || $(touch.target).is("button") || $(touch.target).is("label"))
			return;
		plugin.cancelHold();
		plugin.startHold(touch);
	}
}

plugin.touchEnd = function(event)
{
	if(event.changedTouches.length)
	{
		plugin.cancelHold();
		if(plugin.cancelMouseUp)
		{
			plugin.cancelMouseUp = false;
			// The tap that ends a long press has already opened the context
			// menu; keep Safari from also clicking the row underneath it.
			event.preventDefault();
			return(false);
		}
	}
}

if($.support.touchable && browser.isSafari)
{
	document.addEventListener("touchstart", plugin.touchStart, false);
	document.addEventListener("touchend", plugin.touchEnd, false);
}
