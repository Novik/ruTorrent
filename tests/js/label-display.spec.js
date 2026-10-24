import {readFileSync} from 'fs';
import {TorrentLabelTree} from '../../js/panel';
import {CategoryList} from '../../js/category-list';
window.$ = window.jQuery = require('jquery');
const hash = 'A'.repeat(40), second = 'B'.repeat(40);
const raw = {name:'raw\\xFF',labelKey:'raw%FF'};
function load(file) {
  const script=document.createElement('script');
  script.textContent=readFileSync('../'+file,'utf8'); document.body.appendChild(script);
}
beforeAll(() => {
  for(const file of ['lang/en.js','js/sanitize.js','js/sanitize.config.js','js/browser.js','js/common.js',
    'js/objects.js','js/options.js','js/content.js','js/stable.js','js/graph.js','js/plugins.js','js/rtorrent.js','js/webui.js']) load(file);
});
beforeEach(() => {
  load('js/rtorrent.js'); correctContent();
  // The httprpc plugin answers the list as JSON with its side maps.
  const httprpc=document.createElement('script');
  httprpc.textContent='(function(){var plugin={enabled:true};\n'+readFileSync('../plugins/httprpc/init.js','utf8')+'\n})();';
  document.body.appendChild(httprpc);
  theWebUI.systemInfo={rTorrent:{apiVersion:26,iVersion:0x101a,started:true}};
  theWebUI.settings={'webui.needmessage':true};
});
function row(label) {
  const value=Array(35).fill('0'); value[4]='Name';value[7]='1';value[14]=label;value[25]='/data/Name';value[29]='';return value;
}
function response(label=raw) {
  return new rTorrentStub('?list=1').getResponse({t:{[hash]:row(labelQuery(label))},l:{[hash]:label},cid:1,full:1}).torrents[hash];
}
test('raw label and identical literal display have distinct model and request identities',()=>{
  const model=response();
  expect(model.label).toBe(raw.name);expect(model.label_value).toEqual(raw);expect(model.label_id).toBe('blabel__raw%FF');
  theWebUI.torrents={[hash]:model,[second]:{label:raw.name,label_value:raw.name}};
  theWebUI.getTable=()=>({rowSel:{[hash]:true,[second]:true}});theWebUI.request=jest.fn();
  theWebUI.setLabel(raw);
  expect(theWebUI.request.mock.calls[0][0]).toBe(`?action=setlabel&hash=${second}&s=label&v=raw%FF&list=1`);
  const request=new rTorrentStub(theWebUI.request.mock.calls[0][0]);
  // httprpc decodes the form value once, so d.custom1 receives the stored key.
  expect(request.content).toContain('&v=raw%25FF');
});
test('opaque label survives sparse updates and clears on replacement, deletion and full refresh',()=>{
  response(); const stub=new rTorrentStub('?list=1');
  expect(stub.getResponse({t:{[hash]:{11:'4'}},cid:2}).torrents[hash].label_value).toEqual(raw);
  expect(stub.getResponse({t:{[hash]:{14:'%E6%98%A0%E7%94%BB'}},cid:3}).torrents[hash].label_value).toBe('映画');
  response();stub.getResponse({t:{},d:[hash],cid:4});
  expect(theRequestManager.torrentLabels[hash]).toBeUndefined();
  response();stub.getResponse({t:{},full:1,cid:5});expect(theRequestManager.torrentLabels[hash]).toBeUndefined();
});
test.each([null,[],{[hash]:null},{[hash]:{name:'x',labelKey:'%ff'}},{[second]:raw}])('malformed label map refuses before cache mutation: %j',l=>{
  response(); const old=theRequestManager.cid;
  const stub=new rTorrentStub('?list=1');
  expect(stub.getResponse({t:{[hash]:{14:'new'}},l,cid:9})).toBeUndefined();
  expect(stub.invalidListResponse).toBe(true);expect(theRequestManager.cid).toBe(old);
});
test('side map cannot change identity without a matching label update',()=>{
  response();const stub=new rTorrentStub('?list=1');
  expect(stub.getResponse({t:{[hash]:{11:'7'}},l:{[hash]:raw},cid:10})).toBeUndefined();
  expect(theRequestManager.cid).toBe(1);
});
test('tree keeps raw and literal paths apart while retaining normal panel IDs',()=>{
  const binary={name:'Films/raw\\xFF',labelKey:'Films%2Fraw%FF'};
  const tree=new TorrentLabelTree([labelTreePath(binary),labelTreePath(binary.name),labelTreePath('映画/普通')]);
  const entries=[...tree.torrentLabels.values()];
  expect(entries.filter(info=>info.name===binary.name)).toHaveLength(2);
  expect(entries.find(info=>info.panelId==='blabel__Films%2Fraw%FF').value).toEqual(binary);
  expect(entries.find(info=>info.panelId==='clabel__Films').empty).toBe(true);
  expect(entries.find(info=>info.panelId==='clabel__映画/普通').value).toBe('映画/普通');
  expect(labelValueFromId(tree,'blabel__Films%2Fraw%FF')).toEqual(binary);
});
test('unchanged label editor retains raw bytes; typing the same display makes a literal label',()=>{
  const model=response();theWebUI.torrents={[hash]:model};
  document.body.innerHTML='<input id="txtLabel">';
  theWebUI.getTable=()=>({selCount:1,getFirstSelected:()=>hash,rowSel:{[hash]:true}});
  theDialogManager.show=jest.fn();theWebUI.request=jest.fn();
  theWebUI.newLabel(); expect($('#txtLabel').val()).toBe(raw.name);
  theWebUI.createLabel();expect(theWebUI.request).not.toHaveBeenCalled();
  $('#txtLabel').trigger('input');theWebUI.createLabel();
  expect(theWebUI.request.mock.calls[0][0]).toContain('&v=raw%5CxFF&list=1');
});
test('empty, valid Unicode, literal percents and selected whitespace keep identity',()=>{
  for(const value of ['', '映画', 'raw%FF', {name:' padded ',labelKey:'%20padded%20'}]) {
    expect(labelQuery(value)).toBe(typeof value==='object'?value.labelKey:encodeURIComponent(value));
    document.body.innerHTML='<input id="labelInput">';setLabelInput('#labelInput',value);
    expect(getLabelInput('#labelInput',true)).toEqual(value);
  }
  expect(labelId('映画')).toBe('clabel__映画');expect(labelId('')).toBe('-_-_-nlb-_-_-');
});

