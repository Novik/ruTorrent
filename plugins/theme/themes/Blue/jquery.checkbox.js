/**
 * jQuery custom checkboxes
 * 
 * Copyright (c) 2008 Khavilo Dmitry (http://widowmaker.kiev.ua/checkbox/)
 * Licensed under the MIT License:
 * http://www.opensource.org/licenses/mit-license.php
 *
 * @version 1.3.0 Beta 1
 * @author Khavilo Dmitry
 * @mailto wm.morgun@gmail.com
**/

(function($){
	/* Little trick to remove event bubbling that causes events recursion */
	var CB = function(e)
	{
		if (!e) var e = window.event;
		e.cancelBubble = true;
		if (e.stopPropagation) e.stopPropagation();
	};
	
	$.fn.checkbox = function(options) {
		/* IE6 background flicker fix */
		try	{ document.execCommand('BackgroundImageCache', false, true);	} catch (e) {}
		
		/* Default settings */
		var settings = {
			cls: 'jquery-safari-checkbox',  /* checkbox  */
			empty: './plugins/theme/themes/Blue/images/empty.png'  /* checkbox  */
		};
		
		/* Processing settings */
		settings = $.extend(settings, options || {});
		
		/* Adds check/uncheck & disable/enable events */
		var addEvents = function(object)
		{
			var checked = object.checked;
			var disabled = object.disabled;
			var $object = $(object);
			
			if ( object.stateInterval )
				clearInterval(object.stateInterval);
			
			object.stateInterval = setInterval(
				function() 
				{
					if ( object.disabled != disabled )
						$object.trigger( (disabled = !!object.disabled) ? 'disable' : 'enable');
					if ( object.checked != checked )
						$object.trigger( (checked = !!object.checked) ? 'check' : 'uncheck');
				}, 
				10 /* in miliseconds. Low numbers this can decrease performance on slow computers, high will increase responce time */
			);
			return $object;
		};
		//try { console.log(this); } catch(e) {}
		
		/* Wrapping all passed elements */
		return this.each(function() 
		{
			var ch = this; /* Reference to DOM Element*/
			var $ch = addEvents(ch); /* Adds custom events and returns, jQuery enclosed object */
			
			/* Removing wrapper if already applied  */
			if (ch.wrapper) ch.wrapper.remove();
			
			/* Creating wrapper for checkbox and assigning "hover" event */
			ch.wrapper = $('<span class="' + settings.cls + '"><span class="mark"><img src="' + settings.empty + '" /></span></span>');
			ch.wrapperInner = ch.wrapper.children('span:eq(0)');
			ch.wrapper.
				on('mouseenter', function(e) { ch.wrapperInner.addClass(settings.cls + '-hover');CB(e); }).
				on('mouseleave', function(e) { ch.wrapperInner.removeClass(settings.cls + '-hover');CB(e); });
			
			/* Wrapping checkbox */
			$ch.css({position: 'absolute', zIndex: -1, visibility: 'hidden'}).after(ch.wrapper);
			
			/* Ttying to find "our" label */
			var label = false;
//			if ($ch.attr('id'))
//			{
//				label = $('label[for='+$ch.attr('id')+']');
//				if (!label.length) label = false;
//			}
			if (!label)
			{
				/* Trying to utilize "closest()" from jQuery 1.3+ */
				label = $ch.closest ? $ch.closest('label') : $ch.parents('label:eq(0)');
				if (!label.length) label = false;
			}
			/* Labe found, applying event hanlers */
			if (label)
			{
				label.on('mouseenter', function(e) { ch.wrapper.trigger('mouseover', [e]); }).
					on('mouseleave', function(e) { ch.wrapper.trigger('mouseout', [e]); });
				label.on('click', function(e) { $ch.trigger('click',[e]); CB(e); return false;});
			}
			ch.wrapper.on('click', function(e) { $ch.trigger('click',[e]); CB(e); return false;});
			$ch.on('click', function(e) { CB(e); });
			$ch.on('disable', function() { ch.wrapperInner.addClass(settings.cls+'-disabled');}).on('enable', function() { ch.wrapperInner.removeClass(settings.cls+'-disabled');});
			$ch.on('check', function() { ch.wrapper.addClass(settings.cls+'-checked' );}).on('uncheck', function() { ch.wrapper.removeClass(settings.cls+'-checked' );});
			
			/* Disable image drag-n-drop for IE */
			$('img', ch.wrapper).on('dragstart', function () {return false;}).on('mousedown', function () {return false;});
			
			/* Firefox antiselection hack */
			if ( window.getSelection )
				ch.wrapper.css('MozUserSelect', 'none');
			
			/* Applying checkbox state */
			if ( ch.checked )
				ch.wrapper.addClass(settings.cls + '-checked');
			if ( ch.disabled )
				ch.wrapperInner.addClass(settings.cls + '-disabled');			
		});
	}
})(jQuery);
