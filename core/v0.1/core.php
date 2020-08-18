<?php
use z\debug;
use z\router;
use z\z;
function AppRun($entry)
{
    define('ZPHP_VER', '4.1.0');
    error_reporting(E_ALL);
    $core = str_replace('\\', '/', dirname(__FILE__));
    $p = explode('/', $core);
    'core' === array_pop($p) || array_pop($p);
    define('TIME', $_SERVER['REQUEST_TIME']);
    define('MTIME', microtime(true));
    define('IS_AJAX', isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 'xmlhttprequest' === strtolower($_SERVER['HTTP_X_REQUESTED_WITH']));
    define('IS_WX', false !== strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger'));
    define('METHOD', $_SERVER['REQUEST_METHOD']);
    define('P_IN', str_replace('\\', '/', dirname($entry)) . '/');
    define('P_CORE', $core . '/');
    define('P_ROOT', implode('/', $p) . '/');
    define('P_TMP', P_ROOT . 'tmp/');
    define('P_BASE', P_ROOT . 'base/');
    define('P_LOG', P_ROOT . 'tmp/log/');
    define('P_RUN', P_ROOT . 'tmp/run/');
    define('P_HTML', P_ROOT . 'tmp/html/');
    define('P_CACHE', P_ROOT . 'tmp/cache/');
    define('P_APP', P_ROOT . 'app/' . APP_NAME . '/');
    define('P_COMMON', P_ROOT . 'common/');
    define('LEN_IN', strlen(P_IN));
    define('P_PUBLIC', P_IN === P_ROOT ? P_IN . 'public/' : P_IN);
    define('P_RES', P_PUBLIC . 'res/');
    define('ZPHP_OS', 0 === stripos(strtoupper(PHP_OS), 'WIN') ? 'WINDOWS' : 'LINUX');

    $GLOBALS['ZPHP_MAPPING'] = [
        'z' => P_CORE . 'z/',
        'ext' => P_CORE . 'ext/',
        'root' => P_ROOT,
        'libs' => P_ROOT . 'libs/',
        'common' => P_COMMON,
    ];
    require P_CORE . 'z/z.class.php';
    set_exception_handler('\z\debug::exceptionHandler');
    spl_autoload_register('\z\z::AutoLoad');
    router::init();
    ini_set('date.timezone', $GLOBALS['ZPHP_CONFIG']['TIME_ZONE'] ?? 'Asia/Shanghai');
    isset($GLOBALS['ZPHP_CONFIG']['DEBUG']['level']) || $GLOBALS['ZPHP_CONFIG']['DEBUG']['level'] = 3;
    if ($GLOBALS['ZPHP_CONFIG']['DEBUG']['level'] > 1) {
        ini_set('display_errors', 'On');
        set_error_handler('\z\debug::errorHandler');
        ini_set('expose_php', 'Off');
    } else {
        ini_set('display_errors', 'Off');
        ini_set('expose_php', 'Off');
    }
    z::start();
}
function Zautoload(string $act)
{
    $GLOBALS['ZPHP_AUTOLOAD'] = $act;
}
function Debug(int $i, $msg = '')
{
    $GLOBALS['ZPHP_CONFIG']['DEBUG']['level'] = $i;
    $msg && $GLOBALS['ZPHP_CONFIG']['DEBUG']['type'] = $msg;
}
function IsFullPath(string $path): bool
{
    return 'WINDOWS' === ZPHP_OS ? ':' === $path[1] : '/' === $path[0];
}
function SetConfig(string $key, $value)
{
    if (isset($GLOBALS['ZPHP_CONFIG'][$key]) && is_array($value)) {
        $GLOBALS['ZPHP_CONFIG'][$key] = $value + $GLOBALS['ZPHP_CONFIG'][$key];
    } else {
        $GLOBALS['ZPHP_CONFIG'][$key] = $value;
    }
}
function ReadFileSH($file) {
    $h = fopen($file, 'r');
    if (!flock($h, LOCK_SH)) throw new \Exception('获取文件共享锁失败');
    $result = fread($h, filesize($file));
    flock($h, LOCK_UN);
    fclose($h);
    return $result;
}
function P($var, bool $echo = true)
{
    ob_start();
    var_dump($var);
    $html = '<pre>' . preg_replace('/\]\=\>\n(\s+)/m', '] =>', htmlspecialchars_decode(ob_get_clean())) . '</pre>';
    if ($echo) {
        echo $html;
    } else {
        return $html;
    }
}
function FileSizeFormat(int $size = 0, int $dec = 2): string
{
    $unit = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    $pos = 0;
    while ($size >= 1024) {
        $size /= 1024;
        ++$pos;
    }
    return round($size, $dec) . $unit[$pos];
}
function TransCode($str)
{
    $encode = mb_detect_encoding($str, ['ASCII', 'UTF-8', 'GB2312', 'GBK', 'BIG5', 'EUC-CN']);
    return 'UTF-8' === $encode ? $str : mb_convert_encoding($str, 'UTF-8', $encode);
}
function make_dir($dir, $mode = 0755, $recursive = true)
{
    if (!file_exists($dir) && !mkdir($dir, $mode, $recursive)) {
        throw new Error("创建目录{$dir}失败,请检查权限");
    }
    return true;
}
