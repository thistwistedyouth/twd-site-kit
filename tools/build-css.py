# Rebuilds assets/twd-site-kit.css from tools/kit.src.css. Run from anywhere:
#   python3 tools/build-css.py
# The committed stylesheet is generated: edit tools/kit.src.css, then rebuild.
# tests/test-css.php fails if the committed file is not what this script produces.
import re,sys,os
HERE=os.path.dirname(os.path.abspath(__file__))
src=open(os.path.join(HERE,'kit.src.css')).read()
VIS=re.compile(r'^(color|background|background-color|background-image|font-family|font-size|font-weight|font-style|line-height|letter-spacing|text-transform|text-decoration|text-align|border|border-(top|right|bottom|left)(-(color|width|style))?|border-color|border-width|border-style|border-radius|box-shadow|padding|padding-(top|right|bottom|left)|margin|margin-(top|right|bottom|left))$')
def tok(s):
    s=re.sub(r'#\{\$([a-z0-9-]+)\}',r'var(--twd-site-\1)',s)
    return re.sub(r'\$([a-z0-9-]+)',r'var(--twd-site-\1)',s)
def double(sel):
    return re.sub(r'\.(twd-(?:sk|ap)-[A-Za-z0-9_-]+)',r'.\1.\1',sel)
def sel_out(sel):
    sel=sel.strip()
    sel=double(sel)
    if sel.startswith('&'):
        return '.twd-sk-page'+sel[1:]
    return '.twd-sk-page '+sel
def split_sel(s):
    out=[];depth=0;cur=''
    for ch in s:
        if ch in '([': depth+=1
        if ch in ')]': depth-=1
        if ch==',' and depth==0: out.append(cur);cur=''
        else: cur+=ch
    out.append(cur); return [x.strip() for x in out if x.strip()]
def decls(body):
    ds=[]
    for d in re.split(r';(?![^"]*"[^"]*$)',body):
        d=d.strip()
        if not d: continue
        n,v=d.split(':',1); n=n.strip(); v=tok(v.strip())
        if VIS.match(n) and '!important' not in v: v+=' !important'
        ds.append((n,v))
    return ds
def parse(text,indent=''):
    out=[];i=0
    while i<len(text):
        m=re.compile(r'\s*').match(text,i); i=m.end()
        if i>=len(text): break
        if text.startswith('/*',i):
            j=text.index('*/',i)+2
            if 'Selectors are relative' not in text[i:j]: out.append(indent+text[i:j])
            i=j; continue
        j=text.index('{',i); head=text[i:j].strip()
        depth=1;k=j+1
        while depth:
            if text[k]=='{':depth+=1
            elif text[k]=='}':depth-=1
            k+=1
        body=text[j+1:k-1]; i=k
        if head.startswith('@media'):
            out.append(indent+head+' {'); out.append(parse(body,indent+'  ')); out.append(indent+'}')
        else:
            sels=[sel_out(s) for s in split_sel(head)]
            out.append(indent+(',\n'+indent).join(sels)+' {')
            for n,v in decls(body): out.append(f'{indent}  {n}: {v};')
            out.append(indent+'}')
    return '\n'.join(out)
head='''/* TWD Site Kit: component styles.
 *
 * Rules (enforced by tests/test-css.php):
 *  - Every rule is scoped under .twd-sk-page. The one exception is the page title
 *    fallback at the very end, which has to reach outside the wrapper.
 *  - Every component class is written twice in a row in its selector (a doubled
 *    class), so a theme or Elementor selector cannot win on specificity.
 *  - Every visual property (colour, background, font, border, padding, margin,
 *    radius, shadow, text) carries !important. Layout properties do not, so the
 *    [hidden] rule below always wins.
 *  - No colour or font literals: everything comes from --twd-site-* tokens
 *    (see packs/ and includes/class-twd-sk-packs.php).
 *  - No viewport-width or negative-margin full-width tricks, and no hidden overflow
 *    (overflow clip is used instead). Pages sit in a full-width Elementor container.
 */

'''
tail='''
/* Page title fallback for themes other than Hello. Added by TWD_SK_Page only on a
   page whose kit HTML has its own h1 (the hero title). */
body.twd-sk-has-h1 .page-header,
body.twd-sk-has-h1 .entry-title {
  display: none !important;
}
'''
open(os.path.join(HERE,'..','assets','twd-site-kit.css'),'w').write(head+parse(src)+'\n'+tail)
print('written')
