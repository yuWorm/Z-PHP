<?php
namespace z;

class view
{
    const
    ENCODE_PREFIX = 'z-php-encode',
    ENCODE_END_CHAR = '#',
    OPTIONS = LIBXML_NOBLANKS + LIBXML_HTML_NOIMPLIED + LIBXML_NOERROR + LIBXML_HTML_NODEFDTD + LIBXML_ERR_FATAL + LIBXML_COMPACT,
    PREG_COMPRESS = ['/<!--(.|\s)*?-->/', '/[\n\r\t]+/', '/>\s+</', '/\s{2,}/'],
    REPLACE_COMPRESS = ['', '', '><', ' '],
    PREG_NOTES = '/<!--(.|\s)*?-->/',
    REPLACE_NOTES = '';

    private static $TAG, $PRE, $SUF, $DOMS, $TPL, $FILE, $PARAMS, $RUN, $PREG, $CHANGED, $IMPORTS, $SEARCH_FIX, $REPLACE_FIX;
    private static function replaceEncode($html)
    {
        $i = 0;
        $pre = preg_quote(self::$PRE);
        $suf = preg_quote(self::$SUF);
        $preg0 = "/({$pre}|<\?=)([\s\S]+)({$suf}|\?>)/U";
        $preg1 = '/<\?php([\s\S]+)\?>/U';
        $html = preg_replace_callback($preg0, function ($match) use (&$i) {
            $code = trim($match[2]);
            $encode = self::ENCODE_PREFIX . "{$i}=" . base64_encode("<?php echo {$code};?>") . self::ENCODE_END_CHAR;
            ++$i;
            return $encode;
        }, $html);
        $html = preg_replace_callback($preg1, function ($match) use (&$i) {
            $code = trim($match[1]);
            $encode = self::ENCODE_PREFIX . "{$i}=" . base64_encode("<?php {$code}?>") . self::ENCODE_END_CHAR;
            ++$i;
            return $encode;
        }, $html);
        return $html;
    }
    private static function replaceDecode($html)
    {
        $prefix = preg_quote(self::ENCODE_PREFIX);
        $endchar = preg_quote(self::ENCODE_END_CHAR);
        $preg1 = "/{$prefix}\d+\=([\w\/\+\=]+){$endchar}/";
        $preg0 = "/{$prefix}\d+\=\"([\w\/\+\=]+){$endchar}\"/";
        $html = preg_replace_callback($preg0, function ($match) {
            return base64_decode($match[1]);
        }, $html);
        $html = preg_replace_callback($preg1, function ($match) {
            return base64_decode($match[1]);
        }, $html);
        return $html;
    }
    private static function getTplInfo($name)
    {
        $name = trim($name, '/');
        $info = pathinfo($name);
        if (isset($info['extension'])) {
            $info['fullname'] = $name;
        } else {
            $info['basename'] .= TPL_EXT;
            $info['fullname'] = $name . TPL_EXT;
        }
        return $info;
    }
    public static function GetTpl(string $name, $F = false)
    {
        if (!$name) {
            $file = ROUTE['ctrl'] . '/' . ROUTE['act'] . TPL_EXT;
            $file = P_THEME_ . $file;
        } elseif (isset(self::$FILE[$name])) {
            $file = self::$FILE[$name];
        } elseif (IsFullPath($name)) {
            $file = '/' . $info['fullname'];
        } else {
            $info = self::getTplInfo($name);
            $arr = explode('/', $info['fullname']);
            if (defined($arr[0]) && $file = rtrim(constant($arr[0]), '/')) {
                unset($arr[0]);
                foreach ($arr as $k => $v) {
                    $file .= '/' . (defined($v) ? constant($v) : $v);
                }
            } else {
                switch (count($arr)) {
                    case 1:
                        $file = P_THEME_ . ROUTE['ctrl'] . "/{$info['fullname']}";
                        break;
                    default:
                        $file = P_THEME_ . $info['fullname'];
                }
            }
        }
        if (!is_file($file)) {
            throw new \Exception("file not fond: {$file}");
        }

        $name && self::$FILE[$name] = $file;
        return $file;
    }

