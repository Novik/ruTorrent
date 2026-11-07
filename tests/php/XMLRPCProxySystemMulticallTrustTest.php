<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/../../php/xmlrpc_proxy.php');

class XMLRPCProxySystemMulticallTrustTest extends TestCase
{
    private $hash = '0123456789abcdef0123456789ABCDEF01234567';

    public function testValidatedSettersKeepTheTrustTheyGetAlone()
    {
        $decision = $this->decide([
            $this->member('network.xmlrpc.size_limit.set', ['', '1048576']),
            $this->member('network.xmlrpc.size_limit.set', ['', '2097152']),
        ]);
        $xml = $this->sent($decision, true, 'a homogeneous permitted setter batch is trusted');
        $this->assertEquals(
            2,
            count($this->members($xml)),
            'both validated state changes remain in order',
        );
    }

    public function testElevatedMembersAreRebuiltFromValidatedValues()
    {
        $decision = $this->decide([$this->member(
            'd.custom1.set',
            [$this->hash, '$literal; text & label'],
        )]);
        $xml = $this->sent($decision, true, 'a validated label setter is trusted');
        $values = $xml->params->param->value->array->data->value->struct->member[1]->value->array->data;
        $this->assertEquals(
            strtoupper($this->hash),
            (string) $values->value[0]->string,
            'the batch carries the same normalized hash as the single-call path',
        );
        $this->assertEquals(
            '$literal; text & label',
            (string) $values->value[1]->string,
            'the label remains literal data',
        );
    }

    public function testSizeLimitCeilingStillAppliesInsideATrustedBatch()
    {
        $decision = $this->decide([$this->member(
            'network.xmlrpc.size_limit.set',
            ['', '+0016777217'],
        )], [], ['sizeLimitMax' => 4096]);
        $xml = $this->sent($decision, true, 'a bounded size setter remains trusted');
        $values = $xml->params->param->value->array->data->value->struct->member[1]->value->array->data;
        $this->assertEquals('4096', (string) $values->value[1]->i8, 'the stated ceiling reaches the wire');
    }

    public function testUntrustedReadsRemainUntrusted()
    {
        $decision = $this->decide([$this->member('system.client_version'),
            $this->member('system.library_version')]);
        $this->sent($decision, false, 'a homogeneous read batch does not gain trust');
    }

    public function testUnknownMembersKeepTheirTypedPayloadAndDaemonGate()
    {
        $member = $this->member('custom.unknown', ['placeholder']);
        $opaque = '<value><struct><member><name>nested</name><value><array><data>'
            . '<value><i4>7</i4></value></data></array></value></member></struct></value>';
        $member = str_replace($this->value('placeholder'), $opaque, $member);
        $decision = $this->decide([$member]);
        $xml = $this->sent($decision, false, 'an unknown method stays on the untrusted path');
        $values = $xml->params->param->value->array->data->value->struct->member[1]->value->array->data;
        $this->assertEquals(
            '7',
            (string) $values->value->struct->member->value->array->data->value->i4,
            'opaque unknown arguments retain their type and contents',
        );
    }

    public function testMixedTrustIsForwardedUntrustedInEitherOrder()
    {
        $read = $this->member('system.client_version');
        $write = $this->member('network.xmlrpc.size_limit.set', ['', '1048576']);
        foreach ([[$read, $write], [$write, $read]] as $members) {
            $xml = $this->sent(
                $this->decide($members),
                false,
                'a mixed batch is forwarded untrusted, as before, rather than refused',
            );
            $this->assertEquals(
                2,
                count($this->members($xml)),
                'both members of a mixed batch are forwarded',
            );
        }
    }

    public function testUnknownMemberCannotBorrowTrustFromAValidatedSetter()
    {
        $decision = $this->decide([$this->member('d.custom1.set', [$this->hash, 'label']),
            $this->member('custom.unknown')]);
        $this->sent($decision, false, 'unknown members cannot inherit elevated trust');
    }

