plugin.loadLang();

if(plugin.canChangeMenu())
{
	theWebUI.getSource = function()
	{
		var sr = this.getTable("trt").rowSel;
		var hash = '';
		for( var k in sr )
		{
			if( sr[k] && (k.length == 40) )
			{
				if( hash )
					hash += " " + k;
				else
					hash = k;
			}
		}
		$("#srchash").val(hash);
		$("#getsource").trigger('submit');
	}

	plugin.createMenu = theWebUI.createMenu;
	theWebUI.createMenu = function( e, id )
	{
		plugin.createMenu.call(this, e, id);
		if(plugin.enabled)
		{
			var el = theContextMenu.get( theUILang.Properties );
			if( el )
				theContextMenu.add( el, [theUILang.getSource, "theWebUI.getSource()"] );
		}
	}
}

plugin.onLangLoaded = function()
{
	$("#frm-container").append($("<iframe>").css({display: "none"}).attr({name: "srcfrm", id: "srcfrm"}).on('load', function()
	{
		$("#srchash").val('');
		var d = (this.contentDocument || this.contentWindow.document);
		if(d && (d.location.href != "about:blank"))
			try { eval(d.body.textContent ? d.body.textContent : d.body.innerText); } catch(e) {}
	}));
	$("#form-container").append(
		$("<form>").attr(
			{action: "plugins/source/action.php", id: "getsource", method: "post", target: "srcfrm"}
		).append(
			$("<input>").attr({type: "hidden", name: "hash", id: "srchash"}).val(""),
		),
	);
}

plugin.onRemove = function()
{
	$('#srcfrm').remove();
	$('#getsource').remove();
}
