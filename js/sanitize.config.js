if(!Sanitize.Config) {
  Sanitize.Config = {}
}

Sanitize.Config.Levels = ['RESTRICTED', 'BASIC', 'RELAXED'];

Sanitize.Config.RESTRICTED = {
  elements: [
     'a', 'b', 'blockquote', 'br', 'cite', 'code', 'dd', 'dl', 'dt', 'em',
     'i', 'li', 'ol', 'p', 'pre', 'q', 'small', 'strike', 'strong', 'sub',
     'sup', 'u', 'ul']       
}

Sanitize.Config.BASIC = {
  ...Sanitize.Config.RESTRICTED,

   attributes: {
     'a'         : ['href'],
     'blockquote': ['cite'],
     'q'         : ['cite']
   },

   add_attributes: {
     'a': {'rel': 'noreferrer noopener', 'target': '_blank' }
   },

   protocols: {
     'a'         : {'href': ['ftp', 'http', 'https', 'mailto', Sanitize.RELATIVE]},
     'blockquote': {'cite': ['http', 'https', Sanitize.RELATIVE]},
     'q'         : {'cite': ['http', 'https', Sanitize.RELATIVE]}
   }
}

Sanitize.Config.RELAXED = {
  ...Sanitize.Config.BASIC,
  elements: [
    ...Sanitize.Config.BASIC.elements,
    'caption', 'col', 'colgroup', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
    'img', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'ul'],

  attributes: {
    ...Sanitize.Config.BASIC.attributes,
    'a'         : ['href', 'title'],
    'col'       : ['span', 'width'],
    'colgroup'  : ['span', 'width'],
    'img'       : ['align', 'alt', 'height', 'src', 'title', 'width'],
    'ol'        : ['start', 'type'],
    'table'     : ['summary', 'width'],
    'td'        : ['abbr', 'axis', 'colspan', 'rowspan', 'width'],
    'th'        : ['abbr', 'axis', 'colspan', 'rowspan', 'scope', 'width'],
    'ul'        : ['type']
  },

   add_attributes: {
    ...Sanitize.Config.BASIC.add_attributes,
     'img': {'referrerpolicy': 'no-referrer' }
   },

  protocols: {
    ...Sanitize.Config.BASIC.protocols,
    'img'       : {'src' : ['http', 'https', Sanitize.RELATIVE]},
  }
}