    /**
     * The core Stop button. d.close is not elevated, so this batch is mixed;
     * rTorrent 0.9.8 ignores the untrusted flag and runs it whole.
     */
    public function testTheCoreStopBatchIsForwardedAsBefore()
    {
        $hash = strtoupper($this->hash);
        $this->assertForwardedAsBefore($this->webuiBatch([
            ['d.stop', [$hash]],
            ['d.close', [$hash]],
        ]), 'the Stop batch');
    }

    /**
     * The scheduler plugin's ignore toggle on a running torrent. d.throttle_name.set
     * is not elevated, so this batch is mixed as well.
     */
    public function testTheSchedulerIgnoreBatchIsForwardedAsBefore()
    {
        $hash = strtoupper($this->hash);
        $this->assertForwardedAsBefore($this->webuiBatch([
            ['d.stop', [$hash]],
            ['d.throttle_name.set', [$hash, 'NULL']],
            ['d.start', [$hash]],
            ['d.custom.set', [$hash, 'sch_ignore', '1']],
        ]), 'the scheduler ignore batch');
    }

    public function testImplicitMethodStringsAndReversedFieldsAreCanonicalized()
    {
        $decision = $this->decide([$this->member('d.start', [$this->hash], true, true)]);
        $xml = $this->sent($decision, true, 'an implicit method string keeps the allowed single-call policy');
        $fields = $xml->params->param->value->array->data->value->struct->member;
        $this->assertEquals('methodName', (string) $fields[0]->name, 'the daemon receives methodName first');
        $this->assertEquals('d.start', (string) $fields[0]->value->string, 'the method is an explicit string on the wire');
        $this->assertEquals('params', (string) $fields[1]->name, 'params follow the method name');
    }

    public function testEmptyParameterArraysRemainValidMembers()
    {
        $decision = $this->decide([$this->member('system.client_version', [], true)]);
        $xml = $this->sent($decision, false, 'a zero-argument member is still forwarded untrusted');
        $members = $this->members($xml);
        $this->assertTrue(
            isset($members[0]->struct->member[1]->value->array->data)
            && count($members[0]->struct->member[1]->value->array->data->value) === 0,
            'no target is invented for a zero-argument call',
        );
    }

    public function testWhitespaceInAnImplicitMethodNameDoesNotGainTrust()
    {
        $decision = $this->decide([$this->member(
            ' network.xmlrpc.size_limit.set ',
            ['', '1048576'],
            true,
        )]);
        $this->sent(
            $decision,
            false,
            'an unknown name with surrounding spaces does not become a trusted setter',
        );
    }

    /**
     * The untrusted path still reads an implicit name without its padding, so
     * a padded name is refused there just like the name it pads.
     */
    public function testAPaddedImplicitNameIsRefusedLikeTheNameItPads()
    {
        foreach ([' execute.throw ', ' system.multicall '] as $method) {
            $decision = $this->decide([$this->member($method, [''], true)]);
            $this->assertTrue(
                $decision['action'] === 'reject' && $decision['payload'] === '',
                var_export($method, true) . ' is refused as before',
            );
        }
    }

    public function testAllowedLoadCommandsKeepTheirArgumentsAndTrust()
    {
        $decision = $this->decide([$this->member(
            'load.raw_start',
            ['', 'torrent bytes', 'd.custom1.set=label; text'],
        )], ['d.custom1.set']);
        $xml = $this->sent($decision, true, 'a load whose parameters are all rebuilt is trusted');
        $values = $xml->params->param->value->array->data->value->struct->member[1]->value->array->data;
        $this->assertEquals(
            'd.custom1.set="label; text"',
            (string) $values->value[2]->string,
            'the exact rebuilt command is sent as a single quoted argument',
        );
    }

