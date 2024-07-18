plugin.loadLang();
plugin.loadMainCSS();

plugin.setValue = function( full, free )
{
        var percent = iv(full ? (full-free)/full*100 : 0);
        if(percent>100)
	        percent = 100;
	$("#meter-disk-value").width( percent+"%" ).css( { "background-color": (new RGBackground()).setGradient(this.prgStartColor,this.prgEndColor,percent).getColor(),
		visibility: !percent ? "hidden" : "visible" } );
	$("#meter-disk-text").text(percent+'%');
	$("#meter-disk-pane").prop("title", theConverter.bytes(free)+"/"+theConverter.bytes(full));

	if($.noty && plugin.allStuffLoaded)
	{
		if((free<plugin.notifySpaceLimit) && !plugin.noty)
			plugin.noty = $.noty(
			{
				text: theUILang.diskNotification, 
				layout : 'bottomLeft',
				type: 'error',
				timeout : false,
				closeOnSelfClick: false
			});
		if((free>plugin.notifySpaceLimit) && plugin.noty)
		{
			$.noty.close(plugin.noty);
			plugin.noty = null;
		}
	}
}

plugin.init = function()
{
	if(getCSSRule("#meter-disk-holder"))
	{
		plugin.prgStartColor = new RGBackground("#99D699");
		plugin.prgEndColor = new RGBackground("#E69999");
		plugin.addPaneToStatusbar(
			"meter-disk-pane",
			$("<div>").append(
				$("<div>").addClass("icon"),
				$("<div>").attr({id: "meter-disk-holder"}).append(
					$("<div>").attr({id: "meter-disk-value"}).width(0),
					$("<div>").attr({id: "meter-disk-text"}),
				),
			),
			0, true,
		);

		plugin.check = function()
		{
			var AjaxReq = jQuery.ajax(
			{
				type: "GET",
				timeout: theWebUI.settings["webui.reqtimeout"],
			        async : true,
			        cache: false,
				url : "plugins/diskspace/action.php",
				dataType : "json",
				cache: false,
				success : function(data)
				{
					plugin.setValue( data.total, data.free );
				},
				complete : function(jqXHR, textStatus)
				{
					plugin.diskTimeout = window.setTimeout(plugin.check,plugin.interval*1000);
				}
			});
		};
		plugin.check();
		plugin.markLoaded();
	}
	else
		window.setTimeout(arguments.callee,500);
};

plugin.onRemove = function()
{
	plugin.removePaneFromStatusbar("meter-disk-pane");
	if(plugin.diskTimeout)
	{
		window.clearTimeout(plugin.diskTimeout);
		plugin.diskTimeout = null;
	}
}

plugin.init();