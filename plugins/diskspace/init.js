plugin.loadMainCSS()

plugin.setValue = function( full, free )
{
        var percent = iv(full ? (full-free)/full*100 : 0);
        if(percent>100)
	        percent = 100;
	$("#meter-disk-value").width( percent+"%" ).css( { "background-color": (new RGBackground()).setGradient(this.prgStartColor,this.prgEndColor,percent).getColor(),
		visibility: !percent ? "hidden" : "visible" } );
	$("#meter-disk-text").text(percent+'%').attr("title", theConverter.bytes(free)+"/"+theConverter.bytes(full));
}

plugin.init = function()
{
	this.prgStartColor = new RGBackground("#99D699");
	this.prgEndColor = new RGBackground("#E69999");
	var row = $("#firstStatusRow").get(0);
	var td = row.insertCell(0);
	$(td).attr("id","meter-disk-td").append(
		$("<div>").attr("id","meter-disk-holder").
			append( $("<span></span>").attr("id","meter-disk-text").css({overflow: "visible"}) ).
			append( $("<div>").attr("id","meter-disk-value").css({ visibility: "hidden", float: "left" }).width(0).html("&nbsp;") ) );
	plugin.check = function()
	{
		var AjaxReq = jQuery.ajax(
		{
			type: "GET",
			timeout: 3000,
		        async : true,
		        cache: false,
			url : "plugins/diskspace/action.php",
			dataType : "json",
			cache: false,
			success : function(data)
			{
				plugin.setValue( data.total, data.free );
			}
		});
	};
	plugin.check();
	plugin.reqId = theRequestManager.addRequest( "ttl", null, plugin.check );
	this.markLoaded();
};

plugin.onRemove = function()
{
	$("#meter-disk-td").remove();
	theRequestManager.removeRequest( "ttl", plugin.reqId );
}

plugin.init();