    public function testUnknownLoadTailIsPreservedWithoutTrust()
    {
        $decision = $this->decide([$this->member(
            'load.raw_start',
            ['', 'torrent bytes', 'd.peers_max.set=7'],
        )], ['d.custom1.set']);
        $xml = $this->sent($decision, false, 'a load tail outside the stated allowlist stays untrusted');
        $values = $xml->params->param->value->array->data->value->struct->member[1]->value->array->data;
        $this->assertEquals(
            'd.peers_max.set=7',
            (string) $values->value[2]->string,
            'a batch does not silently discard an unknown command',
        );
    }

    public function testOpaqueLoadDataCannotGainTrust()
    {
        $member = $this->member('load.raw_start', ['target', 'torrent bytes']);
        $member = str_replace($this->value('target'), '<value><int>1</int></value>', $member);
        $decision = $this->decide([$member]);
        $this->sent($decision, false, 'data the load rebuilder does not understand stays untrusted');
    }

    public function testForbiddenAndNestedMembersRefuseTheWholeBatch()
    {
        foreach (['execute.capture', 'system.method.insert', 'system.multicall'] as $method) {
            $decision = $this->decide([$this->member('system.client_version'), $this->member($method)]);
            $this->assertTrue(
                $decision['action'] === 'reject' && $decision['payload'] === '',
                $method . ' refuses the whole batch',
            );
        }
    }

    public function testInvalidElevatedArgumentsStayRefused()
    {
        foreach ([['d.start', ['not-a-hash']],
            ['d.start', [$this->hash, 'extra']],
            ['network.xmlrpc.size_limit.set', ['', '0']]] as $case) {
            $decision = $this->decide([$this->member($case[0], $case[1])]);
            $this->assertEquals('reject', $decision['action'], $case[0] . ' must still match its allowed shape');
        }
    }

    public function testDirectoryAndCommandCarrierRefusalsRemainInForce()
    {
        $options = ['directory' => ['root' => '/downloads']];
        foreach ([
            $this->member('d.directory.set', [$this->hash, '/outside']),
            $this->member('load.raw_start', ['', 'torrent bytes', 'd.directory.set=/outside']),
            $this->member('branch', ['1', 'execute.capture=/bin/true', '0']),
        ] as $member) {
            $decision = $this->decide([$member], ['d.directory.set'], $options);
            $this->assertEquals('reject', $decision['action'], 'the existing directory and nested-command gates still refuse');
        }
    }

    public function testMalformedMembersAreNeverTrusted()
    {
        $good = $this->member('d.start', [$this->hash]);
        foreach ([
            '<value><string>not a member</string></value>',
            str_replace('<struct>', '<struct><member><name>extra</name>' . $this->value('x') . '</member>', $good),
            str_replace('<name>methodName</name>', '<name>params</name>', $good),
            str_replace($this->value('d.start'), '<value><array><data/></array></value>', $good),
            str_replace('<name>params</name><value><array><data>', '<name>params</name><value><array><data><bad/>', $good),
        ] as $member) {
            $decision = $this->decide([$member]);
            $this->assertTrue(
                $decision['trusted'] === false,
                'a malformed member cannot obtain a trusted connection',
            );
        }
    }

    public function testAnEmptyOrNonArrayBatchIsForwardedAsBefore()
    {
        foreach ([$this->batch([]), str_replace(
            '<value><array><data></data></array></value>',
            $this->value('opaque'),
            $this->batch([]),
        )] as $payload) {
            $decision = XMLRPCProxy::decide($payload);
            $this->assertTrue($decision['action'] === 'send' && $decision['trusted'] === false
                && $decision['payload'] === $payload, 'a batch without understood members is forwarded untrusted and unchanged');
        }
    }

