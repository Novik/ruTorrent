<?php
require_once(__DIR__.'/TestCase.php');
require_once(__DIR__.'/PhpProcess.php');
require_once(__DIR__.'/../../php/utility/utf.php');
require_once(__DIR__.'/../../php/utility/labelvalue.php');

// The labels branch of _getdir/info.php runs with a stub daemon request.
class rXMLRPCCommand { public function __construct($command, $parameters = null) {} }
function getCmd($command) { return $command; }

class LabelChoiceRequest
{
    public static $labels;
    public $val;
    public $fault = false;
    public function __construct($command) { $this->val = self::$labels; }
    public function run() { return true; }
}

class LabelValuesTest extends TestCase
{
    private function assertSame($expected, $actual, $message) { $this->assertTrue($expected === $actual, $message); }

    public function testValidLabelsAndLiteralEscapeTextKeepTheirExistingIdentity()
    {
        foreach(array('', '0', '映画/Серия', 'raw%FF', 'raw\\xFF', "a!'()*~") as $label)
            $this->assertSame($label, LabelValue::fromEncoded(rawurlencode($label)), 'a valid label stays a scalar with no storage conversion');
        $this->assertSame(array('name'=>'raw\\xFF', 'labelKey'=>'raw%FF'), LabelValue::fromEncoded('raw%ff'), 'unknown bytes are escaped and canonicalized without guessing');
        $this->assertSame('padded', LabelValue::fromEncoded('%20padded%20'), 'surrounding whitespace is trimmed as the WebUI trims labels');
        $this->assertSame(array('name'=>'raw\\xFF', 'labelKey'=>'raw%FF'), LabelValue::fromEncoded('%20raw%FF%20'), 'a byte label is trimmed before its key is built');
    }

    public function testDirectoryLabelChoicesKeepBothRawAndLiteralSelectionValues()
    {
        $source = file_get_contents(__DIR__.'/../../plugins/_getdir/info.php');
        $start = strpos($source, '\tif(in_array("labels",$modes))');
        // A literal tab is used in the production endpoint.
        if($start === false) $start = strpos($source, "\tif(in_array(\"labels\",\$modes))");
        $branch = substr($source, $start, strpos($source, "\tif(in_array(\"dirlist\",\$modes))", $start)-$start);
        $branch = str_replace('new rXMLRPCRequest(', 'new LabelChoiceRequest(', $branch);
        LabelChoiceRequest::$labels = array('raw%FF', 'raw%5CxFF', '%E6%98%A0%E7%94%BB', '', 'raw%ff', '%20padded%20');
        $modes = array('labels'); $output = array(); eval($branch);
        $this->assertSame(4, count($output['labels']), 'duplicate raw keys collapse; equal display strings remain separate choices');
        $this->assertSame(true, in_array(array('name'=>'raw\\xFF','labelKey'=>'raw%FF'), $output['labels'], true), 'the legacy choice carries its exact raw query value');
        $this->assertSame(true, in_array('raw\\xFF', $output['labels'], true), 'the literal escape text remains an independent choice');
        $this->assertSame(true, in_array('映画', $output['labels'], true), 'valid Unicode stays backward-compatible');
    }

    public function testMissingByteCodecsLeaveTheLabelToTheBrowser()
    {
        $loader = 'require '.var_export(__DIR__.'/../../php/utility/utf.php', true).';'
            .'require '.var_export(__DIR__.'/../../php/utility/labelvalue.php', true).';';
        foreach(array('rawurldecode', 'rawurlencode', 'trim') as $disabled) {
            $code = $loader.'exit(LabelValue::fromEncoded("raw%FF") === null ? 0 : 1);';
            $result = PhpProcess::run(array_merge(PhpProcess::sameIni(), array('-d', 'xdebug.mode=off', '-d', 'disable_functions='.$disabled, '-r', $code)));
            $this->assertSame(0, $result['code'], 'PHP NULL and Error both give no label value: '.$disabled);
            $this->assertSame('', $result['out'].$result['err'], 'the missing codec prints nothing: '.$disabled);
        }
    }
}