    private static function getBlock($file)
    {
        $key = md5($file);
        if (!isset(self::$DOMS[$key])) {
            $time = filemtime($file);
            $time > self::$CHANGED && self::$CHANGED = $time;
            $html = self::replaceEncode(file_get_contents($file));
            $html = '<?xml encoding="UTF-8">' . $html;
            $dom = new \DOMDocument('1.0', 'UTF-8');
            $dom->loadHTML($html, self::OPTIONS);
            self::$DOMS[$key] = $dom->getElementsByTagName(self::$TAG['template']);
            2 === $GLOBALS['ZPHP_CONFIG']['DEBUG'] && debug::setMsg(1140, $file);
        }
        if (self::$DOMS[$key]->length) {
            foreach (self::$DOMS[$key] as $k => $v) {
                $name = $v->getAttribute('name') ?: $k;
                self::$TPL[$key][$name] = $v->childNodes;
            }
            self::replaceTemplate($dom, $file);
        } else {
            throw new \Exception("template error: {$file}");
        }
    }
    private static function format($html)
    {
        $compress = $GLOBALS['ZPHP_CONFIG']['VIEW']['compress'] ?? false;
        if ($compress) {
            return preg_replace(self::PREG_COMPRESS, self::REPLACE_COMPRESS, $html);
        } else {
            return preg_replace(self::PREG_NOTES, self::REPLACE_NOTES, $html);
        }
    }
    private static function replaceTemplate($dom, $file = null)
    {
        $imports = $dom->getElementsByTagName(self::$TAG['import']);
        if ($imports->length) {
            foreach ($imports as $v) {
                $name = $v->getAttribute('name');
                $f = $v->getAttribute('file');
                $tpl = $f ? self::getTpl($f) : $file;
                $key = md5($tpl);
                isset(self::$TPL[$key]) || self::getBlock($tpl);
                if (!isset(self::$TPL[$key][$name])) {
                    throw new \Exception("template tagName '{$name}' not exits : {$tpl}");
                }
                foreach (self::$TPL[$key][$name] as $k => $n) {
                    if (1 !== $n->nodeType || self::$TAG['import'] === $n->tagName) {
                        continue;
                    }
                    $new = $dom->importNode($n, true);
                    $v->parentNode->insertBefore($new, $v);
                }
                self::$IMPORTS[] = $v;
            }
        }
        return;
    }

    private static function getRun($file)
    {
        $arr = explode('/', $file);
        $len = count($arr);
        $name = $arr[$len - 1];
        $run[0] = $arr[$len - 2];
        $run[1] = explode('.', $name)[0];
        return $run;
    }
    public static function GetCache($time, $name = '')
    {
        $tpl = self::getTpl($name, true);
        $run = self::getRun($tpl);
        $html_path = P_HTML_ . THEME . '/' . $run[0] . '/' . $run[1];
        $html_file = $html_path . '/' . md5(ROUTE['uri']) . '.html';
        $html_time = is_file($html_file) ? filemtime($html_file) : 0;
        if ($html_time && $html_time + $time > TIME) {
            return file_get_contents($html_file);
        } else {
            return false;
        }
    }

