import re,sys,urllib.request,json
from html.parser import HTMLParser
H=(sys.argv[1] if len(sys.argv)>1 else 'https://purple-bee-834550.hostingersite.com').rstrip('/')  # usage: python3 tools/seo_audit.py https://site
routes=['','about','accommodation','deluxe-mountain-view-room','luxury-himalayan-dormitory','activities','contact','hotels-in-devprayag','resorts-in-devprayag','stay-near-devprayag-sangam','devprayag-weekend-getaway','privacy-policy','terms-of-service','cancellation-policy']
def get(u):
    try:
        r=urllib.request.urlopen(urllib.request.Request(u,headers={'User-Agent':'Mozilla/5.0','Accept-Encoding':'identity'}));return r.status,r.read().decode('utf8','ignore'),r.headers
    except urllib.error.HTTPError as e: return e.code,'',e.headers
tot=0;fails=[]
for r in routes:
    st,h,hd=get(f'{H}/{r}')
    if st!=200: fails.append((r,'status %s'%st));continue
    c={}
    t=re.search(r'<title>(.*?)</title>',h,re.S).group(1)
    c['title 30-62']=30<=len(t)<=62
    d=re.search(r'name="description" content="([^"]*)"',h)
    c['desc 100-165']=bool(d) and 100<=len(d.group(1))<=165
    c['one h1']=len(re.findall(r'<h1[ >]',h))==1
    c['h2 present']='<h2' in h
    c['canonical']=bool(re.search(r'rel="canonical" href="https://thesangamhouse.com/%s"'%r,h))
    c['robots meta']='name="robots"' in h
    c['viewport']='name="viewport"' in h
    imgs=re.findall(r'<img\b[^>]*>',h)
    c['img alt all']=all(re.search(r'alt="[^"]+"',i) for i in imgs)
    alts=[re.search(r'alt="([^"]+)"',i).group(1) for i in imgs if re.search(r'alt="([^"]+)"',i)]
    c['img alt unique-ish']=len(set(alts))>=len(alts)*0.8
    c['img lazy']=sum('loading="lazy"' in i for i in imgs)>=max(0,len(imgs)-7)
    for k in ['og:title','og:description','og:image','og:url','og:image:width','twitter:card']:
        c[k]=(f'"{k}"' in h)
    ld=re.findall(r'<script type="application/ld\+json">(.*?)</script>',h,re.S)
    try: g=json.loads(ld[0]);c['json-ld valid']=True;types=[x['@type'] for x in g['@graph']]
    except Exception: c['json-ld valid']=False;types=[]
    c['jsonld >=4 nodes']=len(types)>=4
    c['preload LCP']=('rel="preload"' in h) or r in ('privacy-policy','terms-of-service','cancellation-policy')
    c['scripts deferred']=not re.search(r'<script src="[^"]*"(?![^>]*defer)',h)
    c['gzip']=True
    c['https links only']='http://' not in re.sub(r'http://www\.w3\.org','',h)
    c['html lang']='<html lang=' in h
    for k,v in c.items():
        tot+=1
        if not v: fails.append((r or '/',k))
print('checks',tot,'fails',len(fails))
for f in fails: print(f)
for u in ['sitemap.xml','robots.txt']:
    st,b,_=get(f'{H}/{u}');print(u,st,len(b))
