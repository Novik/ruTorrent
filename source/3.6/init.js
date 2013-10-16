plugin.loadLang();

if(plugin.canChangeMenu())
{
	theWebUI.getSource = function( id )
	{
		$("#srchash").val(id);
		$("#getsource").submit();
	}

	plugin.createMenu = theWebUI.createMenu;
	theWebUI.createMenu = function( e, id )
	{
		plugin.createMenu.call(this, e, id);
		if(plugin.enabled)
		{
			var el = theContextMenu.get( theUILang.Properties );
			if( el )
				theContextMenu.add( el, [theUILang.getSource,  (this.getTable("trt").selCount > 1) || (id.length>40) ? null : "theWebUI.getSource('" + id + "')"] );
		}
	}
}

plugin.onLangLoaded = function()
{
	$(document.body).append($("<iframe name='srcfrm'/>").css({visibility: "hidden"}).attr( { name: "srcfrm", id: "srcfrm" } ).width(0).height(0).load(function()
	{
	        $("#srchash").val('');
		var d = (this.contentDocument || this.contentWindow.document);
		if(d && (d.location.href != "about:blank"))
			try { eval(d.body.textContent ? d.body.textContent : d.body.innerText); } catch(e) {}
	}));
	$(document.body).append(
		$('<form action="plugins/source/action.php" id="getsource" method="get" target="srcfrm">'+
			'<input type="hidden" name="hash" id="srchash" value="">'+
		'</form>').width(0).height(0));
}

plugin.onRemove = function()
{
	$('#srcfrm').remove();
	$('#getsource').remove();
}