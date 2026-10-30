# PHP Framework

A small, fast, performance-oriented PHP framework with a CodeIgniter 3–style directory layout and original code.

- **PHP 8.1+** (written to be compatible with PHP 8.5: no deprecated features are used)
- **No Query Builder**: you write raw SQL; parameters are always bound through PDO prepared statements
- **Lazy everything**: the database connection, libraries, models and helpers are only loaded when you ask for them
- **CI3-like API**: `$this->load->view()`, `$this->input->post()`, `$this->db->get()`, hooks, routes, autoload

---

## Table of contents

1. [Requirements](#1-requirements)
2. [Installation and web server setup](#2-installation-and-web-server-setup)
3. [Directory structure](#3-directory-structure)
4. [Request lifecycle](#4-request-lifecycle)
5. [Configuration](#5-configuration)
6. [Routing](#6-routing)
7. [Controllers](#7-controllers)
8. [Views](#8-views)
9. [Models](#9-models)
10. [Libraries](#10-libraries)
11. [Helpers](#11-helpers)
12. [Input](#12-input)
13. [Output](#13-output)
14. [Database](#14-database)
15. [Hooks](#15-hooks)
16. [Session library](#16-session-library)
17. [Validation library](#17-validation-library)
18. [Error handling and logging](#18-error-handling-and-logging)
19. [Security notes](#19-security-notes)
20. [Performance tips](#20-performance-tips)
21. [Global function reference](#21-global-function-reference)
22. [Complete example: JSON login API](#22-complete-example-json-login-api)
23. [Common pitfalls](#23-common-pitfalls)

---

## 1. Requirements

- PHP 8.1 or newer
- `pdo` and the PDO driver for your database (`pdo_mysql`, `pdo_pgsql` or `pdo_sqlite`)
- `mbstring` (used by the Validation library)
- Apache with `mod_rewrite`, Nginx, or PHP's built-in server for development

## 2. Installation and web server setup

1. Copy the project to your server. Make sure `app/logs/` is writable by the web server user.
2. Edit `app/config/database.php` with your database credentials.
3. Edit `app/config/config.php` (at minimum set `base_url` and `timezone` for production).
4. Set the environment (see below).

### Environment

`index.php` reads the `APP_ENV` environment variable. The default is `development`.

| Environment   | Behavior |
|---------------|----------|
| `development` | All PHP errors/warnings are converted to exceptions and displayed with a stack trace. SQL queries are recorded (`$this->db->query_log()`). |
| `production`  | Errors are hidden from visitors, a generic message is shown, details go to `app/logs/`. Warnings and notices are logged and execution continues. |

Apache (`.htaccess` or vhost): `SetEnv APP_ENV production`
Nginx: `fastcgi_param APP_ENV production;`

### Apache

The included `.htaccess` is ready to use (`mod_rewrite` and `AllowOverride All` required). It sends every request that is not an existing file or directory to `index.php`. `app/` and `system/` contain their own `.htaccess` that denies direct access.

### Nginx

```nginx
server {
    listen 80;
    server_name example.com;
    root /var/www/php-framework;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Never serve framework internals
    location ~ ^/(app|system)/ {
        deny all;
    }

    # Only index.php may be executed
    location = /index.php {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root/index.php;
        fastcgi_param APP_ENV production;
        fastcgi_pass unix:/run/php/php-fpm.sock;
    }
}
```

### PHP built-in server (development only)

```bash
php -S localhost:8000 index.php
```

### Running from the command line

Any controller method can be invoked from the CLI. The first argument is the URI:

```bash
php index.php welcome/hello/Ahmet
```

### Installing in a sub-directory

If the app lives in `http://example.com/myapp/`, nothing special is needed: the base path is detected from `SCRIPT_NAME`. Leave `base_url` empty or set it to `http://example.com/myapp/`.

---

## 3. Directory structure

```
php-framework/
├── app/                        Your application
│   ├── config/
│   │   ├── autoload.php        What to load on every request
│   │   ├── config.php          General settings
│   │   ├── database.php        Database groups
│   │   ├── hooks.php           Hook definitions
│   │   └── routes.php          Routes
│   ├── controllers/            Controllers (sub-folders allowed)
│   ├── helpers/                Your helper files (*_helper.php)
│   ├── hooks/                  Optional: classes/files used by hooks.php
│   ├── libraries/              Your libraries
│   ├── logs/                   Log files (must be writable)
│   ├── models/                 Models
│   └── views/
│       └── errors/             error_404.php, error_general.php
├── system/                     The framework itself (do not edit)
│   ├── core/                   Framework, Router, Loader, Input, Output, Hooks, Config, BaseController, BaseModel
│   ├── database/               DB.php (PDO wrapper)
│   ├── helpers/                url_helper.php, html_helper.php
│   └── libraries/              Session.php, Validation.php
├── .htaccess
└── index.php                   Front controller
```

Only `index.php` (and your public assets) should be reachable from the web.

---

## 4. Request lifecycle

```
index.php
  └─ Framework::run()
       1. error/exception handlers registered, output buffering started
       2. Config, Hooks, Input, Output, Router created
       3. hook: pre_system
       4. Router resolves the URI → controller file, class, method, arguments  (404 if not found)
       5. hook: pre_controller
       6. Controller file is loaded; method/arguments are validated            (404 if invalid)
       7. Controller is instantiated (BaseController::__construct runs autoload)
       8. hook: post_controller_constructor
       9. Controller method is called
      10. hook: post_controller
      11. Output is sent (headers + buffered body)
      12. hook: post_system   (runs after the response is flushed when PHP-FPM is used)
```

Everything you `echo` or render with `$this->load->view()` is buffered and sent in step 11, so you can still set headers or status codes late in the request.

---

## 5. Configuration

### `app/config/config.php`

Settings are defined with the `$config` array:

| Key                  | Default        | Description |
|----------------------|----------------|-------------|
| `base_url`           | `''` (auto)    | Site root URL. If empty it is detected from the request. **Set it explicitly in production.** |
| `index_page`         | `''`           | Set to `'index.php'` if you cannot use URL rewriting. |
| `timezone`           | `'Europe/Istanbul'` | Passed to `date_default_timezone_set()`. |
| `charset`            | `'UTF-8'`      | Used in `Content-Type` headers. |
| `log_threshold`      | `1`            | `0` off, `1` errors, `2` info, `3` debug. |
| `proxy_ips`          | `[]`           | Trusted proxy IPs. `X-Forwarded-For` is only honored when the request comes from one of these. |
| `session_name`       | `'fw_session'` | Session cookie name. |
| `session_expiration` | `7200`         | Session garbage-collection lifetime in seconds. |

You can add your own keys and read them anywhere:

```php
$config['site_name'] = 'My Site';          // app/config/config.php

config_item('site_name');                  // anywhere
config_item('missing', 'fallback');        // with default
$this->config->item('site_name');          // inside controllers/views
$this->config->set('site_name', 'Other');  // change at runtime
```

Additional config files can be loaded with `$this->config->load('mysettings')` (reads `app/config/mysettings.php`, which must define `$config`) or automatically via `$autoload['config']`.

### `app/config/autoload.php`

```php
$autoload['config']    = [];                 // extra config files
$autoload['helpers']   = ['url', 'html'];    // helper names (without _helper)
$autoload['libraries'] = [];                 // e.g. ['Session']
$autoload['models']    = [];                 // e.g. ['User_model']
```

Keep this list short; everything listed is loaded on **every** request.

### `app/config/database.php`

See [Database](#14-database).

---

## 6. Routing

URLs map to `controller/method/arg1/arg2/...` by default:

```
/                       → default_controller → index()
/welcome                → Welcome::index()
/welcome/hello          → Welcome::hello()
/welcome/hello/Ahmet    → Welcome::hello('Ahmet')
/admin/dash             → app/controllers/admin/Dash.php → Dash::index()
```

### Custom routes (`app/config/routes.php`)

```php
$route['default_controller'] = 'welcome';

$route['product/(:num)']  = 'products/detail/$1';   // (:num) = digits only
$route['blog/(:any)']     = 'blog/show/$1';          // (:any) = anything, including slashes
$route['news/([a-z]+)/(\d+)'] = 'news/view/$1/$2';   // plain regex works too
```

Patterns are matched against the full URI, anchored at both ends, and the first match wins. `$1`, `$2`, ... are back-references.

### Routes by HTTP method

```php
$route['api/users'] = [
    'GET'  => 'users/index',
    'POST' => 'users/create',
    '*'    => 'users/other',      // optional fallback for any other method
];
```

If the request method has no entry (and there is no `*`), the route is skipped.

### Rules the router enforces

- Controller and directory names must match `[A-Za-z0-9_]+`, method names `[A-Za-z][A-Za-z0-9_]*`. Anything else is a 404.
- `.` / `..` segments and NUL bytes are rejected.
- The URL segment is only upper-cased on its first letter to find the file/class: `/welcome` → `Welcome.php`. See [Common pitfalls](#23-common-pitfalls) for CamelCase controllers.
- Methods starting with `_` can never be called from the URL.

---

## 7. Controllers

Controllers live in `app/controllers/`. The file name must equal the class name (`Welcome.php` → `class Welcome`).

```php
<?php

class Welcome extends BaseController {

    public function __construct() {

        parent::__construct();      // required

    }

    public function index() {

        $this->load->view('welcome_message');

    }

    public function user(int $id) {            // /welcome/user/42

        $this->output->json(['id' => $id]);

    }

}
```

### What `BaseController` gives you

| Property          | Description |
|-------------------|-------------|
| `$this->load`     | The [Loader](#8-views) (views, models, libraries, helpers, databases) |
| `$this->input`    | [Input](#12-input) |
| `$this->output`   | [Output](#13-output) |
| `$this->config`   | Config object |
| `$this->db`       | Database (created lazily on first use) |
| `$this->session`, `$this-><library>`, `$this-><model>` | Available after loading them |

### Which methods can be called from a URL?

Only methods that are: `public`, non-static, declared in your controller class (not in `BaseController`), not the constructor, and not starting with `_`. Use `private`/`protected` or a leading underscore for helpers.

### Arguments

- Remaining URL segments are passed as arguments, URL-decoded.
- Too few segments for the required parameters, or too many for a non-variadic method → **404**.
- Type declarations work with automatic conversion: `/user/42` into `int $id` gives an integer. A non-numeric value for an `int`/`float` parameter gives a **404**.

```php
public function archive(int $year, int $month = 1) { ... }   // /archive/2026 or /archive/2026/10
public function search(string ...$terms) { ... }              // /search/a/b/c
```

### Sub-directories

`app/controllers/admin/Dash.php` is reachable at `/admin/dash`. If the URL only names a directory (`/admin`), the default controller name is looked up inside it (e.g. `admin/Welcome.php`).

### Accessing the controller elsewhere

```php
$ci = get_instance();     // from hooks, helpers, libraries
$ci->input->post('x');
```

---

## 8. Views

Views are plain PHP templates in `app/views/`.

```php
$this->load->view('welcome_message');                              // app/views/welcome_message.php
$this->load->view('admin/users/list', ['users' => $users]);        // sub-folders
$html = $this->load->view('partials/row', $data, true);            // return as string instead of printing
```

- The `.php` extension is optional.
- Array keys become local variables inside the view (`['title' => 'Hi']` → `$title`).
- Output is buffered; calling `view()` several times simply appends.
- **Inside a view, `$this` is the current controller**, so `$this->session`, `$this->input`, `$this->config` and any loaded library are available.

### Shared variables

```php
$this->load->vars('site_name', 'My Site');
$this->load->vars(['user' => $user, 'lang' => 'en']);
// available in every view rendered afterwards; variables passed to view() win on conflicts
```

### Escaping

Always escape dynamic values with `e()` (autoloaded `html` helper) or `html_escape()`:

```php
<h1><?= e($title) ?></h1>
<a href="<?= e(site_url('user/' . $id)) ?>">Profile</a>
```

`e($x)` is `htmlspecialchars((string) $x, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`. Printing a raw `$x` is only safe for trusted HTML (e.g. the result of another `view(..., true)`).

### Layout pattern

```php
// app/views/layout/header.php, footer.php
$this->load->view('layout/header', $data);
$this->load->view('pages/dashboard', $data);
$this->load->view('layout/footer');
```

---

## 9. Models

Models live in `app/models/` and extend `BaseModel`.

```php
<?php

class User_model extends BaseModel {

    public function find(int $id): ?object {

        return $this->db->get('SELECT * FROM users WHERE id = ?', [$id])->row();

    }

}
```

Inside a model, `$this->db`, `$this->input`, `$this->session`, `$this->load` etc. are forwarded to the controller, so you use them exactly as in a controller.

### Loading

```php
$this->load->model('User_model');            // available as $this->user_model   (lower-cased!)
$this->load->model('User_model', 'users');   // available as $this->users        (alias)
$this->load->model(['User_model', 'Post_model']);
$this->load->model('admin/Report_model');    // app/models/admin/Report_model.php → class Report_model
```

> **The property name is the class name in lower case**, as in CodeIgniter 3. `AuthModel` is available as `$this->authmodel`, not `$this->AuthModel`. Use an alias if you prefer a different name:
> `$this->load->model('AuthModel', 'AuthModel');`

The names `load`, `input`, `output`, `config` and `db` are reserved and cannot be used as model/library names or aliases.

---

## 10. Libraries

Put your own classes in `app/libraries/`; framework libraries live in `system/libraries/`. The application folder is searched first, so you can override a system library by using the same file name.

```php
<?php
// app/libraries/Mailer.php

class Mailer {

    private array $cfg;

    public function __construct(array $params = []) {

        $this->cfg = $params;

    }

    public function send(string $to, string $subject, string $body): bool {

        // ...
        return true;

    }

}
```

```php
$this->load->library('Mailer', ['host' => 'smtp.example.com']);   // → $this->mailer
$this->load->library('Mailer', [], 'mail');                       // alias → $this->mail
$this->load->library(['Session', 'Validation']);                  // several at once
$this->mailer->send('a@b.com', 'Hi', 'Hello');
```

- The class name must match the file name.
- Constructor parameters: an array of `$params` (only passed if not empty).
- Inside a library use `get_instance()` to reach the controller (`get_instance()->db`, ...).
- Sub-folders are supported: `library('sub/Name')`.
- A library is instantiated only once per request.

---

## 11. Helpers

Helpers are files of plain functions named `<name>_helper.php`.

```php
$this->load->helper('url');
$this->load->helper(['url', 'html', 'myhelper']);
```

Lookup: `app/helpers/` first, then `system/helpers/`; **both** are loaded if both exist. Wrap your functions in `if (!function_exists(...))` so they can be overridden.

### Built-in helpers

**`url`**

| Function | Description |
|----------|-------------|
| `base_url($uri = '')` | Base URL + `$uri` (use for assets) |
| `site_url($uri = '')` | Base URL + `index_page` + `$uri` (use for pages/links) |
| `current_url()` | Site URL for the current URI (no query string) |
| `uri_segment($n, $default = null)` | The n-th URI segment (1-based) |
| `redirect($uri = '', $code = 302)` | Send a `Location` header and exit. Accepts a relative URI or a full `http(s)://` URL. |

**`html`**

| Function | Description |
|----------|-------------|
| `e($value)` | HTML-escape for output |
| `asset_url($path)` | `base_url($path)` |

---

## 12. Input

`$this->input` gives access to request data.

```php
$this->input->post('email');                  // value or null
$this->input->post('email', 'default');       // with default
$this->input->post();                         // all POST data (array)

$this->input->get('page', 1);
$this->input->get_post('q');                  // POST first, then GET
$this->input->cookie('theme');
$this->input->server('request_uri');          // key is upper-cased automatically
$this->input->header('Authorization');        // request header (case-insensitive)
$this->input->file('avatar');                 // $_FILES entry or null
```

| Method | Description |
|--------|-------------|
| `method()` | `'GET'`, `'POST'`, ... (upper-case) |
| `is_ajax()` | `true` if `X-Requested-With: XMLHttpRequest` |
| `ip_address()` | Client IP. `REMOTE_ADDR`, or the first `X-Forwarded-For` address only if `REMOTE_ADDR` is listed in `proxy_ips`. Returns `0.0.0.0` if invalid. |
| `raw_body()` | The raw request body (string) |
| `json()` | The request body decoded as an associative array, or `null` if empty/invalid |

### JSON requests

```php
$data = $this->input->json();                       // array|null
$obj  = json_decode($this->input->raw_body());      // stdClass|null, if you prefer objects
```

When the request has `Content-Type: application/json` and `$_POST` is empty, `$this->input->post()` automatically reads the decoded JSON body as well.

> Input values have NUL bytes stripped, but they are **not** HTML-escaped. Escape on output (`e()`), and use prepared statements for SQL.

---

## 13. Output

`$this->output` controls the response.

```php
$this->output->set_status_header(404);
$this->output->set_header('X-Frame-Options', 'SAMEORIGIN');
$this->output->set_content_type('text/plain');
$this->output->set_output('Replace the whole body with this');
$this->output->json(['ok' => true]);                 // 200 + application/json
$this->output->json(['error' => 'Nope'], 422);       // with status
```

Methods that set state return `$this`, so they can be chained. `set_output()` and `json()` replace everything that was echoed or rendered earlier in the request.

For redirects use the `redirect()` helper.

---

## 14. Database

A thin PDO wrapper. **There is no Query Builder**: write SQL yourself.

### Configuration (`app/config/database.php`)

```php
$db['active_group'] = 'default';

$db['default'] = [
    'dsn'          => '',            // if set, hostname/port/database are ignored
    'driver'       => 'mysql',       // mysql | mariadb | pgsql | sqlite
    'hostname'     => '127.0.0.1',
    'port'         => 3306,
    'username'     => 'root',
    'password'     => '',
    'database'     => 'test',
    'charset'      => 'utf8mb4',
    'persistent'   => false,
    'init_command' => "SET time_zone = '+03:00'",   // optional, run after connecting
    'options'      => [],            // extra PDO options
];

// More groups:
$db['reporting'] = [ /* ... */ ];

// SQLite:
// $db['default'] = ['driver' => 'sqlite', 'database' => APPPATH . 'database.sqlite'];
```

PDO runs in exception mode, fetches associative arrays by default, and uses native prepared statements (`ATTR_EMULATE_PREPARES = false`). The connection is created on the first query.

### Running queries

```php
// Positional parameters
$result = $this->db->get('SELECT * FROM users WHERE id = ?', [5]);

// Named parameters (with or without the leading colon)
$result = $this->db->get('SELECT * FROM users WHERE email = :email', ['email' => $mail]);

// Writes
$this->db->query('INSERT INTO users (email, password) VALUES (?, ?)', [$mail, $hash]);
$id   = $this->db->insert_id();
$rows = $this->db->affected_rows();
```

`get()` and `query()` are identical; use whichever reads better. Both return a `DB_result`.

Bound values are typed automatically: `int` → integer, `bool` → boolean, `null` → NULL, everything else → string. This also makes `LIMIT ? OFFSET ?` work with integers.

> Never concatenate user input into SQL. If you need a dynamic identifier (column/table name), validate it against a whitelist.

### Reading results

```php
$result->result();          // array of stdClass objects
$result->result_array();    // array of associative arrays
$result->row();             // first row as object, or null
$result->row(2);            // third row
$result->row_array();       // first row as array, or null
$result->num_rows();        // number of rows returned
$result->scalar();          // first column of the first row (or null)

// Large result sets: stream rows without loading them all into memory
foreach ($this->db->get('SELECT * FROM big_table')->iterate() as $row) { /* array */ }
```

```php
$count = (int) $this->db->get('SELECT COUNT(*) FROM users')->scalar();
$user  = $this->db->get('SELECT * FROM users WHERE id = ?', [$id])->row();
if ($user === null) { show_404(); }
```

### `IN (...)` lists

Build the placeholders yourself:

```php
$ids = [1, 2, 3];
$in  = implode(',', array_fill(0, count($ids), '?'));
$rows = $this->db->get("SELECT * FROM users WHERE id IN ($in)", $ids)->result();
```

### LIKE searches

```php
$term = '%' . $this->db->escape_like($q) . '%';
$this->db->get("SELECT * FROM posts WHERE title LIKE ? ESCAPE '\\'", [$term]);
```

### Transactions

```php
// Closure form: commits on success, rolls back and re-throws on exception
$orderId = $this->db->transaction(function ($db) use ($data) {
    $db->query('INSERT INTO orders (user_id) VALUES (?)', [$data['user']]);
    $id = $db->insert_id();
    $db->query('INSERT INTO order_items (order_id, sku) VALUES (?, ?)', [$id, $data['sku']]);
    return $id;
});

// Manual form
$this->db->trans_begin();         // alias: trans_start()
try {
    $this->db->query(...);
    $this->db->query(...);
    $this->db->trans_complete();  // commit (or rollback if a query failed)
} catch (Throwable $e) {
    $this->db->trans_rollback();
    throw $e;
}
```

Transactions can be nested; only the outermost one commits. If any query fails inside, the whole transaction is rolled back.

### Other methods

| Method | Description |
|--------|-------------|
| `last_query()` | The last SQL string executed |
| `query_count()` | Number of queries run on this connection |
| `query_log()` | `[['sql' => ..., 'time' => ...], ...]` (development only) |
| `escape($value)` | Quoted literal for emergencies; prefer bound parameters |
| `escape_like($str)` | Escapes `%`, `_` and `\` |
| `pdo()` | The underlying `PDO` object |
| `close()` | Drops the connection |

### Multiple databases

```php
$reports = $this->load->database('reporting', true);   // returns a DB object, $this->db untouched
$reports->get('SELECT ...');

$this->load->database('reporting');                    // replaces $this->db with this group
```

### Errors

Failed queries throw `DatabaseException` (a `RuntimeException`). In development the message includes the SQL; in production visitors only see a generic error and the details are logged.

---

## 15. Hooks

Hooks let you run code at fixed points of the request. Define them in `app/config/hooks.php` with the `$hook` array.

| Hook point | When it runs |
|------------|--------------|
| `pre_system` | Very early: config is loaded, the route is not resolved yet |
| `pre_controller` | Route resolved, controller not yet created |
| `post_controller_constructor` | Controller created (constructor finished), method not yet called |
| `post_controller` | Controller method finished, output not yet sent |
| `post_system` | Output sent (with PHP-FPM the visitor no longer waits for this) |

```php
// A single closure
$hook['pre_system'] = function () {
    // maintenance mode, custom bootstrap, ...
};

$hook['post_controller_constructor'] = function () {
    $ci = get_instance();                       // the running controller
    if (!$ci->session->has_userdata('authorized')) { /* ... */ }
};

// Several hooks on the same point
$hook['post_controller'][] = function () { /* first  */ };
$hook['post_controller'][] = function () { /* second */ };

// Class-based hook
require_once APPPATH . 'hooks/Example_hook.php';
$hook['post_controller'][] = [Example_hook::class, 'add_security_headers'];
```

- Hooks receive no arguments. Use `get_instance()` (available from `post_controller_constructor` on) to reach the controller.
- Hooks run in the order they were registered.
- `app/hooks/` is just a conventional place for hook classes; it is not loaded automatically. Require what you need from `hooks.php`.
- Hooks can also be added at runtime: `Framework::core('hooks')->add('post_controller', $callable);`
- In `pre_system` the controller does not exist yet, so `get_instance()` returns `null`.

---

## 16. Session library

```php
$this->load->library('Session');       // or $autoload['libraries'] = ['Session'];
```

```php
$this->session->set_userdata('user_id', 5);
$this->session->set_userdata(['user_id' => 5, 'role' => 'admin']);

$this->session->userdata('user_id');              // 5
$this->session->userdata('missing', 'default');   // with default
$this->session->userdata();                       // everything (array)
$this->session->has_userdata('user_id');          // bool
$this->session->unset_userdata('user_id');
$this->session->unset_userdata(['user_id', 'role']);

// Flash data: readable only in the NEXT request
$this->session->set_flashdata('message', 'Saved!');
$this->session->flashdata('message');             // in the next request

$this->session->regenerate();                     // new session ID (call after login)
$this->session->destroy();                        // log out
$this->session->close();                          // release the session lock early
```

Cookie settings: `HttpOnly`, `SameSite=Lax`, `Secure` when the request is HTTPS, session-lifetime cookie. Name and garbage-collection lifetime come from `session_name` and `session_expiration` in `config.php`. Strict session mode is enabled.

Tip: call `close()` before slow operations (external API calls, large downloads) so parallel requests from the same user are not blocked by the session lock. You cannot write to the session after closing it.

---

## 17. Validation library

```php
$this->load->library('Validation');          // → $this->validation

$this->validation->set_rules('email', 'E-mail', 'required|valid_email|max_length[100]');
$this->validation->set_rules('password', 'Password', 'required|min_length[8]');
$this->validation->set_rules('password2', 'Password confirmation', 'required|matches[password]');

if ($this->validation->run()) {
    // valid
} else {
    $errors = $this->validation->errors();            // ['email' => 'E-mail must be a valid email address.', ...]
    $first  = $this->validation->error('email');      // string|null
}
```

`run()` validates `$this->input->post()` by default. To validate other data:

```php
$this->validation->run($this->input->json() ?? []);
// or
$this->validation->set_data($array)->run();
```

### Rules

| Rule | Passes when |
|------|-------------|
| `required` | Not empty |
| `min_length[n]` / `max_length[n]` / `exact_length[n]` | Character count (multibyte-aware) |
| `valid_email`, `valid_url`, `valid_ip` | Valid format |
| `numeric` | Numeric string/number |
| `integer` | Integer (may be negative) |
| `is_natural` | Digits only |
| `alpha` | Letters only (Unicode) |
| `alpha_numeric` | Letters and digits only (Unicode) |
| `matches[field]` | Equal to another field |
| `regex_match[/pattern/]` | Matches the regex |
| `in_list[a,b,c]` | One of the listed values |
| `greater_than[n]` / `less_than[n]` | Numeric comparison |

If a field is empty and not `required`, its other rules are skipped. Validation stops at the first failing rule of each field.

### Custom rules and messages

```php
$this->validation->set_rules('age', 'Age', [
    'required',
    'is_natural',
    fn ($value, $all) => (int) $value >= 18 ?: '{field} must be at least 18.',   // true = pass, string = error
]);

$this->validation->set_message('required', '{field} cannot be empty.');
```

Message placeholders: `{field}` (the label) and `{param}` (the text inside `[...]`). Default messages are in English.

---

## 18. Error handling and logging

- **Exceptions and PHP errors** are caught by the framework.
  - Development: the error page shows the message, file, line and stack trace (`app/views/errors/error_general.php`).
  - Production: visitors see a generic message; the details are logged.
- **404 pages** use `app/views/errors/error_404.php`. Call `show_404()` yourself when a record is missing.
- **Custom error pages**: edit the two views in `app/views/errors/`. `error_general.php` receives `$status`, `$heading`, `$message` and `$exception`.
- **CLI**: errors are written to STDERR with exit code 1.

```php
show_404();                                   // 404 page and stop
show_404('That product does not exist.');
show_error('Something went wrong', 500);      // generic error page and stop
```

### Logging

```php
log_message('error', 'Payment failed for order 15');
log_message('info',  'User 5 logged in');
log_message('debug', 'Cache miss');
```

Logs go to `app/logs/log-YYYY-MM-DD.log`. A message is written only if its level is within `log_threshold` (1 = error only, 2 = + info, 3 = + debug; 0 = off). Failed queries and uncaught exceptions are logged automatically. Rotate or clean old log files with your usual tooling.

---

## 19. Security notes

What the framework does for you:

- Only `public` methods of your own controller classes are reachable; `_`-prefixed methods are blocked.
- Controller, method and directory names are validated against strict patterns; `..` and NUL bytes in the URI are rejected.
- SQL parameters are always bound via prepared statements.
- `app/` and `system/` are protected by `.htaccess` (add the Nginx rule from section 2 when using Nginx).
- `Host` header is validated before auto-detecting `base_url`; header values set via `$this->output->set_header()` have CR/LF removed.
- Session cookies are `HttpOnly`, `SameSite=Lax`, `Secure` on HTTPS; strict mode is on.
- Production mode never shows internals to visitors.

What you must do:

- Escape all dynamic output with `e()` / `html_escape()`.
- Store passwords with `password_hash()` and verify them with `password_verify()`. Never compare plain text.
- Call `$this->session->regenerate()` after login and `destroy()` on logout.
- Set `base_url` explicitly in production.
- Add **CSRF protection** to state-changing forms/endpoints; the framework does not include it.
- Validate file uploads (type, size, destination) yourself; store them outside `app/` and `system/`.
- Only list real proxy addresses in `proxy_ips`.
- Keep `APP_ENV=production` on live servers.

---

## 20. Performance tips

- Enable **OPcache** in production (`opcache.enable=1`, and `opcache.validate_timestamps=0` with a cache reset on deploy). This is the biggest single win.
- Keep `autoload.php` lean: load libraries/models only where they are needed.
- The database connection is lazy: pages that never touch `$this->db` never connect.
- Avoid loading all rows when you only need to iterate: use `->iterate()` for big result sets.
- Select only the columns you need; add indexes for your `WHERE`/`ORDER BY` columns.
- Use `post_system` hooks for work that can happen after the response (with PHP-FPM the response is flushed first).
- Serve static files (CSS/JS/images) directly from the web server; the rewrite rules skip existing files.
- Call `$this->session->close()` before slow operations.

---

## 21. Global function reference

Available everywhere after the framework boots (`system/core/Common.php`):

| Function | Description |
|----------|-------------|
| `get_instance()` | The running controller (or `null` before it exists) |
| `config_item($key, $default = null)` | Read a config value |
| `html_escape($value)` | Escape a string or array of strings |
| `show_404($message = ...)` | Render the 404 page and stop |
| `show_error($message, $status = 500, $heading = ...)` | Render an error page and stop |
| `log_message($level, $message)` | Write to the log (`error`, `info`, `debug`) |
| `elapsed_time()` | Seconds since the request started (string, 4 decimals) |
| `memory_usage_mb()` | Peak memory in MB (string) |
| `is_cli()` | `true` when running from the command line |
| `is_https()` | `true` when the request is HTTPS |
| `base_path()` | URL path where `index.php` lives (`''` at the web root) |
| `include_config($file)` | Include a config file in isolation and return its variables |

Core objects can be fetched with `Framework::core('config' | 'input' | 'output' | 'router' | 'hooks')`.

---

## 22. Complete example: JSON login API

**`app/config/routes.php`**

```php
$route['default_controller'] = 'welcome';
$route['api/login'] = ['POST' => 'auth/login'];
```

**`app/models/Auth_model.php`**

```php
<?php

class Auth_model extends BaseModel {

    public function find_by_username(string $username): ?object {

        return $this->db
            ->get('SELECT id, username, password FROM users WHERE username = ?', [$username])
            ->row();

    }

}
```

**`app/controllers/Auth.php`**

```php
<?php

class Auth extends BaseController {

    public function __construct() {

        parent::__construct();

        $this->load->library('Session');

    }

    public function login() {

        $data = $this->input->json();

        if ($data === null) {
            $this->output->json(['ok' => false, 'error' => 'Invalid JSON'], 400);
            return;
        }

        $this->load->model('Auth_model', 'auth');

        $user = $this->auth->find_by_username((string) ($data['username'] ?? ''));

        if ($user === null || !password_verify((string) ($data['password'] ?? ''), $user->password)) {
            $this->output->json(['ok' => false, 'error' => 'Wrong username or password'], 401);
            return;
        }

        $this->session->regenerate();
        $this->session->set_userdata([
            'id'         => $user->id,
            'username'   => $user->username,
            'authorized' => true,
        ]);

        $this->output->json(['ok' => true]);

    }

    public function logout() {

        $this->session->destroy();
        redirect('');

    }

}
```

**Protecting areas with a hook** (`app/config/hooks.php`)

```php
$hook['post_controller_constructor'] = function () {

    $ci = get_instance();

    // Guard everything under /panel
    if (Framework::core('router')->segment(1) === 'panel') {

        $ci->load->library('Session');

        if (!$ci->session->has_userdata('authorized')) {
            redirect('login');
        }

    }

};
```

Or check explicitly at the top of each protected controller's constructor:

```php
class Panel extends BaseController {

    public function __construct() {

        parent::__construct();
        $this->load->library('Session');

        if (!$this->session->has_userdata('authorized')) {
            redirect('login');
        }

    }

}
```

---

## 23. Common pitfalls

**`Undefined property: Auth::$AuthModel`**
Models and libraries are attached under their **lower-case** class name. `AuthModel` → `$this->authmodel`. Use an alias (`$this->load->model('AuthModel', 'AuthModel')`) or the lower-case name.

**404 for a CamelCase controller**
The router only upper-cases the first letter of the URL segment. `/userprofile` looks for `Userprofile.php`, not `UserProfile.php` (file names are case-sensitive on Linux). Either name the class `User_profile` (URL `/user_profile`), request `/UserProfile`, or add a route: `$route['user-profile'] = 'UserProfile';`.

**`Undefined property: ...::$session`**
The Session library is not loaded. Add `$this->load->library('Session');` in the constructor (after `parent::__construct()`) or list it in `$autoload['libraries']`.

**`$this->input->raw_input_stream` does not exist**
Use `$this->input->raw_body()` (string) or `$this->input->json()` (array).

**Class name conflicts**
Core class names (`Config`, `Input`, `Output`, `Router`, `Loader`, `Hooks`, `DB`, `Session`, `Validation`, ...) live in the global namespace. Avoid reusing them for your own classes.

**Forgetting `parent::__construct()`**
Without it, `$this->load`, `$this->input` and friends are not initialized and autoloading does not happen.

**Output after `redirect()`**
`redirect()` sends the header and exits immediately; nothing after it runs.

**Dynamic properties on controllers/models**
`BaseController` and `BaseModel` allow dynamic properties (they are needed for `$this->session`, `$this->user_model`, ...). Your own classes that do not extend them should declare their properties, as required by modern PHP.

---

Happy building!
