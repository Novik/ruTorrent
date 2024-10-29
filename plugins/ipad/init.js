$.extend($.support, 
{
	touchable: 'createTouch' in document
});

plugin.holdMouse = { x:0, y: 0 };
plugin.curMouse = null;

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

plugin.dispatchMouse = function(event,type)
{
	var touch = event.changedTouches[0];
	touch.timeStamp = $.now();
	window.setTimeout( function() 
	{
		var mouseEvent = document.createEvent("MouseEvent");
		mouseEvent.initMouseEvent(type, true, true, window, 1, touch.screenX, touch.screenY, touch.clientX, touch.clientY,
			false, false, false, false, 0, null);
		touch.target.dispatchEvent(mouseEvent);
	}, 0);
	return(touch);
}

plugin.cancelTarget = function() 
{
	plugin.target = null;
}

plugin.touchStart = function(event)
{
	if(event.changedTouches.length)
	{
		if($(event.changedTouches[0].target).is("select") || $(event.changedTouches[0].target).is("input") || $(event.changedTouches[0].target).is("button") || $(event.changedTouches[0].target).is("label"))
			return;
		plugin.dispatchMouse(event,"mousemove");
		var touch = plugin.dispatchMouse(event,"mousedown");;
		if(plugin.targetTimeout)
			window.clearTimeout(plugin.targetTimeout);
		if(!plugin.target || (plugin.target != touch.target))
		{
			plugin.target = touch.target;
			plugin.targetTimeout = window.setTimeout(plugin.cancelTarget, 600);
			plugin.startHold(touch);
		}
		else 
		{
			if(plugin.target) 
			{
				plugin.cancelTarget();
				plugin.dispatchMouse(event,"click");
				plugin.dispatchMouse(event,"dblclick");
			}
		}
		plugin.curMouse = { x: touch.screenX, y: touch.screenY, timeStamp: $.now() };
	}
	event.preventDefault();
	return(false);
}

plugin.touchEnd = function(event)
{
	if(event.changedTouches.length)
	{
		if(plugin.cancelMouseUp) 
		{
			plugin.cancelMouseUp = false;
			event.preventDefault();
			return(false);
		}
		plugin.cancelHold();
		var touch = plugin.dispatchMouse(event,"mouseup");
		if(plugin.target && (plugin.target == touch.target))
			plugin.dispatchMouse(event,"click");
		plugin.curMouse = null;
	}
}

if($.support.touchable && browser.isSafari)
{
	document.addEventListener("touchstart", plugin.touchStart, false);
	document.addEventListener("touchend", plugin.touchEnd, false);
}
