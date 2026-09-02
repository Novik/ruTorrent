<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/../../plugins/check_port/providers.php');

/**
 * The shipped check_port configuration against the provider registry.
 *
 * action.php skips any provider that is unknown or that does not claim the
 * address family being checked, so a name that does not line up costs no
 * error -- the provider is simply never tried, and the chain the comment in
 * conf.php describes is quietly shorter than it reads. These cases hold the
 * shipped defaults to what that comment promises.
 */
class CheckPortProvidersTest extends TestCase
{
	/** conf.php assigns at file scope, so including it here yields locals. */
	private function shippedConf()
	{
		include(__DIR__ . '/../../plugins/check_port/conf.php');

		return array(
			'primary' => array('ipv4' => $useWebsiteIPv4, 'ipv6' => $useWebsiteIPv6),
			'failover' => array('ipv4' => $failoverProvidersIPv4, 'ipv6' => $failoverProvidersIPv6),
		);
	}

	public function testEveryRegisteredProviderNamesAFunctionThatExists()
	{
		global $checkPortProviders;

		foreach ($checkPortProviders as $name => $provider) {
			$this->assertTrue(function_exists($provider['function']),
				"provider $name resolves to " . $provider['function']);
		}
	}

	public function testEveryRegisteredProviderClaimsAtLeastOneFamily()
	{
		global $checkPortProviders;

		foreach ($checkPortProviders as $name => $provider) {
			$this->assertTrue(!empty($provider['ipv4']) || !empty($provider['ipv6']),
				"provider $name is reachable for at least one address family");
		}
	}

	/**
	 * A name in the failover list that is not in the registry is dropped by
	 * action.php without a word, so the shipped lists have to be right.
	 */
	public function testShippedFailoverProvidersAreRegistered()
	{
		global $checkPortProviders;
		$conf = $this->shippedConf();

		foreach ($conf['failover'] as $family => $providers) {
			foreach ($providers as $name) {
				$this->assertTrue(isset($checkPortProviders[$name]),
					"$family failover provider $name is a registered provider");
			}
		}
	}

	/**
	 * The failure this pins: listing yougetsignal, which has no IPv6 support,
	 * in the IPv6 chain would leave that chain empty at run time while still
	 * reading as configured.
	 */
	public function testShippedFailoverProvidersSupportTheFamilyTheyAreListedUnder()
	{
		global $checkPortProviders;
		$conf = $this->shippedConf();

		foreach ($conf['failover'] as $family => $providers) {
			foreach ($providers as $name) {
				$this->assertTrue(!empty($checkPortProviders[$name][$family]),
					"$family failover provider $name supports $family");
			}
		}
	}

	public function testShippedPrimaryProvidersSupportTheirFamily()
	{
		global $checkPortProviders;
		$conf = $this->shippedConf();

		foreach ($conf['primary'] as $family => $name) {
			if ($name === false)
				continue;

			$this->assertTrue(isset($checkPortProviders[$name]),
				"$family primary provider $name is a registered provider");
			$this->assertTrue(!empty($checkPortProviders[$name][$family]),
				"$family primary provider $name supports $family");
		}
	}

	/**
	 * Failing over to the provider that already failed wastes part of the
	 * timeout budget on a repeat of the same request.
	 */
	public function testShippedFailoverDoesNotRepeatThePrimaryProvider()
	{
		$conf = $this->shippedConf();

		foreach ($conf['failover'] as $family => $providers) {
			$this->assertTrue(!in_array($conf['primary'][$family], $providers, true),
				"$family failover chain does not retry the primary provider");
			$this->assertEquals(count($providers), count(array_unique($providers)),
				"$family failover chain lists each provider once");
		}
	}
}
