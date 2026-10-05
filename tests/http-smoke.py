"""Exercise the local demo over HTTP, including the actual admin save handler."""
import http.cookiejar
import json
import re
import urllib.parse
import urllib.request

base = 'http://localhost:8098'

def public(path):
    return urllib.request.urlopen(base + path).read().decode()

jar = http.cookiejar.CookieJar()
admin = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
admin.open(base + '/wp-login.php').read()
admin.open(base + '/wp-login.php', urllib.parse.urlencode({
    'log': 'batadmin', 'pwd': 'bat-local-development', 'wp-submit': 'Log In',
    'redirect_to': base + '/wp-admin/', 'testcookie': '1'
}).encode()).read()
html = admin.open(base + '/wp-admin/admin.php?page=builder-ab').read().decode()
assert 'Demo: llamada a la acción' in html, 'Demo must be seeded first'
experiment = int(re.search(r'page=builder-ab&#038;edit=(\d+)[^>]*>Demo: llamada a la acción</a>', html).group(1))
html = admin.open(base + '/wp-admin/admin.php?page=builder-ab&edit=' + str(experiment)).read().decode()
nonce = re.search(r'name="_wpnonce" value="([^"]+)"', html).group(1)
pages = {}
for name in ('entry', 'a', 'b', 'thank_you'):
    select = re.search(r'<select[^>]*name=[\'"]' + name + r'[\'"][^>]*>(.*?)</select>', html, re.S).group(1)
    pages[name] = int(re.search(r'<option[^>]*value=[\'"](\d+)[\'"][^>]*selected=', select).group(1))
fields = dict(pages, action='bat_save', experiment_id=experiment, title='Demo: llamada a la acción', weight=50, goal='page', selector='', active='on', _wpnonce=nonce)
saved = admin.open(base + '/wp-admin/admin-post.php', urllib.parse.urlencode(fields).encode())
assert 'saved=1' in saved.url, 'Admin experiment save failed'
print('PASS: authenticated administration saves experiment with nonce')
entry = public('/?page_id=' + str(pages['entry']))
assert 'window.BAT_CONFIG=' in entry and 'tracking.js' in entry
assert 'tracking.js' not in admin.open(base + '/?page_id=' + str(pages['entry'])).read().decode()
print('PASS: anonymous entry loads runtime; administrators are excluded')

def post(route, data):
    req = urllib.request.Request(base + '/?rest_route=/builder-ab/v1/' + route,
        data=json.dumps(data).encode(), headers={'Content-Type': 'application/json', 'Origin': base}, method='POST')
    with urllib.request.urlopen(req) as response:
        assert 'no-store' in response.headers['Cache-Control']
        return json.load(response)

assignment = post('assign', {'id': experiment})
assert assignment['url'] == base + '/?page_id=' + str(pages[assignment['variant']])
exposure = {'id': experiment, 'token': assignment['token'], 'type': 'exposure', 'page': assignment['url']}
assert post('event', exposure)['recorded'] is True
assert post('event', exposure)['recorded'] is False
conversion = dict(exposure, type='conversion', page=base + '/?page_id=' + str(pages['thank_you']))
assert post('event', conversion)['recorded'] is True
assert post('event', conversion)['recorded'] is False
print('PASS: HTTP REST assignment, no-store responses, exposure and conversion deduplication')