test('literal labels resembling opaque tree keys never overwrite either entry',()=>{
  const tree=new TorrentLabelTree([labelTreePath(raw),labelTreePath('blabel__raw%FF')]);
  expect(tree.torrentLabels.size).toBe(2);
  expect(tree.torrentLabels.get('blabel__raw%FF').value).toEqual(raw);
  expect(tree.torrentLabels.get('clabel__blabel__raw%FF').value).toBe('blabel__raw%FF');
});

test.each(['raw', 'leading-slash'])('category counts, filters and saved ordinary selections use identity rather than display: %s', kind=>{
  const selected = kind === 'raw' ? raw : {name:'/raw\\xFF',labelKey:'%2Fraw%FF'};
  const models={};
  for(const [key,value] of [[hash,selected],[second,labelDisplay(selected)],['C','映画'],['D','']])
    models[key]={label:labelDisplay(value),label_value:value,label_id:labelId(value),label_path:labelTreePath(value),
      name:'Release',size:100,state:0,done:1000,ul:0,dl:0};
  const panels=['pview','pstate','plabel','psearch'];
  const list=new CategoryList({
    panelAttribs:Object.fromEntries(panels.map(key=>[key,{text:key}])),
    panelLabelAttribs:Object.fromEntries(panels.map(key=>[key,new Map([[key+'_all',{text:'All'}],
      ...(key==='plabel'?[['-_-_-nlb-_-_-',{text:'No label'}]]:[])])])),
    syncFn:()=>{},onSelectionChangeFn:()=>{},onConfigChangeFn:()=>{},borrowTorrentsFn:()=>models,
    byteSizeToStringFn:String,dStatus:{started:1,paused:2,checking:4,hashing:8,error:16},
  });
  list.config({'webui.category_panels':panels,'webui.closed_panels':{},'webui.open_tegs.last':[],
    'webui.selected_labels.keep':true,'webui.selected_labels.last':{plabel:['clabel__映画']},
    'webui.selected_labels.views':[],'webui.show_label_path_tree':true,'webui.show_empty_path_labels':true});
  for(const [key,model] of Object.entries(models)) list.statistic.scan(key,model);
  list.syncAfterScan();
  expect(list.panelLabelAttribs.plabel.get(labelId(selected)).count).toBe('1');
  expect(list.panelLabelAttribs.plabel.get('clabel__'+labelDisplay(selected)).count).toBe('1');
  expect(list.panelLabelAttribs.plabel.get('-_-_-nlb-_-_-').text).toBe('No label');
  expect(list.selection.ids('plabel')).toEqual(['clabel__映画']);
  expect(list.selected('C')).toBe(true);expect(list.selected(hash)).toBe(false);
  list.switchLabel('plabel',labelId(selected));
  expect(list.selected(hash)).toBe(true);expect(list.selected(second)).toBe(false);
  list.switchLabel('plabel','-_-_-nlb-_-_-');
  expect(list.selected('D')).toBe(true);expect(list.selected(hash)).toBe(false);
});

test('valid whitespace ancestors share the same identity as standalone labels',()=>{
  for(const values of [[' padded /child',' padded '],[' padded ',' padded /child']]) {
    const tree=new TorrentLabelTree(values.map(labelTreePath));
    expect(tree.torrentLabels.get('clabel__ padded ').value).toBe(' padded ');
    expect(tree.torrentLabels.get('clabel__ padded ').empty).toBe(false);
  }
});

test('httprpc form wrapping preserves encoded custom1 instead of decoding it as UTF8',()=>{
  const script=document.createElement('script');
  script.textContent='(function(){var plugin={enabled:true};'+readFileSync('../plugins/httprpc/init.js','utf8')+'})();';
  document.body.appendChild(script);
  const request=new rTorrentStub(`?action=setlabel&hash=${hash}&s=label&v=raw%FF&list=1`);
  expect(new URLSearchParams(request.content).get('v')).toBe('raw%FF');
});
test('keys encode what encodeURIComponent leaves alone, as PHP rawurlencode does',()=>{
  expect(pathQuery("/a(1)!'*")).toBe('%2Fa%281%29%21%27%2A');
  expect(labelQuery("a(1)!'*")).toBe('a%281%29%21%27%2A');
  expect(isPathValue({name:'x',pathKey:pathQuery("/a(1)!'*")})).toBe(true);
  expect(isLabelValue({name:'x',labelKey:labelQuery("a(1)!'*")})).toBe(true);
});
test('torrents with the same label share one tree path, built once per label',()=>{
  const stub=new rTorrentStub('?list=1');
  const rows={[hash]:row('Movies%2FHD'),[second]:row('Movies%2FHD')};
  const list=stub.getResponse({t:rows,cid:1,full:1}).torrents;
  expect(list[hash].label_path).toBe(list[second].label_path);
  expect(list[hash].label_path.map(part=>part.name)).toEqual(['Movies','HD']);
});
