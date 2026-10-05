"""Exercise the local demo over HTTP, including the actual admin save handler."""
import http.cookiejar
import json
import re
import urllib.parse
import urllib.request
import urllib.error
import subprocess

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
form = next(form for form in re.findall(r'<form\b[^>]*>(.*?)</form>', html, re.S) if 'value="bat_save"' in form)
nonce = re.search(r'name="_wpnonce" value="([^"]+)"', form).group(1)
pages = {}
for name in ('entry', 'a', 'b', 'thank_you'):
    select = re.search(r'<select[^>]*name=[\'"]' + name + r'[\'"][^>]*>(.*?)</select>', form, re.S).group(1)
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

# Toggle must preserve the current revision and assignment, and enforce its own nonce.
def toggle_fields(target=None):
    target = experiment if target is None else target
    dashboard = admin.open(base + '/wp-admin/admin.php?page=builder-ab').read().decode()
    forms = re.findall(r'<form\b[^>]*>(.*?)</form>', dashboard, re.S)
    toggle = next(form for form in forms if 'value="bat_toggle"' in form and f'name="experiment_id" value="{target}"' in form)
    return {'action': 'bat_toggle', 'experiment_id': target, '_wpnonce': re.search(r'name="_wpnonce" value="([^\"]+)"', toggle).group(1)}

try:
    admin.open(base + '/wp-admin/admin-post.php', urllib.parse.urlencode(dict(toggle_fields(), _wpnonce='invalid')).encode())
    raise AssertionError('Invalid toggle nonce accepted')
except urllib.error.HTTPError as error:
    assert error.code == 403

paused = admin.open(base + '/wp-admin/admin-post.php', urllib.parse.urlencode(toggle_fields()).encode())
assert 'toggled=1' in paused.url
try:
    post('assign', {'id': experiment})
    raise AssertionError('Paused experiment accepted assignment')
except urllib.error.HTTPError as error:
    assert error.code == 404
finally:
    resumed = admin.open(base + '/wp-admin/admin-post.php', urllib.parse.urlencode(toggle_fields()).encode())
    assert 'toggled=1' in resumed.url
assert post('assign', {'id': experiment, 'token': assignment['token']})['token'] == assignment['token']
print('PASS: dashboard pause/resume preserves assignment and revision; invalid nonce rejected')

# A paused duplicate is allowed, but resuming it must not overlap an active test.
edit_html = admin.open(base + '/wp-admin/admin.php?page=builder-ab&edit=' + str(experiment)).read().decode()
save_form = next(form for form in re.findall(r'<form\b[^>]*>(.*?)</form>', edit_html, re.S) if 'value="bat_save"' in form)
new_fields = dict(fields, experiment_id=0, title='HTTP test: overlapping pages', _wpnonce=re.search(r'name="_wpnonce" value="([^\"]+)"', save_form).group(1))
new_fields.pop('active')
created = admin.open(base + '/wp-admin/admin-post.php', urllib.parse.urlencode(new_fields).encode())
fixture_id = int(urllib.parse.parse_qs(urllib.parse.urlparse(created.url).query)['edit'][0])
try:
    try:
        admin.open(base + '/wp-admin/admin-post.php', urllib.parse.urlencode(toggle_fields(fixture_id)).encode())
        raise AssertionError('Overlapping experiment resumed')
    except urllib.error.HTTPError as error:
        assert error.code == 400
        assert 'otro experimento activo' in error.read().decode()
    print('PASS: dashboard prevents resuming an experiment on already active pages')
finally:
    subprocess.run(['docker', 'compose', 'run', '--rm', 'cli', 'wp', 'post', 'delete', str(fixture_id), '--force'], check=True, stdout=subprocess.DEVNULL)