    public function testAmbiguousBatchWrappersNeverGainTrust()
    {
        $member = $this->member('d.start', [$this->hash]);
        $batch = $this->batch([$member]);
        foreach ([
            'extra outer parameter' => str_replace('</param></params>', '</param><param>' . $this->value('extra')
                . '</param></params>', $batch),
            'extra member type' => $this->batch([str_replace(
                '</struct></value>',
                '</struct><int>7</int></value>',
                $member,
            )]),
            'extra struct' => $this->batch([str_replace(
                '</struct></value>',
                '</struct><struct/></value>',
                $member,
            )]),
            'extra method type' => $this->batch([str_replace(
                $this->value('d.start'),
                '<value><string>d.start</string><int>7</int></value>',
                $member,
            )]),
            'extra params type' => $this->batch([str_replace(
                '</data></array></value>',
                '</data></array><int>7</int></value>',
                $member,
            )]),
            'namespaced sibling' => $this->batch([str_replace(
                '<struct>',
                '<struct xmlns:x="urn:test"><x:member/>',
                $member,
            )]),
            'attribute on a trusted member' => $this->batch([str_replace(
                '<struct>',
                '<struct unexpected="yes">',
                $member,
            )]),
        ] as $name => $payload) {
            $decision = XMLRPCProxy::decide($payload);
            $this->assertTrue(
                $decision['trusted'] === false,
                $name . ' does not obtain a trusted connection',
            );
        }
    }

    private function value($text)
    {
        return '<value><string>' . htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8') . '</string></value>';
    }

    private function member($method, $params = [], $implicit = false, $reverse = false)
    {
        $name = '<member><name>methodName</name>' . ($implicit
            ? '<value>' . htmlspecialchars($method, ENT_NOQUOTES, 'UTF-8') . '</value>'
            : $this->value($method)) . '</member>';
        $args = '<member><name>params</name><value><array><data>';
        foreach ($params as $param) {
            $args .= $this->value($param);
        }
        $args .= '</data></array></value></member>';
        return '<value><struct>' . ($reverse ? $args . $name : $name . $args) . '</struct></value>';
    }

    private function batch($members)
    {
        return '<methodCall><methodName>system.multicall</methodName><params><param>'
            . '<value><array><data>' . implode('', $members) . '</data></array></value>'
            . '</param></params></methodCall>';
    }

    private function decide($members, $safe = [], $options = [])
    {
        return XMLRPCProxy::decide($this->batch($members), 'sanitize', $safe, false, $options);
    }

    private function sent($decision, $trusted, $message)
    {
        $this->assertTrue(
            $decision['action'] === 'send' && $decision['trusted'] === $trusted,
            $message,
        );
        // An empty call when nothing was sent, so later assertions fail rather
        // than stop the file.
        $xml = @simplexml_load_string($decision['payload']);
        return ($xml === false) ? new SimpleXMLElement('<methodCall/>') : $xml;
    }

    /** The members a forwarded batch carries; none when it carries no batch. */
    private function members($xml)
    {
        return isset($xml->params->param->value->array->data->value)
            ? $xml->params->param->value->array->data->value : [];
    }

    /** The request js/rtorrent.js builds for a batch of the given calls. */
    private function webuiBatch($calls)
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><methodCall><methodName>'
            . 'system.multicall</methodName><params><param><value><array><data>';
        foreach ($calls as $call) {
            $xml .= '<value><struct><member><name>methodName</name><value><string>'
                . $call[0] . '</string></value></member><member><name>params</name><value><array><data>';
            foreach ($call[1] as $param) {
                $xml .= '<value><string>' . $param . '</string></value>';
            }
            $xml .= '</data></array></value></member></struct></value>';
        }
        return $xml . '</data></array></value></param></params></methodCall>';
    }

    /** Forwarded untrusted and byte for byte, as before this class existed. */
    private function assertForwardedAsBefore($request, $what)
    {
        $decision = XMLRPCProxy::decide($request, 'sanitize', [], false, []);
        $this->assertTrue($decision['action'] === 'send', $what . ' is still forwarded');
        $this->assertTrue($decision['trusted'] === false, $what . ' is still forwarded untrusted');
        $this->assertTrue($decision['payload'] === $request, $what . ' still reaches rtorrent unchanged');
        $this->assertEquals(
            ['untrusted: system.multicall'],
            $decision['log'],
            $what . ' is still logged as an untrusted batch',
        );
    }
}
