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
	var mouseEvent = document.createEvent("MouseEvent");
	mouseEvent.initMouseEvent(type, true, true, window, 1, touch.screenX, touch.screenY, touch.clientX, touch.clientY,
		false, false, false, false, 0, null);
	touch.target.dispatchEvent(mouseEvent);
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
		if($(event.changedTouches[0].target).is("select"))
			return;
		var touch = plugin.dispatchMouse(event,"mousemove");
		window.setTimeout( function() { plugin.dispatchMouse(event,"mousedown"); }, 0 );
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
		plugin.curMouse = { x: touch.screenX, y: touch.screenY };
	}
	event.preventDefault();
	return(false);
}

plugin.touchMove = function(event)
{
	if(event.changedTouches.length)
	{
		var touch = plugin.dispatchMouse(event,"mousemove");
		if(plugin.rightClick && 
			(plugin.rightClick.target!=touch.target))
			plugin.cancelMouseUp = false;
		if(plugin.curMouse)
		{
			var delta = { x:touch.screenX-plugin.curMouse.x, y:touch.screenY-plugin.curMouse.y };
			if(delta.x || delta.y)
			{
				var target = $(touch.target);
				var mode = { x: true, y: true };
				while(target.length)
				{
					if(target.css("overflow")=="auto")
						break;
					else
					if(target.css("overflow-x")=="auto")
					{
						mode.y = false;
						break;
					}
					else
					if(target.css("overflow-y")=="auto")
					{
						mode.x = false;
						break;
					}
                        	        target = target.parent();
				}
				if(target.length)
				{
					target = target.get(0);
					if(mode.x)
						target.scrollLeft = target.scrollLeft-delta.x;
					if(mode.y)
						target.scrollTop = target.scrollTop-delta.y;
					var scrollEvent = document.createEvent("HTMLEvents");
					scrollEvent.initEvent( 'scroll', true, true )
					target.dispatchEvent(scrollEvent);
				}
			}
		}
		plugin.curMouse = { x: touch.screenX, y: touch.screenY };

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

if($.support.touchable) 
{
	document.addEventListener("touchstart", plugin.touchStart, false);
	document.addEventListener("touchmove", plugin.touchMove, false);
	document.addEventListener("touchend", plugin.touchEnd, false);
}