    public static function Fetch(string $name = '', int $cache = 0)
    {
        $tpl = self::getTpl($name, true);
        2 === $GLOBALS['ZPHP_CONFIG']['DEBUG'] && debug::setMsg(1140, $tpl);
        isset(self::$TAG) || self::$TAG = [
            'php' => $GLOBALS['ZPHP_CONFIG']['VIEW']['php_tag'] ?? 'php',
            'import' => $GLOBALS['ZPHP_CONFIG']['VIEW']['import_tag'] ?? 'import',
            'template' => $GLOBALS['ZPHP_CONFIG']['VIEW']['template_tag'] ?? 'template',
        ];

        $run = self::getRun($tpl);
        $run_path = P_RUN_ . THEME;
        $run_file = $run_path . '/' . $run[0] . '-' . $run[1] . '.php';
        $run_time = is_file($run_file) ? filemtime($run_file) : 0;
        if ($GLOBALS['ZPHP_CONFIG']['DEBUG'] || !$run_time) {
            if (!file_exists($run_path) && !mkdir($run_path, 0755, true)) {
                throw new \Exception("file can not write: {$run_path}");
            }
            self::$PRE = $GLOBALS['ZPHP_CONFIG']['VIEW']['prefix'] ?? '<{';
            self::$SUF = $GLOBALS['ZPHP_CONFIG']['VIEW']['suffix'] ?? '}>';
            self::$CHANGED = filemtime($tpl);
            $dom = new \DOMDocument('1.0', 'UTF-8');
            $html = self::replaceEncode(file_get_contents($tpl));
            $html = '<meta id="z-php-def-meta-utf-8" http-equiv="Content-Type" content="text/html; charset=utf-8">' . $html;
            $dom->loadHTML($html, self::OPTIONS);
            $rm = $dom->getElementById('z-php-def-meta-utf-8');
            $dom->removeChild($rm);
            self::replaceTemplate($dom, $tpl);

            foreach (self::$IMPORTS as $v) {
                $v->parentNode->removeChild($v);
            }
            if (!$run_time || self::$CHANGED > $run_time) {
                self::replacePHP($dom);
                $html = $dom->saveHTML();
                $html = self::replaceDecode($html);
                $html = str_replace('<?php }?><?php }else{?>', '<?php }else{?>', $html);
                $html = self::format($html);
                if (!file_put_contents($run_file, $html)) {
                    throw new \Exception("file can not write:{$run_file}");
                }
            }
        }
        self::$PARAMS && extract(self::$PARAMS);
        self::$TPL = null;
        ob_start() && require $run_file;
        $html = ob_get_contents();
        ob_end_clean();
        if ($cache) {
            $html_path = P_HTML_ . THEME . '/' . $run[0] . '/' . $run[1];
            $html_file = $html_path . '/' . md5(ROUTE['uri']) . '.html';
            if (!file_exists($html_path) && !mkdir($html_path, 0755, true)) {
                throw new \Exception("file can not write: {$html_path}");
            }
            $html_time = is_file($html_file) ? filemtime($html_file) : 0;
            if (!$html_time || $html_time + $cache < TIME) {
                if (!file_put_contents($html_file, $html)) {
                    throw new \Exception("file can not write:{$html_file}");
                }
            }
        }
        return $html;
    }

    private static function replacePHP($dom)
    {
        $tags = $dom->getElementsByTagName(self::$TAG['php']);
        for ($i = $tags->length - 1; 0 <= $i; --$i) {
            $t = $tags[$i];
            $parent = $t->parentNode;
            if ($t->attributes->length) {
                $code = '';
                $d = [];
                $lastNode = null;
                $lastName = '';
                for ($n = 0, $len = $t->attributes->length; $n !== $len; ++$n) {
                    $a = $t->attributes[$n];
                    switch ($a->name) {
                        case 'case':
                            $break = true;
                            if (isset($t->attributes[1 + $n])) {
                                $next = $t->attributes[1 + $n];
                                if ('break' === $next->name) {
                                    ++$n;
                                    $break = 'false' === $next->value ? false : (bool) $next->value;
                                }
                            }
                            $code .= 'default' === $a->value ? '<?php default:?>' : "<?php case {$a->value}:?>";
                            $dd = $break ? '<?php break;?>' : '';
                            break;
                        case 'else':
                            $code .= '<?php }else{?>';
                            $dd = '<?php }?>';
                            break;
                        default:
                            $code .= "<?php {$a->name}({$a->value}){?>";
                            $dd = '<?php }?>';
                    }
                    $code = self::ENCODE_PREFIX . "{$n}=" . base64_encode($code) . self::ENCODE_END_CHAR;
                    $d[] = self::ENCODE_PREFIX . "{$n}=" . base64_encode($dd) . self::ENCODE_END_CHAR;
                }
                $new = $dom->createTextNode($code);
                $parent->insertBefore($new, $t);
                foreach ($t->childNodes as $c) {
                    $new = $c->cloneNode(true);
                    $parent->insertBefore($new, $t);
                }
                if ($d) {
                    $str = $dom->createTextNode(implode('', $d));
                    $parent->insertBefore($str, $t);
                }
                $parent->removeChild($t);
            } else {
                $code = self::ENCODE_PREFIX . '0=' . base64_encode("<?php {$t->nodeValue};?>") . self::ENCODE_END_CHAR;
                $new = $dom->createTextNode($code);
                $parent->replaceChild($new, $t);
            }
        }
    }
    public static function Display(string $name = '', int $cache = 0)
    {
        $html = self::Fetch($name, $cache);
        echo $html;
    }
    public static function Assign($key, $val)
    {
        self::$PARAMS[$key] = $val;
    }
    public static function GetParams()
    {
        return self::$PARAMS;
    }
}
