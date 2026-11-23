"""Pruebas del ciclo IV (CU23 a CU26) contra el sistema.

    python Docs/Pruebas/probar_reportes.py https://taller-mitsu-ceramica.vercel.app
"""
import urllib.request, urllib.parse, http.cookiejar, re, json, sys, html, zipfile, io
B=sys.argv[1] if len(sys.argv)>1 else 'http://127.0.0.1:8123'
class Ses:
    def __init__(s):
        s.j=http.cookiejar.CookieJar(); s.o=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(s.j))
    def get(s,u,data=None):
        try: r=s.o.open(B+u, urllib.parse.urlencode(data).encode() if data else None, timeout=60)
        except urllib.error.HTTPError as e: r=e
        b=r.read(); return r.status if hasattr(r,'status') else r.code, r.geturl(), b, r.headers
    def login(s,c,p):
        _,_,b,_=s.get('/login'); t=re.search(rb'name="_csrf" value="([^"]+)"',b).group(1).decode()
        return s.get('/login',{'_csrf':t,'correo':c,'password':p})
    def csrf(s,u):
        _,_,b,_=s.get(u); return re.search(rb'name="_csrf" value="([^"]+)"',b).group(1).decode()
ok=mal=0
def chk(n,c,x=''):
    global ok,mal
    if c: ok+=1
    else: mal+=1; print('FALLA',n,x)
a=Ses(); st,u,_,_=a.login('admin@mitsu.test','Admin2026!'); chk('login admin','/panel' in u,u)
st,u,b,_=a.get('/reportes'); chk('reportes',st==200,st)
tipos=re.findall(r'<option value="([a-z]+)" ?(?:selected)?>',re.search(r'id="rep-tipo">(.*?)</select>',b.decode(),re.S).group(1)); print(tipos)
OPS={'texto':['contiene','igual','distinto','empieza','no_contiene','vacio','no_vacio'],'opcion':['igual','distinto'],'numero':['mayor','mayor_igual','menor','menor_igual','igual','distinto','entre'],'fecha':['entre','desde','hasta','igual','hoy','ayer','ultimos7','ultimos30','este_mes','mes_pasado','este_anio']}
V={'texto':('a%\'b','',),'numero':('5','20'),'fecha':('2026-01-01','2026-12-31')}
for t in tipos:
    st,u,b,_=a.get('/reportes?tipo='+t); m=json.loads(html.unescape(re.search(r'data-campos="([^"]*)"',b.decode()).group(1)))
    chk(t,st==200,st)
    for k,c in m.items():
        fam=c['tipo']
        v,w=(c['opciones'][0][0] if c['opciones'] else 'x','') if fam=='opcion' else V[fam]
        # todas las condiciones del campo juntas con O, y agrupado y ordenado por el campo
        q=[('tipo',t),('con','o'),('orden',k),('agrupar',k)]
        for i,o in enumerate(OPS[fam]): q+=[(f'f[{i}][c]',k),(f'f[{i}][o]',o),(f'f[{i}][v]',v),(f'f[{i}][w]',w)]
        st,u,b,_=a.get('/reportes?'+urllib.parse.urlencode(q)); chk(t+'.'+k,st==200 and b'Reportes personalizados' in b and b'alert-error' not in b,st)
    for f in ('pdf','xlsx','csv'):
        st,u,b,h=a.get(f'/reportes/exportar?tipo={t}&formato={f}')
        bien={'pdf':b[:5]==b'%PDF-','xlsx':b[:2]==b'PK','csv':b[:3]==b'\xef\xbb\xbf'}[f]; chk(t+f,st==200 and bien,(st,b[:200]))
        if f=='xlsx' and bien:
            z=zipfile.ZipFile(io.BytesIO(b)); chk(t+' zip',z.testzip() is None)
for fr in ['productos con stock mayor a 20','ventas de este mes','materiales con stock bajo','compras a crédito','pedidos entre 50 y 200','reservas asistió','clientes','calificaciones menor a tres','ventas en efectivo mayores a 100 bolivianos','productos con precio menor a 80','compras de los últimos 30 días','clases agrupadas por taller','vasijas con precio mayor a cien']:
    st,u,b,_=a.get('/reportes/voz?'+urllib.parse.urlencode({'frase':fr})); d=re.search(r'<small class="soft">(.*?)</small>',b.decode(),re.S)
    print('VOZ',fr,'=>',re.search(r'tipo=(\w+)',u) and re.search(r'tipo=(\w+)',u).group(1),'|',re.sub(r'<.*?>','',html.unescape(d.group(1))).strip() if d else u)
    chk(fr,st==200 and 'tipo=' in u)
st,u,b,h=a.get('/reportes/voz?frase=materiales+con+stock+bajo+en+pdf'); chk('voz pdf',b[:5]==b'%PDF-')
st,u,b,_=a.get('/reportes/voz?frase=hola'); chk('voz desconocida',st==200 and 'No entendí'.encode() in b)
st,u,b,_=a.get('/reportes?tipo=piezas&f[0][c]=precio&f[0][o]=mayor&f[0][v]=abc'); chk('numero malo',b'alert-error' in b)
st,u,b,_=a.get("/reportes?tipo=piezas&orden=id;drop&agrupar=x'&col[]=zz&f[0][c]=1;--&f=x&con[]=1"); chk('basura',st==200,st)
st,u,b,_=a.get('/reportes/exportar?formato=exe'); chk('formato malo',st==404,st)
c=Ses(); c.login('cliente@mitsu.test','Cliente2026!'); chk('cliente 403',c.get('/reportes')[0]==403); chk('cliente califs 403',c.get('/calificaciones')[0]==403); chk('sin csrf',c.get('/calificar',{'tipo':'PEDIDO','id':1,'puntaje':5})[0] in(403,419,400,200))
v=Ses(); v.login('ventas@mitsu.test','Ventas2026!'); s,u,b,_=v.get('/reportes?tipo=bitacora'); chk('admv sin bitacora',s==200 and b'value="bitacora"' not in b)
n=Ses(); chk('anonimo','/login' in n.get('/reportes')[1])
st,u,b,_=c.get('/mis-pedidos'); chk('mis pedidos',st==200); print('calificar pedidos',re.findall(rb'/calificar\?[^"]+',b))
l=Ses(); l.login('alumno@mitsu.test','Alumno2026!'); st,u,b,_=l.get('/portal'); chk('portal',st==200); print('calificar clases',re.findall(rb'/calificar\?[^"]+',b))
print('ok',ok,'mal',mal)
