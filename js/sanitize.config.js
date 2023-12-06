if(!Sanitize.Config) {
  Sanitize.Config = {}
}

Sanitize.Config.RESTRICTED = {
  elements: [
     'a', 'b', 'blockquote', 'br', 'cite', 'code', 'dd', 'dl', 'dt', 'em',
     'i', 'li', 'ol', 'p', 'pre', 'q', 'small', 'strike', 'strong', 'sub',
     'sup', 'u', 'ul']       
}

Sanitize.Config.BASIC = {
  elements: [
     'a', 'b', 'blockquote', 'br', 'cite', 'code', 'dd', 'dl', 'dt', 'em',
     'i', 'li', 'ol', 'p', 'pre', 'q', 'small', 'strike', 'strong', 'sub',
     'sup', 'u', 'ul'],

   attributes: {
     'a'         : ['href'],
     'blockquote': ['cite'],
     'q'         : ['cite']
   },

   add_attributes: {
     'a': {'rel': 'nofollow'}
   },

   protocols: {
     'a'         : {'href': ['ftp', 'http', 'https', 'mailto', Sanitize.RELATIVE]},
     'blockquote': {'cite': ['http', 'https', Sanitize.RELATIVE]},
     'q'         : {'cite': ['http', 'https', Sanitize.RELATIVE]}
   }
}

Sanitize.Config.RELAXED = {
  elements: [
    'a', 'b', 'blockquote', 'br', 'caption', 'cite', 'code', 'col',
    'colgroup', 'dd', 'dl', 'dt', 'em', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
    'i', 'img', 'li', 'ol', 'p', 'pre', 'q', 'small', 'strike', 'strong',
    'sub', 'sup', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'u',
    'ul'],

  attributes: {
    'a'         : ['href', 'title'],
    'blockquote': ['cite'],
    'col'       : ['span', 'width'],
    'colgroup'  : ['span', 'width'],
    'img'       : ['align', 'alt', 'height', 'src', 'title', 'width'],
    'ol'        : ['start', 'type'],
    'q'         : ['cite'],
    'table'     : ['summary', 'width'],
    'td'        : ['abbr', 'axis', 'colspan', 'rowspan', 'width'],
    'th'        : ['abbr', 'axis', 'colspan', 'rowspan', 'scope', 'width'],
    'ul'        : ['type']
  },

  protocols: {
    'a'         : {'href': ['ftp', 'http', 'https', 'mailto', Sanitize.RELATIVE]},
    'blockquote': {'cite': ['http', 'https', Sanitize.RELATIVE]},
    'img'       : {'src' : ['http', 'https', Sanitize.RELATIVE]},
    'q'         : {'cite': ['http', 'https', Sanitize.RELATIVE]}
  }
}
