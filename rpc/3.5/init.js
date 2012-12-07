plugin.XMLRPCMountPoint = theURLs.XMLRPCMountPoint;
theURLs.XMLRPCMountPoint = "plugins/rpc/rpc.php";

plugin.onRemove = function()
{
	theURLs.XMLRPCMountPoint = plugin.XMLRPCMountPoint;
}
